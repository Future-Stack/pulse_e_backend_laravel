<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\MenopauseExportSnapshot;
use App\Models\MenopauseInsightSnapshot;
use App\Models\MenopauseSymptomSnapshot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MenopauseController extends Controller
{
    /**
     * Get Menopause Symptoms Overview & Trackers.
     * Integrates with AI endpoint: /api/v1/menopause/symptoms
     *
     * GET|POST /api/v1/menopause/symptoms
     */
    public function symptoms(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or user_id is missing.',
            ], 401);
        }

        $userId = (int) $userId;
        $period = (string) ($request->input('period') ?? $request->query('period') ?? '7d');
        $todayStr = today()->toDateString();
        $forceRefresh = $request->boolean('refresh') || $request->boolean('sync');

        // Fast In-Memory Cache Key
        $cacheKey = "menopause_symptoms_{$userId}_{$period}_{$todayStr}";

        // 1. Check in-memory cache if forceRefresh is not requested
        if (!$forceRefresh && Cache::has($cacheKey)) {
            return response()->json([
                'success' => true,
                'source'  => 'cache',
                'data'    => Cache::get($cacheKey),
            ], 200);
        }

        // 2. Check Database for today's snapshot
        $snapshot = MenopauseSymptomSnapshot::where('user_id', $userId)
            ->where('period', $period)
            ->whereDate('snapshot_date', $todayStr)
            ->first();

        if ($snapshot && !$forceRefresh) {
            $formattedData = $this->formatSymptomSnapshot($snapshot);

            // Store in fast in-memory cache for 1 hour
            Cache::put($cacheKey, $formattedData, now()->addHour());

            return response()->json([
                'success' => true,
                'source'  => 'database',
                'data'    => $formattedData,
            ], 200);
        }

        // 3. New Day or Force Refresh -> Fetch from AI API
        $data = $this->fetchAndSaveSymptomsFromAi($userId, $period);

        if ($data) {
            Cache::put($cacheKey, $data, now()->addHour());

            return response()->json([
                'success' => true,
                'source'  => 'ai_service',
                'data'    => $data,
            ], 200);
        }

        // 4. Fallback: If AI fails and no snapshot exists for today, try previous DB snapshot
        $previousSnapshot = MenopauseSymptomSnapshot::where('user_id', $userId)
            ->where('period', $period)
            ->latest('snapshot_date')
            ->first();

        if ($previousSnapshot) {
            $formattedData = $this->formatSymptomSnapshot($previousSnapshot);

            return response()->json([
                'success' => true,
                'source'  => 'database_fallback',
                'message' => 'Serving latest available snapshot.',
                'data'    => $formattedData,
            ], 200);
        }

        // 5. Ultimate Fallback: Generate local default payload if user has no prior history
        $fallbackData = $this->generateSymptomLocalFallback($userId, $period);
        $this->persistSymptomSnapshot($userId, $period, $fallbackData);

        return response()->json([
            'success' => true,
            'source'  => 'local_fallback',
            'data'    => $fallbackData,
        ], 200);
    }

    /**
     * Get Menopause Symptom Matrix & Insights.
     * Integrates with AI endpoint: /api/v1/menopause/insights
     *
     * GET|POST /api/v1/menopause/insights
     */
    public function insights(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or user_id is missing.',
            ], 401);
        }

        $userId = (int) $userId;
        $period = (string) ($request->input('period') ?? $request->query('period') ?? '7d');
        $todayStr = today()->toDateString();
        $forceRefresh = $request->boolean('refresh') || $request->boolean('sync');

        // Fast In-Memory Cache Key
        $cacheKey = "menopause_insights_{$userId}_{$period}_{$todayStr}";

        // 1. Check in-memory cache if forceRefresh is not requested
        if (!$forceRefresh && Cache::has($cacheKey)) {
            return response()->json([
                'success' => true,
                'source'  => 'cache',
                'data'    => Cache::get($cacheKey),
            ], 200);
        }

        // 2. Check Database for today's snapshot
        $snapshot = MenopauseInsightSnapshot::where('user_id', $userId)
            ->where('period', $period)
            ->whereDate('snapshot_date', $todayStr)
            ->first();

        if ($snapshot && !$forceRefresh) {
            $formattedData = $this->formatInsightSnapshot($snapshot);

            // Store in fast in-memory cache for 1 hour
            Cache::put($cacheKey, $formattedData, now()->addHour());

            return response()->json([
                'success' => true,
                'source'  => 'database',
                'data'    => $formattedData,
            ], 200);
        }

        // 3. New Day or Force Refresh -> Fetch from AI API
        $data = $this->fetchAndSaveInsightsFromAi($userId, $period);

        if ($data) {
            Cache::put($cacheKey, $data, now()->addHour());

            return response()->json([
                'success' => true,
                'source'  => 'ai_service',
                'data'    => $data,
            ], 200);
        }

        // 4. Fallback: If AI fails and no snapshot exists for today, try previous DB snapshot
        $previousSnapshot = MenopauseInsightSnapshot::where('user_id', $userId)
            ->where('period', $period)
            ->latest('snapshot_date')
            ->first();

        if ($previousSnapshot) {
            $formattedData = $this->formatInsightSnapshot($previousSnapshot);

            return response()->json([
                'success' => true,
                'source'  => 'database_fallback',
                'message' => 'Serving latest available snapshot.',
                'data'    => $formattedData,
            ], 200);
        }

        // 5. Ultimate Fallback: Generate local default payload if user has no prior history
        $fallbackData = $this->generateInsightLocalFallback($userId, $period);
        $this->persistInsightSnapshot($userId, $period, $fallbackData);

        return response()->json([
            'success' => true,
            'source'  => 'local_fallback',
            'data'    => $fallbackData,
        ], 200);
    }

    /**
     * Get Menopause Clinical Consultation Export.
     * Integrates with AI endpoint: /api/v1/menopause/export
     *
     * GET|POST /api/v1/menopause/export
     */
    public function export(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or user_id is missing.',
            ], 401);
        }

        $userId = (int) $userId;
        $period = (string) ($request->input('period') ?? $request->query('period') ?? '7d');
        $todayStr = today()->toDateString();
        $forceRefresh = $request->boolean('refresh') || $request->boolean('sync');

        // Fast In-Memory Cache Key
        $cacheKey = "menopause_export_{$userId}_{$period}_{$todayStr}";

        // 1. Check in-memory cache if forceRefresh is not requested
        if (!$forceRefresh && Cache::has($cacheKey)) {
            return response()->json([
                'success' => true,
                'source'  => 'cache',
                'data'    => Cache::get($cacheKey),
            ], 200);
        }

        // 2. Check Database for today's snapshot
        $snapshot = MenopauseExportSnapshot::where('user_id', $userId)
            ->where('period', $period)
            ->whereDate('snapshot_date', $todayStr)
            ->first();

        if ($snapshot && !$forceRefresh) {
            $formattedData = $this->formatExportSnapshot($snapshot);

            // Store in fast in-memory cache for 1 hour
            Cache::put($cacheKey, $formattedData, now()->addHour());

            return response()->json([
                'success' => true,
                'source'  => 'database',
                'data'    => $formattedData,
            ], 200);
        }

        // 3. New Day or Force Refresh -> Fetch from AI API
        $data = $this->fetchAndSaveExportFromAi($userId, $period);

        if ($data) {
            Cache::put($cacheKey, $data, now()->addHour());

            return response()->json([
                'success' => true,
                'source'  => 'ai_service',
                'data'    => $data,
            ], 200);
        }

        // 4. Fallback: If AI fails and no snapshot exists for today, try previous DB snapshot
        $previousSnapshot = MenopauseExportSnapshot::where('user_id', $userId)
            ->where('period', $period)
            ->latest('snapshot_date')
            ->first();

        if ($previousSnapshot) {
            $formattedData = $this->formatExportSnapshot($previousSnapshot);

            return response()->json([
                'success' => true,
                'source'  => 'database_fallback',
                'message' => 'Serving latest available snapshot.',
                'data'    => $formattedData,
            ], 200);
        }

        // 5. Ultimate Fallback: Generate local default payload if user has no prior history
        $fallbackData = $this->generateExportLocalFallback($userId, $period);
        $this->persistExportSnapshot($userId, $period, $fallbackData);

        return response()->json([
            'success' => true,
            'source'  => 'local_fallback',
            'data'    => $fallbackData,
        ], 200);
    }

    /**
     * Fetch Symptoms from remote AI service and persist to database.
     */
    protected function fetchAndSaveSymptomsFromAi(int $userId, string $period): ?array
    {
        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
        $url = "{$baseUrl}/api/v1/menopause/symptoms";

        try {
            $response = Http::timeout(8)
                ->connectTimeout(3)
                ->acceptJson()
                ->get($url, [
                    'user_id' => $userId,
                    'period'  => $period,
                ]);

            if ($response->successful() && is_array($response->json())) {
                $payload = $response->json();

                // Save/update snapshot in database
                $snapshot = $this->persistSymptomSnapshot($userId, $period, $payload);

                return $snapshot ? $this->formatSymptomSnapshot($snapshot) : $payload;
            } else {
                Log::warning("AI Menopause Symptoms API returned status {$response->status()}", [
                    'user_id' => $userId,
                    'period'  => $period,
                    'body'    => substr((string) $response->body(), 0, 300),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('AI Menopause Symptoms API connection exception: ' . $e->getMessage(), [
                'user_id' => $userId,
                'period'  => $period,
            ]);
        }

        return null;
    }

    /**
     * Fetch Insights from remote AI service and persist to database.
     */
    protected function fetchAndSaveInsightsFromAi(int $userId, string $period): ?array
    {
        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
        $url = "{$baseUrl}/api/v1/menopause/insights";

        try {
            $response = Http::timeout(8)
                ->connectTimeout(3)
                ->acceptJson()
                ->get($url, [
                    'user_id' => $userId,
                    'period'  => $period,
                ]);

            if ($response->successful() && is_array($response->json())) {
                $payload = $response->json();

                // Save/update snapshot in database
                $snapshot = $this->persistInsightSnapshot($userId, $period, $payload);

                return $snapshot ? $this->formatInsightSnapshot($snapshot) : $payload;
            } else {
                Log::warning("AI Menopause Insights API returned status {$response->status()}", [
                    'user_id' => $userId,
                    'period'  => $period,
                    'body'    => substr((string) $response->body(), 0, 300),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('AI Menopause Insights API connection exception: ' . $e->getMessage(), [
                'user_id' => $userId,
                'period'  => $period,
            ]);
        }

        return null;
    }

    /**
     * Fetch Export from remote AI service and persist to database.
     */
    protected function fetchAndSaveExportFromAi(int $userId, string $period): ?array
    {
        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
        $url = "{$baseUrl}/api/v1/menopause/export";

        try {
            $response = Http::timeout(8)
                ->connectTimeout(3)
                ->acceptJson()
                ->get($url, [
                    'user_id' => $userId,
                    'period'  => $period,
                ]);

            if ($response->successful() && is_array($response->json())) {
                $payload = $response->json();

                // Save/update snapshot in database
                $snapshot = $this->persistExportSnapshot($userId, $period, $payload);

                return $snapshot ? $this->formatExportSnapshot($snapshot) : $payload;
            } else {
                Log::warning("AI Menopause Export API returned status {$response->status()}", [
                    'user_id' => $userId,
                    'period'  => $period,
                    'body'    => substr((string) $response->body(), 0, 300),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('AI Menopause Export API connection exception: ' . $e->getMessage(), [
                'user_id' => $userId,
                'period'  => $period,
            ]);
        }

        return null;
    }

    /**
     * Persist or update menopause symptom snapshot in database.
     */
    protected function persistSymptomSnapshot(int $userId, string $period, array $data): ?MenopauseSymptomSnapshot
    {
        try {
            $todayStr = today()->toDateString();

            return MenopauseSymptomSnapshot::updateOrCreate(
                [
                    'user_id'       => $userId,
                    'snapshot_date' => $todayStr,
                    'period'        => $period,
                ],
                [
                    'transition_stage_tracker' => $data['transition_stage_tracker'] ?? [],
                    'vasomotor_tracker'        => $data['vasomotor_tracker'] ?? [],
                    'gsm_health'               => $data['gsm_health'] ?? [],
                    'period_selected'          => $data['period_selected'] ?? $period,
                    'tabs'                     => $data['tabs'] ?? ['Symptoms'],
                    'journey_active'           => (bool) ($data['journey_active'] ?? true),
                    'message'                  => $data['message'] ?? null,
                    'last_updated_ai'          => $data['last_updated'] ?? now()->toIso8601String(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Error persisting menopause symptom snapshot: ' . $e->getMessage(), [
                'user_id' => $userId,
                'period'  => $period,
            ]);
            return null;
        }
    }

    /**
     * Persist or update menopause insight snapshot in database.
     */
    protected function persistInsightSnapshot(int $userId, string $period, array $data): ?MenopauseInsightSnapshot
    {
        try {
            $todayStr = today()->toDateString();

            return MenopauseInsightSnapshot::updateOrCreate(
                [
                    'user_id'       => $userId,
                    'snapshot_date' => $todayStr,
                    'period'        => $period,
                ],
                [
                    'symptom_matrix'  => $data['symptom_matrix'] ?? [],
                    'period_selected' => $data['period_selected'] ?? $period,
                    'tabs'            => $data['tabs'] ?? ['Insights'],
                    'journey_active'  => (bool) ($data['journey_active'] ?? true),
                    'message'         => $data['message'] ?? null,
                    'last_updated_ai' => $data['last_updated'] ?? now()->toIso8601String(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Error persisting menopause insight snapshot: ' . $e->getMessage(), [
                'user_id' => $userId,
                'period'  => $period,
            ]);
            return null;
        }
    }

    /**
     * Persist or update menopause export snapshot in database.
     */
    protected function persistExportSnapshot(int $userId, string $period, array $data): ?MenopauseExportSnapshot
    {
        try {
            $todayStr = today()->toDateString();

            return MenopauseExportSnapshot::updateOrCreate(
                [
                    'user_id'       => $userId,
                    'snapshot_date' => $todayStr,
                    'period'        => $period,
                ],
                [
                    'clinical_export' => $data['clinical_export'] ?? [],
                    'tabs'            => $data['tabs'] ?? ['Export'],
                    'journey_active'  => (bool) ($data['journey_active'] ?? true),
                    'message'         => $data['message'] ?? null,
                    'last_updated_ai' => $data['last_updated'] ?? now()->toIso8601String(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Error persisting menopause export snapshot: ' . $e->getMessage(), [
                'user_id' => $userId,
                'period'  => $period,
            ]);
            return null;
        }
    }

    /**
     * Format MenopauseSymptomSnapshot model to array matching frontend contract.
     */
    protected function formatSymptomSnapshot(MenopauseSymptomSnapshot $snapshot): array
    {
        return [
            'id'                       => $snapshot->id,
            'user_id'                  => $snapshot->user_id,
            'snapshot_date'            => $snapshot->snapshot_date?->format('Y-m-d'),
            'transition_stage_tracker' => $snapshot->transition_stage_tracker ?? [],
            'vasomotor_tracker'        => $snapshot->vasomotor_tracker ?? [],
            'gsm_health'               => $snapshot->gsm_health ?? [],
            'period_selected'          => $snapshot->period_selected ?? $snapshot->period,
            'tabs'                     => $snapshot->tabs ?? ['Symptoms'],
            'journey_active'           => (bool) $snapshot->journey_active,
            'message'                  => $snapshot->message,
            'last_updated'             => $snapshot->last_updated_ai ?: $snapshot->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Format MenopauseInsightSnapshot model to array matching frontend contract.
     */
    protected function formatInsightSnapshot(MenopauseInsightSnapshot $snapshot): array
    {
        return [
            'id'              => $snapshot->id,
            'user_id'         => $snapshot->user_id,
            'snapshot_date'   => $snapshot->snapshot_date?->format('Y-m-d'),
            'symptom_matrix'  => $snapshot->symptom_matrix ?? [],
            'period_selected' => $snapshot->period_selected ?? $snapshot->period,
            'tabs'            => $snapshot->tabs ?? ['Insights'],
            'journey_active'  => (bool) $snapshot->journey_active,
            'message'         => $snapshot->message,
            'last_updated'    => $snapshot->last_updated_ai ?: $snapshot->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Format MenopauseExportSnapshot model to array matching frontend contract.
     */
    protected function formatExportSnapshot(MenopauseExportSnapshot $snapshot): array
    {
        return [
            'id'              => $snapshot->id,
            'user_id'         => $snapshot->user_id,
            'snapshot_date'   => $snapshot->snapshot_date?->format('Y-m-d'),
            'clinical_export' => $snapshot->clinical_export ?? [],
            'tabs'            => $snapshot->tabs ?? ['Export'],
            'journey_active'  => (bool) $snapshot->journey_active,
            'message'         => $snapshot->message,
            'last_updated'    => $snapshot->last_updated_ai ?: $snapshot->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Generate local default payload for Symptoms fallback.
     */
    protected function generateSymptomLocalFallback(int $userId, string $period): array
    {
        $days = $period === '30d' ? 30 : ($period === '90d' ? 90 : 7);

        return [
            'transition_stage_tracker' => [
                'menopause_stage'          => 'unknown',
                'is_in_perimenopause'      => false,
                'months_since_last_period' => null,
                'last_period_date'         => null,
                'cycle_status'             => 'absent',
            ],
            'vasomotor_tracker'        => [
                'period'                 => $period,
                'start_date'             => today()->subDays($days)->toDateString(),
                'end_date'               => today()->toDateString(),
                'total_events'           => 0,
                'avg_daily_frequency'    => 0,
                'avg_severity'           => 0,
                'hot_flash_events'       => 0,
                'hot_flash_avg_severity' => 0,
                'night_sweat_events'     => 0,
                'night_sweat_avg_severity'=> 0,
                'peak_times'             => [],
                'common_triggers'        => [],
                'severity_distribution'  => [
                    'mild (1-3)'     => 0,
                    'moderate (4-7)' => 0,
                    'severe (8-10)'  => 0,
                ],
                'trend'                  => 'stable',
                'trend_description'      => 'Insufficient data for trend analysis',
                'events'                 => [],
            ],
            'gsm_health'               => [
                'vaginal_dryness'   => ['level' => 'not_reported', 'value' => 0, 'percentage' => 0],
                'urinary_frequency' => ['level' => 'not_reported', 'value' => 0, 'percentage' => 0],
                'pelvic_discomfort' => ['level' => 'not_reported', 'value' => 0, 'percentage' => 0],
                'libido_impact'     => ['level' => 'not_reported', 'value' => 0, 'percentage' => 0],
            ],
            'period_selected'          => $period,
            'tabs'                     => [
                'Symptoms',
            ],
            'journey_active'           => true,
            'message'                  => null,
            'last_updated'             => now()->toIso8601String(),
        ];
    }

    /**
     * Generate local default payload for Insights fallback.
     */
    protected function generateInsightLocalFallback(int $userId, string $period): array
    {
        $days = $period === '30d' ? 30 : ($period === '90d' ? 90 : 7);

        return [
            'symptom_matrix' => [
                'period'                     => $period,
                'start_date'                 => today()->subDays($days)->toDateString(),
                'end_date'                   => today()->toDateString(),
                'entries'                    => [],
                'symptom_frequency'          => [],
                'symptom_severity'           => [],
                'symptom_correlations'       => [],
                'most_common_symptoms'       => [],
                'avg_energy_level_percent'   => 50,
                'avg_sleep_hours'            => 7,
                'avg_mood_stability_percent' => 50,
                'symptom_trends'             => [],
            ],
            'period_selected' => $period,
            'tabs'            => [
                'Insights',
            ],
            'journey_active'  => true,
            'message'         => null,
            'last_updated'    => now()->toIso8601String(),
        ];
    }

    /**
     * Generate local default payload for Export fallback.
     */
    protected function generateExportLocalFallback(int $userId, string $period): array
    {
        return [
            'clinical_export' => [
                'export_date'                     => today()->format('M d, Y'),
                'period_covered'                  => today()->subDays(7)->format('M d') . ' - ' . today()->format('M d, Y'),
                'menopause_stage'                 => 'unknown',
                'months_since_onset'              => null,
                'months_since_last_period'        => null,
                'vasomotor_events_total'          => 0,
                'vasomotor_avg_frequency_per_day' => 0,
                'vasomotor_avg_severity'          => 0,
                'vasomotor_trend'                 => 'stable',
                'vasomotor_primary_triggers'      => [],
                'primary_symptoms'                => [],
                'symptom_severity_breakdown'      => [],
                'avg_sleep_hours'                 => 7,
                'sleep_quality'                   => 'fair',
                'avg_mood_stability'              => 50,
                'weight_change_lbs'               => null,
                'clinical_recommendations'        => [],
                'lifestyle_recommendations'       => [
                    'Maintain consistent sleep schedule (aim for 7-8 hours)',
                    'Regular exercise (150 min/week aerobic + strength training)',
                    'Reduce caffeine and spicy foods if they trigger symptoms',
                    'Practice stress reduction techniques (yoga, meditation, breathing)',
                ],
                'warning_flags'                   => [],
                'suggested_tests'                 => [
                    'FSH (Follicle-Stimulating Hormone)',
                    'Estradiol level',
                    'TSH (Thyroid function)',
                    'Vitamin D',
                    'Iron and ferritin',
                    'Lipid panel',
                ],
                'data_points_collected'           => 0,
                'data_completeness_percent'       => 0,
                'profile_id'                      => 10,
                'journey_id'                      => 4,
                'journey_title'                   => 'Peri / Menopause & Vitality',
            ],
            'tabs'            => [
                'Export',
            ],
            'journey_active'  => true,
            'message'         => null,
            'last_updated'    => now()->toIso8601String(),
        ];
    }
}
