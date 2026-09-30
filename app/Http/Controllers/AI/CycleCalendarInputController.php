<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\CycleCalendarInput;
use App\Services\CycleCalculatorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CycleCalendarInputController extends Controller
{
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
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_day_n' => ['required', 'boolean'],
            'cycle_length' => ['nullable', 'integer', 'min:20', 'max:50'],
            'average_cycle_length' => ['nullable', 'integer', 'min:20', 'max:50'],
            'period_length' => ['nullable', 'integer', 'min:2', 'max:12'],
            'average_period_length' => ['nullable', 'integer', 'min:2', 'max:12'],
            'luteal_phase_length' => ['nullable', 'integer', 'min:8', 'max:18'],
        ]);

        $user = $request->user();
        $endDate = !empty($validated['end_date']) && $validated['end_date'] !== '0000-00-00' ? $validated['end_date'] : null;

        $cycleCalendarInput = CycleCalendarInput::create([
            'user_id' => $user->id,
            'start_date' => $validated['start_date'],
            'end_date' => $endDate,
            'is_day_n' => $validated['is_day_n'],
        ]);

        // Auto-detect or save cycle settings if provided
        $explicitCycleLength = $validated['cycle_length'] ?? $validated['average_cycle_length'] ?? null;
        $explicitPeriodLength = $validated['period_length'] ?? $validated['average_period_length'] ?? null;
        $explicitLutealLength = $validated['luteal_phase_length'] ?? null;

        if ($explicitCycleLength || $explicitPeriodLength || $explicitLutealLength) {
            $setting = \App\Models\CycleSetting::firstOrNew(['user_id' => $user->id]);
            if ($explicitCycleLength) {
                $setting->average_cycle_length = (int) $explicitCycleLength;
            }
            if ($explicitPeriodLength) {
                $setting->average_period_length = (int) $explicitPeriodLength;
            }
            if ($explicitLutealLength) {
                $setting->luteal_phase_length = (int) $explicitLutealLength;
            }
            $setting->save();
        }

        $userSettings = \App\Services\CycleCalculatorService::getUserCycleSettings($user);
        $resolvedCycleLength = $userSettings['cycle_length'];

        // 1. Update/Create active MenstrualCycle
        $cycle = \App\Models\MenstrualCycle::updateOrCreate(
            [
                'user_id' => $user->id,
                'is_completed' => false,
            ],
            [
                'period_start_date' => $validated['start_date'],
                'period_end_date' => $endDate,
                'cycle_length' => $resolvedCycleLength,
                'prediction_source' => 'calendar',
            ]
        );

        // 2. Populate PeriodLogs
        $periodStart = \Carbon\Carbon::parse($validated['start_date']);
        $periodEnd = $endDate ? \Carbon\Carbon::parse($endDate) : $periodStart->copy();

        for ($date = $periodStart->copy(); $date->lte($periodEnd); $date->addDay()) {
            \App\Models\PeriodLog::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'cycle_id' => $cycle->id,
                    'log_date' => $date->toDateString(),
                ],
                [
                    'flow' => 'medium',
                ]
            );
        }

        // 3. Trigger AI Engine cycle sync if available
        try {
            if (class_exists(\App\Http\Controllers\AI\CycleSummaryController::class)) {
                app(\App\Http\Controllers\AI\CycleSummaryController::class)->sync();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to auto-sync AI engine after calendar input: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Cycle calendar input saved successfully.',
            'data' => [
                'id' => $cycleCalendarInput->id,
                'user_id' => $cycleCalendarInput->user_id,
                'start_date' => $cycleCalendarInput->start_date?->format('Y-m-d'),
                'end_date' => $cycleCalendarInput->end_date?->format('Y-m-d'),
                'is_day_n' => $cycleCalendarInput->is_day_n,
                'cycle_length' => $resolvedCycleLength,
                'created_at' => $cycleCalendarInput->created_at,
                'updated_at' => $cycleCalendarInput->updated_at,
            ],
        ], 201);
    }




/**
 * Get current cycle calendar data.
 *
 * Optional:
 * ?date=2026-08-21
 */
