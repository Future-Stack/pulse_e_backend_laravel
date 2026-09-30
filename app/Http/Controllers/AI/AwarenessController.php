<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\MenstrualCycle;
use App\Models\NewAwarenessSnapshot;
use App\Services\CycleCalculatorService;
use Carbon\Carbon;
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
            | Call AI Cycle Awareness API
            |--------------------------------------------------------------------------
            */

            $baseUrl = rtrim(
                config(
                    'services.ai.base_url',
                    'https://ai.fightthenumber.com'
                ),
                '/'
            );

            $aiUrl = "{$baseUrl}/api/cycle-awareness";

            $response = Http::timeout(90)
                ->connectTimeout(30)
                ->acceptJson()
                ->get($aiUrl, [
                    'user_id' => $user->id,
                ]);

            /*
            |--------------------------------------------------------------------------
            | AI API Failed
            |--------------------------------------------------------------------------
            */

            if (! $response->successful()) {
                Log::warning(
                    'Cycle Awareness AI API Failed, applying local fallback',
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]
                );

                return $this->applyLocalFallback($user, $cycle);
            }

            /*
            |--------------------------------------------------------------------------
            | Validate AI Response
            |--------------------------------------------------------------------------
            */

            $result = $response->json();

            $awareness = $result['cycle_awareness'] ?? null;

            if (! is_array($awareness)) {
                Log::warning(
                    'Invalid Cycle Awareness AI Response, applying local fallback',
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                        'response' => $result,
                    ]
                );

                return $this->applyLocalFallback($user, $cycle);
            }

            /*
            |--------------------------------------------------------------------------
            | Extract AI Data
            |--------------------------------------------------------------------------
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
            | Save AI Awareness Snapshot
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

            /*
            |--------------------------------------------------------------------------
            | Get Saved Snapshot
            |--------------------------------------------------------------------------
            */

            $snapshot = NewAwarenessSnapshot::where('user_id', $user->id)
                ->where('cycle_id', $cycle->id)
                ->first();

            /*
            |--------------------------------------------------------------------------
            | Return AI Response
            |--------------------------------------------------------------------------
            */

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
            /*
            |--------------------------------------------------------------------------
            | Exception -> Local Fallback
            |--------------------------------------------------------------------------
            */

            Log::error(
                'Cycle Awareness Sync Failed Exception, applying local fallback',
                [
                    'user_id' => $user->id,
                    'cycle_id' => $cycle->id,
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]
            );

            return $this->applyLocalFallback($user, $cycle);
        }
    }

    /**
     * Shared local fallback logic when AI engine is unreachable
     * or returns invalid data.
     */
    private function applyLocalFallback($user, $cycle)
    {
        /*
        |--------------------------------------------------------------------------
        | Cycle Start Date
        |--------------------------------------------------------------------------
        */

        $startDate = $cycle->period_start_date
            ? Carbon::parse($cycle->period_start_date)
            : today();

        /*
        |--------------------------------------------------------------------------
        | Get Centralized Cycle Settings
        |--------------------------------------------------------------------------
        |
        | CycleCalculatorService is the single source of truth for:
        | - average cycle length
        | - cycle history
        | - luteal length
        | - period length
        |
        */

        $settings = CycleCalculatorService::getUserCycleSettings($user);

        /*
        |--------------------------------------------------------------------------
        | Cycle Length
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | Do not use:
        |
        | $cycle->cycle_length ?? $settings['cycle_length']
        |
        | because an old cycle_length value can override the dynamically
        | calculated historical average.
        |
        */

        $avgCycleLength = (int) (
            $settings['cycle_length'] ?? 28
        );

        $lutealLength = (int) (
            $settings['luteal_phase_length'] ?? 14
        );

        $periodLength = (int) (
            $settings['period_length'] ?? 5
        );

        /*
        |--------------------------------------------------------------------------
        | Logged Period Information
        |--------------------------------------------------------------------------
        */

        $isPeriodLogged = false;

        if (! empty($cycle->period_end_date)) {
            $periodEndDate = Carbon::parse(
                $cycle->period_end_date
            );

            $isPeriodLogged = today()->betweenIncluded(
                $startDate,
                $periodEndDate
            );

            $loggedPeriodDays = $startDate->diffInDays(
                $periodEndDate
            ) + 1;

            if (
                $loggedPeriodDays >= 1 &&
                $loggedPeriodDays <= 12
            ) {
                $periodLength = $loggedPeriodDays;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Current Cycle Day
        |--------------------------------------------------------------------------
        |
        | Example:
        |
        | Cycle length = 32
        | Start date = Sep 10
        |
        | After day 32:
        | Day 33 -> Day 1
        |
        */

        $daysSinceStart = max(
            0,
            $startDate->diffInDays(today())
        );

        $currentCycleDay = (
            $daysSinceStart % $avgCycleLength
        ) + 1;

        /*
        |--------------------------------------------------------------------------
        | Calculate Phases
        |--------------------------------------------------------------------------
        */

        $phases = CycleCalculatorService::calculatePhases(
            $avgCycleLength,
            $lutealLength,
            $periodLength
        );

        /*
        |--------------------------------------------------------------------------
        | Get Current Phase
        |--------------------------------------------------------------------------
        */

        $dayInfo = CycleCalculatorService::getPhaseForCycleDay(
            $currentCycleDay,
            $avgCycleLength,
            $lutealLength,
            $periodLength,
            $isPeriodLogged
        );

        $phaseName = $dayInfo['phase']['name'];
        $phaseKey = $dayInfo['phase']['key'];

        /*
        |--------------------------------------------------------------------------
        | Title
        |--------------------------------------------------------------------------
        */

        $title = "Cycle Day {$currentCycleDay} • {$phaseName}";

        /*
        |--------------------------------------------------------------------------
        | Cycle Context
        |--------------------------------------------------------------------------
        */

        $cycleContext = [
            'cycle_day' => $currentCycleDay,
            'phase' => $phaseName,
            'average_cycle_length' => "~{$avgCycleLength}d (est.)",
        ];

        /*
        |--------------------------------------------------------------------------
        | Current Phase
        |--------------------------------------------------------------------------
        */

        $currentPhase = [
            'name' => $phaseName,

            'day_range' => match ($phaseKey) {
                'menstrual' => "Day {$phases['menstrual_start']} - {$phases['menstrual_end']}",

                'follicular' => "Day {$phases['follicular_start']} - {$phases['follicular_end']}",

                'ovulatory' => "Day {$phases['ovulatory_start']} - {$phases['ovulatory_end']}",

                'luteal' => "Day {$phases['luteal_start']} - {$phases['luteal_end']}",

                default => null,
            },

            'description' => match ($phaseKey) {
                'menstrual' =>
                    'Uterine lining sheds as a new cycle begins.',

                'follicular' =>
                    'Follicles mature in preparation for ovulation.',

                'ovulatory' =>
                    'An egg is released from the ovary; peak fertility window.',

                'luteal' =>
                    'Progesterone rises to support potential implantation.',

                default => null,
            },
        ];

        /*
        |--------------------------------------------------------------------------
        | Luteal Phase
        |--------------------------------------------------------------------------
        */

        $lutealPhase = [
            'estimated_start_day' => $phases['luteal_start'],
            'estimated_end_day' => $phases['luteal_end'],

            'status' => $phaseKey === 'luteal'
                ? 'active'
                : (
                    $currentCycleDay < $phases['luteal_start']
                        ? 'upcoming'
                        : 'completed'
                ),
        ];

        /*
        |--------------------------------------------------------------------------
        | Hormone Levels
        |--------------------------------------------------------------------------
        */

        $hormoneLevels = [
            'estrogen' => match ($phaseKey) {
                'menstrual' => 'Low',
                'follicular' => 'Rising',
                'ovulatory' => 'Peak',
                'luteal' => 'Moderate',
                default => 'Unknown',
            },

            'progesterone' => match ($phaseKey) {
                'menstrual' => 'Low',
                'follicular' => 'Low',
                'ovulatory' => 'Low to Rising',
                'luteal' => 'High',
                default => 'Unknown',
            },

            'lh' => match ($phaseKey) {
                'ovulatory' => 'Surge',
                default => 'Baseline',
            },
        ];

        /*
        |--------------------------------------------------------------------------
        | What To Know
        |--------------------------------------------------------------------------
        */

        $whatToKnow = [
            'overview' =>
                "You are currently in your {$phaseName}. "
                . 'Keep logging symptoms, basal body temperature, '
                . 'and daily notes to refine insights.',
        ];

        /*
        |--------------------------------------------------------------------------
        | Four Phase Cycle
        |--------------------------------------------------------------------------
        */

        $fourPhaseCycle = [
            'menstrual' => [
                'days' =>
                    "{$phases['menstrual_start']}-{$phases['menstrual_end']}",

                'status' =>
                    $phaseKey === 'menstrual'
                        ? 'current'
                        : (
                            $currentCycleDay > $phases['menstrual_end']
                                ? 'completed'
                                : 'upcoming'
                        ),
            ],

            'follicular' => [
                'days' =>
                    "{$phases['follicular_start']}-{$phases['follicular_end']}",

                'status' =>
                    $phaseKey === 'follicular'
                        ? 'current'
                        : (
                            $currentCycleDay > $phases['follicular_end']
                                ? 'completed'
                                : 'upcoming'
                        ),
            ],

            'ovulatory' => [
                'days' =>
                    "{$phases['ovulatory_start']}-{$phases['ovulatory_end']}",

                'status' =>
                    $phaseKey === 'ovulatory'
                        ? 'current'
                        : (
                            $currentCycleDay > $phases['ovulatory_end']
                                ? 'completed'
                                : 'upcoming'
                        ),
            ],

            'luteal' => [
                'days' =>
                    "{$phases['luteal_start']}-{$phases['luteal_end']}",

                'status' =>
                    $phaseKey === 'luteal'
                        ? 'current'
                        : (
                            $currentCycleDay > $phases['luteal_end']
                                ? 'completed'
                                : 'upcoming'
                        ),
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Save Local Fallback Snapshot
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Return Local Fallback Response
        |--------------------------------------------------------------------------
        */

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
