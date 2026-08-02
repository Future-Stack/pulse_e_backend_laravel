<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AwarenessSnapshot;
use App\Models\MenstrualCycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AwarenessController extends Controller
{
    /**
     * Sync Cycle Awareness Data
     *
     * Backend:
     * GET /api/v1/cycle-awareness
     *
     * AI:
     * GET /api/cycle-awareness?user_id={user_id}
     */
    public function sync(Request $request)
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Find Active Cycle
        |--------------------------------------------------------------------------
        */

        $cycle = MenstrualCycle::where('user_id', $user->id)
            ->where('is_completed', false)
            ->latest('id')
            ->first();

        if (! $cycle) {
            return response()->json([
                'success' => false,
                'message' => 'No active menstrual cycle found.',
            ], 404);
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | AI URL
            |--------------------------------------------------------------------------
            */

            $aiUrl = rtrim(
                config('services.ai.base_url'),
                '/'
            ) . '/api/cycle-awareness';

            /*
            |--------------------------------------------------------------------------
            | Call AI API
            |--------------------------------------------------------------------------
            |
            | AI expects:
            |
            | GET /api/cycle-awareness?user_id=2
            |
            */

            $response = Http::timeout(120)
                ->acceptJson()
                ->get($aiUrl, [
                    'user_id' => $user->id,
                ]);

            /*
            |--------------------------------------------------------------------------
            | Check AI Response
            |--------------------------------------------------------------------------
            */

            if (! $response->successful()) {

                Log::error('Cycle Awareness AI API Failed', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch cycle awareness data.',
                    'error' => $response->json(),
                ], $response->status());
            }

            $result = $response->json();

            /*
            |--------------------------------------------------------------------------
            | Get Cycle Awareness
            |--------------------------------------------------------------------------
            */

            $awareness = $result['cycle_awareness'] ?? null;

            if (! $awareness) {

                return response()->json([
                    'success' => false,
                    'message' => 'Cycle awareness data not found in AI response.',
                ], 500);
            }

            /*
            |--------------------------------------------------------------------------
            | Get Nested Data
            |--------------------------------------------------------------------------
            */

            $cycleContext = $awareness['cycle_context'] ?? [];
            $currentPhase = $awareness['current_phase'] ?? [];
            $lutealPhase = $awareness['luteal_phase'] ?? [];
            $hormoneLevels = $awareness['hormone_levels'] ?? [];
            $whatToKnow = $awareness['what_to_know'] ?? [];
            $fourPhaseCycle = $awareness['four_phase_cycle'] ?? [];

            /*
            |--------------------------------------------------------------------------
            | Save AI Response
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            |
            | Do NOT update menstrual_cycles.current_phase.
            |
            | AI returns:
            |
            | "Follicular Phase"
            |
            | But menstrual_cycles.current_phase is using another
            | database format/enum.
            |
            | So we only save the AI response into AwarenessSnapshot.
            |
            */

            DB::transaction(function () use (
                $user,
                $cycle,
                $awareness,
                $cycleContext,
                $currentPhase,
                $lutealPhase,
                $hormoneLevels,
                $whatToKnow,
                $fourPhaseCycle,
                $result
            ) {

                AwarenessSnapshot::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                    ],
                    [

                        /*
                        |--------------------------------------------------------------------------
                        | Cycle Context
                        |--------------------------------------------------------------------------
                        */

                        'phase' => $cycleContext['phase'] ?? null,

                        'day_range' => $currentPhase['day_range'] ?? null,

                        'current_cycle_day' =>
                            $cycleContext['cycle_day'] ?? null,

                        /*
                        |--------------------------------------------------------------------------
                        | Current Phase
                        |--------------------------------------------------------------------------
                        |
                        | Save exactly what AI sends:
                        |
                        | "Follicular Phase"
                        |
                        */

                        'current_phase' =>
                            $currentPhase['phase'] ?? null,

                        'dominant_hormone_note' =>
                            $currentPhase['summary'] ?? null,

                        /*
                        |--------------------------------------------------------------------------
                        | AI Data
                        |--------------------------------------------------------------------------
                        */

                        'luteal_phase' => $lutealPhase,

                        'hormone_levels' => $hormoneLevels,

                        'what_to_know' => $whatToKnow,

                        'four_phase_cycle' => $fourPhaseCycle,

                        /*
                        |--------------------------------------------------------------------------
                        | AI Metadata
                        |--------------------------------------------------------------------------
                        */

                        'ai_generated' => true,

                        'ai_cached' =>
                            $result['fetched'] ?? false,
                    ]
                );
            });

            /*
            |--------------------------------------------------------------------------
            | Get Saved Snapshot
            |--------------------------------------------------------------------------
            */

            $snapshot = AwarenessSnapshot::where('user_id', $user->id)
                ->where('cycle_id', $cycle->id)
                ->first();

            /*
            |--------------------------------------------------------------------------
            | Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,
                'message' => 'Cycle awareness data synced successfully.',
                'data' => $snapshot,
            ]);

        } catch (\Throwable $e) {

            Log::error('Cycle Awareness Sync Failed', [
                'user_id' => $user->id,
                'cycle_id' => $cycle->id,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync cycle awareness data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}