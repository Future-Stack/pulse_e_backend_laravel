<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
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


        $baseUrl = config('services.ai.base_url')
            . '/api/v1/cycle-engine/engine';

        /*
        |--------------------------------------------------------------------------
        | Fetch AI Endpoints Concurrently
        |--------------------------------------------------------------------------
        */

           try {

            $responses = Http::pool(function ($pool) use ($baseUrl, $user) {

            return [

                $pool->timeout(120)
                    ->acceptJson()
                    ->get($baseUrl . '/summary', [
                        'user_id' => $user->id,
                    ]),

                $pool->timeout(120)
                    ->acceptJson()
                    ->get($baseUrl . '/signal-status', [
                        'user_id' => $user->id,
                    ]),

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
        ],500);

    }

$summaryResponse = $responses[0];
$signalResponse = $responses[1];
$discrepancyResponse = $responses[2];
        /*
        |--------------------------------------------------------------------------
        | Validate Responses
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
    ], 500);
}

$summary = $summaryResponse->json();
$signal = $signalResponse->json();
$discrepancy = $discrepancyResponse->json();
    try {

    DB::transaction(function () use (
        $user,
        $summary,
        $signal,
        $discrepancy
    ) {

        /*
        |--------------------------------------------------------------------------
        | Cycle Statistics
        |--------------------------------------------------------------------------
        */

        CycleStatistic::updateOrCreate(
            [
                'user_id' => $user->id,
            ],
            [
                'completed_cycles' =>
                    $summary['reliability']['completed_cycles'],

                'average_cycle_length' =>
                    $summary['cycle_summary']['avg_cycle_length'],

                'cycle_variance_days' =>
                    $summary['cycle_summary']['cycle_variance_days'],

                'reliability_level' =>
                    $summary['reliability']['level'],
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Current Active Cycle
        |--------------------------------------------------------------------------
        */

        $cycle = MenstrualCycle::updateOrCreate(
            [
                'user_id' => $user->id,
                'is_completed' => false,
            ],
            [
                'current_cycle_day' =>
                    $summary['cycle_summary']['current_cycle_day'],

                'current_phase' =>
                    $summary['cycle_summary']['current_phase'],

                'fertile_start_day' =>
                    $summary['fertile_window']['start_day'],

                'fertile_end_day' =>
                    $summary['fertile_window']['end_day'],

                'predicted_peak_day' =>
                    $summary['fertile_window']['peak_day'],

                'prediction_source' =>
                    $summary['fertile_window']['peak_source'],
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Read AI Signals
        |--------------------------------------------------------------------------
        */

        $calendar = false;
        $bbt = false;
        $opk = false;
        $mucus = false;

        $statusMessages = [];


        foreach ($signal['signals'] as $item) {

            $statusMessages[] = $item['status_text'];

            switch ($item['signal']) {

                case 'Calendar':
                    $calendar = $item['logged_today'];
                    break;

                case 'BBT':
                    $bbt = $item['logged_today'];
                    break;

                case 'OPK / LH':
                    $opk = $item['logged_today'];
                    break;

                case 'Mucus':
                    $mucus = $item['logged_today'];
                    break;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Calculate Signal Strength
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

            $count == 0 => 'none',

            $count == 1 => 'low',

            $count <= 3 => 'medium',

            default => 'high',

        };


        /*
        |--------------------------------------------------------------------------
        | Save Signal History
        |--------------------------------------------------------------------------
        */

        SignalHistory::updateOrCreate(
            [
                'user_id'  => $user->id,
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

                'signals' => $signal['signals'],

                'ai_generated' => $signal['ai_generated'],

                'ai_cached' => $signal['ai_cached'],

                'sources' => $signal['sources'] ?? null,

                'backend_errors' => $signal['backend_errors'] ?? null,

                'status_message' =>
                    implode("\n", $statusMessages),
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Save Ovulation Reconciliation
        |--------------------------------------------------------------------------
        */

        OvulationReconciliation::updateOrCreate(

            [
                'user_id'  => $user->id,
                'cycle_id' => $cycle->id,
            ],

            [

                'calendar_predicted_day' =>
                    $summary['reconciliation']['calendar_predicted_day'],

                'bbt_confirmed_day' =>
                    $summary['reconciliation']['bbt_confirmed_day'],

                'lh_surge_day' =>
                    $summary['reconciliation']['lh_surge_day'],

                'mucus_peak_day' =>
                    $summary['fertile_window']['mucus_peak_day'],


                'final_confirmed_day' =>
                    $summary['reconciliation']['final_confirmed_day'],

                'final_source' =>
                    $summary['reconciliation']['final_source'],


                'offset_days' =>
                    $summary['reconciliation']['offset_days'],

                'luteal_phase_length' =>
                    $summary['reconciliation']['luteal_phase_length'],


                'has_discrepancy' =>
                    $discrepancy['active'],

                'discrepancy_note' =>
                    $discrepancy['message'],


                'is_reconciled' =>
                    ! $discrepancy['active'],

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
| Return Response
|--------------------------------------------------------------------------
*/

return response()->json([

    'success' => true,

    'message' => 'Cycle dashboard synced successfully.',

    'data' => [
        'summary' => $summary,
        'signal_status' => $signal,
        'discrepancy' => $discrepancy,
    ]

]);

    } // sync() method close

} // class close
