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

            $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');

            $response = Http::timeout(90)->post("{$baseUrl}/api/v1/cycle-engine/bbt/ui?user_id=" . $userId, [
                'temperature_f' => (float) $tempF,
                'flags'         => $flags,
            ]);

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to log BBT data with AI Engine.',
                    'error'   => $response->json() ?? $response->body(),
                ], $response->status());
            }

            $data = $response->json();

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
            } else {
                $logData = [
                    'user_id'     => $userId,
                    'cycle_id'    => $cycle->id,
                    'temperature' => $tempF,
                    'unit'        => 'F',
                    'logged_at'   => now(),
                    'illness'     => in_array('illness', $flags),
                    'poor_sleep'  => in_array('poor_sleep', $flags),
                    'alcohol'     => in_array('alcohol', $flags),
                    'late_wakeup' => in_array('late_wakeup', $flags),
                    'travel'      => in_array('travel', $flags),
                    'notes'       => $request->input('notes'),
                ];

                BbtLog::updateOrCreate(
                    [
                        'user_id'  => $userId,
                        'cycle_id' => $cycle->id,
                        'log_date' => $logDate,
                    ],
                    $logData
                );
            }

            // Auto-trigger cycle summary sync to update SignalHistory & OvulationReconciliation
            try {
                app(\App\Http\Controllers\AI\CycleSummaryController::class)->sync();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('BBT sync trigger warning: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'BBT data logged successfully.',
                'data'    => $data,
            ], 200);

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
            $userId = auth()->id();
            $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');

            $response = Http::timeout(90)->get("{$baseUrl}/api/v1/cycle-engine/bbt/ui",
                [
                    'user_id' => $userId
                ]);


            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'API returned error',
                'data' => $response->json(),
            ], $response->status());

        } catch (\Exception $e) {
            \Log::error('Cycle Engine API failed: ' . $e->getMessage());

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
