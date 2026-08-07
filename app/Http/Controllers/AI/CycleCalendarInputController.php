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
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_day_n' => ['required', 'boolean'],
        ]);

        $endDate = !empty($validated['end_date']) && $validated['end_date'] !== '0000-00-00' ? $validated['end_date'] : null;

        $cycleCalendarInput = CycleCalendarInput::create([
            'user_id' => $request->user()->id,
            'start_date' => $validated['start_date'],
            'end_date' => $endDate,
            'is_day_n' => $validated['is_day_n'],
        ]);

        $user = $request->user();

        // 1. Update/Create active MenstrualCycle
        $cycle = \App\Models\MenstrualCycle::updateOrCreate(
            [
                'user_id' => $user->id,
                'is_completed' => false,
            ],
            [
                'period_start_date' => $validated['start_date'],
                'period_end_date' => $endDate,
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

        // 3. Trigger AI Engine cycle sync
        try {
            app(\App\Http\Controllers\AI\CycleSummaryController::class)->sync();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to auto-sync AI engine after calendar input: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Cycle calendar input saved successfully and cycle synced.',
            'data' => $cycleCalendarInput,
        ], 201);
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
            'success' => true,
            'data' => $cycleCalendarInputs,
        ]);
    }
}