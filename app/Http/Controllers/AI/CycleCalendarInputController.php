<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\CycleCalendarInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CycleCalendarInputController extends Controller
{
    /**
     * Store cycle calendar input.
     */
    // public function store(Request $request): JsonResponse
    // {
    //     $validated = $request->validate([
    //         'start_date' => ['required', 'date'],
    //         'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
    //         'is_day_n' => ['required', 'boolean'],
    //     ]);

    //     $endDate = !empty($validated['end_date']) && $validated['end_date'] !== '0000-00-00' ? $validated['end_date'] : null;

    //     $cycleCalendarInput = CycleCalendarInput::create([
    //         'user_id' => $request->user()->id,
    //         'start_date' => $validated['start_date'],
    //         'end_date' => $endDate,
    //         'is_day_n' => $validated['is_day_n'],
    //     ]);

    //     $user = $request->user();

    //     // 1. Update/Create active MenstrualCycle
    //     $cycle = \App\Models\MenstrualCycle::updateOrCreate(
    //         [
    //             'user_id' => $user->id,
    //             'is_completed' => false,
    //         ],
    //         [
    //             'period_start_date' => $validated['start_date'],
    //             'period_end_date' => $endDate,
    //             'prediction_source' => 'calendar',
    //         ]
    //     );

    //     // 2. Populate PeriodLogs
    //     $periodStart = \Carbon\Carbon::parse($validated['start_date']);
    //     $periodEnd = $endDate ? \Carbon\Carbon::parse($endDate) : $periodStart->copy();
        
    //     for ($date = $periodStart->copy(); $date->lte($periodEnd); $date->addDay()) {
    //         \App\Models\PeriodLog::updateOrCreate(
    //             [
    //                 'user_id' => $user->id,
    //                 'cycle_id' => $cycle->id,
    //                 'log_date' => $date->toDateString(),
    //             ],
    //             [
    //                 'flow' => 'medium',
    //             ]
    //         );
    //     }

    //     // 3. Trigger AI Engine cycle sync
    //     try {
    //         app(\App\Http\Controllers\AI\CycleSummaryController::class)->sync();
    //     } catch (\Throwable $e) {
    //         \Illuminate\Support\Facades\Log::warning("Failed to auto-sync AI engine after calendar input: " . $e->getMessage());
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Cycle calendar input saved successfully and cycle synced.',
    //         'data' => $cycleCalendarInput,
    //     ], 201);
    // }




    //new

    /**
 * Store cycle calendar input.
 */
/**
 * Store cycle calendar input.
 */
public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'start_date' => ['required', 'date'],
        'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        'is_day_n' => ['required', 'boolean'],
    ]);

    $user = $request->user();

    $cycleCalendarInput = CycleCalendarInput::create([
        'user_id' => $user->id,
        'start_date' => $validated['start_date'],
        'end_date' => $validated['end_date'],
        'is_day_n' => $validated['is_day_n'],
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Cycle calendar input saved successfully.',
        'data' => [
            'id' => $cycleCalendarInput->id,
            'user_id' => $cycleCalendarInput->user_id,
            'start_date' => $cycleCalendarInput->start_date?->format('Y-m-d'),
            'end_date' => $cycleCalendarInput->end_date?->format('Y-m-d'),
            'is_day_n' => $cycleCalendarInput->is_day_n,
            'created_at' => $cycleCalendarInput->created_at,
            'updated_at' => $cycleCalendarInput->updated_at,
        ],
    ], 201);
}

//current

/**
 * Get current cycle calendar data.
 *
 * Optional:
 * ?date=2026-08-21
 */
public function current(Request $request): JsonResponse
{
     $user = $request->user();

    $calendarInput = CycleCalendarInput::where('user_id', $user->id)
        ->latest('start_date')
        ->first();

    if (!$calendarInput) {
        return response()->json([
            'success' => false,
            'message' => 'No cycle calendar input found.',
            'data' => null,
        ], 404);
    }

    $request->validate([
        'date' => ['nullable', 'date'],
    ]);

    $startDate = \Carbon\Carbon::parse($calendarInput->start_date);

    $periodEndDate = $calendarInput->end_date
        ? \Carbon\Carbon::parse($calendarInput->end_date)
        : $startDate->copy();

    // If date is provided, use it. Otherwise use today.
    $selectedDate = $request->filled('date')
        ? \Carbon\Carbon::parse($request->date)
        : \Carbon\Carbon::today();

    /*
    |--------------------------------------------------------------------------
    | Cycle Day
    |--------------------------------------------------------------------------
    */

    if ($selectedDate->lt($startDate)) {
        $cycleDay = 1;
    } else {
        $cycleDay = $startDate->diffInDays($selectedDate) + 1;

        // 28-day cycle
        $cycleDay = min($cycleDay, 28);
    }

    /*
    |--------------------------------------------------------------------------
    | Phase
    |--------------------------------------------------------------------------
    */

    $phase = match (true) {

        $cycleDay <= 5 => [
            'name' => 'Menstrual Phase',
            'color' => 'red',
            'icon' => '🔴',
        ],

        $cycleDay <= 13 => [
            'name' => 'Follicular Phase',
            'color' => 'green',
            'icon' => '🟢',
        ],

        $cycleDay <= 16 => [
            'name' => 'Ovulatory Phase',
            'color' => 'yellow',
            'icon' => '🟡',
        ],

         default => [
            'name' => 'Luteal Phase',
            'color' => 'blue',
            'icon' => '🔵',
        ],
    };

    /*
    |--------------------------------------------------------------------------
    | Calendar Status
    |--------------------------------------------------------------------------
    */

    $isPeriod = $selectedDate->betweenIncluded(
        $startDate,
        $periodEndDate
    );

    $calendarStatus = [
        'is_period' => $isPeriod,
        'is_fertile_window' => $cycleDay >= 10 && $cycleDay <= 16,
        'is_luteal' => $cycleDay >= 17 && $cycleDay <= 28,
        'is_ovulation' => $cycleDay >= 14 && $cycleDay <= 16,
        'is_confirmed_ovulation' => false,
    ];

    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    return response()->json([
        'success' => true,
        'data' => [
            'start_date' => $startDate->format('Y-m-d'),
            'period_end_date' => $periodEndDate->format('Y-m-d'),
            'selected_date' => $selectedDate->format('Y-m-d'),

            'cycle_day' => $cycleDay,

            'phase' => $phase,

            'calendar_status' => $calendarStatus,
        ],
    ]);
}

    /**
     * Show cycle calendar inputs for a user.
     */
    public function show($user_id): JsonResponse
    {
        $cycleCalendarInputs = CycleCalendarInput::where('user_id', $user_id)
            ->orderByDesc('start_date')
            ->get()
            ->map(function ($input) {
                $formatDate = function ($dateVal) {
                    if (! $dateVal || $dateVal === '0000-00-00' || $dateVal === '0000-00-00 00:00:00') {
                        return null;
                    }
                    if ($dateVal instanceof \DateTimeInterface) {
                        return $dateVal->format('Y-m-d');
                    }
                    return \Carbon\Carbon::parse($dateVal)->format('Y-m-d');
                };

                return [
                    'id' => $input->id,
                    'user_id' => $input->user_id,
                    'start_date' => $formatDate($input->start_date),
                    'end_date' => $formatDate($input->end_date),
                    'is_day_n' => (bool) $input->is_day_n,
                    'created_at' => $input->created_at,
                    'updated_at' => $input->updated_at,
                ];
            });

        return response()->json([
            'success' =>true,
            'data' => $cycleCalendarInputs,
        ]);
    }
}