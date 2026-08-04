<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\MenstrualCycle;
use App\Models\PeriodLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class CalendarController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Confirm Calendar Day
    |--------------------------------------------------------------------------
    |
    | User confirms whether a specific date is Day 1 / period day.
    |
    */
    public function confirmDay(Request $request)
    {
        $request->validate([
            'date' => ['required', 'date'],
            'is_day_n' => ['required', 'boolean'],
        ]);

        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | AI Engine URL
        |--------------------------------------------------------------------------
        */
        $url = config('services.ai.base_url')
            . '/api/v1/cycle-engine/calendar/confirm-day';

        /*
        |--------------------------------------------------------------------------
        | Call AI Engine
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | user_id is required as QUERY PARAMETER.
        |
        */
        try {

            $response = Http::timeout(120)
                ->acceptJson()
                ->withQueryParameters([
                    'user_id' => $user->id,
                ])
                ->post($url, [
                    'date' => $request->date,
                    'is_day_n' => $request->boolean('is_day_n'),
                ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Unable to connect to AI Engine.',
                'error' => $e->getMessage(),
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | Validate AI Response
        |--------------------------------------------------------------------------
        */
        if (! $response->successful()) {

            return response()->json([
                'success' => false,
                'message' => 'Unable to confirm calendar day.',
                'error' => $response->body(),
            ], 500);
        }

        $data = $response->json();

        /*
        |--------------------------------------------------------------------------
        | Save Local Database
        |--------------------------------------------------------------------------
        */
        try {

            DB::transaction(function () use ($user, $request, $data) {

                $isPeriodDay = $request->boolean('is_day_n');

                /*
                |--------------------------------------------------------------------------
                | Only create/update cycle when this is a confirmed period day
                |--------------------------------------------------------------------------
                */
                if ($isPeriodDay) {

                    /*
                    |--------------------------------------------------------------------------
                    | Find Current Active Cycle
                    |--------------------------------------------------------------------------
                    */
                    $cycle = MenstrualCycle::where('user_id', $user->id)
                        ->where('is_completed', false)
                        ->latest('id')
                        ->first();

                    /*
                    |--------------------------------------------------------------------------
                    | New Period Start
                    |--------------------------------------------------------------------------
                    |
                    | If there is an existing active cycle and the new period
                    | date is later than its start date, the old cycle is completed.
                    |
                    */
                    if ($cycle) {

                        $newStartDate = Carbon::parse($request->date);
                        $oldStartDate = Carbon::parse($cycle->period_start_date);

                        if ($newStartDate->gt($oldStartDate)) {

                            $cycleLength = $oldStartDate->diffInDays(
                                $newStartDate
                            );

                            $cycle->update([
                                'cycle_length' => $cycleLength,
                                'is_completed' => true,
                                'period_end_date' => $newStartDate->copy()->subDay()->toDateString(),
                            ]);

                            $cycle = null;
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Create New Cycle
                    |--------------------------------------------------------------------------
                    */
                    if (! $cycle) {

                        $cycle = MenstrualCycle::create([
                            'user_id' => $user->id,

                            'period_start_date' => $request->date,

                            'current_cycle_day' =>
                                $data['current_cycle_day'] ?? 1,

                            'is_confirmed' => true,

                            'is_completed' => false,
                        ]);

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Same Active Cycle
                        |--------------------------------------------------------------------------
                        */
                        $cycle->update([
                            'current_cycle_day' =>
                                $data['current_cycle_day'] ?? 1,

                            'is_confirmed' => true,
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Save Period Log
                    |--------------------------------------------------------------------------
                    */
                    PeriodLog::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'cycle_id' => $cycle->id,
                            'log_date' => $request->date,
                        ],
                        [
                            'flow' => 'medium',

                            'clotting' => false,

                            'pain_level' => null,

                            'cramps' => false,

                            'headache' => false,

                            'fatigue' => false,

                            'notes' => null,
                        ]
                    );
                }
            });

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Calendar data could not be saved.',
                'error' => $e->getMessage(),
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'success' => true,

            'accepted' =>
                $data['accepted'] ?? false,

            'current_cycle_day' =>
                $data['current_cycle_day'] ?? null,

            'recomputed' =>
                $data['recomputed'] ?? false,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Calendar Month
    |--------------------------------------------------------------------------
    |
    | GET /calendar/month?month=2026-08
    |
    */
    public function syncMonth(Request $request)
    {
        $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $url = config('services.ai.base_url')
            . '/api/v1/cycle-engine/calendar/month';

        /*
        |--------------------------------------------------------------------------
        | Call AI Engine
        |--------------------------------------------------------------------------
        */
        try {

            $response = Http::timeout(120)
                ->acceptJson()
                ->withQueryParameters([
                    'user_id' => $user->id,
                    'month' => $request->month,
                ])
                ->get($url);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Unable to connect to AI Engine.',
                'error' => $e->getMessage(),
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Response
        |--------------------------------------------------------------------------
        */
        if (! $response->successful()) {

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch calendar month.',
                'error' => $response->body(),
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | Return AI Calendar
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'success' => true,
            'data' => $response->json(),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Next Period Prediction
    |--------------------------------------------------------------------------
    |
    | GET /calendar/next-period
    |
    */
    public function syncNextPeriod()
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $url = config('services.ai.base_url')
            . '/api/v1/cycle-engine/calendar/next-period';

        try {

            /*
            |--------------------------------------------------------------------------
            | Get Last 4 Completed Cycle Lengths
            |--------------------------------------------------------------------------
            */
            $last4Cycles = MenstrualCycle::where(
                    'user_id',
                    $user->id
                )
                ->where('is_completed', true)
                ->whereNotNull('cycle_length')
                ->orderByDesc('period_start_date')
                ->limit(4)
                ->pluck('cycle_length')
                ->values()
                ->toArray();

            /*
            |--------------------------------------------------------------------------
            | Call AI Engine
            |--------------------------------------------------------------------------
            |
            | user_id + cycle lengths are query parameters.
            |
            */
            $response = Http::timeout(120)
                ->acceptJson()
                ->withQueryParameters([
                    'user_id' => $user->id,

                    'last_4_cycle_lengths' =>
                        implode(',', $last4Cycles),
                ])
                ->get($url);

            /*
            |--------------------------------------------------------------------------
            | Validate AI Response
            |--------------------------------------------------------------------------
            */
            if (! $response->successful()) {

                return response()->json([
                    'success' => false,

                    'message' =>
                        'Unable to fetch next period prediction.',

                    'error' =>
                        $response->body(),

                ], 500);
            }

            $data = $response->json();

            /*
            |--------------------------------------------------------------------------
            | Return Prediction
            |--------------------------------------------------------------------------
            */
            return response()->json([

                'success' => true,

                'predicted_date' =>
                    $data['predicted_date'] ?? null,

                'days_until' =>
                    $data['days_until'] ?? null,

                'rolling_avg_length' =>
                    $data['rolling_avg_length'] ?? null,

                'variance_days' =>
                    $data['variance_days'] ?? null,

                'within_normal_range' =>
                    $data['within_normal_range'] ?? null,

                'last_4_cycle_lengths' =>
                    $data['last_4_cycle_lengths']
                    ?? $last4Cycles,

                'ai_generated' =>
                    $data['ai_generated'] ?? false,

                'ai_cached' =>
                    $data['ai_cached'] ?? false,

                'sources' =>
                    $data['sources'] ?? null,
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,

                'message' =>
                    'AI service unavailable.',

                'error' =>
                    $e->getMessage(),

            ], 500);
        }
    }
}

