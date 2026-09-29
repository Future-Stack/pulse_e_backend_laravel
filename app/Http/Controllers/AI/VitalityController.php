<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\LifeArcSnapshot;
use App\Models\MobilityStressSnapshot;
use App\Models\PreventativeReminderSnapshot;
use App\Models\User;
use App\Models\VitalitySnapshot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VitalityController extends Controller
{
    /**
     * Get Lifelong Thriving & Vitality Overview.
     * Integrates with AI endpoint: /api/v1/lifelong-thriving/vitality
     *
     * GET /api/v1/lifelong-thriving/vitality
     * GET /api/v1/vitality/overview
     */
    public function overview(Request $request): JsonResponse
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
        $todayStr = today()->toDateString();
        $forceRefresh = $request->boolean('refresh') || $request->boolean('sync');

        // Fast In-Memory Cache Key
        $cacheKey = "vitality_overview_{$userId}_{$todayStr}";

        // 1. Check in-memory cache if forceRefresh is not requested
        if (!$forceRefresh && Cache::has($cacheKey)) {
            return response()->json([
                'success' => true,
                'source'  => 'cache',
                'data'    => Cache::get($cacheKey),
            ], 200);
        }

        // 2. Check Database for today's snapshot
        $snapshot = VitalitySnapshot::where('user_id', $userId)
            ->whereDate('snapshot_date', $todayStr)
            ->first();

        if ($snapshot && !$forceRefresh) {
            $formattedData = $this->formatSnapshot($snapshot);

            // Store in fast in-memory cache for 1 hour
            Cache::put($cacheKey, $formattedData, now()->addHour());

            return response()->json([
                'success' => true,
                'source'  => 'database',
                'data'    => $formattedData,
            ], 200);
        }

        // 3. New Day or Force Refresh -> Fetch from AI API
        $data = $this->fetchAndSaveFromAi($userId);

        if ($data) {
            Cache::put($cacheKey, $data, now()->addHour());

            return response()->json([
                'success' => true,
                'source'  => 'ai_service',
                'data'    => $data,
            ], 200);
        }

        // 4. Fallback: If AI fails and no snapshot exists for today, try previous DB snapshot
        $previousSnapshot = VitalitySnapshot::where('user_id', $userId)
            ->latest('snapshot_date')
            ->first();

        if ($previousSnapshot) {
            $formattedData = $this->formatSnapshot($previousSnapshot);

            return response()->json([
                'success' => true,
                'source'  => 'database_fallback',
                'message' => 'Serving latest available snapshot.',
                'data'    => $formattedData,
            ], 200);
        }

        // 5. Ultimate Fallback: Generate local default payload if user has no prior history
        $fallbackData = $this->generateLocalFallback($userId);
        $this->persistVitalitySnapshot($userId, $fallbackData);

        return response()->json([
            'success' => true,
            'source'  => 'local_fallback',
            'data'    => $fallbackData,
        ], 200);
    }

    /**
     * Get Lifelong Thriving & Life Arc Milestones.
     * Integrates with AI endpoint: /api/v1/lifelong-thriving/life-arc
     *
     * GET /api/v1/lifelong-thriving/life-arc
     * GET /api/v1/life-arc/overview
     */
    public function lifeArc(Request $request): JsonResponse
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
        $todayStr = today()->toDateString();
        $forceRefresh = $request->boolean('refresh') || $request->boolean('sync');

        // Fast In-Memory Cache Key
        $cacheKey = "life_arc_{$userId}_{$todayStr}";

        // 1. Check in-memory cache if forceRefresh is not requested
        if (!$forceRefresh && Cache::has($cacheKey)) {
            return response()->json([
                'success' => true,
                'source'  => 'cache',
                'data'    => Cache::get($cacheKey),
            ], 200);
        }

        // 2. Check Database for today's snapshot
        $snapshot = LifeArcSnapshot::where('user_id', $userId)
            ->whereDate('snapshot_date', $todayStr)
            ->first();

        if ($snapshot && !$forceRefresh) {
            $formattedData = $this->formatLifeArcSnapshot($snapshot);

            // Store in fast in-memory cache for 1 hour
            Cache::put($cacheKey, $formattedData, now()->addHour());

            return response()->json([
                'success' => true,
                'source'  => 'database',
                'data'    => $formattedData,
            ], 200);
        }

        // 3. New Day or Force Refresh -> Fetch from AI API
        $data = $this->fetchAndSaveLifeArcFromAi($userId);

        if ($data) {
            Cache::put($cacheKey, $data, now()->addHour());

            return response()->json([
                'success' => true,
                'source'  => 'ai_service',
                'data'    => $data,
            ], 200);
        }

        // 4. Fallback: If AI fails and no snapshot exists for today, try previous DB snapshot
        $previousSnapshot = LifeArcSnapshot::where('user_id', $userId)
            ->latest('snapshot_date')
            ->first();

        if ($previousSnapshot) {
            $formattedData = $this->formatLifeArcSnapshot($previousSnapshot);

            return response()->json([
                'success' => true,
                'source'  => 'database_fallback',
                'message' => 'Serving latest available snapshot.',
                'data'    => $formattedData,
            ], 200);
        }

        // 5. Ultimate Fallback: Generate local default payload if user has no prior history
        $fallbackData = $this->generateLifeArcLocalFallback($userId);
        $this->persistLifeArcSnapshot($userId, $fallbackData);

        return response()->json([
            'success' => true,
            'source'  => 'local_fallback',
            'data'    => $fallbackData,
        ], 200);
    }

    /**
     * Get Lifelong Thriving & Preventative Reminders.
     * Integrates with AI endpoint: /api/v1/lifelong-thriving/preventative-reminders
     *
     * GET /api/v1/lifelong-thriving/preventative-reminders
     * GET /api/v1/preventative-reminders/overview
     */
    public function preventativeReminders(Request $request): JsonResponse
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
        $todayStr = today()->toDateString();
        $forceRefresh = $request->boolean('refresh') || $request->boolean('sync');

        // Fast In-Memory Cache Key
        $cacheKey = "preventative_reminders_{$userId}_{$todayStr}";

        // 1. Check in-memory cache if forceRefresh is not requested
        if (!$forceRefresh && Cache::has($cacheKey)) {
            return response()->json([
                'success' => true,
                'source'  => 'cache',
                'data'    => Cache::get($cacheKey),
            ], 200);
        }

        // 2. Check Database for today's snapshot
        $snapshot = PreventativeReminderSnapshot::where('user_id', $userId)
            ->whereDate('snapshot_date', $todayStr)
            ->first();

        if ($snapshot && !$forceRefresh) {
            $formattedData = $this->formatPreventativeReminderSnapshot($snapshot);

            // Store in fast in-memory cache for 1 hour
            Cache::put($cacheKey, $formattedData, now()->addHour());

            return response()->json([
                'success' => true,
                'source'  => 'database',
                'data'    => $formattedData,
            ], 200);
        }

        // 3. New Day or Force Refresh -> Fetch from AI API
        $data = $this->fetchAndSavePreventativeRemindersFromAi($userId);

        if ($data) {
            Cache::put($cacheKey, $data, now()->addHour());

            return response()->json([
                'success' => true,
                'source'  => 'ai_service',
                'data'    => $data,
            ], 200);
        }

        // 4. Fallback: If AI fails and no snapshot exists for today, try previous DB snapshot
        $previousSnapshot = PreventativeReminderSnapshot::where('user_id', $userId)
            ->latest('snapshot_date')
            ->first();

        if ($previousSnapshot) {
            $formattedData = $this->formatPreventativeReminderSnapshot($previousSnapshot);

            return response()->json([
                'success' => true,
                'source'  => 'database_fallback',
                'message' => 'Serving latest available snapshot.',
                'data'    => $formattedData,
            ], 200);
        }

        // 5. Ultimate Fallback: Generate local default payload if user has no prior history
        $fallbackData = $this->generatePreventativeReminderLocalFallback($userId);
        $this->persistPreventativeReminderSnapshot($userId, $fallbackData);

        return response()->json([
            'success' => true,
            'source'  => 'local_fallback',
            'data'    => $fallbackData,
        ], 200);
    }

    /**
     * Get Lifelong Thriving & Mobility/Stress Indicators.
     * Integrates with AI endpoint: /api/v1/lifelong-thriving/mobility-stress-indicators
     *
     * GET /api/v1/lifelong-thriving/mobility-stress-indicators
     * GET /api/v1/mobility-stress-indicators/overview
     */
    public function mobilityStressIndicators(Request $request): JsonResponse
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
        $todayStr = today()->toDateString();
        $forceRefresh = $request->boolean('refresh') || $request->boolean('sync');

        // Fast In-Memory Cache Key
        $cacheKey = "mobility_stress_{$userId}_{$todayStr}";

        // 1. Check in-memory cache if forceRefresh is not requested
        if (!$forceRefresh && Cache::has($cacheKey)) {
            return response()->json([
                'success' => true,
                'source'  => 'cache',
                'data'    => Cache::get($cacheKey),
            ], 200);
        }

        // 2. Check Database for today's snapshot
        $snapshot = MobilityStressSnapshot::where('user_id', $userId)
            ->whereDate('snapshot_date', $todayStr)
            ->first();

        if ($snapshot && !$forceRefresh) {
            $formattedData = $this->formatMobilityStressSnapshot($snapshot);

            // Store in fast in-memory cache for 1 hour
            Cache::put($cacheKey, $formattedData, now()->addHour());

            return response()->json([
                'success' => true,
                'source'  => 'database',
                'data'    => $formattedData,
            ], 200);
        }

        // 3. New Day or Force Refresh -> Fetch from AI API
        $data = $this->fetchAndSaveMobilityStressFromAi($userId);

        if ($data) {
            Cache::put($cacheKey, $data, now()->addHour());

            return response()->json([
                'success' => true,
                'source'  => 'ai_service',
                'data'    => $data,
            ], 200);
        }

        // 4. Fallback: If AI fails and no snapshot exists for today, try previous DB snapshot
        $previousSnapshot = MobilityStressSnapshot::where('user_id', $userId)
            ->latest('snapshot_date')
            ->first();

        if ($previousSnapshot) {
            $formattedData = $this->formatMobilityStressSnapshot($previousSnapshot);

            return response()->json([
                'success' => true,
                'source'  => 'database_fallback',
                'message' => 'Serving latest available snapshot.',
                'data'    => $formattedData,
            ], 200);
        }

        // 5. Ultimate Fallback: Generate local default payload if user has no prior history
        $fallbackData = $this->generateMobilityStressLocalFallback($userId);
        $this->persistMobilityStressSnapshot($userId, $fallbackData);

        return response()->json([
            'success' => true,
            'source'  => 'local_fallback',
            'data'    => $fallbackData,
        ], 200);
    }

    /**
     * Fetch data from remote AI service and persist to database.
     */
    protected function fetchAndSaveFromAi(int $userId): ?array
    {
        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
        $url = "{$baseUrl}/api/v1/lifelong-thriving/vitality";

        try {
            $response = Http::timeout(8)
                ->connectTimeout(3)
                ->acceptJson()
                ->get($url, [
                    'user_id'              => $userId,
                    'include_ai_insights' => 'true',
                ]);

            if ($response->successful() && is_array($response->json())) {
                $payload = $response->json();

                // Save/update snapshot in database
                $snapshot = $this->persistVitalitySnapshot($userId, $payload);

                return $snapshot ? $this->formatSnapshot($snapshot) : $payload;
            } else {
                Log::warning("AI Vitality API returned status {$response->status()}", [
                    'user_id' => $userId,
                    'body'    => substr((string) $response->body(), 0, 300),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('AI Vitality API connection exception: ' . $e->getMessage(), [
                'user_id' => $userId,
            ]);
        }

        return null;
    }

    /**
     * Fetch Life Arc data from remote AI service and persist to database.
     */
    protected function fetchAndSaveLifeArcFromAi(int $userId): ?array
    {
        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
        $url = "{$baseUrl}/api/v1/lifelong-thriving/life-arc";

        try {
            $response = Http::timeout(8)
                ->connectTimeout(3)
                ->acceptJson()
                ->get($url, [
                    'user_id'              => $userId,
                    'include_ai_insights' => 'true',
                ]);

            if ($response->successful() && is_array($response->json())) {
                $payload = $response->json();

                // Save/update snapshot in database
                $snapshot = $this->persistLifeArcSnapshot($userId, $payload);

                return $snapshot ? $this->formatLifeArcSnapshot($snapshot) : $payload;
            } else {
                Log::warning("AI Life Arc API returned status {$response->status()}", [
                    'user_id' => $userId,
                    'body'    => substr((string) $response->body(), 0, 300),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('AI Life Arc API connection exception: ' . $e->getMessage(), [
                'user_id' => $userId,
            ]);
        }

        return null;
    }

    /**
     * Fetch Preventative Reminders from remote AI service and persist to database.
     */
    protected function fetchAndSavePreventativeRemindersFromAi(int $userId): ?array
    {
        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
        $url = "{$baseUrl}/api/v1/lifelong-thriving/preventative-reminders";

        try {
            $response = Http::timeout(8)
                ->connectTimeout(3)
                ->acceptJson()
                ->get($url, [
                    'user_id'              => $userId,
                    'include_ai_insights' => 'true',
                ]);

            if ($response->successful() && is_array($response->json())) {
                $payload = $response->json();

                // Save/update snapshot in database
                $snapshot = $this->persistPreventativeReminderSnapshot($userId, $payload);

                return $snapshot ? $this->formatPreventativeReminderSnapshot($snapshot) : $payload;
            } else {
                Log::warning("AI Preventative Reminders API returned status {$response->status()}", [
                    'user_id' => $userId,
                    'body'    => substr((string) $response->body(), 0, 300),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('AI Preventative Reminders API connection exception: ' . $e->getMessage(), [
                'user_id' => $userId,
            ]);
        }

        return null;
    }

    /**
     * Fetch Mobility & Stress Indicators from remote AI service and persist to database.
     */
    protected function fetchAndSaveMobilityStressFromAi(int $userId): ?array
    {
        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
        $url = "{$baseUrl}/api/v1/lifelong-thriving/mobility-stress-indicators";

        try {
            $response = Http::timeout(8)
                ->connectTimeout(3)
                ->acceptJson()
                ->get($url, [
                    'user_id' => $userId,
                ]);

            if ($response->successful() && is_array($response->json())) {
                $payload = $response->json();

                // Save/update snapshot in database
                $snapshot = $this->persistMobilityStressSnapshot($userId, $payload);

                return $snapshot ? $this->formatMobilityStressSnapshot($snapshot) : $payload;
            } else {
                Log::warning("AI Mobility Stress API returned status {$response->status()}", [
                    'user_id' => $userId,
                    'body'    => substr((string) $response->body(), 0, 300),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('AI Mobility Stress API connection exception: ' . $e->getMessage(), [
                'user_id' => $userId,
            ]);
        }

        return null;
    }

    /**
     * Persist or update vitality snapshot in database.
     */
    protected function persistVitalitySnapshot(int $userId, array $data): ?VitalitySnapshot
    {
        try {
            $todayStr = today()->toDateString();

            return VitalitySnapshot::updateOrCreate(
                [
                    'user_id'       => $userId,
                    'snapshot_date' => $todayStr,
                ],
                [
                    'vitality_index'  => (float) ($data['vitality_index'] ?? 60.0),
                    'vitality_level'  => (string) ($data['vitality_level'] ?? 'Moderate'),
                    'personal_best'   => $data['personal_best'] ?? null,
                    'trend_6_years'   => $data['trend_6_years'] ?? [],
                    'dimensions'      => $data['dimensions'] ?? [],
                    'ai_insights'     => $data['ai_insights'] ?? [],
                    'last_updated_ai' => $data['last_updated'] ?? now()->toIso8601String(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Error persisting vitality snapshot: ' . $e->getMessage(), [
                'user_id' => $userId,
            ]);
            return null;
        }
    }

    /**
     * Persist or update life arc snapshot in database.
     */
    protected function persistLifeArcSnapshot(int $userId, array $data): ?LifeArcSnapshot
    {
        try {
            $todayStr = today()->toDateString();

            return LifeArcSnapshot::updateOrCreate(
                [
                    'user_id'       => $userId,
                    'snapshot_date' => $todayStr,
                ],
                [
                    'milestones'       => $data['milestones'] ?? [],
                    'timeline_summary' => $data['timeline_summary'] ?? [],
                    'last_updated_ai'  => $data['last_updated'] ?? now()->toIso8601String(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Error persisting life arc snapshot: ' . $e->getMessage(), [
                'user_id' => $userId,
            ]);
            return null;
        }
    }

    /**
     * Persist or update preventative reminder snapshot in database.
     */
    protected function persistPreventativeReminderSnapshot(int $userId, array $data): ?PreventativeReminderSnapshot
    {
        try {
            $todayStr = today()->toDateString();

            return PreventativeReminderSnapshot::updateOrCreate(
                [
                    'user_id'       => $userId,
                    'snapshot_date' => $todayStr,
                ],
                [
                    'reminders'       => $data['reminders'] ?? [],
                    'summary'         => $data['summary'] ?? [],
                    'last_updated_ai' => $data['last_updated'] ?? now()->toIso8601String(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Error persisting preventative reminder snapshot: ' . $e->getMessage(), [
                'user_id' => $userId,
            ]);
            return null;
        }
    }

    /**
     * Persist or update mobility stress snapshot in database.
     */
    protected function persistMobilityStressSnapshot(int $userId, array $data): ?MobilityStressSnapshot
    {
        try {
            $todayStr = today()->toDateString();

            return MobilityStressSnapshot::updateOrCreate(
                [
                    'user_id'       => $userId,
                    'snapshot_date' => $todayStr,
                ],
                [
                    'indicators'             => $data['indicators'] ?? [],
                    'overall_mobility_score' => isset($data['overall_mobility_score']) ? (float)$data['overall_mobility_score'] : null,
                    'overall_stress_status'  => $data['overall_stress_status'] ?? 'good',
                    'last_updated_ai'        => $data['last_updated'] ?? now()->toIso8601String(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Error persisting mobility stress snapshot: ' . $e->getMessage(), [
                'user_id' => $userId,
            ]);
            return null;
        }
    }

    /**
     * Format VitalitySnapshot model to array matching frontend contract.
     */
    protected function formatSnapshot(VitalitySnapshot $snapshot): array
    {
        return [
            'id'             => $snapshot->id,
            'user_id'        => $snapshot->user_id,
            'snapshot_date'  => $snapshot->snapshot_date?->format('Y-m-d'),
            'vitality_index' => (float) $snapshot->vitality_index,
            'vitality_level' => $snapshot->vitality_level,
            'personal_best'  => $snapshot->personal_best,
            'trend_6_years'  => $snapshot->trend_6_years ?? [],
            'dimensions'     => $snapshot->dimensions ?? [],
            'ai_insights'    => $snapshot->ai_insights ?? [],
            'last_updated'   => $snapshot->last_updated_ai ?: $snapshot->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Format LifeArcSnapshot model to array matching frontend contract.
     */
    protected function formatLifeArcSnapshot(LifeArcSnapshot $snapshot): array
    {
        return [
            'id'               => $snapshot->id,
            'user_id'          => $snapshot->user_id,
            'snapshot_date'    => $snapshot->snapshot_date?->format('Y-m-d'),
            'milestones'       => $snapshot->milestones ?? [],
            'timeline_summary' => $snapshot->timeline_summary ?? [],
            'last_updated'     => $snapshot->last_updated_ai ?: $snapshot->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Format PreventativeReminderSnapshot model to array matching frontend contract.
     */
    protected function formatPreventativeReminderSnapshot(PreventativeReminderSnapshot $snapshot): array
    {
        return [
            'id'            => $snapshot->id,
            'user_id'       => $snapshot->user_id,
            'snapshot_date' => $snapshot->snapshot_date?->format('Y-m-d'),
            'reminders'     => $snapshot->reminders ?? [],
            'summary'       => $snapshot->summary ?? [],
            'last_updated'  => $snapshot->last_updated_ai ?: $snapshot->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Format MobilityStressSnapshot model to array matching frontend contract.
     */
    protected function formatMobilityStressSnapshot(MobilityStressSnapshot $snapshot): array
    {
        return [
            'id'                     => $snapshot->id,
            'user_id'                => $snapshot->user_id,
            'snapshot_date'          => $snapshot->snapshot_date?->format('Y-m-d'),
            'indicators'             => $snapshot->indicators ?? [],
            'overall_mobility_score' => $snapshot->overall_mobility_score !== null ? (float)$snapshot->overall_mobility_score : null,
            'overall_stress_status'  => $snapshot->overall_stress_status,
            'last_updated'           => $snapshot->last_updated_ai ?: $snapshot->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Generate local default payload as ultimate fallback.
     */
    protected function generateLocalFallback(int $userId): array
    {
        return [
            'vitality_index' => 63.2,
            'vitality_level' => 'Moderate',
            'personal_best'  => 'Good foundation — opportunity to enhance wellness',
            'trend_6_years'  => [],
            'dimensions'     => [
                [
                    'name'        => 'Mobility & Strength',
                    'score'       => 70,
                    'status'      => 'strong',
                    'trend'       => 'stable',
                    'last_updated'=> null,
                    'description' => 'Based on activity levels and reported mobility',
                ],
                [
                    'name'        => 'Cardiovascular Health',
                    'score'       => 60,
                    'status'      => 'moderate',
                    'trend'       => 'stable',
                    'last_updated'=> null,
                    'description' => 'Based on energy levels and cardiovascular biomarkers',
                ],
                [
                    'name'        => 'Cognitive Wellness',
                    'score'       => 60,
                    'status'      => 'moderate',
                    'trend'       => 'stable',
                    'last_updated'=> null,
                    'description' => 'Based on focus, brain fog, and mental clarity',
                ],
                [
                    'name'        => 'Sleep Quality',
                    'score'       => 65,
                    'status'      => 'moderate',
                    'trend'       => 'stable',
                    'last_updated'=> null,
                    'description' => 'Based on sleep duration and quality patterns',
                ],
                [
                    'name'        => 'Emotional Wellbeing',
                    'score'       => 60,
                    'status'      => 'moderate',
                    'trend'       => 'stable',
                    'last_updated'=> null,
                    'description' => 'Based on mood patterns and emotional state',
                ],
                [
                    'name'        => 'Metabolic Health',
                    'score'       => 60,
                    'status'      => 'moderate',
                    'trend'       => 'stable',
                    'last_updated'=> null,
                    'description' => 'Based on metabolic biomarkers and lab results',
                ],
                [
                    'name'        => 'Reproductive Health',
                    'score'       => 70,
                    'status'      => 'strong',
                    'trend'       => 'stable',
                    'last_updated'=> null,
                    'description' => 'Based on cycle regularity and hormone balance',
                ],
            ],
            'ai_insights'    => [
                'summary'          => 'Your vitality is currently Moderate. Keep tracking your health—more data will provide deeper insights over time.',
                'strengths'        => [
                    'Mobility & Strength',
                    'Reproductive Health',
                ],
                'areas_to_focus'   => [
                    'Emotional Wellbeing',
                    'Metabolic Health',
                ],
                'recommendations'  => [
                    'Maintain consistent health tracking for pattern detection',
                ],
                'confidence_score' => 70,
            ],
            'last_updated'   => now()->toIso8601String(),
        ];
    }

    /**
     * Generate local default payload for Life Arc fallback.
     */
    protected function generateLifeArcLocalFallback(int $userId): array
    {
        return [
            'milestones' => [
                [
                    'date'           => today()->subDays(20)->toDateString(),
                    'description'    => 'New menstrual cycle began',
                    'health_context' => [
                        'cycle_day' => 1,
                        'phase'     => 'menstrual',
                    ],
                    'icon'         => 'flow',
                    'significance' => 0.6,
                    'title'        => 'Period Started',
                    'type'         => 'cycle_start',
                ],
            ],
            'timeline_summary' => [
                'avg_monthly_milestones' => 4,
                'date_range'             => [
                    'start' => today()->subMonths(6)->toDateString(),
                    'end'   => today()->toDateString(),
                ],
                'major_events'           => 5,
                'total_milestones'       => 24,
            ],
            'last_updated' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate local default payload for Preventative Reminders fallback.
     */
    protected function generatePreventativeReminderLocalFallback(int $userId): array
    {
        return [
            'reminders' => [
                [
                    'id'                   => 1,
                    'type'                 => 'Mammogram',
                    'status'               => 'not_started',
                    'priority'             => 'medium',
                    'last_done'            => null,
                    'due_date'             => today()->toDateString(),
                    'scheduled_date'       => null,
                    'days_overdue'         => null,
                    'days_until_due'       => 0,
                    'days_until_scheduled' => null,
                    'guideline'            => 'Every 1 year(s) for ages 40+',
                    'recommendation'       => 'Schedule your first screening',
                    'status_label'         => 'Not Started',
                    'status_color'         => 'yellow',
                ],
                [
                    'id'                   => 2,
                    'type'                 => 'Pap Smear',
                    'status'               => 'not_started',
                    'priority'             => 'medium',
                    'last_done'            => null,
                    'due_date'             => today()->toDateString(),
                    'scheduled_date'       => null,
                    'days_overdue'         => null,
                    'days_until_due'       => 0,
                    'days_until_scheduled' => null,
                    'guideline'            => 'Every 3 year(s) for ages 21+',
                    'recommendation'       => 'Schedule your first screening',
                    'status_label'         => 'Not Started',
                    'status_color'         => 'yellow',
                ],
            ],
            'summary' => [
                'total_reminders' => 2,
                'not_started'     => 2,
                'overdue'         => 0,
                'due_soon'        => 0,
                'scheduled'       => 0,
                'up_to_date'      => 0,
                'not_applicable'  => 0,
            ],
            'last_updated' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate local default payload for Mobility & Stress Indicators fallback.
     */
    protected function generateMobilityStressLocalFallback(int $userId): array
    {
        return [
            'indicators' => [
                [
                    'area'           => 'Hip Flexibility',
                    'score'          => 70,
                    'status'         => 'good',
                    'last_measured'  => today()->toDateString(),
                    'recommendation' => null,
                ],
                [
                    'area'           => 'Grip Strength',
                    'score'          => 72,
                    'status'         => 'good',
                    'last_measured'  => today()->toDateString(),
                    'recommendation' => null,
                ],
                [
                    'area'           => 'Balance Score',
                    'score'          => 75,
                    'status'         => 'good',
                    'last_measured'  => today()->toDateString(),
                    'recommendation' => null,
                ],
                [
                    'area'           => 'Posture Alignment',
                    'score'          => 73,
                    'status'         => 'good',
                    'last_measured'  => today()->toDateString(),
                    'recommendation' => null,
                ],
            ],
            'overall_mobility_score' => 72.5,
            'overall_stress_status'  => 'good',
            'last_updated'           => now()->toIso8601String(),
        ];
    }
}
