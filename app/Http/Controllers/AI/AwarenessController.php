<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AwarenessSnapshot;
use App\Models\MenstrualCycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AwarenessController extends Controller
{
    /**
     * Sync Awareness Data
     */
    public function sync(Request $request)
    {
        $user = auth()->user();

        $cycle = MenstrualCycle::where('user_id', $user->id)
            ->where('is_completed', false)
            ->first();

        if (! $cycle) {
            return response()->json([
                'success' => false,
                'message' => 'No active menstrual cycle found.',
            ], 404);
        }

        $baseUrl = config('services.ai.base_url') . '/api/v1/cycle-engine/awareness';

        $currentPhase = Http::timeout(120)
            ->acceptJson()
            ->withToken($request->bearerToken())
            ->get($baseUrl . '/current-phase');

        $hormoneLevels = Http::timeout(120)
            ->acceptJson()
            ->withToken($request->bearerToken())
            ->get($baseUrl . '/hormone-levels');

        $phaseEducation = Http::timeout(120)
            ->acceptJson()
            ->withToken($request->bearerToken())
            ->get($baseUrl . '/phase-education');

        $fourPhaseWheel = Http::timeout(120)
            ->acceptJson()
            ->withToken($request->bearerToken())
            ->get($baseUrl . '/four-phase-wheel');

        if (
            ! $currentPhase->successful() ||
            ! $hormoneLevels->successful() ||
            ! $phaseEducation->successful() ||
            ! $fourPhaseWheel->successful()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch awareness data.',
            ], 500);
        }

        $phase = $currentPhase->json();
        $hormone = $hormoneLevels->json();
        $education = $phaseEducation->json();
        $wheel = $fourPhaseWheel->json();

        DB::transaction(function () use (
            $user,
            $cycle,
            $phase,
            $hormone,
            $education,
            $wheel
        ) {

            $cycle->update([
                'current_phase' => $phase['phase'] ?? null,
                'current_cycle_day' => $phase['current_cycle_day'] ?? null,
            ]);

            AwarenessSnapshot::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'cycle_id' => $cycle->id,
                ],
                [
                    // Current Phase
                    'phase' => $phase['phase'] ?? null,
                    'day_range' => $phase['day_range'] ?? null,
                    'current_cycle_day' => $phase['current_cycle_day'] ?? null,
                    'dominant_hormone_note' => $phase['dominant_hormone_note'] ?? null,
                    'energy' => $phase['energy'] ?? null,
                    'skin' => $phase['skin'] ?? null,
                    'mood' => $phase['mood'] ?? null,

                    // Hormone Levels
                    'estrogen' => $hormone['estrogen'] ?? null,
                    'progesterone' => $hormone['progesterone'] ?? null,
                    'lh' => $hormone['lh'] ?? null,
                    'modeled' => $hormone['modeled'] ?? false,
                    'source' => $hormone['source'] ?? null,
                    'note' => $hormone['note'] ?? null,

                    // Phase Education
                    'bbt_note' => $education['bbt_note'] ?? null,
                    'energy_note' => $education['energy_note'] ?? null,
                    'hormone_note' => $education['hormone_note'] ?? null,
                    'focus_note' => $education['focus_note'] ?? null,

                    // Four Phase Wheel
                    'current_phase' => $wheel['current_phase'] ?? null,
                    'phases' => $wheel['phases'] ?? [],

                    // AI Metadata
                    'ai_generated' => $phase['ai_generated'] ?? false,
                    'ai_cached' => $phase['ai_cached'] ?? false,
                ]
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Awareness data synced successfully.',
            'data' => AwarenessSnapshot::where('user_id', $user->id)
                ->where('cycle_id', $cycle->id)
                ->first(),
        ]);
    }
}