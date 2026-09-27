<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AthletePerformance;
use App\Models\MenstrualCycle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AthletePerformanceController extends Controller
{
    /**
     * Get Unified Athlete Performance data and persist in database.
     * Integrates with AI endpoint: /api/v1/athlete/unified-performance
     *
     * GET /api/v1/athlete/unified-performance
     * GET /api/v1/athlete/performance
     * GET /api/athlete/unified-performance
     */
    public function unifiedPerformance(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? $request->query('user_id')
            ?? 1;

        $data = $this->fetchAndPersistAthletePerformance((int) $userId);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ], 200);
    }

    /**
     * Get historical Athlete Performance logs.
     *
     * GET /api/v1/athlete/history
     */
    public function history(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? $request->query('user_id')
            ?? 1;

        $days = (int) ($request->input('days') ?? $request->query('days') ?? 30);

        $records = AthletePerformance::where('user_id', $userId)
            ->orderBy('performance_date', 'desc')
            ->limit($days)
            ->get();

        return response()->json([
            'success' => true,
            'count'   => $records->count(),
            'data'    => $records,
        ], 200);
    }

    /**
     * Fetch from AI service, persist in database, and format for UI.
     */
    public function fetchAndPersistAthletePerformance(int $userId): array
    {
        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
        $aiUrl = "{$baseUrl}/api/v1/athlete/unified-performance";

        $payload = null;

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->acceptJson()
                ->get($aiUrl, [
                    'user_id' => $userId,
                ]);

            if ($response->successful() && is_array($response->json())) {
                $payload = $response->json();
            } else {
                Log::warning('AI Athlete Performance API unsuccessful or non-json', [
                    'user_id' => $userId,
                    'status'  => $response->status(),
                    'body'    => substr((string) $response->body(), 0, 300),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('AI Athlete Performance connection exception: ' . $e->getMessage());
        }

        // If remote API failed, try loading existing DB record for today
        if (!$payload) {
            $existing = AthletePerformance::where('user_id', $userId)
                ->whereDate('performance_date', today())
                ->first();

            if ($existing) {
                return $this->formatAthletePerformance($existing->toArray());
            }

            // Fallback generated locally
            $payload = $this->generateLocalFallback($userId);
        }

        // Format data with UI-ready helpers
        $formatted = $this->formatAthletePerformance($payload);

        // Persist to Database
        $this->persistAthletePerformance($userId, $payload);

        return $formatted;
    }

    /**
     * Persist to database athlete_performances table.
     */
    protected function persistAthletePerformance(int $userId, array $data): ?AthletePerformance
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return null;
            }

            $date = !empty($data['date']) ? Carbon::parse($data['date'])->toDateString() : today()->toDateString();
            $nextUpdate = !empty($data['next_update']) ? Carbon::parse($data['next_update'])->toDateTimeString() : null;

            return AthletePerformance::updateOrCreate(
                [
                    'user_id'          => $userId,
                    'performance_date' => $date,
                ],
                [
                    'readiness_score' => (int) ($data['readiness_score'] ?? 80),
                    'readiness_level' => (string) ($data['readiness_level'] ?? 'Ready'),
                    'hrv'             => $data['hrv'] ?? null,
                    'recovery'        => $data['recovery'] ?? null,
                    'training_load'   => $data['training_load'] ?? null,
                    'metrics'         => $data['metrics'] ?? null,
                    'fatigue_alerts'  => $data['fatigue_alerts'] ?? null,
                    'cycle_info'      => $data['cycle_info'] ?? null,
                    'phase_cards'     => $data['phase_cards'] ?? null,
                    'next_update'     => $nextUpdate,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Failed persisting athlete performance: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Format payload to match mobile UI requirements.
     */
    protected function formatAthletePerformance(array $raw): array
    {
        $date = $raw['date'] ?? today()->toDateString();
        $readinessScore = (int) ($raw['readiness_score'] ?? 80);
        $readinessLevel = $raw['readiness_level'] ?? 'Ready';

        // 1. Metric Cards Grid
        $rawMetrics = $raw['metrics'] ?? [];
        $hrv = $raw['hrv'] ?? ($rawMetrics['hrv'] ?? []);
        $recovery = $raw['recovery'] ?? ($rawMetrics['recovery'] ?? []);
        $trainingLoad = $raw['training_load'] ?? ($rawMetrics['training_load'] ?? []);
        $sleep = $rawMetrics['sleep'] ?? [];

        $metricsGrid = [
            'hrv' => [
                'key'         => 'hrv',
                'title'       => 'HRV (7-day avg)',
                'icon'        => 'heart',
                'value'       => $hrv['value'] ?? 68,
                'unit'        => $hrv['unit'] ?? 'ms',
                'trend'       => (int) ($hrv['trend'] ?? 0),
                'trend_label' => ($hrv['trend'] ?? 0) >= 0 ? '+' . ($hrv['trend'] ?? 0) : (string) ($hrv['trend'] ?? 0),
                'status'      => $hrv['status'] ?? 'good',
            ],
            'training_load' => [
                'key'         => 'training_load',
                'title'       => 'Training Load',
                'icon'        => 'bolt',
                'value'       => $trainingLoad['value'] ?? 185.0,
                'unit'        => $trainingLoad['unit'] ?? 'AU',
                'trend'       => (int) ($trainingLoad['trend'] ?? 0),
                'trend_label' => ($trainingLoad['trend'] ?? 0) >= 0 ? '+' . ($trainingLoad['trend'] ?? 0) : (string) ($trainingLoad['trend'] ?? 0),
                'status'      => $trainingLoad['status'] ?? 'moderate',
            ],
            'recovery' => [
                'key'         => 'recovery',
                'title'       => 'Active Recovery',
                'icon'        => 'refresh',
                'value'       => $recovery['percentage'] ?? 74,
                'percentage'  => $recovery['percentage'] ?? 74,
                'unit'        => '%',
                'trend'       => (int) ($recovery['trend'] ?? 0),
                'trend_label' => ($recovery['trend'] ?? 0) >= 0 ? '+' . ($recovery['trend'] ?? 0) : (string) ($recovery['trend'] ?? 0),
                'status'      => $recovery['status'] ?? 'moderate',
            ],
            'sleep' => [
                'key'         => 'sleep',
                'title'       => 'Sleep Quality',
                'icon'        => 'moon',
                'value'       => $sleep['percentage'] ?? 90,
                'percentage'  => $sleep['percentage'] ?? 90,
                'unit'        => '%',
                'trend'       => (int) ($sleep['trend'] ?? 0),
                'trend_label' => ($sleep['trend'] ?? 0) >= 0 ? '+' . ($sleep['trend'] ?? 0) : (string) ($sleep['trend'] ?? 0),
                'status'      => $sleep['status'] ?? 'good',
            ],
        ];

        // 2. Fatigue Alerts
        $fatigueAlerts = [];
        foreach ($raw['fatigue_alerts'] ?? [] as $alert) {
            $type = $alert['type'] ?? 'alert';
            $level = strtolower((string) ($alert['level'] ?? 'low'));
            $title = ucwords(str_replace('_', ' ', $type));

            $color = match ($level) {
                'high', 'severe' => 'red',
                'moderate'       => 'amber',
                default          => 'green',
            };

            $gaugePercent = match ($level) {
                'high', 'severe' => 85,
                'moderate'       => 55,
                default          => 25,
            };

            $fatigueAlerts[] = [
                'type'          => $type,
                'title'         => $title,
                'level'         => $level,
                'level_badge'   => ucfirst($level),
                'badge_color'   => $color,
                'gauge_percent' => $gaugePercent,
                'message'       => $alert['message'] ?? '',
            ];
        }

        // 3. Cycle Info
        $cycleInfo = $raw['cycle_info'] ?? [
            'phase'              => 'follicular',
            'cycle_day'          => 10,
            'days_to_next_phase' => 4,
            'phase_boost'        => 2,
            'phase_description'  => 'Follicular phase - building energy, good for strength training',
        ];
        $currentPhase = strtolower((string) ($cycleInfo['phase'] ?? 'follicular'));

        // 4. Phase Cards (4 phases with UI button meta)
        $phaseDaysMapping = [
            'menstrual'  => ['range' => 'D1-5', 'intensity' => 'Gentle', 'color' => 'neutral'],
            'follicular' => ['range' => 'D6-13', 'intensity' => 'High', 'color' => 'blue'],
            'ovulation'  => ['range' => 'D14', 'intensity' => 'Peak', 'color' => 'coral'],
            'luteal'     => ['range' => 'D15-28', 'intensity' => 'Moderate', 'color' => 'purple'],
        ];

        $phaseCards = [];
        foreach ($raw['phase_cards'] ?? [] as $card) {
            $phaseKey = strtolower((string) ($card['phase'] ?? ''));
            $meta = $phaseDaysMapping[$phaseKey] ?? ['range' => 'D1-28', 'intensity' => 'Optimal', 'color' => 'blue'];

            $phaseCards[] = [
                'phase'           => $phaseKey,
                'phase_name'      => ucfirst($phaseKey) . ' Phase',
                'days_range'      => $meta['range'],
                'intensity'       => $meta['intensity'],
                'is_active'       => $phaseKey === $currentPhase,
                'focus'           => $card['focus'] ?? '',
                'recommendations' => $card['recommendations'] ?? [],
            ];
        }

        return [
            'date'            => $date,
            'readiness_score' => $readinessScore,
            'readiness_max'   => 100,
            'readiness_level' => $readinessLevel,
            'summary_pills'   => [
                'hrv'      => ($hrv['value'] ?? 68) . 'ms',
                'recovery' => ($recovery['percentage'] ?? 74) . '%',
                'load'     => ucfirst($trainingLoad['status'] ?? 'Moderate'),
            ],
            'hrv'             => $hrv,
            'recovery'        => $recovery,
            'training_load'   => $trainingLoad,
            'metrics'         => $metricsGrid,
            'fatigue_alerts'  => $fatigueAlerts,
            'cycle_info'      => $cycleInfo,
            'phase_cards'     => $phaseCards,
            'next_update'     => $raw['next_update'] ?? now()->addDay()->toIso8601String(),
        ];
    }

    /**
     * Local dynamic fallback for athlete performance.
     */
    protected function generateLocalFallback(int $userId): array
    {
        $cycle = MenstrualCycle::where('user_id', $userId)
            ->where('is_completed', false)
            ->latest('id')
            ->first();

        $cycleDay = $cycle?->current_cycle_day ?? 10;
        $phase = $cycle?->current_phase ?? 'follicular';

        return [
            'date'            => today()->toDateString(),
            'readiness_score' => 84,
            'readiness_level' => 'Peak Ready',
            'hrv'             => [
                'value'  => 68,
                'unit'   => 'ms',
                'trend'  => 4,
                'status' => 'good',
            ],
            'recovery'        => [
                'percentage' => 74,
                'trend'      => 5,
                'status'     => 'moderate',
            ],
            'training_load'   => [
                'value'  => 185.5,
                'unit'   => 'AU',
                'trend'  => 12,
                'status' => 'moderate',
            ],
            'metrics'         => [
                'hrv'           => ['value' => 68, 'unit' => 'ms', 'trend' => 4, 'status' => 'good'],
                'sleep'         => ['percentage' => 95, 'trend' => 3, 'status' => 'good'],
                'recovery'      => ['percentage' => 74, 'trend' => 5, 'status' => 'moderate'],
                'training_load' => ['value' => 185.5, 'unit' => 'AU', 'trend' => 12, 'status' => 'moderate'],
            ],
            'fatigue_alerts'  => [
                ['type' => 'overtraining_risk', 'level' => 'low', 'message' => ''],
                ['type' => 'injury_risk_index', 'level' => 'low', 'message' => ''],
                ['type' => 'cumulative_fatigue', 'level' => 'moderate', 'message' => ''],
            ],
            'cycle_info'      => [
                'phase'              => $phase,
                'cycle_day'          => $cycleDay,
                'days_to_next_phase' => 4,
                'phase_boost'        => 2,
                'phase_description'  => ucfirst($phase) . ' phase - building energy, optimal for training',
            ],
            'phase_cards'     => [
                [
                    'phase'           => 'menstrual',
                    'focus'           => 'Restore and Recharge Gently',
                    'recommendations' => [
                        'Prioritize deep sleep',
                        'Walk instead of run',
                        'Focus on hydration',
                        'Try restorative yoga',
                    ],
                ],
                [
                    'phase'           => 'follicular',
                    'focus'           => 'Build Strength, Embrace Challenges',
                    'recommendations' => [
                        'Lift heavier weights',
                        'Try new workouts',
                        'Push intensity higher',
                        'Prioritize compound movements',
                    ],
                ],
                [
                    'phase'           => 'ovulation',
                    'focus'           => 'Peak Power Performance',
                    'recommendations' => [
                        'Hit heavy PRs',
                        'Maximize sprint intervals',
                        'Push explosive lifts',
                        'Attack high intensity',
                    ],
                ],
                [
                    'phase'           => 'luteal',
                    'focus'           => 'Steady endurance and recovery',
                    'recommendations' => [
                        'Prioritize moderate steady cardio',
                        'Manage fatigue proactively',
                        'Focus on hydration',
                        'Include active recovery',
                    ],
                ],
            ],
            'next_update'     => now()->addDay()->toIso8601String(),
        ];
    }
}