public function current(Request $request): JsonResponse
{
    $user = $request->user();

    /*
    |--------------------------------------------------------------------------
    | Validate Request
    |--------------------------------------------------------------------------
    */
    $request->validate([
        'date' => ['nullable', 'date'],
    ]);

    /*
    |--------------------------------------------------------------------------
    | Get Latest Cycle Calendar Input
    |--------------------------------------------------------------------------
    */
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

    /*
    |--------------------------------------------------------------------------
    | Dates
    |--------------------------------------------------------------------------
    */
    $startDate = Carbon::parse($calendarInput->start_date);

    $periodEndDate = $calendarInput->end_date
        ? Carbon::parse($calendarInput->end_date)
        : $startDate->copy();

    $selectedDate = $request->filled('date')
        ? Carbon::parse($request->date)
        : Carbon::today();

    /*
    |--------------------------------------------------------------------------
    | Get Dynamic User Cycle Settings
    |--------------------------------------------------------------------------
    */
    $userSettings = CycleCalculatorService::getUserCycleSettings($user);

    $cycleLength = (int) ($userSettings['cycle_length'] ?? 28);

    $lutealLength = (int) (
        $userSettings['luteal_phase_length'] ?? 14
    );

    $periodLength = (int) (
        $userSettings['period_length'] ?? 5
    );

    /*
    |--------------------------------------------------------------------------
    | Actual Logged Period Length
    |--------------------------------------------------------------------------
    |
    | Example:
    | Oct 11 -> Oct 14 = 4 days
    |
    | This is period length, NOT cycle length.
    |--------------------------------------------------------------------------
    */
    if ($calendarInput->end_date) {
        $loggedPeriodDays = $startDate->diffInDays($periodEndDate) + 1;

        if ($loggedPeriodDays >= 1 && $loggedPeriodDays <= 12) {
            $periodLength = $loggedPeriodDays;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate Current Cycle Day
    |--------------------------------------------------------------------------
    */
    if ($selectedDate->lt($startDate)) {
        $cycleDay = 1;
    } else {
        $cycleDay = $startDate->diffInDays($selectedDate) + 1;
    }

    /*
    |--------------------------------------------------------------------------
    | Keep Cycle Day Within Cycle Length
    |--------------------------------------------------------------------------
    */
    $cycleDay = (($cycleDay - 1) % $cycleLength) + 1;

    /*
    |--------------------------------------------------------------------------
    | Check Actual Logged Period
    |--------------------------------------------------------------------------
    */
    $isPeriod = $selectedDate->betweenIncluded(
        $startDate,
        $periodEndDate
    );

    /*
    |--------------------------------------------------------------------------
    | Get Phase Information
    |--------------------------------------------------------------------------
    */
    $dayInfo = CycleCalculatorService::getPhaseForCycleDay(
        $cycleDay,
        $cycleLength,
        $lutealLength,
        $periodLength,
        $isPeriod
    );

    /*
    |--------------------------------------------------------------------------
    | Historical Cycle Information
    |--------------------------------------------------------------------------
    */
    $cycleHistory = $userSettings['cycle_history'] ?? [];

    /*
    |--------------------------------------------------------------------------
    | Calculate Average & Variation
    |--------------------------------------------------------------------------
    */
    $historyLengths = collect($cycleHistory)
        ->pluck('cycle_length')
        ->filter(fn ($length) => is_numeric($length))
        ->map(fn ($length) => (int) $length)
        ->values();

    $avgCycleLength = $historyLengths->isNotEmpty()
        ? (int) round($historyLengths->avg())
        : $cycleLength;

    $cycleVariation = $historyLengths->isNotEmpty()
        ? $historyLengths->max() - $historyLengths->min()
        : 0;

    /*
    |--------------------------------------------------------------------------
    | Next Period Prediction
    |--------------------------------------------------------------------------
    |
    | Latest period start + resolved cycle length
    |--------------------------------------------------------------------------
    */
    $nextPeriodDate = $startDate
        ->copy()
        ->addDays($cycleLength);

    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */
    return response()->json([
        'success' => true,

        'data' => [

            /*
            |--------------------------------------------------------------------------
            | Dates
            |--------------------------------------------------------------------------
            */
            'start_date' => $startDate->format('Y-m-d'),

            'period_end_date' => $periodEndDate->format('Y-m-d'),

            'selected_date' => $selectedDate->format('Y-m-d'),

            /*
            |--------------------------------------------------------------------------
            | Current Cycle
            |--------------------------------------------------------------------------
            */
            'cycle_day' => $cycleDay,

            'cycle_length' => $cycleLength,

            'avg_cycle_length' => $avgCycleLength,

            'cycle_variation' => $cycleVariation,

            /*
            |--------------------------------------------------------------------------
            | Current Period
            |--------------------------------------------------------------------------
            */
            'period_length' => $periodLength,

            /*
            |--------------------------------------------------------------------------
            | Next Period Prediction
            |--------------------------------------------------------------------------
            */
            'next_period_date' => $nextPeriodDate->format('Y-m-d'),

            /*
            |--------------------------------------------------------------------------
            | Current Phase
            |--------------------------------------------------------------------------
            */
            'phase' => $dayInfo['phase'],

            /*
            |--------------------------------------------------------------------------
            | Calendar Status
            |--------------------------------------------------------------------------
            */
            'calendar_status' => $dayInfo['calendar_status'],

            /*
            |--------------------------------------------------------------------------
            | Phase Ranges
            |--------------------------------------------------------------------------
            */
            'phases' => $dayInfo['phases'],

            /*
            |--------------------------------------------------------------------------
            | Historical Cycle Lengths
            |--------------------------------------------------------------------------
            */
            'cycle_history' => $cycleHistory,
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