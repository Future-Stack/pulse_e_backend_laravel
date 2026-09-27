<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\BbtLog;
use App\Models\CervicalMucusLog;
use App\Models\CycleCalendarInput;
use App\Models\CycleFertilityOverview;
use App\Models\CyclePredictionCache;
use App\Models\MenstrualCycle;
use App\Models\OpkLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CycleFertilityController extends Controller
{
    /**
     * Get Complete Cycle & Fertility Overview.
     * Integrates with AI endpoint: /api/cycle-overview
     * Returns:
     * - current_metrics
     * - fertile_window & fertile_window_prediction
     * - hormone_trends (with progress bar, status colors, formatted values)
     * - today_insights (cards with emoji icons for LH surge, mucus, BBT, etc.)
     * - ai_insights (full assessment, timing, recommendations)
     *
     * GET /api/v1/cycle-fertility/overview
     * GET /api/v1/cycle-overview
     * GET /api/cycle-overview
     */
    public function overview(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? $request->query('user_id')
            ?? 1;

        $mode = (string) ($request->input('mode') ?? $request->query('mode') ?? 'standard');
        $includeBbt = filter_var($request->input('include_bbt') ?? $request->query('include_bbt') ?? false, FILTER_VALIDATE_BOOLEAN);

        $overview = $this->fetchOverviewData((int) $userId, $mode, $includeBbt);

        return response()->json([
            'success' => true,
            'data'    => $overview,
        ], 200);
    }

    /**
     * Get Standalone Hormone Trends.
     *
     * GET /api/v1/cycle-fertility/hormone-trends
     * GET /api/v1/cycle/hormone-trends
     */
    public function hormoneTrends(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? $request->query('user_id')
            ?? 1;

        $mode = (string) ($request->input('mode') ?? $request->query('mode') ?? 'standard');
        $includeBbt = filter_var($request->input('include_bbt') ?? $request->query('include_bbt') ?? false, FILTER_VALIDATE_BOOLEAN);

        $overview = $this->fetchOverviewData((int) $userId, $mode, $includeBbt);

        return response()->json([
            'success'           => true,
            'date'              => now()->toDateString(),
            'current_cycle_day' => $overview['current_metrics']['current_cycle_day'] ?? 1,
            'current_phase'     => $overview['current_metrics']['current_phase'] ?? 'follicular',
            'hormone_trends'    => $overview['hormone_trends'] ?? [],
        ], 200);
    }

    /**
     * Get Standalone Today's Insights.
     *
     * GET /api/v1/cycle-fertility/today-insights
     * GET /api/v1/cycle/today-insights
     * GET /api/v1/today-insights
     */
    public function todayInsights(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? $request->query('user_id')
            ?? 1;

        $mode = (string) ($request->input('mode') ?? $request->query('mode') ?? 'standard');
        $includeBbt = filter_var($request->input('include_bbt') ?? $request->query('include_bbt') ?? false, FILTER_VALIDATE_BOOLEAN);

        $overview = $this->fetchOverviewData((int) $userId, $mode, $includeBbt);

        return response()->json([
            'success'        => true,
            'today_insights' => $overview['today_insights'] ?? [],
            'ai_insights'    => $overview['ai_insights'] ?? null,
        ], 200);
    }

    /**
     * Fetch Cycle Overview from AI service, with caching and fallback.
     */
    public function fetchOverviewData(int $userId, string $mode = 'standard', bool $includeBbt = false): array
    {
        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
        $aiUrl = "{$baseUrl}/api/cycle-overview";

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->acceptJson()
                ->get($aiUrl, [
                    'user_id'     => $userId,
                    'mode'        => $mode,
                    'include_bbt' => $includeBbt ? 'true' : 'false',
                ]);

            if ($response->successful()) {
                $payload = $response->json();
                if (is_array($payload) && (!empty($payload['hormone_trends']) || !empty($payload['current_metrics']))) {
                    $this->cachePrediction($userId, $payload, $mode, $includeBbt);
                    return $this->formatCycleOverview($payload, $userId);
                }
            }

            Log::warning('AI Cycle Overview API call unsuccessful or returned empty payload', [
                'user_id' => $userId,
                'status'  => $response->status(),
                'body'    => substr((string) $response->body(), 0, 300),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI Cycle Overview API connection failed: ' . $e->getMessage());
        }

        // Try cached prediction if available
        $cached = $this->getCachedPrediction($userId);
        if ($cached) {
            return $this->formatCycleOverview($cached, $userId);
        }

        // Fallback to local dynamic calculation
        return $this->generateLocalFallback($userId);
    }

    /**
     * Format raw AI response into rich UI-ready structure.
     */
    protected function formatCycleOverview(array $raw, int $userId): array
    {
        $currentMetrics = $raw['current_metrics'] ?? [];
        $fertileWindow  = $raw['fertile_window'] ?? [];
        $rawHormones    = $raw['hormone_trends'] ?? [];
        $aiInsights     = $raw['ai_insights'] ?? [];
        $cycleHistory   = $raw['cycle_history'] ?? [];
        $bbtAnalysis    = $raw['bbt_analysis'] ?? null;

        // 1. Format Hormone Trends
        $hormoneTrends = $this->formatHormoneTrends($rawHormones);

        // 2. Format Today's Insights (Cards with icons)
        $todayInsights = $this->formatTodayInsights($aiInsights, $fertileWindow, $currentMetrics, $hormoneTrends);

        // 3. Format Fertile Window Prediction block
        $fertilePrediction = $this->formatFertilePrediction($fertileWindow, $currentMetrics);

        // 4. Enrich Current Metrics
        $currentPhase = $currentMetrics['current_phase'] ?? 'follicular';
        $currentMetrics['phase_label'] = ucwords(str_replace('_', ' ', $currentPhase)) . ' Phase';

        return [
            'current_metrics'           => $currentMetrics,
            'fertile_window'            => $fertileWindow,
            'fertile_window_prediction' => $fertilePrediction,
            'bbt_analysis'              => $bbtAnalysis,
            'cycle_history'             => $cycleHistory,
            'hormone_trends'            => $hormoneTrends,
            'today_insights'            => $todayInsights,
            'ai_insights'               => $aiInsights,
        ];
    }

    /**
     * Format Hormone Trends with badges and progress percentages.
     */
    protected function formatHormoneTrends(array $rawTrends): array
    {
        $formatted = [];

        foreach ($rawTrends as $trend) {
            $name       = $trend['name'] ?? 'Hormone';
            $value      = $trend['value'] ?? 0;
            $unit       = $trend['unit'] ?? '';
            $status     = $trend['status'] ?? 'Normal';
            $barPercent = (int) ($trend['bar_percent'] ?? $trend['percentage'] ?? 0);

            // If percentage was 0 or not provided, calculate a reasonable bar percent based on value & unit
            if ($barPercent <= 0) {
                $barPercent = match (true) {
                    str_contains(strtolower($name), 'estrogen') => min(100, max(15, (int) round(($value / 350) * 100))),
                    str_contains(strtolower($name), 'progesterone') => min(100, max(15, (int) round(($value / 25) * 100))),
                    str_contains(strtolower($name), 'lh') => min(100, max(15, (int) round(($value / 80) * 100))),
                    default => 50,
                };
            }

            $statusColor = match (strtolower((string) $status)) {
                'optimal', 'rising', 'elevated' => 'optimal',
                'normal', 'baseline' => 'normal',
                'high', 'surge', 'peak' => 'high',
                'moderate' => 'moderate',
                'low', 'suppressed' => 'low',
                default => 'normal',
            };

            $formatted[] = [
                'name'            => $name,
                'value'           => $value,
                'unit'            => $unit,
                'formatted_value' => trim("{$value} {$unit}"),
                'status'          => $status,
                'status_color'    => $statusColor,
                'bar_percent'     => min(100, max(5, $barPercent)),
            ];
        }

        return $formatted;
    }

    /**
     * Format Today's Insights into clean cards with emojis.
     */
    protected function formatTodayInsights(array $aiInsights, array $fertileWindow, array $currentMetrics, array $hormoneTrends): array
    {
        $keyInsights     = $aiInsights['key_insights'] ?? [];
        $recommendations = $aiInsights['recommendations'] ?? [];
        $symptomTracking = $aiInsights['symptom_tracking'] ?? '';
        $currentPhase    = strtolower($currentMetrics['current_phase'] ?? 'follicular');
        $isFertileNow    = (bool) ($fertileWindow['is_fertile_now'] ?? false);
        $daysUntilOvu    = $fertileWindow['days_until_ovulation'] ?? null;

        // Check LH surge status
        $lhTrend = collect($hormoneTrends)->first(fn($h) => str_contains(strtolower($h['name'] ?? ''), 'lh'));
        $lhStatus = strtolower($lhTrend['status'] ?? '');

        $cards = [];

        // -------------------------------------------------------------
        // 1. LH Surge & Fertile Window Card (🌸)
        // -------------------------------------------------------------
        $lhMessage = null;
        foreach ($keyInsights as $insight) {
            $lower = strtolower($insight);
            if (str_contains($lower, 'lh surge') || str_contains($lower, 'fertile window') || str_contains($lower, 'ovulation')) {
                $lhMessage = $insight;
                break;
            }
        }
        if (!$lhMessage) {
            if ($lhStatus === 'high' || $lhStatus === 'surge' || $isFertileNow) {
                $lhMessage = 'Your LH surge indicates ovulation is imminent. High fertility window confirmed.';
            } elseif ($daysUntilOvu !== null && $daysUntilOvu <= 1) {
                $lhMessage = 'You enter your fertile window tomorrow. Ovulation is predicted in 1 day.';
            } elseif ($daysUntilOvu !== null) {
                $lhMessage = "You enter your fertile window in {$daysUntilOvu} days. Ovulation probability is strong.";
            } else {
                $lhMessage = 'Follicles are maturing steadily; hormone levels are rising towards the fertile window.';
            }
        }

        $cards[] = [
            'id'       => 1,
            'icon'     => '🌸',
            'type'     => 'lh_surge',
            'category' => 'ovulation',
            'title'    => 'Fertility & Ovulation',
            'message'  => $lhMessage,
        ];

        // -------------------------------------------------------------
        // 2. Cervical Mucus Card (💧)
        // -------------------------------------------------------------
        $mucusMessage = null;
        foreach ($keyInsights as $insight) {
            $lower = strtolower($insight);
            if (str_contains($lower, 'mucus') || str_contains($lower, 'cervical') || str_contains($lower, 'egg-white')) {
                $mucusMessage = $insight;
                break;
            }
        }
        if (!$mucusMessage && !empty($symptomTracking)) {
            if (preg_match('/cervical mucus[^.]*\./i', $symptomTracking, $matches)) {
                $mucusMessage = trim($matches[0]);
            }
        }
        if (!$mucusMessage) {
            if ($currentPhase === 'ovulatory' || $isFertileNow) {
                $mucusMessage = 'Cervical mucus likely at peak egg-white consistency. Optimal for conception.';
            } elseif ($currentPhase === 'follicular') {
                $mucusMessage = 'Monitor cervical mucus daily — watch for stretchy consistency signaling peak fertility.';
            } else {
                $mucusMessage = 'Track cervical mucus changes daily to confirm your unique fertile patterns.';
            }
        }

        $cards[] = [
            'id'       => 2,
            'icon'     => '💧',
            'type'     => 'cervical_mucus',
            'category' => 'cervical_mucus',
            'title'    => 'Cervical Mucus',
            'message'  => $mucusMessage,
        ];

        // -------------------------------------------------------------
        // 3. BBT (Basal Body Temperature) Card (🌡️)
        // -------------------------------------------------------------
        $bbtMessage = null;
        foreach ($recommendations as $rec) {
            $lower = strtolower($rec);
            if (str_contains($lower, 'bbt') || str_contains($lower, 'temperature') || str_contains($lower, 'temp')) {
                $bbtMessage = $rec;
                break;
            }
        }
        if (!$bbtMessage) {
            if ($currentPhase === 'ovulatory') {
                $bbtMessage = 'Track BBT tonight — a temperature rise confirms ovulation has occurred.';
            } else {
                $bbtMessage = 'Begin daily basal body temperature tracking first thing each morning to establish your baseline.';
            }
        }

        $cards[] = [
            'id'       => 3,
            'icon'     => '🌡️',
            'type'     => 'bbt',
            'category' => 'temperature',
            'title'    => 'Basal Body Temperature',
            'message'  => $bbtMessage,
        ];

        // -------------------------------------------------------------
        // 4. Additional Key Insights
        // -------------------------------------------------------------
        $cardId = 4;
        foreach ($keyInsights as $insight) {
            // Avoid duplicate messages
            if ($insight === $lhMessage || $insight === $mucusMessage || $insight === $bbtMessage) {
                continue;
            }

            $lower = strtolower($insight);
            $icon = match (true) {
                str_contains($lower, 'ovulat') || str_contains($lower, 'fertile') => '🌸',
                str_contains($lower, 'mucus') || str_contains($lower, 'hydrat') => '💧',
                str_contains($lower, 'temperature') || str_contains($lower, 'bbt') => '🌡️',
                str_contains($lower, 'sleep') => '🌙',
                str_contains($lower, 'diet') || str_contains($lower, 'food') || str_contains($lower, 'nutrit') => '🥗',
                str_contains($lower, 'energy') || str_contains($lower, 'exercise') => '⚡',
                str_contains($lower, 'cycle') || str_contains($lower, 'phase') => '🗓️',
                default => '✨',
            };

            $cards[] = [
                'id'       => $cardId++,
                'icon'     => $icon,
                'type'     => 'general_insight',
                'category' => 'insight',
                'title'    => 'Cycle Insight',
                'message'  => $insight,
            ];
        }

        return $cards;
    }

    /**
     * Format Fertile Window Prediction with dates and day spans.
     */
    protected function formatFertilePrediction(array $fertileWindow, array $currentMetrics): array
    {
        $startDateStr = $currentMetrics['period_start_date'] ?? null;
        $periodStart = $startDateStr ? Carbon::parse($startDateStr) : today()->subDays(($currentMetrics['current_cycle_day'] ?? 9) - 1);

        $fertileStartDay = (int) ($fertileWindow['fertile_start_day'] ?? 10);
        $fertileEndDay   = (int) ($fertileWindow['fertile_end_day'] ?? 15);
        $peakDay         = (int) ($currentMetrics['predicted_ovulation_day'] ?? round(($fertileStartDay + $fertileEndDay) / 2));
        $spanDays        = max(1, ($fertileEndDay - $fertileStartDay + 1));

        $windowOpensDate  = $periodStart->copy()->addDays($fertileStartDay - 1);
        $peakDate         = $periodStart->copy()->addDays($peakDay - 1);
        $windowClosesDate = $periodStart->copy()->addDays($fertileEndDay - 1);

        return [
            'window_opens_day'   => $fertileStartDay,
            'window_opens_date'  => $windowOpensDate->format('M d'),
            'window_opens_full'  => $windowOpensDate->toDateString(),
            'peak_day'           => $peakDay,
            'peak_date'          => $peakDate->format('M d'),
            'peak_date_full'     => $peakDate->toDateString(),
            'window_closes_day'  => $fertileEndDay,
            'window_closes_date' => $windowClosesDate->format('M d'),
            'window_closes_full' => $windowClosesDate->toDateString(),
            'span_days'          => $spanDays,
            'description'        => "Range-based prediction model — avoids single-day assumptions. Your window spans {$spanDays} days for maximum accuracy.",
        ];
    }

    /**
     * Cache prediction in database if user exists.
     */
    protected function cachePrediction(int $userId, array $data, string $mode, bool $includeBbt): void
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return;
            }

            $cycle = MenstrualCycle::where('user_id', $userId)
                ->where('is_completed', false)
                ->latest('id')
                ->first();

            // Persist to cycle_fertility_overviews table (storing Hormone Trends & Today's Insights)
            $formatted = $this->formatCycleOverview($data, $userId);
            CycleFertilityOverview::updateOrCreate(
                [
                    'user_id'       => $userId,
                    'overview_date' => today()->toDateString(),
                ],
                [
                    'cycle_id'                  => $cycle?->id,
                    'current_cycle_day'         => $formatted['current_metrics']['current_cycle_day'] ?? null,
                    'current_phase'             => $formatted['current_metrics']['current_phase'] ?? null,
                    'period_start_date'         => $formatted['current_metrics']['period_start_date'] ?? null,
                    'period_end_date'           => $formatted['current_metrics']['period_end_date'] ?? null,
                    'fertile_window'            => $formatted['fertile_window'] ?? null,
                    'fertile_window_prediction' => $formatted['fertile_window_prediction'] ?? null,
                    'hormone_trends'            => $formatted['hormone_trends'] ?? null,
                    'today_insights'            => $formatted['today_insights'] ?? null,
                    'ai_insights'               => $formatted['ai_insights'] ?? null,
                    'bbt_analysis'              => $formatted['bbt_analysis'] ?? null,
                    'cycle_history'             => $formatted['cycle_history'] ?? null,
                ]
            );

            // Cache in prediction cache table if cycle exists
            if ($cycle) {
                CyclePredictionCache::updateOrCreate(
                    [
                        'cycle_id'  => $cycle->id,
                        'cache_key' => "cycle_overview_user_{$userId}",
                    ],
                    [
                        'endpoint'           => '/api/cycle-overview',
                        'request_payload'    => [
                            'user_id'     => $userId,
                            'mode'        => $mode,
                            'include_bbt' => $includeBbt,
                        ],
                        'prediction'         => $data,
                        'prediction_version' => '1.0',
                        'ai_generated'       => true,
                        'ai_cached'          => false,
                        'expires_at'         => now()->addHours(6),
                    ]
                );
            }

            // Also update active cycle model if metrics are present
            if ($cycle && !empty($data['current_metrics'])) {
                $metrics = $data['current_metrics'];
                $cycle->update([
                    'current_cycle_day'       => $metrics['current_cycle_day'] ?? $cycle->current_cycle_day,
                    'cycle_length'            => $metrics['cycle_length'] ?? $cycle->cycle_length,
                    'current_phase'           => $metrics['current_phase'] ?? $cycle->current_phase,
                    'predicted_ovulation_day' => $metrics['predicted_ovulation_day'] ?? $cycle->predicted_ovulation_day,
                    'fertile_start_day'       => $data['fertile_window']['fertile_start_day'] ?? $cycle->fertile_start_day,
                    'fertile_end_day'         => $data['fertile_window']['fertile_end_day'] ?? $cycle->fertile_end_day,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Error caching cycle overview: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve cached prediction.
     */
    protected function getCachedPrediction(int $userId): ?array
    {
        try {
            $cache = CyclePredictionCache::where('cache_key', "cycle_overview_user_{$userId}")
                ->where('expires_at', '>', now())
                ->latest('id')
                ->first();

            if ($cache && is_array($cache->prediction)) {
                return $cache->prediction;
            }
        } catch (\Throwable $e) {
            // Ignore cache errors
        }

        return null;
    }

    /**
     * Local dynamic fallback when AI service is unavailable.
     */
    protected function generateLocalFallback(int $userId): array
    {
        $calendarInput = CycleCalendarInput::where('user_id', $userId)->latest('start_date')->first();
        $cycle = MenstrualCycle::where('user_id', $userId)->latest('id')->first();

        $startDate = $calendarInput?->start_date
            ? Carbon::parse($calendarInput->start_date)->toDateString()
            : ($cycle?->period_start_date ? Carbon::parse($cycle->period_start_date)->toDateString() : today()->subDays(8)->toDateString());

        $startCarbon = Carbon::parse($startDate);
        $currentCycleDay = max(1, (int) $startCarbon->diffInDays(today()) + 1);

        $phase = match (true) {
            $currentCycleDay <= 5  => 'menstrual',
            $currentCycleDay <= 13 => 'follicular',
            $currentCycleDay <= 16 => 'ovulatory',
            default               => 'luteal',
        };

        $cycleLength = $cycle?->cycle_length ?? 28;
        $fertileStart = max(1, $cycleLength - 18);
        $fertileEnd   = max(1, $cycleLength - 12);
        $ovulationDay = (int) round(($fertileStart + $fertileEnd) / 2);
        $daysUntilOvu = max(0, $ovulationDay - $currentCycleDay);

        $raw = [
            'current_metrics' => [
                'current_cycle_day'       => $currentCycleDay,
                'cycle_length'            => $cycleLength,
                'current_phase'           => $phase,
                'period_start_date'       => $startDate,
                'period_end_date'         => Carbon::parse($startDate)->addDays(4)->toDateString(),
                'predicted_ovulation_day' => $ovulationDay,
                'confirmed_ovulation_day' => null,
                'is_confirmed'            => false,
            ],
            'fertile_window' => [
                'fertile_start_day'     => $fertileStart,
                'fertile_end_day'       => $fertileEnd,
                'days_until_ovulation'  => $daysUntilOvu,
                'ovulation_probability' => $currentCycleDay >= $fertileStart && $currentCycleDay <= $fertileEnd ? 75.0 : 35.0,
                'is_fertile_now'        => $currentCycleDay >= $fertileStart && $currentCycleDay <= $fertileEnd,
            ],
            'bbt_analysis' => null,
            'cycle_history' => [
                'previous_cycles_count'  => MenstrualCycle::where('user_id', $userId)->where('is_completed', true)->count(),
                'avg_cycle_length'       => 28.0,
                'avg_period_length'      => 5.0,
                'cycle_regularity_score' => 85.0,
            ],
            'hormone_trends' => [
                [
                    'name'        => 'Estrogen (E2)',
                    'value'       => $phase === 'ovulatory' ? 284.0 : ($phase === 'follicular' ? 150.0 : 80.0),
                    'unit'        => 'pg/mL',
                    'status'      => $phase === 'ovulatory' ? 'Optimal' : ($phase === 'follicular' ? 'Rising' : 'Normal'),
                    'bar_percent' => $phase === 'ovulatory' ? 82 : ($phase === 'follicular' ? 43 : 25),
                ],
                [
                    'name'        => 'Progesterone',
                    'value'       => $phase === 'luteal' ? 14.5 : 4.0,
                    'unit'        => 'ng/mL',
                    'status'      => 'Normal',
                    'bar_percent' => $phase === 'luteal' ? 70 : 20,
                ],
                [
                    'name'        => 'LH Surge',
                    'value'       => $phase === 'ovulatory' ? 68.0 : ($phase === 'follicular' ? 20.0 : 8.0),
                    'unit'        => 'mIU/mL',
                    'status'      => $phase === 'ovulatory' ? 'High' : ($phase === 'follicular' ? 'Moderate' : 'Baseline'),
                    'bar_percent' => $phase === 'ovulatory' ? 75 : ($phase === 'follicular' ? 25 : 15),
                ],
            ],
            'ai_insights' => [
                'cycle_assessment' => "You're currently on day {$currentCycleDay} of a {$cycleLength}-day cycle in the {$phase} phase.",
                'optimal_timing'   => "Your peak fertility window is days {$fertileStart}-{$fertileEnd}, with ovulation predicted around day {$ovulationDay}.",
                'phase_explanation'=> "During the {$phase} phase, hormones fluctuate to prepare your body for ovulation and luteal support.",
                'symptom_tracking' => 'Track cervical mucus changes, basal body temperature each morning, and energy levels.',
                'key_insights'     => [
                    "Cycle length of {$cycleLength} days falls within a healthy standard range",
                    $daysUntilOvu > 0 ? "You are {$daysUntilOvu} days away from predicted ovulation" : "You are at your peak ovulation day",
                    "Fertility window spans days {$fertileStart} to {$fertileEnd}",
                    "Follicular phase is ideal for high-energy activities and balanced nutrition",
                ],
                'recommendations'  => [
                    'Begin daily basal body temperature tracking first thing each morning',
                    'Monitor cervical mucus daily and log changes in texture',
                    'Consider using ovulation predictor kits (OPKs) around your fertile window',
                    'Maintain a balanced diet rich in folate, iron, and antioxidants',
                    'Prioritize 7-9 hours of quality sleep to support hormonal balance',
                ],
                'next_steps'       => 'Log symptoms daily over the next week to establish precise hormonal baselines.',
                'confidence_score' => 70,
            ],
        ];

        return $this->formatCycleOverview($raw, $userId);
    }
}
