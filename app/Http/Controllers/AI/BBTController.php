<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\BbtLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BBTController extends Controller
{
    public function logBbtData(Request $request)
    {
        try {
            $validated = $request->validate([
                'temperature_f' => 'required_without:temperature|numeric',
                'temperature'   => 'required_without:temperature_f|numeric',
                'log_date'      => 'nullable|date',
                'flags'         => 'nullable|array',
            ]);

            $userId = auth()->id() ?? $request->input('user_id');
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $tempF = $validated['temperature_f'] ?? $validated['temperature'];
            $logDate = $validated['log_date'] ?? now()->toDateString();
            $flags = $validated['flags'] ?? [];

            // 1. Get or create active MenstrualCycle
            $cycle = \App\Models\MenstrualCycle::where('user_id', $userId)
                ->where('is_completed', false)
                ->latest('period_start_date')
                ->first();

            if (! $cycle) {
                $cycle = \App\Models\MenstrualCycle::create([
                    'user_id'           => $userId,
                    'period_start_date' => $logDate,
                    'is_completed'      => false,
                    'prediction_source' => 'bbt',
                ]);
            }

            // 2. ALWAYS Save BbtLog to DB first
            $bbtLog = BbtLog::updateOrCreate(
                [
                    'user_id'  => $userId,
                    'cycle_id' => $cycle->id,
                    'log_date' => $logDate,
                ],
                [
                    'user_id'     => $userId,
                    'cycle_id'    => $cycle->id,
                    'temperature' => (float) $tempF,
                    'unit'        => 'F',
                    'logged_at'   => now(),
                    'illness'     => in_array('illness', $flags),
                    'poor_sleep'  => in_array('poor_sleep', $flags),
                    'alcohol'     => in_array('alcohol', $flags),
                    'late_wakeup' => in_array('late_wakeup', $flags),
                    'travel'      => in_array('travel', $flags),
                    'notes'       => $request->input('notes'),
                ]
            );

            // 3. Call AI Engine for BBT Chart / Coverline (Graceful Fallback)
            $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
            $data = null;

            try {
                $response = Http::timeout(10)->post("{$baseUrl}/api/v1/cycle-engine/bbt/ui?user_id=" . $userId, [
                    'temperature_f' => (float) $tempF,
                    'flags'         => $flags,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $points = $data['bbt_chart']['points'] ?? [];
                    $hasCoverlineCol = \Illuminate\Support\Facades\Schema::hasColumn('bbt_logs', 'coverline_value');

                    if (! empty($points) && is_array($points)) {
                        foreach ($points as $point) {
                            $coverlineVal = $data['bbt_chart']['coverline_value'] ?? null;
                            $coverlineAlgStr = $data['coverline_algorithm']['summary']['coverline'] ?? '—';
                            $ovulationConfirmed = ($coverlineAlgStr !== '—');
                            $phase = $data['coverline_algorithm']['summary']['phase'] ?? null;

                            $logData = [
                                'user_id'     => $userId,
                                'cycle_id'    => $cycle->id,
                                'temperature' => $point['temperature_f'] ?? $tempF,
                                'unit'        => 'F',
                                'logged_at'   => now(),
                                'is_excluded' => $point['is_excluded'] ?? false,
                                'illness'     => in_array('illness', $point['flags'] ?? []),
                                'poor_sleep'  => in_array('poor_sleep', $point['flags'] ?? []),
                                'alcohol'     => in_array('alcohol', $point['flags'] ?? []),
                                'late_wakeup' => in_array('late_wakeup', $point['flags'] ?? []),
                                'travel'      => in_array('travel', $point['flags'] ?? []),
                                'notes'       => $request->input('notes'),
                            ];

                            if ($hasCoverlineCol) {
                                $logData['coverline_value']     = $coverlineVal;
                                $logData['ovulation_confirmed'] = $ovulationConfirmed;
                                $logData['cycle_day']           = $point['day'] ?? null;
                                $logData['phase']               = $phase;
                            }

                            BbtLog::updateOrCreate(
                                [
                                    'user_id'  => $userId,
                                    'cycle_id' => $cycle->id,
                                    'log_date' => $point['date'] ?? $logDate,
                                ],
                                $logData
                            );
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("AI Engine BBT call warning: " . $e->getMessage());
            }

            if (! is_array($data) || empty($data['bbt_chart'])) {
                $userLogs = BbtLog::where('user_id', $userId)->orderBy('log_date')->get();
                $points = $userLogs->map(function ($log, $idx) {
                    return [
                        'day'           => $idx + 1,
                        'date'          => $log->log_date ? $log->log_date->format('Y-m-d') : null,
                        'temperature_f' => (float) $log->temperature,
                        'is_excluded'   => (bool) $log->is_excluded,
                        'flags'         => collect(['illness', 'poor_sleep', 'alcohol', 'late_wakeup', 'travel'])
                            ->filter(fn($f) => (bool) ($log->$f ?? false))
                            ->values()
                            ->toArray(),
                    ];
                })->toArray();

                $data = [
                    'bbt_chart' => [
                        'title' => 'BBT CHART — CYCLE DAY 1-' . count($points),
                        'subtitle' => count($points) >= 6 ? 'Coverline active' : 'Awaiting confirmation',
                        'points' => $points,
                        'coverline_value' => null,
                        'cycle_day_range' => [
                            'start' => 1,
                            'end' => max(1, count($points)),
                        ],
                    ],
                    'coverline_algorithm' => [
                        'title' => 'COVERLINE ALGORITHM',
                        'steps' => [
                            ['checked' => count($points) >= 6, 'text' => 'Step 1: Coverline = highest of 6 pre-shift low temps.'],
                            ['checked' => false, 'text' => 'Step 2: Shift requires 3 consecutive days ≥ 0.2°F above coverline.'],
                            ['checked' => false, 'text' => 'Step 3: Ovulation confirmation pending.'],
                        ],
                        'summary' => [
                            'coverline' => '—',
                            'luteal_length' => '14d',
                            'phase' => 'Normal',
                        ],
                    ],
                ];
            }

            // 4. Auto-trigger cycle summary sync (updates SignalHistory & OvulationReconciliation)
            try {
                app(\App\Http\Controllers\AI\CycleSummaryController::class)->sync();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('BBT sync trigger warning: ' . $e->getMessage());
            }

            return response()->json(array_merge(is_array($data) ? $data : [], [
                'success' => true,
                'message' => 'BBT data logged successfully.',
                'bbt_log' => BbtLog::where('user_id', $userId)->where('log_date', $logDate)->first(),
                'data'    => $data,
            ]), 200);

        } catch (\Exception $e) {
            \Log::error('BBT log failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function fetchLog(Request $request)
    {
        try {
            $userId = auth()->id() ?? $request->query('user_id');
            $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');

            try {
                $response = Http::timeout(15)->acceptJson()->get("{$baseUrl}/api/v1/cycle-engine/bbt/ui", [
                    'user_id' => $userId
                ]);

                if ($response->successful()) {
                    return response()->json($response->json(), 200);
                }
            } catch (\Throwable $e) {
                \Log::warning('Cycle Engine BBT fetch API warning: ' . $e->getMessage());
            }

            // Fallback dynamic local generation if AI service unavailable
            $userLogs = BbtLog::where('user_id', $userId)->orderBy('log_date')->get();
            $points = $userLogs->map(function ($log, $idx) {
                return [
                    'day'           => $idx + 1,
                    'date'          => $log->log_date ? $log->log_date->format('Y-m-d') : null,
                    'temperature_f' => (float) $log->temperature,
                    'is_excluded'   => (bool) $log->is_excluded,
                    'flags'         => collect(['illness', 'poor_sleep', 'alcohol', 'late_wakeup', 'travel'])
                        ->filter(fn($f) => (bool) ($log->$f ?? false))
                        ->values()
                        ->toArray(),
                ];
            })->toArray();

            return response()->json([
                'bbt_chart' => [
                    'title' => 'BBT CHART — CYCLE DAY 1-' . count($points),
                    'subtitle' => count($points) >= 6 ? 'Coverline active' : 'Awaiting confirmation',
                    'points' => $points,
                    'coverline_value' => null,
                    'cycle_day_range' => [
                        'start' => 1,
                        'end' => max(1, count($points)),
                    ],
                ],
                'coverline_algorithm' => [
                    'title' => 'COVERLINE ALGORITHM',
                    'steps' => [
                        ['checked' => count($points) >= 6, 'text' => 'Step 1: Coverline = highest of 6 pre-shift low temps.'],
                        ['checked' => false, 'text' => 'Step 2: Shift requires 3 consecutive days ≥ 0.2°F above coverline.'],
                        ['checked' => false, 'text' => 'Step 3: Ovulation confirmation pending.'],
                    ],
                    'summary' => [
                        'coverline' => '—',
                        'luteal_length' => '14d',
                        'phase' => 'Normal',
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Fetch BBT log failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function fetchBbtLogs(Request $request)
    {
        try {
            // Fetch all logs for the authenticated user
            $logs = BbtLog::where('user_id', auth()->id())
                ->orderBy('log_date')
                ->get();

            // Group logs by date
            $grouped = $logs->groupBy('log_date');

            return response()->json([
                'success' => true,
                'data'    => $grouped,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Fetching BBT logs failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


}
