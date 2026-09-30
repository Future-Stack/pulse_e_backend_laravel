<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\CycleSetting;
use App\Models\MenstrualCycle;
use App\Models\CycleStatistic;
use App\Services\CycleCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CycleSettingsController extends Controller
{
    /**
     * Get cycle settings for the authenticated user.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user() ?? ($request->filled('user_id') ? \App\Models\User::find($request->user_id) : null);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $settings = CycleCalculatorService::getUserCycleSettings($user);

        $dbSetting = CycleSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'average_cycle_length'  => $settings['cycle_length'],
                'average_period_length' => $settings['period_length'],
                'luteal_phase_length'   => $settings['luteal_phase_length'],
            ]
        );

        $phases = CycleCalculatorService::calculatePhases(
            $dbSetting->average_cycle_length,
            $dbSetting->luteal_phase_length,
            $dbSetting->average_period_length
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                    => $dbSetting->id,
                'user_id'               => $dbSetting->user_id,
                'average_cycle_length'  => (int) $dbSetting->average_cycle_length,
                'average_period_length' => (int) $dbSetting->average_period_length,
                'luteal_phase_length'   => (int) $dbSetting->luteal_phase_length,
                'prediction_method'     => $dbSetting->prediction_method ?? 'combined',
                'temperature_unit'      => $dbSetting->temperature_unit ?? 'F',
                'calculated_phases'     => $phases,
            ],
        ]);
    }

    /**
     * Update cycle settings for the authenticated user.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user() ?? ($request->filled('user_id') ? \App\Models\User::find($request->user_id) : null);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'average_cycle_length'  => ['nullable', 'integer', 'min:20', 'max:50'],
            'cycle_length'          => ['nullable', 'integer', 'min:20', 'max:50'],
            'average_period_length' => ['nullable', 'integer', 'min:2', 'max:12'],
            'period_length'         => ['nullable', 'integer', 'min:2', 'max:12'],
            'luteal_phase_length'   => ['nullable', 'integer', 'min:8', 'max:18'],
            'prediction_method'     => ['nullable', 'string', 'in:calendar,bbt,opk,combined'],
            'temperature_unit'      => ['nullable', 'string', 'in:C,F'],
        ]);

        $cycleLength = $validated['average_cycle_length']
            ?? $validated['cycle_length']
            ?? null;

        $periodLength = $validated['average_period_length']
            ?? $validated['period_length']
            ?? null;

        $dbSetting = CycleSetting::firstOrNew(['user_id' => $user->id]);

        if ($cycleLength !== null) {
            $dbSetting->average_cycle_length = (int) $cycleLength;
        }
        if ($periodLength !== null) {
            $dbSetting->average_period_length = (int) $periodLength;
        }
        if (isset($validated['luteal_phase_length'])) {
            $dbSetting->luteal_phase_length = (int) $validated['luteal_phase_length'];
        }
        if (isset($validated['prediction_method'])) {
            $dbSetting->prediction_method = $validated['prediction_method'];
        }
        if (isset($validated['temperature_unit'])) {
            $dbSetting->temperature_unit = $validated['temperature_unit'];
        }

        $dbSetting->save();

        // Update active cycle & statistics if cycle length was modified
        if ($cycleLength !== null) {
            MenstrualCycle::where('user_id', $user->id)
                ->where('is_completed', false)
                ->update(['cycle_length' => (int) $cycleLength]);

            CycleStatistic::updateOrCreate(
                ['user_id' => $user->id],
                ['average_cycle_length' => (int) $cycleLength]
            );
        }

        $phases = CycleCalculatorService::calculatePhases(
            $dbSetting->average_cycle_length,
            $dbSetting->luteal_phase_length,
            $dbSetting->average_period_length
        );

        // Auto trigger cycle sync if available
        try {
            if (class_exists(CycleSummaryController::class)) {
                app(CycleSummaryController::class)->sync();
            }
        } catch (\Throwable $e) {
            // Ignore background sync errors
        }

        return response()->json([
            'success' => true,
            'message' => 'Cycle settings updated successfully.',
            'data'    => [
                'id'                    => $dbSetting->id,
                'user_id'               => $dbSetting->user_id,
                'average_cycle_length'  => (int) $dbSetting->average_cycle_length,
                'average_period_length' => (int) $dbSetting->average_period_length,
                'luteal_phase_length'   => (int) $dbSetting->luteal_phase_length,
                'prediction_method'     => $dbSetting->prediction_method,
                'temperature_unit'      => $dbSetting->temperature_unit,
                'calculated_phases'     => $phases,
            ],
        ]);
    }
}
