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
     * Get and Sync Cycle Awareness Data
     *
     * Frontend:
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
            | AI API URL
            |--------------------------------------------------------------------------
            */

            $aiUrl = rtrim(
                config('services.ai.base_url'),
                '/'
            ) . '/api/cycle-awareness';

            Log::info('Calling Cycle Awareness AI API', [
                'url' => $aiUrl,
                'user_id' => $user->id,
                'cycle_id' => $cycle->id,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Call AI API
            |--------------------------------------------------------------------------
            |
            | AI endpoint requires user_id as QUERY PARAMETER.
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
            | Log Response
            |--------------------------------------------------------------------------
            */

            Log::info('Cycle Awareness AI Response', [
                'status' => $response->status(),
                'user_id' => $user->id,
                'body' => $response->body(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Check AI Response
            |--------------------------------------------------------------------------
            */

            if (! $response->successful()) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch cycle awareness data from AI service.',
                    'error' => $response->json(),
                ], $response->status());
            }

            $result = $response->json();

            /*
            |--------------------------------------------------------------------------
            | Validate Response
            |--------------------------------------------------------------------------
            */

            if (! isset($result['cycle_awareness'])) {

                Log::error('Cycle Awareness Data Missing', [
                    'user_id' => $user->id,
                    'response' => $result,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Cycle awareness data is missing from AI response.',
                ], 500);
            }

            $awareness = $result['cycle_awareness'];

            /*
            |--------------------------------------------------------------------------
            | Extract AI Data
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
            | Normalize Phase
            |--------------------------------------------------------------------------
            |
            | AI returns:
            | "Follicular Phase"
            |
            | Database may expect:
            | "follicular"
            |
            */

            $rawPhase = strtolower(
                trim($currentPhase['phase'] ?? '')
            );

            $normalizedPhase = match (true) {

                str_contains($rawPhase, 'menstrual')
                    => 'menstrual',

                str_contains($rawPhase, 'follicular')
                    => 'follicular',

                str_contains($rawPhase, 'ovulatory')
                    => 'ovulatory',

                str_contains($rawPhase, 'luteal')
                    => 'luteal',

                default
                    => null,
            };

            /*
            |--------------------------------------------------------------------------
            | Validate Phase
            |--------------------------------------------------------------------------
            */

            if (! $normalizedPhase) {

                Log::warning('Unknown Cycle Phase From AI', [
                    'user_id' => $user->id,
                    'phase' => $currentPhase['phase'] ?? null,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Save Everything
            |--------------------------------------------------------------------------
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
                $normalizedPhase
            ) {

                /*
                |--------------------------------------------------------------------------
                | Update Menstrual Cycle
                |--------------------------------------------------------------------------
                */

                $cycle->update([
                    'current_cycle_day' =>
                        $cycleContext['cycle_day'] ?? null,

                    'current_phase' =>
                        $normalizedPhase,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Save Awareness Snapshot
                |--------------------------------------------------------------------------
                */

                AwarenessSnapshot::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                    ],
                    [

                        /*
                        |--------------------------------------------------------------------------
                        | Main
                        |--------------------------------------------------------------------------
                        */

                        'title' =>
                            $awareness['title'] ?? null,

                        /*
                        |--------------------------------------------------------------------------
                        | Cycle Context
                        |--------------------------------------------------------------------------
                        */

                        'cycle_day' =>
                            $cycleContext['cycle_day'] ?? null,

                        'phase' =>
                            $normalizedPhase,

                        'average_cycle_length' =>
                            $cycleContext['average_cycle_length'] ?? null,

                        /*
                        |--------------------------------------------------------------------------
                        | Current Phase
                        |--------------------------------------------------------------------------
                        */

                        'current_phase' =>
                            $normalizedPhase,

                        'day_range' =>
                            $currentPhase['day_range'] ?? null,

                        'dominant_hormone_note' =>
                            $currentPhase['summary'] ?? null,

                        /*
                        |--------------------------------------------------------------------------
                        | Luteal Phase
                        |--------------------------------------------------------------------------
                        */

                        'luteal_phase' =>
                            $lutealPhase,

                        /*
                        |--------------------------------------------------------------------------
                        | Hormone Levels
                        |--------------------------------------------------------------------------
                        */

                        'hormone_levels' =>
                            $hormoneLevels,

                        /*
                        |--------------------------------------------------------------------------
                        | What To Know
                        |--------------------------------------------------------------------------
                        */

                        'what_to_know' =>
                            $whatToKnow,

                        /*
                        |--------------------------------------------------------------------------
                        | Four Phase Cycle
                        |--------------------------------------------------------------------------
                        */

                        'four_phase_cycle' =>
                            $fourPhaseCycle,

                        /*
                        |--------------------------------------------------------------------------
                        | Complete AI Data
                        |--------------------------------------------------------------------------
                        */

                        'ai_data' =>
                            $awareness,

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
            | Return Response
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

