<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\CycleStatistic;
use App\Models\MenstrualCycle;
use App\Models\SignalHistory;
use App\Models\OvulationReconciliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;


//summary 1st page
class CycleSummaryController extends Controller
{
  public function sync()
{
    $user = auth()->user();

    // AI Engine Base URL
    $url = config('services.ai.base_url')
        . '/api/v1/cycle-engine/engine/summary';

    $response = Http::timeout(120)->get($url);

    if (! $response->successful()) {
        return response()->json([
            'success' => false,
            'message' => 'Unable to fetch AI cycle summary.',
        ], 500);
    }

    $data = $response->json();

    DB::transaction(function () use ($user, $data) {

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
                'average_cycle_length' => $data['cycle_summary']['avg_cycle_length'],
                'cycle_variance_days'  => $data['cycle_summary']['cycle_variance_days'],
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Current Menstrual Cycle
        |--------------------------------------------------------------------------
        */
        $cycle = MenstrualCycle::updateOrCreate(
            [
                'user_id' => $user->id,
                'is_completed' => false,
            ],
            [
                'period_start_date' => now()->toDateString(),

                'current_cycle_day' => $data['cycle_summary']['current_cycle_day'],
                'current_phase'     => $data['cycle_summary']['current_phase'],

                'fertile_start_day' => $data['fertile_window']['start_day'],
                'fertile_end_day'   => $data['fertile_window']['end_day'],

                'predicted_peak_day' => $data['fertile_window']['peak_day'],
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Ovulation Reconciliation
        |--------------------------------------------------------------------------
        */
        OvulationReconciliation::updateOrCreate(
            [
                'cycle_id' => $cycle->id,
            ],
            [
                'user_id' => $user->id,

                'calendar_predicted_day' => $data['reconciliation']['calendar_predicted_day'],
                'bbt_confirmed_day'      => $data['reconciliation']['bbt_confirmed_day'],
                'lh_surge_day'           => $data['reconciliation']['lh_surge_day'],
                'mucus_peak_day'         => $data['fertile_window']['mucus_peak_day'],

                'final_confirmed_day' => $data['reconciliation']['final_confirmed_day'],
                'final_source'        => $data['reconciliation']['final_source'],

                'offset_days'         => $data['reconciliation']['offset_days'],
                'luteal_phase_length' => $data['reconciliation']['luteal_phase_length'],

                'is_reconciled' => true,
                'reconciled_at' => now(),
            ]
        );
    });

    return response()->json([
        'success' => true,
        'message' => 'Cycle summary synced successfully.',
        'data' => $data,
    ]);
}



    //signal part 1st page
   public function syncSignalStatus()
{
    $user = auth()->user();

    /*
    |--------------------------------------------------------------------------
    | AI Engine URL
    |--------------------------------------------------------------------------
    */
    $url = config('services.ai.base_url')
        . '/api/v1/cycle-engine/engine/signal-status';

    $response = Http::timeout(120)->get($url);

    if (! $response->successful()) {
        return response()->json([
            'success' => false,
            'message' => 'Unable to fetch signal status.',
        ], 500);
    }

    $data = $response->json();

    DB::transaction(function () use ($user, $data) {

        /*
        |--------------------------------------------------------------------------
        | Current Active Cycle
        |--------------------------------------------------------------------------
        */
        $cycle = MenstrualCycle::where('user_id', $user->id)
            ->where('is_completed', false)
            ->latest()
            ->first();

        if (! $cycle) {
            throw new \Exception('No active menstrual cycle found.');
        }

        /*
        |--------------------------------------------------------------------------
        | Default Signal Values
        |--------------------------------------------------------------------------
        */
        $calendar = false;
        $bbt = false;
        $opk = false;
        $mucus = false;

        /*
        |--------------------------------------------------------------------------
        | Read AI Signals
        |--------------------------------------------------------------------------
        */
        foreach ($data['signals'] as $signal) {

            switch ($signal['signal']) {

                case 'Calendar':
                    $calendar = $signal['logged_today'];
                    break;

                case 'BBT':
                    $bbt = $signal['logged_today'];
                    break;

                case 'OPK / LH':
                    $opk = $signal['logged_today'];
                    break;

                case 'Mucus':
                    $mucus = $signal['logged_today'];
                    break;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Signal Strength
        |--------------------------------------------------------------------------
        */
        $count = collect([
            $calendar,
            $bbt,
            $opk,
            $mucus,
        ])->filter()->count();

        $strength = match (true) {
            $count == 0 => 'none',
            $count == 1 => 'low',
            $count <= 3 => 'medium',
            default => 'high',
        };

        /*
        |--------------------------------------------------------------------------
        | Save Daily Signal History
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
                'bbt_logged'      => $bbt,
                'opk_logged'      => $opk,
                'mucus_logged'    => $mucus,
                'symptoms_logged' => false,

                'signal_strength' => $strength,

                // Save original AI response
                'signals' => $data['signals'],

                'ai_generated' => $data['ai_generated'],
                'ai_cached'    => $data['ai_cached'],
            ]
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Return Original AI Response
    |--------------------------------------------------------------------------
    */
    return response()->json([
        'signals' => $data['signals'],
        'ai_generated' => $data['ai_generated'],
        'ai_cached' => $data['ai_cached'],
    ]);
}





public function syncDiscrepancyNote()
{
    $user = auth()->user();

    /*
    |--------------------------------------------------------------------------
    | AI Engine URL
    |--------------------------------------------------------------------------
    */
    $url = config('services.ai.base_url')
        . '/api/v1/cycle-engine/engine/discrepancy-note';

    $response = Http::timeout(120)->get($url);

    if (! $response->successful()) {
        return response()->json([
            'success' => false,
            'message' => 'Unable to fetch discrepancy note.',
        ], 500);
    }

    $data = $response->json();

    DB::transaction(function () use ($user, $data) {

        /*
        |--------------------------------------------------------------------------
        | Current Active Cycle
        |--------------------------------------------------------------------------
        */
        $cycle = MenstrualCycle::where('user_id', $user->id)
            ->where('is_completed', false)
            ->latest()
            ->first();

        if (! $cycle) {
            throw new \Exception('No active menstrual cycle found.');
        }

        /*
        |--------------------------------------------------------------------------
        | Existing Reconciliation
        |--------------------------------------------------------------------------
        */
        $reconciliation = OvulationReconciliation::where('cycle_id', $cycle->id)
            ->first();

        if (! $reconciliation) {
            throw new \Exception('Please sync cycle summary first.');
        }

        /*
        |--------------------------------------------------------------------------
        | Update Discrepancy Information
        |--------------------------------------------------------------------------
        */
        $reconciliation->update([
            'has_discrepancy' => $data['active'],
            'discrepancy_note' => $data['message'],
            'is_reconciled' => ! $data['active'],
            'reconciled_at' => now(),
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Return Original AI Response
    |--------------------------------------------------------------------------
    */
    return response()->json([
        'active' => $data['active'],
        'message' => $data['message'],
        'ai_generated' => $data['ai_generated'],
        'ai_cached' => $data['ai_cached'],
    ]);
}
}