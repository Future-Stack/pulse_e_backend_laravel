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
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Get Latest Calendar Input
        |--------------------------------------------------------------------------
        */

        $calendarInput = CycleCalendarInput::where('user_id', $user->id)
            ->latest('start_date')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | AI Engine Base URL
        |--------------------------------------------------------------------------
        */

        $baseUrl = config('services.ai.base_url')
            . '/api/v1/cycle-engine/engine';

        /*
        |--------------------------------------------------------------------------
        | Fetch AI Engine Data
        |--------------------------------------------------------------------------
        */

        try {

            $responses = Http::pool(function ($pool) use ($baseUrl, $user) {

                return [

                    // Cycle Summary
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

                    // Discrepancy Note
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

        $summaryResponse = $responses[0];
        $signalResponse = $responses[1];
        $discrepancyResponse = $responses[2];

        /*
        |--------------------------------------------------------------------------
        | Validate AI Responses
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
                    'summary' => $summaryResponse->body(),
                    'signal_status' => $signalResponse->body(),
                    'discrepancy' => $discrepancyResponse->body(),
                ],
            ], 500);
        }

        $summary = $summaryResponse->json();
        $signal = $signalResponse->json();
        $discrepancy = $discrepancyResponse->json();

        /*
        |--------------------------------------------------------------------------
        | Save Data
        |--------------------------------------------------------------------------
        */

        try {

            DB::transaction(function () use (
                $user,
                $calendarInput,
                $summary,
                $signal,
                $discrepancy
            ) {

                /*
                |--------------------------------------------------------------------------
                | 1. Cycle Statistics
                |--------------------------------------------------------------------------
                */

                CycleStatistic::updateOrCreate(
                    [
                        'user_id' => $user->id,
                    ],
                    [
                        'completed_cycles' =>
                            $summary['reliability']['completed_cycles'] ?? 0,

                        'average_cycle_length' =>
                            $summary['cycle_summary']['avg_cycle_length'] ?? null,

                        'cycle_variance_days' =>
                            $summary['cycle_summary']['cycle_variance_days'] ?? null,

                        'reliability_level' =>
                            $summary['reliability']['level'] ?? null,
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | 2. Current Active Cycle
                |--------------------------------------------------------------------------
                */

                $cycle = MenstrualCycle::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'is_completed' => false,
                    ],
                    [
                        /*
                         * Calendar input না থাকলে NULL হবে
                         */
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
                | 3. Process Signal Status
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

                /*
                |--------------------------------------------------------------------------
                | 4. Calculate Signal Strength
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
                | 5. Save Signal History
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
                | 6. Save Ovulation Reconciliation
                |--------------------------------------------------------------------------
                */

                OvulationReconciliation::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                    ],
                    [
                        'calendar_predicted_day' =>
                            $summary['reconciliation']['calendar_predicted_day'] ?? null,

                        'bbt_confirmed_day' =>
                            $summary['reconciliation']['bbt_confirmed_day'] ?? null,

                        'lh_surge_day' =>
                            $summary['reconciliation']['lh_surge_day'] ?? null,

                        'mucus_peak_day' =>
                            $summary['fertile_window']['mucus_peak_day'] ?? null,

                        'final_confirmed_day' =>
                            $summary['reconciliation']['final_confirmed_day'] ?? null,

                        'final_source' =>
                            $summary['reconciliation']['final_source'] ?? null,

                        'offset_days' =>
                            $summary['reconciliation']['offset_days'] ?? null,

                        'luteal_phase_length' =>
                            $summary['reconciliation']['luteal_phase_length'] ?? null,

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
        | Final Response
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
}