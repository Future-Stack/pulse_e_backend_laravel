<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\MenstrualCycle;
use App\Models\PeriodLog;
use App\Models\CycleStatistic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class CalendarController extends Controller
{
   public function confirmDay(Request $request)
{
    $request->validate([
        'date' => ['required', 'date'],
        'is_day_n' => ['required', 'boolean'],
    ]);

    $user = auth()->user();

    /*
    |--------------------------------------------------------------------------
    | AI Engine URL
    |--------------------------------------------------------------------------
    */
    $url = config('services.ai.base_url')
        . '/api/v1/cycle-engine/calendar/confirm-day';

    /*
    |--------------------------------------------------------------------------
    | Call AI API
    |--------------------------------------------------------------------------
    */
    $response = Http::timeout(120)->post($url, [
        'date' => $request->date,
        'is_day_n' => $request->boolean('is_day_n'),
    ]);

    if (! $response->successful()) {
        return response()->json([
            'success' => false,
            'message' => 'Unable to confirm calendar day.',
        ], 500);
    }

    $data = $response->json();

    DB::transaction(function () use ($user, $request, $data) {

        /*
        |--------------------------------------------------------------------------
        | Create or Update Active Cycle
        |--------------------------------------------------------------------------
        */
        $cycle = MenstrualCycle::updateOrCreate(
            [
                'user_id' => $user->id,
                'is_completed' => false,
            ],
            [
                'period_start_date' => $request->date,
                'current_cycle_day' => $data['current_cycle_day'],
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Save Period Log
        |--------------------------------------------------------------------------
        */
        PeriodLog::updateOrCreate(
            [
                'user_id'  => $user->id,
                'cycle_id' => $cycle->id,
                'log_date' => $request->date,
            ],
            [
                'flow'       => 'medium',
                'clotting'   => false,
                'pain_level' => null,
                'cramps'     => false,
                'headache'   => false,
                'fatigue'    => false,
                'notes'      => null,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Update Current Cycle Day
        |--------------------------------------------------------------------------
        */
        $cycle->update([
            'current_cycle_day' => $data['current_cycle_day'],
        ]);
    });

    return response()->json([
        'accepted'          => $data['accepted'],
        'current_cycle_day' => $data['current_cycle_day'],
        'recomputed'        => $data['recomputed'],
    ]);
}



public function syncMonth(Request $request)
{
    $request->validate([
        'month' => ['required', 'date_format:Y-m'],
    ]);

    $url = config('services.ai.base_url')
        . '/api/v1/cycle-engine/calendar/month';

    $response = Http::timeout(120)->get($url, [
        'month' => $request->month,
    ]);

    if (! $response->successful()) {
        return response()->json([
            'success' => false,
            'message' => 'Unable to fetch calendar month.',
        ], 500);
    }

    return response()->json($response->json());
}




 /**
     * Next Period Prediction
     */
    public function syncNextPeriod(Request $request)
    {

        $url = config('services.ai.base_url')
            . '/api/v1/cycle-engine/calendar/next-period';



        try {


            $response = Http::timeout(120)
                ->acceptJson()
                ->withToken($request->bearerToken())
                ->get($url);



            if (! $response->successful()) {


                return response()->json([

                    'success'=>false,

                    'message'=>'Unable to fetch next period prediction.',

                    'error'=>$response->body(),

                ],500);

            }



            $data = $response->json();



            return response()->json([

                'success'=>true,

                'predicted_date'=>$data['predicted_date'] ?? null,

                'days_until'=>$data['days_until'] ?? null,

                'rolling_avg_length'=>$data['rolling_avg_length'] ?? null,

                'variance_days'=>$data['variance_days'] ?? null,

                'within_normal_range'=>$data['within_normal_range'] ?? null,

                'last_4_cycle_lengths'=>$data['last_4_cycle_lengths'] ?? [],

                'ai_generated'=>$data['ai_generated'] ?? false,

                'ai_cached'=>$data['ai_cached'] ?? false,

            ]);



        } catch (\Exception $e) {


            return response()->json([

                'success'=>false,

                'message'=>'AI service unavailable.',

                'error'=>$e->getMessage(),

            ],500);


        }

    }
}
