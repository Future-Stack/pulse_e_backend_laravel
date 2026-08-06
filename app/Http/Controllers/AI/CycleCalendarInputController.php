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

        return response()->json([
            'success' => true,
            'message' => 'Cycle calendar input saved successfully.',
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
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cycleCalendarInputs,
        ]);
    }
}