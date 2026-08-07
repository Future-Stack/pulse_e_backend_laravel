<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\CycleCalendarInput;
use App\Models\CycleStatistic;
use App\Models\MenstrualCycle;
use App\Models\SignalHistory;
use App\Models\OvulationReconciliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CycleSummaryController extends Controller
{
    public function sync()
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Authenticated User
        |--------------------------------------------------------------------------
        */

        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Get Latest Calendar Input
        |--------------------------------------------------------------------------
        */

        $calendarInput = CycleCalendarInput::where('user_id', $user->id)
            ->latest('start_date')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | 3. AI Engine URL
        |--------------------------------------------------------------------------
        */

        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/') . '/api/v1/cycle-engine/engine';

        /*
        |--------------------------------------------------------------------------
        | 4. Fetch AI Engine Data
        |--------------------------------------------------------------------------
        */

        /*
        |--------------------------------------------------------------------------
        | 4. Fetch AI Engine Data
        |--------------------------------------------------------------------------
        */

        $summary = null;
        $signal = null;
        $discrepancy = null;
        $summaryData = null;

        try {
            $responses = Http::pool(function ($pool) use ($baseUrl, $user) {
                return [
                    $pool->timeout(10)->acceptJson()->get($baseUrl . '/summary', ['user_id' => $user->id]),
                    $pool->timeout(10)->acceptJson()->get($baseUrl . '/signal-status', ['user_id' => $user->id]),
                    $pool->timeout(10)->acceptJson()->get($baseUrl . '/discrepancy-note', ['user_id' => $user->id]),
                ];
            });

            if (
                isset($responses[0]) && $responses[0]->successful() &&
                isset($responses[1]) && $responses[1]->successful() &&
                isset($responses[2]) && $responses[2]->successful()
            ) {
                $summary = $responses[0]->json();
                $signal = $responses[1]->json();
                $discrepancy = $responses[2]->json();
                $summaryData = $summary['data'] ?? $summary;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("AI Engine connection failed, using dynamic local calculation: " . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Dynamic Fallback Generation if AI response is missing/empty/invalid
        |--------------------------------------------------------------------------
        */

        if (! is_array($summaryData) || empty($summaryData['cycle_summary']) || ($summaryData['status'] ?? null) === 'empty') {
            $startDate = $calendarInput?->start_date
                ? \Carbon\Carbon::parse($calendarInput->start_date)->toDateString()
                : today()->toDateString();

            $startCarbon = \Carbon\Carbon::parse($startDate);
            $currentCycleDay = max(1, (int) $startCarbon->diffInDays(today()) + 1);

            $phase = match (true) {
                $currentCycleDay <= 5 => 'menstrual',
                $currentCycleDay <= 13 => 'follicular',
                $currentCycleDay <= 16 => 'ovulatory',
                default => 'luteal',
            };

            $todayBbt = \App\Models\BbtLog::where('user_id', $user->id)
                ->whereDate('log_date', today())
                ->exists();

            $todayOpk = \App\Models\OpkLog::whereHas('cycle', fn($q) => $q->where('user_id', $user->id))
                ->whereDate('log_date', today())
                ->exists();

            $todayCalendar = (bool) $calendarInput;

            $summaryData = [
                'cycle_summary' => [
                    'user_id' => $user->id,
                    'current_cycle_day' => $currentCycleDay,
                    'current_phase' => $phase,
                    'avg_cycle_length' => 28.0,
                    'cycle_variance_days' => 2,
                    'current_mode' => 'cycle_awareness',
                ],
                'fertile_window' => [
                    'start_day' => 10,
                    'end_day' => 15,
                    'label' => 'predicted',
                    'peak_day' => 14,
                    'peak_source' => 'calendar',
                    'mucus_peak_day' => null,
                    'lh_surge_day' => null,
                    'bbt_confirmed_day' => null,
                ],
                'reliability' => [
                    'level' => 'low',
                    'completed_cycles' => \App\Models\MenstrualCycle::where('user_id', $user->id)->where('is_completed', true)->count(),
                    'text' => 'Predictions are dynamically calculated locally from your logged cycle inputs.',
                ],
                'reconciliation' => [
                    'calendar_predicted_day' => 14,
                    'bbt_confirmed_day' => null,
                    'lh_surge_day' => null,
                    'final_confirmed_day' => 14,
                    'final_source' => 'calendar',
                    'offset_days' => 0,
                    'luteal_phase_length' => 14,
                ],
                'ai_generated' => false,
                'ai_cached' => false,
            ];

            $summary = $summaryData;

            $signal = [
                'signals' => [
                    ['signal' => 'Calendar', 'logged_today' => $todayCalendar, 'status_text' => "Cycle Day {$currentCycleDay} · " . ucfirst($phase) . " phase"],
                    ['signal' => 'OPK / LH', 'logged_today' => $todayOpk, 'status_text' => $todayOpk ? 'OPK logged today' : 'No LH test logged'],
                    ['signal' => 'BBT', 'logged_today' => $todayBbt, 'status_text' => $todayBbt ? 'BBT logged today' : 'No BBT logged today'],
                    ['signal' => 'Mucus', 'logged_today' => false, 'status_text' => 'No mucus logged'],
                ],
                'ai_generated' => false,
                'ai_cached' => false,
            ];

            $discrepancy = [
                'active' => false,
                'message' => 'Calendar and biometric signals are aligned locally.',
                'ai_generated' => false,
                'ai_cached' => false,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Get Values for Persistence
        |--------------------------------------------------------------------------
        */

        $averageCycleLength = $summaryData['cycle_summary']['avg_cycle_length'] ?? 28;
        $cycleVarianceDays = $summaryData['cycle_summary']['cycle_variance_days'] ?? 2;
        $rawReliability = strtolower((string) ($summaryData['reliability']['level'] ?? 'low'));
        $reliabilityLevel = in_array($rawReliability, ['low', 'medium', 'high']) ? $rawReliability : 'low';

        try {
            DB::transaction(function () use (
                $user,
                $calendarInput,
                $summary,
                $summaryData,
                $signal,
                $discrepancy,
                $averageCycleLength,
                $cycleVarianceDays,
                $reliabilityLevel
            ) {

                /*
                |--------------------------------------------------------------------------
                | 7. Cycle Statistics (Always Inserted / Updated)
                |--------------------------------------------------------------------------
                */

                $cycleStatistic = CycleStatistic::firstOrNew([
                    'user_id' => $user->id,
                ]);

                $cycleStatistic->completed_cycles = $summaryData['reliability']['completed_cycles'] ?? 0;
                $cycleStatistic->average_cycle_length = (int) $averageCycleLength;
                $cycleStatistic->cycle_variance_days = (int) $cycleVarianceDays;
                $cycleStatistic->reliability_level = $reliabilityLevel;
                $cycleStatistic->save();

                /*
                |--------------------------------------------------------------------------
                | 8. Current Active Cycle (Always Inserted / Updated)
                |--------------------------------------------------------------------------
                */

                $rawPhase = strtolower((string) ($summaryData['cycle_summary']['current_phase'] ?? ''));
                $currentPhase = in_array($rawPhase, ['menstrual', 'follicular', 'ovulatory', 'luteal']) ? $rawPhase : null;

                $rawSource = strtolower((string) ($summaryData['fertile_window']['peak_source'] ?? 'calendar'));
                $predictionSource = in_array($rawSource, ['calendar', 'bbt', 'opk', 'mucus', 'combined']) ? $rawSource : 'calendar';

                $existingCycle = MenstrualCycle::where('user_id', $user->id)->where('is_completed', false)->first();

                $cycle = MenstrualCycle::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'is_completed' => false,
                    ],
                    [
                        'period_start_date' =>
                            $calendarInput?->start_date ?? $existingCycle?->period_start_date ?? today()->toDateString(),

                        'current_cycle_day' =>
                            $summaryData['cycle_summary']['current_cycle_day'] ?? 1,

                        'current_phase' => $currentPhase,

                        'fertile_start_day' =>
                            $summaryData['fertile_window']['start_day'] ?? 10,

                        'fertile_end_day' =>
                            $summaryData['fertile_window']['end_day'] ?? 15,

                        'predicted_peak_day' =>
                            $summaryData['fertile_window']['peak_day'] ?? 14,

                        'prediction_source' => $predictionSource,
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | 9. Save Signal History (Always Inserted / Updated)
                |--------------------------------------------------------------------------
                */

                $calendar = false;
                $bbt = false;
                $opk = false;
                $mucus = false;
                $statusMessages = [];

                foreach ($signal['signals'] ?? [] as $item) {
                    $statusMessages[] = $item['status_text'] ?? '';

                    switch ($item['signal'] ?? null) {
                        case 'Calendar':
                            $calendar = (bool) ($item['logged_today'] ?? false);
                            break;
                        case 'BBT':
                            $bbt = (bool) ($item['logged_today'] ?? false);
                            break;
                        case 'OPK / LH':
                            $opk = (bool) ($item['logged_today'] ?? false);
                            break;
                        case 'Mucus':
                            $mucus = (bool) ($item['logged_today'] ?? false);
                            break;
                    }
                }

                // Fallback check against local DB logs for active cycle if AI didn't mark them true
                $hasLocalBbt = \App\Models\BbtLog::where('cycle_id', $cycle->id)->exists();
                $hasLocalOpk = \App\Models\OpkLog::where('cycle_id', $cycle->id)->exists();

                $bbt = $bbt || $hasLocalBbt;
                $opk = $opk || $hasLocalOpk;
                $calendar = $calendar || true;

                $count = collect([$calendar, $bbt, $opk, $mucus])->filter()->count();

                $strength = match (true) {
                    $count === 0 => 'none',
                    $count === 1 => 'low',
                    $count <= 3 => 'medium',
                    default => 'high',
                };

                SignalHistory::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                        'log_date' => today(),
                    ],
                    [
                        'calendar_logged' => $calendar,
                        'bbt_logged' => $bbt,
                        'opk_logged' => $opk,
                        'mucus_logged' => $mucus,
                        'symptoms_logged' => false,
                        'signal_strength' => $strength,
                        'signals' => $signal['signals'] ?? [],
                        'ai_generated' => $signal['ai_generated'] ?? false,
                        'ai_cached' => $signal['ai_cached'] ?? false,
                        'sources' => $signal['sources'] ?? null,
                        'backend_errors' => $signal['backend_errors'] ?? null,
                        'status_message' => implode("\n", array_filter($statusMessages)),
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | 10. Save Ovulation Reconciliation (Always Inserted / Updated)
                |--------------------------------------------------------------------------
                */

                $lhSurgeDay = $summaryData['reconciliation']['lh_surge_day'] ?? null;
                if (!$lhSurgeDay) {
                    $opkPeakLog = \App\Models\OpkLog::where('cycle_id', $cycle->id)
                        ->whereIn('result', ['peak', 'positive'])
                        ->orderBy('log_date')
                        ->first();

                    if ($opkPeakLog && $cycle->period_start_date) {
                        $lhSurgeDay = max(1, (int) \Carbon\Carbon::parse($cycle->period_start_date)->diffInDays(\Carbon\Carbon::parse($opkPeakLog->log_date)) + 1);
                    }
                }

                $bbtConfirmedDay = $summaryData['reconciliation']['bbt_confirmed_day'] ?? null;
                if (!$bbtConfirmedDay) {
                    $bbtConfirmedLog = \App\Models\BbtLog::where('cycle_id', $cycle->id)
                        ->where(function($q) {
                            $q->where('ovulation_confirmed', true)->orWhereNotNull('cycle_day');
                        })
                        ->orderBy('log_date')
                        ->first();

                    if ($bbtConfirmedLog) {
                        $bbtConfirmedDay = $bbtConfirmedLog->cycle_day
                            ?? ($cycle->period_start_date ? max(1, (int) \Carbon\Carbon::parse($cycle->period_start_date)->diffInDays(\Carbon\Carbon::parse($bbtConfirmedLog->log_date)) + 1) : null);
                    }
                }

                $calendarPredictedDay = (int) ($summaryData['reconciliation']['calendar_predicted_day'] ?? 14);
                $finalConfirmedDay = $lhSurgeDay ?? $bbtConfirmedDay ?? $summaryData['reconciliation']['final_confirmed_day'] ?? $calendarPredictedDay;

                $finalSource = $lhSurgeDay ? 'opk' : ($bbtConfirmedDay ? 'bbt' : (in_array(strtolower((string) ($summaryData['reconciliation']['final_source'] ?? '')), ['calendar', 'bbt', 'opk', 'mucus', 'combined']) ? strtolower((string) $summaryData['reconciliation']['final_source']) : 'calendar'));

                $offsetDays = (int) ($finalConfirmedDay - $calendarPredictedDay);

                OvulationReconciliation::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                    ],
                    [
                        'calendar_predicted_day' => $calendarPredictedDay,
                        'bbt_confirmed_day' => $bbtConfirmedDay,
                        'lh_surge_day' => $lhSurgeDay,
                        'mucus_peak_day' => $summaryData['fertile_window']['mucus_peak_day'] ?? null,
                        'final_confirmed_day' => $finalConfirmedDay,
                        'final_source' => $finalSource,
                        'offset_days' => $offsetDays,
                        'luteal_phase_length' => (int) ($summaryData['reconciliation']['luteal_phase_length'] ?? 14),
                        'has_discrepancy' => $discrepancy['active'] ?? false,
                        'discrepancy_note' => $discrepancy['message'] ?? null,
                        'is_reconciled' => ! ($discrepancy['active'] ?? false),
                        'reconciled_at' => now(),
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | 11. Save Prediction Cache
                |--------------------------------------------------------------------------
                */

                \App\Models\CyclePredictionCache::updateOrCreate(
                    [
                        'cycle_id'  => $cycle->id,
                        'cache_key' => "summary_user_{$user->id}_cycle_{$cycle->id}",
                    ],
                    [
                        'endpoint'           => '/api/v1/cycle-engine/engine/summary',
                        'request_payload'    => ['user_id' => $user->id],
                        'prediction'         => $summaryData,
                        'prediction_version' => '1.0',
                        'ai_generated'       => $summaryData['ai_generated'] ?? false,
                        'ai_cached'          => $summaryData['ai_cached'] ?? false,
                        'expires_at'         => now()->addHours(6),
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | 12. Populate Cycle Daily Log for Today
                |--------------------------------------------------------------------------
                */

                if ($currentPhase && $cycle->current_cycle_day) {
                    $tag = 'none';
                    if ($cycle->current_cycle_day == ($summaryData['fertile_window']['peak_day'] ?? 14)) {
                        $tag = 'ovulation';
                    } elseif (
                        $cycle->fertile_start_day &&
                        $cycle->fertile_end_day &&
                        $cycle->current_cycle_day >= $cycle->fertile_start_day &&
                        $cycle->current_cycle_day <= $cycle->fertile_end_day
                    ) {
                        $tag = 'fertile';
                    } elseif ($currentPhase === 'menstrual') {
                        $tag = 'period';
                    }

                    \App\Models\CycleDailyLog::updateOrCreate(
                        [
                            'cycle_id' => $cycle->id,
                            'log_date' => today()->toDateString(),
                        ],
                        [
                            'cycle_day'     => $cycle->current_cycle_day,
                            'phase'         => $currentPhase,
                            'tag'           => $tag,
                            'is_prediction' => true,
                        ]
                    );
                }
            });

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("CycleSummaryController DB transaction error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Database sync failed.',
                'error' => $e->getMessage(),
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | 18. Final Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Cycle dashboard synced successfully.',
            'data' => [
                'summary' => $summary,
                'signal_status' => $signal,
                'discrepancy' => $discrepancy,
            ],
        ]);
    }

    public function syncSignalStatus()
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/') . '/api/v1/cycle-engine/engine/signal-status';

        try {
            $response = Http::timeout(120)
                ->acceptJson()
                ->get($baseUrl, [
                    'user_id' => $user->id,
                ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch signal status from AI Engine.',
                'error' => $response->json() ?? $response->body(),
            ], $response->status());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to connect to AI Engine.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function syncDiscrepancyNote()
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/') . '/api/v1/cycle-engine/engine/discrepancy-note';

        try {
            $response = Http::timeout(120)
                ->acceptJson()
                ->get($baseUrl, [
                    'user_id' => $user->id,
                ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch discrepancy note from AI Engine.',
                'error' => $response->json() ?? $response->body(),
            ], $response->status());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to connect to AI Engine.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function aiSummary()
{
    $user = auth()->user();

    if (! $user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
        ], 401);
    }

    $url = 'https://ai.fightthenumber.com/api/v1/cycle-engine/engine/summary';

    try {

        $response = Http::timeout(120)
            ->acceptJson()
            ->get($url, [
                'user_id' => $user->id,
            ]);

        return response()->json([
            'success' => $response->successful(),
            'data' => $response->json(),
        ], $response->status());

    } catch (\Throwable $e) {

        return response()->json([
            'success' => false,
            'message' => 'Unable to connect AI Engine.',
            'error' => $e->getMessage(),
        ], 500);
    }
}
}

