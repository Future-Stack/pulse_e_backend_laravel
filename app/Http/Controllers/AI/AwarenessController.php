<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\MenstrualCycle;
use App\Models\NewAwarenessSnapshot;
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
            | AI API URL
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

            $response = Http::timeout(5)
                ->connectTimeout(2)
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

            /*
            |--------------------------------------------------------------------------
            | Decode AI Response
            |--------------------------------------------------------------------------
            */

            $result = $response->json();

            /*
            |--------------------------------------------------------------------------
            | Get cycle_awareness
            |--------------------------------------------------------------------------
            */

            $awareness = $result['cycle_awareness'] ?? null;

            if (! is_array($awareness)) {

                Log::error('Invalid Cycle Awareness AI Response', [
                    'user_id' => $user->id,
                    'response' => $result,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Cycle awareness data not found in AI response.',
                ], 500);
            }

            /*
            |--------------------------------------------------------------------------
            | Extract AI Data
            |--------------------------------------------------------------------------
            |
            | These are saved exactly as received from AI.
            |
            */

            $title = $awareness['title'] ?? null;

            $cycleContext = $awareness['cycle_context'] ?? null;

            $currentPhase = $awareness['current_phase'] ?? null;

            $lutealPhase = $awareness['luteal_phase'] ?? null;

            $hormoneLevels = $awareness['hormone_levels'] ?? null;

            $whatToKnow = $awareness['what_to_know'] ?? null;

            $fourPhaseCycle = $awareness['four_phase_cycle'] ?? null;

            /*
            |--------------------------------------------------------------------------
            | Save AI Response
            |--------------------------------------------------------------------------
            */

            DB::transaction(function () use (
                $user,
                $cycle,
                $awareness,
                $title,
                $cycleContext,
                $currentPhase,
                $lutealPhase,
                $hormoneLevels,
                $whatToKnow,
                $fourPhaseCycle,
                $result
            ) {

                NewAwarenessSnapshot::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                    ],
                    [

                        /*
                        |--------------------------------------------------------------------------
                        | Title
                        |--------------------------------------------------------------------------
                        */

                        'title' => $title,

                        /*
                        |--------------------------------------------------------------------------
                        | Cycle Context
                        |--------------------------------------------------------------------------
                        |
                        | Example:
                        |
                        | {
                        |   "cycle_day": 2,
                        |   "phase": "Menstrual phase",
                        |   "average_cycle_length": "~28d (est.)"
                        | }
                        |
                        */

                        'cycle_context' => $cycleContext,

                        /*
                        |--------------------------------------------------------------------------
                        | Current Phase
                        |--------------------------------------------------------------------------
                        |
                        | Save complete object exactly as AI sends it.
                        |
                        */

                        'current_phase' => $currentPhase,

                        /*
                        |--------------------------------------------------------------------------
                        | Luteal Phase
                        |--------------------------------------------------------------------------
                        */

                        'luteal_phase' => $lutealPhase,

                        /*
                        |--------------------------------------------------------------------------
                        | Hormone Levels
                        |--------------------------------------------------------------------------
                        */

                        'hormone_levels' => $hormoneLevels,

                        /*
                        |--------------------------------------------------------------------------
                        | What To Know
                        |--------------------------------------------------------------------------
                        */

                        'what_to_know' => $whatToKnow,

                        /*
                        |--------------------------------------------------------------------------
                        | Four Phase Cycle
                        |--------------------------------------------------------------------------
                        */

                        'four_phase_cycle' => $fourPhaseCycle,

                        /*
                        |--------------------------------------------------------------------------
                        | Complete cycle_awareness Response
                        |--------------------------------------------------------------------------
                        |
                        | This keeps the complete AI response so no AI data
                        | is lost even if the AI adds new fields later.
                        |
                        */

                        'ai_response' => $awareness,

                        /*
                        |--------------------------------------------------------------------------
                        | AI Metadata
                        |--------------------------------------------------------------------------
                        */

                        'ai_generated' =>
                            $result['fetched'] ?? true,

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

            $snapshot = NewAwarenessSnapshot::where('user_id', $user->id)
                ->where('cycle_id', $cycle->id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Cycle awareness data synced successfully.',
                'data' => [
                    'id' => $snapshot->id,
                    'user_id' => $snapshot->user_id,
                    'cycle_id' => $snapshot->cycle_id,

                    'title' => $snapshot->title,
                    'cycle_context' => $snapshot->cycle_context,
                    'current_phase' => $snapshot->current_phase,
                    'luteal_phase' => $snapshot->luteal_phase,
                    'hormone_levels' => $snapshot->hormone_levels,
                    'what_to_know' => $snapshot->what_to_know,
                    'four_phase_cycle' => $snapshot->four_phase_cycle,

                    'ai_generated' => $snapshot->ai_generated,
                    'ai_cached' => $snapshot->ai_cached,

                    'created_at' => $snapshot->created_at,
                    'updated_at' => $snapshot->updated_at,
                ],
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

