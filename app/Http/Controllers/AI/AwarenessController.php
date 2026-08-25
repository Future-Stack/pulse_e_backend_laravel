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
            $baseUrl = rtrim(
                config('services.ai.base_url', 'https://ai.fightthenumber.com'),
                '/'
            );
            $aiUrl = "{$baseUrl}/api/cycle-awareness";

            $response = Http::timeout(90)
                ->connectTimeout(30)
                ->acceptJson()
                ->get($aiUrl, [
                    'user_id' => $user->id,
                ]);

            if (! $response->successful()) {
                Log::warning('Cycle Awareness AI API Failed, applying local fallback', [
                    'user_id' => $user->id,
                    'cycle_id' => $cycle->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->applyLocalFallback($user, $cycle);
            }

            $result = $response->json();
            $awareness = $result['cycle_awareness'] ?? null;

            if (! is_array($awareness)) {
                Log::warning('Invalid Cycle Awareness AI Response, applying local fallback', [
                    'user_id' => $user->id,
                    'cycle_id' => $cycle->id,
                    'response' => $result,
                ]);

                return $this->applyLocalFallback($user, $cycle);
            }

            $title = $awareness['title'] ?? null;
            $cycleContext = $awareness['cycle_context'] ?? null;
            $currentPhase = $awareness['current_phase'] ?? null;
            $lutealPhase = $awareness['luteal_phase'] ?? null;
            $hormoneLevels = $awareness['hormone_levels'] ?? null;
            $whatToKnow = $awareness['what_to_know'] ?? null;
            $fourPhaseCycle = $awareness['four_phase_cycle'] ?? null;

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
                        'title' => $title,
                        'cycle_context' => $cycleContext,
                        'current_phase' => $currentPhase,
                        'luteal_phase' => $lutealPhase,
                        'hormone_levels' => $hormoneLevels,
                        'what_to_know' => $whatToKnow,
                        'four_phase_cycle' => $fourPhaseCycle,
                        'ai_response' => $awareness,
                        'ai_generated' => true,
                        'ai_cached' => $result['cached'] ?? false,
                    ]
                );
            });

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
            Log::error('Cycle Awareness Sync Failed Exception, applying local fallback', [
                'user_id' => $user->id,
                'cycle_id' => $cycle->id,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return $this->applyLocalFallback($user, $cycle);
        }
    }

    /**
     * Shared local fallback logic when AI engine is unreachable or returns invalid data.
     */
    private function applyLocalFallback($user, $cycle)
    {
        $startDate = $cycle->period_start_date ? \Carbon\Carbon::parse($cycle->period_start_date) : today();
        $currentCycleDay = max(1, (int) $startDate->diffInDays(today()) + 1);
        $avgCycleLength = $cycle->cycle_length ?? 28;

        $phaseName = match (true) {
            $currentCycleDay <= 5 => 'Menstrual phase',
            $currentCycleDay <= 13 => 'Follicular phase',
            $currentCycleDay <= 16 => 'Ovulatory phase',
            default => 'Luteal phase',
        };

        $phaseKey = match (true) {
            $currentCycleDay <= 5 => 'menstrual',
            $currentCycleDay <= 13 => 'follicular',
            $currentCycleDay <= 16 => 'ovulatory',
            default => 'luteal',
        };

        $title = "Cycle Day {$currentCycleDay} • {$phaseName}";

        $cycleContext = [
            'cycle_day' => $currentCycleDay,
            'phase' => $phaseName,
            'average_cycle_length' => "~{$avgCycleLength}d (est.)",
        ];

        $currentPhase = [
            'name' => $phaseName,
            'day_range' => match ($phaseKey) {
                'menstrual' => 'Day 1 - 5',
                'follicular' => 'Day 6 - 13',
                'ovulatory' => 'Day 14 - 16',
                'luteal' => "Day 17 - {$avgCycleLength}",
            },
            'description' => match ($phaseKey) {
                'menstrual' => 'Uterine lining sheds as a new cycle begins.',
                'follicular' => 'Follicles mature in preparation for ovulation.',
                'ovulatory' => 'An egg is released from the ovary; peak fertility window.',
                'luteal' => 'Progesterone rises to support potential implantation.',
            },
        ];

        $lutealPhase = [
            'estimated_start_day' => 17,
            'estimated_end_day' => $avgCycleLength,
            'status' => $phaseKey === 'luteal' ? 'active' : 'upcoming',
        ];

        $hormoneLevels = [
            'estrogen' => match ($phaseKey) {
                'menstrual' => 'Low',
                'follicular' => 'Rising',
                'ovulatory' => 'Peak',
                'luteal' => 'Moderate',
            },
            'progesterone' => match ($phaseKey) {
                'menstrual' => 'Low',
                'follicular' => 'Low',
                'ovulatory' => 'Low to Rising',
                'luteal' => 'High',
            },
            'lh' => match ($phaseKey) {
                'ovulatory' => 'Surge',
                default => 'Baseline',
            },
        ];

        $whatToKnow = [
            'overview' => "You are currently in your {$phaseName}. Keep logging symptoms, basal body temperature, and daily notes to refine insights.",
        ];

        $fourPhaseCycle = [
            'menstrual' => ['days' => '1-5', 'status' => $phaseKey === 'menstrual' ? 'current' : 'completed'],
            'follicular' => ['days' => '6-13', 'status' => $phaseKey === 'follicular' ? 'current' : ($currentCycleDay > 13 ? 'completed' : 'upcoming')],
            'ovulatory' => ['days' => '14-16', 'status' => $phaseKey === 'ovulatory' ? 'current' : ($currentCycleDay > 16 ? 'completed' : 'upcoming')],
            'luteal' => ['days' => "17-{$avgCycleLength}", 'status' => $phaseKey === 'luteal' ? 'current' : 'upcoming'],
        ];

        $snapshot = DB::transaction(function () use (
            $user,
            $cycle,
            $title,
            $cycleContext,
            $currentPhase,
            $lutealPhase,
            $hormoneLevels,
            $whatToKnow,
            $fourPhaseCycle
        ) {
            return NewAwarenessSnapshot::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'cycle_id' => $cycle->id,
                ],
                [
                    'title' => $title,
                    'cycle_context' => $cycleContext,
                    'current_phase' => $currentPhase,
                    'luteal_phase' => $lutealPhase,
                    'hormone_levels' => $hormoneLevels,
                    'what_to_know' => $whatToKnow,
                    'four_phase_cycle' => $fourPhaseCycle,
                    'ai_response' => null,
                    'ai_generated' => false,
                    'ai_cached' => false,
                ]
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Cycle awareness data synced with local fallback.',
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
    }
}

