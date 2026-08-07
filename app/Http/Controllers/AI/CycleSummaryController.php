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

        try {

            $responses = Http::pool(function ($pool) use ($baseUrl, $user) {

                return [

                    // Summary
                    $pool->timeout(120)
                        ->acceptJson()
                        ->get($baseUrl . '/summary', [
                            'user_id' => $user->id,
                        ]),

                    // Signal Status
                    $pool->timeout(120)
                        ->acceptJson()
                        ->get($baseUrl . '/signal-status', [
                            'user_id' => $user->id,
                        ]),

                    // Discrepancy
                    $pool->timeout(120)
                        ->acceptJson()
                        ->get($baseUrl . '/discrepancy-note', [
                            'user_id' => $user->id,
                        ]),
                ];
            });

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Unable to connect AI Engine.',
                'error' => $e->getMessage(),
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Get Responses
        |--------------------------------------------------------------------------
        */

        $summaryResponse = $responses[0];
        $signalResponse = $responses[1];
        $discrepancyResponse = $responses[2];

        /*
        |--------------------------------------------------------------------------
        | 6. Validate AI Responses
        |--------------------------------------------------------------------------
        */

        if (
            ! $summaryResponse->successful() ||
            ! $signalResponse->successful() ||
            ! $discrepancyResponse->successful()
        ) {

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch AI Engine data.',
                'errors' => [
                    'summary' => [
                        'status' => $summaryResponse->status(),
                        'body' => $summaryResponse->body(),
                    ],
                    'signal_status' => [
                        'status' => $signalResponse->status(),
                        'body' => $signalResponse->body(),
                    ],
                    'discrepancy' => [
                        'status' => $discrepancyResponse->status(),
                        'body' => $discrepancyResponse->body(),
                    ],
                ],
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Decode Responses
        |--------------------------------------------------------------------------
        */

        /*
        |--------------------------------------------------------------------------
        | 7. Decode Responses & Normalize Data
        |--------------------------------------------------------------------------
        */

        $summary = $summaryResponse->json();
        $signal = $signalResponse->json();
        $discrepancy = $discrepancyResponse->json();

        $summaryData = $summary['data'] ?? $summary;

        /*
        |--------------------------------------------------------------------------
        | 8. Handle Empty Cycle Data
        |--------------------------------------------------------------------------
        */

        if (($summaryData['status'] ?? $summary['status'] ?? null) === 'empty') {
            return response()->json([
                'success' => true,
                'message' => 'No cycle data available yet.',
                'data' => [
                    'summary' => $summary,
                    'signal_status' => $signal,
                    'discrepancy' => $discrepancy,
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 9. Validate Required Summary Data
        |--------------------------------------------------------------------------
        */

        if (
            ! isset($summaryData['cycle_summary']) ||
            ! isset($summaryData['reliability'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid response received from AI Engine.',
                'data' => $summary,
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | 10. Get Required Values
        |--------------------------------------------------------------------------
        */

        $averageCycleLength = $summaryData['cycle_summary']['avg_cycle_length'] ?? null;
        $cycleVarianceDays = $summaryData['cycle_summary']['cycle_variance_days'] ?? null;
        $rawReliability = strtolower((string) ($summaryData['reliability']['level'] ?? ''));
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
                | 12. Cycle Statistics
                |--------------------------------------------------------------------------
                */

                $cycleStatistic = CycleStatistic::firstOrNew([
                    'user_id' => $user->id,
                ]);

                $cycleStatistic->completed_cycles = $summaryData['reliability']['completed_cycles'] ?? 0;

                if ($averageCycleLength !== null) {
                    $cycleStatistic->average_cycle_length = (int) $averageCycleLength;
                }

                $cycleStatistic->cycle_variance_days = (int) ($cycleVarianceDays ?? 0);
                $cycleStatistic->reliability_level = $reliabilityLevel;
                $cycleStatistic->save();

                /*
                |--------------------------------------------------------------------------
                | 13. Current Active Cycle
                |--------------------------------------------------------------------------
                */

                $rawPhase = strtolower((string) ($summaryData['cycle_summary']['current_phase'] ?? ''));
                $currentPhase = in_array($rawPhase, ['menstrual', 'follicular', 'ovulatory', 'luteal']) ? $rawPhase : null;

                $rawSource = strtolower((string) ($summaryData['fertile_window']['peak_source'] ?? ''));
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
                            $summaryData['cycle_summary']['current_cycle_day'] ?? null,

                        'current_phase' => $currentPhase,

                        'fertile_start_day' =>
                            $summaryData['fertile_window']['start_day'] ?? null,

                        'fertile_end_day' =>
                            $summaryData['fertile_window']['end_day'] ?? null,

                        'predicted_peak_day' =>
                            $summaryData['fertile_window']['peak_day'] ?? null,

                        'prediction_source' => $predictionSource,
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | 14. Process Signal Status
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

                $count = collect([$calendar, $bbt, $opk, $mucus])->filter()->count();

                $strength = match (true) {
                    $count === 0 => 'none',
                    $count === 1 => 'low',
                    $count <= 3 => 'medium',
                    default => 'high',
                };

                /*
                |--------------------------------------------------------------------------
                | 16. Save Signal History
                |--------------------------------------------------------------------------
                */

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
                | 17. Save Ovulation Reconciliation
                |--------------------------------------------------------------------------
                */

                $finalSource = in_array(strtolower((string) ($summaryData['reconciliation']['final_source'] ?? '')), ['calendar', 'bbt', 'opk', 'mucus', 'combined'])
                    ? strtolower((string) $summaryData['reconciliation']['final_source'])
                    : null;

                OvulationReconciliation::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                    ],
                    [
                        'calendar_predicted_day' =>
                            $summaryData['reconciliation']['calendar_predicted_day'] ?? null,

                        'bbt_confirmed_day' =>
                            $summaryData['reconciliation']['bbt_confirmed_day'] ?? null,

                        'lh_surge_day' =>
                            $summaryData['reconciliation']['lh_surge_day'] ?? null,

                        'mucus_peak_day' =>
                            $summaryData['fertile_window']['mucus_peak_day'] ?? null,

                        'final_confirmed_day' =>
                            $summaryData['reconciliation']['final_confirmed_day'] ?? null,

                        'final_source' => $finalSource,

                        'offset_days' =>
                            (int) ($summaryData['reconciliation']['offset_days'] ?? 0),

                        'luteal_phase_length' =>
                            (int) ($summaryData['reconciliation']['luteal_phase_length'] ?? 14),

                        'has_discrepancy' =>
                            $discrepancy['active'] ?? false,

                        'discrepancy_note' =>
                            $discrepancy['message'] ?? null,

                        'is_reconciled' =>
                            ! ($discrepancy['active'] ?? false),

                        'reconciled_at' => now(),
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | 18. Save Prediction Cache
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
                        'ai_generated'       => $summaryData['ai_generated'] ?? true,
                        'ai_cached'          => $summaryData['ai_cached'] ?? false,
                        'expires_at'         => now()->addHours(6),
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | 19. Populate Cycle Daily Log for Today
                |--------------------------------------------------------------------------
                */

                if ($currentPhase && $cycle->current_cycle_day) {
                    $tag = 'none';
                    if ($cycle->current_cycle_day == $summaryData['fertile_window']['peak_day']) {
                        $tag = 'ovulation';
                    } elseif ($cycle->fertile_start_day && $cycle->fertile_end_day && $cycle->current_cycle_day >= $cycle->fertile_start_day && $cycle->current_cycle_day <= $cycle->fertile_end_day) {
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

