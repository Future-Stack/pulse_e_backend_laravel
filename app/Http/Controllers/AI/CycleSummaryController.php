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

        $baseUrl = 'https://ai.fightthenumber.com/api/v1/cycle-engine/engine';

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

        $summary = $summaryResponse->json();
        $signal = $signalResponse->json();
        $discrepancy = $discrepancyResponse->json();

        /*
        |--------------------------------------------------------------------------
        | 8. Handle Empty Cycle Data
        |--------------------------------------------------------------------------
        |
        | AI may return:
        |
        | {
        |     "status": "empty",
        |     "message": "No cycle data yet"
        | }
        |
        | In this case we must NOT try to save avg_cycle_length = null
        | into cycle_statistics because that column is NOT NULL.
        |
        */

        if (($summary['status'] ?? null) === 'empty') {

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
            ! isset($summary['cycle_summary']) ||
            ! isset($summary['reliability'])
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

        $averageCycleLength =
            $summary['cycle_summary']['avg_cycle_length'] ?? null;

        $cycleVarianceDays =
            $summary['cycle_summary']['cycle_variance_days'] ?? null;

        $reliabilityLevel =
            $summary['reliability']['level'] ?? null;

        /*
        |--------------------------------------------------------------------------
        | 11. Prevent NULL average_cycle_length
        |--------------------------------------------------------------------------
        |
        | Your database does not allow average_cycle_length to be NULL.
        |
        | If AI does not provide it, don't update that column.
        |
        */

        try {

            DB::transaction(function () use (
                $user,
                $calendarInput,
                $summary,
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

                $cycleStatistic->completed_cycles =
                    $summary['reliability']['completed_cycles'] ?? 0;

                /*
                | Only update average_cycle_length when AI provides a value.
                */

                if ($averageCycleLength !== null) {
                    $cycleStatistic->average_cycle_length =
                        $averageCycleLength;
                }

                /*
                | cycle_variance_days can be nullable
                */

                $cycleStatistic->cycle_variance_days =
                    $cycleVarianceDays;

                $cycleStatistic->reliability_level =
                    $reliabilityLevel;

                $cycleStatistic->save();

                /*
                |--------------------------------------------------------------------------
                | 13. Current Active Cycle
                |--------------------------------------------------------------------------
                */

                $cycle = MenstrualCycle::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'is_completed' => false,
                    ],
                    [
                        'period_start_date' =>
                            $calendarInput?->start_date,

                        'current_cycle_day' =>
                            $summary['cycle_summary']['current_cycle_day'] ?? null,

                        'current_phase' =>
                            $summary['cycle_summary']['current_phase'] ?? null,

                        'fertile_start_day' =>
                            $summary['fertile_window']['start_day'] ?? null,

                        'fertile_end_day' =>
                            $summary['fertile_window']['end_day'] ?? null,

                        'predicted_peak_day' =>
                            $summary['fertile_window']['peak_day'] ?? null,

                        'prediction_source' =>
                            $summary['fertile_window']['peak_source'] ?? null,
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

                    $statusMessages[] =
                        $item['status_text'] ?? '';

                    switch ($item['signal'] ?? null) {

                        case 'Calendar':
                            $calendar =
                                (bool) ($item['logged_today'] ?? false);
                            break;

                        case 'BBT':
                            $bbt =
                                (bool) ($item['logged_today'] ?? false);
                            break;

                        case 'OPK / LH':
                            $opk =
                                (bool) ($item['logged_today'] ?? false);
                            break;

                        case 'Mucus':
                            $mucus =
                                (bool) ($item['logged_today'] ?? false);
                            break;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | 15. Calculate Signal Strength
                |--------------------------------------------------------------------------
                */

                $count = collect([
                    $calendar,
                    $bbt,
                    $opk,
                    $mucus,
                ])
                    ->filter()
                    ->count();

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

                        'signals' =>
                            $signal['signals'] ?? [],

                        'ai_generated' =>
                            $signal['ai_generated'] ?? false,

                        'ai_cached' =>
                            $signal['ai_cached'] ?? false,

                        'sources' =>
                            $signal['sources'] ?? null,

                        'backend_errors' =>
                            $signal['backend_errors'] ?? null,

                        'status_message' =>
                            implode("\n", $statusMessages),
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | 17. Save Ovulation Reconciliation
                |--------------------------------------------------------------------------
                */

                OvulationReconciliation::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                    ],
                    [
                        'calendar_predicted_day' =>
                            $summary['reconciliation']['calendar_predicted_day']
                            ?? null,

                        'bbt_confirmed_day' =>
                            $summary['reconciliation']['bbt_confirmed_day']
                            ?? null,

                        'lh_surge_day' =>
                            $summary['reconciliation']['lh_surge_day']
                            ?? null,

                        'mucus_peak_day' =>
                            $summary['fertile_window']['mucus_peak_day']
                            ?? null,

                        'final_confirmed_day' =>
                            $summary['reconciliation']['final_confirmed_day']
                            ?? null,

                        'final_source' =>
                            $summary['reconciliation']['final_source']
                            ?? null,

                        'offset_days' =>
                            $summary['reconciliation']['offset_days']
                            ?? null,

                        'luteal_phase_length' =>
                            $summary['reconciliation']['luteal_phase_length']
                            ?? null,

                        'has_discrepancy' =>
                            $discrepancy['active'] ?? false,

                        'discrepancy_note' =>
                            $discrepancy['message'] ?? null,

                        'is_reconciled' =>
                            ! ($discrepancy['active'] ?? false),

                        'reconciled_at' => now(),
                    ]
                );
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
}

