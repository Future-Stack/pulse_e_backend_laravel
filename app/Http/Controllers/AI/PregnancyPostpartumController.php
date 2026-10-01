<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\LifeJourney;
use App\Models\PostpartumRecovery;
use App\Models\PregnancyMilestone;
use App\Models\PregnancyWeeklyGuide;
use App\Models\User;
use App\Models\UserPregnancy;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PregnancyPostpartumController extends Controller
{
    /**
     * Unified Overview for Pregnancy, Postpartum, and Support tabs.
     * Dynamic data synced from AI engine into Database and returned from Database.
     * GET /api/v1/pregnancy-postpartum/overview
     */
    public function overview(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $userId = $user->id;
        $user->loadMissing(['profile.lifeJourneys']);

        $requestedTab = strtolower($request->input('tab') ?? $request->query('tab') ?? '');

        // 1. Sync live intelligence from AI API into Database only for relevant stage
        if ($requestedTab === 'postpartum') {
            $this->syncPostpartumFromAi((int) $userId);
        } elseif ($requestedTab === 'pregnancy') {
            $this->syncPregnancyFromAi((int) $userId);
        } else {
            // When no tab is specified: dynamically detect whether user is postpartum or pregnant
            $existingPregnancy = UserPregnancy::where('user_id', $userId)->latest()->first();
            $existingPostpartum = PostpartumRecovery::where('user_id', $userId)->latest()->first();

            $isKnownPostpartum = ($existingPregnancy && $existingPregnancy->status === 'completed')
                || ($existingPostpartum && (!$existingPregnancy || $existingPregnancy->status !== 'active'));

            if ($isKnownPostpartum) {
                $this->syncPostpartumFromAi((int) $userId);
            } else {
                $syncedPregnancy = $this->syncPregnancyFromAi((int) $userId);
                if (!$syncedPregnancy || $syncedPregnancy->status === 'completed') {
                    $this->syncPostpartumFromAi((int) $userId);
                }
            }
        }

        $latestPregnancy = UserPregnancy::where('user_id', $userId)
            ->latest()
            ->first();

        $postpartumRecord = PostpartumRecovery::where('user_id', $userId)
            ->latest()
            ->first();

        $isMiscarriage = $latestPregnancy && $latestPregnancy->status === 'miscarriage';
        $isActivePregnancy = $latestPregnancy && $latestPregnancy->status === 'active';
        $isPostpartum = !$isActivePregnancy && (
            ($latestPregnancy && $latestPregnancy->status === 'completed')
            || ($postpartumRecord && !$isMiscarriage)
        );

        // Auto-attach Pregnancy & Postpartum to life_journey_profile if user has active pregnancy
        if ($user->profile && $isActivePregnancy) {
            $pregnancyJourney = LifeJourney::where('title', 'like', '%Pregnancy%')->first();
            if ($pregnancyJourney && !$user->profile->lifeJourneys()->where('life_journeys.id', $pregnancyJourney->id)->exists()) {
                $user->profile->lifeJourneys()->attach($pregnancyJourney->id);
                $user->load('profile.lifeJourneys');
            }
        }

        // Determine user actual sub-stage based on life stage records
        $userSubStage = 'pregnancy';
        if ($isMiscarriage) {
            $userSubStage = 'miscarriage';
        } elseif ($isPostpartum) {
            $userSubStage = 'postpartum';
        }

        // Resolve user's life stages from life_journey_profile (many-to-many from life_journeys table)
        $userLifeJourneys = $user->profile?->lifeJourneys ?? collect();
        $matchingJourney = $userLifeJourneys->first(function ($j) {
            return str_contains(strtolower($j->title), 'pregnancy')
                || str_contains(strtolower($j->title), 'postpartum');
        });

        if ($matchingJourney) {
            $lifeStageTitle = $matchingJourney->title;
        } else {
            $dbJourney = LifeJourney::where('title', 'like', '%Pregnancy%')->first();
            $lifeStageTitle = $dbJourney ? $dbJourney->title : 'Pregnancy & Postpartum';
        }

        $userLifeStages = $userLifeJourneys->map(function ($journey) {
            return [
                'id'         => $journey->id,
                'title'      => $journey->title,
                'subtitle'   => $journey->subtitle,
                'icon'       => $journey->icon,
                'is_current' => (bool) (str_contains(strtolower($journey->title), 'pregnancy') || str_contains(strtolower($journey->title), 'postpartum')),
            ];
        })->values();

        // Automatically default to user's actual current stage
        $defaultTab = $userSubStage === 'postpartum' ? 'postpartum' : ($userSubStage === 'miscarriage' ? 'support' : 'pregnancy');
        $tab = strtolower($request->input('tab') ?? $request->query('tab') ?? $defaultTab);

        // Fetch user's pregnancy record from DB or AI sync (only when tab is pregnancy or all)
        $pregnancy = ($latestPregnancy && ($latestPregnancy->status === 'active' || $isMiscarriage))
            ? $latestPregnancy
            : (($tab === 'pregnancy' || $tab === 'all') ? $this->getPregnancyRecord((int) $userId) : null);

        // Fetch postpartum record ONLY if requested tab is postpartum/all or user stage is postpartum (and tab is not pregnancy)
        $postpartum = ($tab === 'postpartum' || $tab === 'all' || ($userSubStage === 'postpartum' && $tab !== 'pregnancy'))
            ? $this->getPostpartumRecord((int) $userId)
            : null;

        // Dynamic subtitle according to active stage and maternal phase
        if ($tab === 'postpartum') {
            $subtitle = $postpartum ? "Week {$postpartum->weeks_since_delivery} · Postpartum recovery" : null;
        } elseif ($tab === 'support' || $isMiscarriage) {
            $subtitle = 'Compassionate care and healing support';
        } else {
            $subtitle = $pregnancy ? "Week {$pregnancy->current_week} of 40 · {$pregnancy->days_to_due_date} days to due date" : null;
        }

        $isPregnant = (bool) ($pregnancy && $pregnancy->status === 'active');

        $data = [
            'life_stage'    => $lifeStageTitle,
            'life_stages'   => $userLifeStages,
            'current_stage' => $userSubStage,
            'active_tab'    => $tab,
            'tabs'          => [
                ['id' => 'pregnancy', 'label' => 'Pregnancy', 'is_active' => ($tab === 'pregnancy')],
                ['id' => 'postpartum', 'label' => 'Postpartum', 'is_active' => ($tab === 'postpartum')],
                ['id' => 'support', 'label' => 'Support', 'is_active' => ($tab === 'support')],
            ],
            'header' => [
                'title'       => $lifeStageTitle,
                'subtitle'    => $subtitle,
                'can_go_back' => true,
            ],
        ];

        if ($isMiscarriage) {
            $data['status'] = 'miscarriage';
            $data['healing_mode_active'] = true;
        }

        if ($tab === 'all') {
            $data['pregnancy']  = $pregnancy ? $this->formatPregnancyData($pregnancy) : null;
            $data['postpartum'] = $postpartum ? $this->formatPostpartumData($postpartum) : null;
            $data['support']    = $this->formatSupportData((int) $userId);
        } elseif ($tab === 'postpartum') {
            $data['postpartum'] = $postpartum ? $this->formatPostpartumData($postpartum) : null;
        } elseif ($tab === 'support') {
            $data['support']    = $this->formatSupportData((int) $userId);
        } else {
            // Default: pregnancy
            $data['pregnancy'] = $pregnancy ? $this->formatPregnancyData($pregnancy) : null;
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ], 200);
    }

    /**
     * Dedicated Pregnancy Summary Endpoint (matches external AI route).
     * GET/POST /api/v1/pregnancy/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $request->merge(['tab' => 'pregnancy']);
        return $this->overview($request);
    }

    /**
     * Dedicated Postpartum Recovery Endpoint (matches external AI route).
     * GET/POST /api/v1/postpartum/recovery
     */
    public function recovery(Request $request): JsonResponse
    {
        $request->merge(['tab' => 'postpartum']);
        return $this->overview($request);
    }

    /**
     * Explicit Sync Endpoint for Pregnancy & Postpartum.
     * POST /api/v1/pregnancy/sync
     */
    public function syncOverview(Request $request): JsonResponse
    {
        return $this->overview($request);
    }

    /**
     * Setup or Update Pregnancy Profile.
     * POST /api/v1/pregnancy/setup
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }
        $userId = $user->id;

        $validated = $request->validate([
            'due_date'                   => 'required|date',
            'last_menstrual_period_date' => 'nullable|date',
            'conception_date'            => 'nullable|date',
        ]);

        $pregnancy = UserPregnancy::updateOrCreate(
            ['user_id' => $userId, 'status' => 'active'],
            [
                'due_date'                   => $validated['due_date'],
                'last_menstrual_period_date' => $validated['last_menstrual_period_date'] ?? null,
                'conception_date'            => $validated['conception_date'] ?? null,
                'ai_data'                    => null,
            ]
        );

        Cache::forget("user_pregnancy_ai_{$userId}");

        // Ensure Pregnancy & Postpartum is attached to user's life_journey_profile
        if ($user->profile) {
            $pregnancyJourney = LifeJourney::where('title', 'like', '%Pregnancy%')->first();
            if ($pregnancyJourney && !$user->profile->lifeJourneys()->where('life_journeys.id', $pregnancyJourney->id)->exists()) {
                $user->profile->lifeJourneys()->attach($pregnancyJourney->id);
            }
        }

        // Recalculate all milestone dates dynamically based on updated due_date
        $this->ensureMilestonesFromDbOrDefaults($pregnancy);

        // Sync fresh AI intelligence if available without overriding user due_date
        $this->syncPregnancyFromAi((int) $userId);
        $pregnancy->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Pregnancy details updated successfully.',
            'data'    => $this->formatPregnancyData($pregnancy),
        ], 200);
    }

    /**
     * Toggle Pregnancy Milestone Checklist item.
     * POST /api/v1/pregnancy/milestones/{id}/toggle
     */
    public function toggleMilestone(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }
        $userId = $user->id;

        $milestone = PregnancyMilestone::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$milestone) {
            return response()->json([
                'success' => false,
                'message' => 'Milestone not found.',
            ], 404);
        }

        $milestone->is_completed = !$milestone->is_completed;
        $milestone->completed_at = $milestone->is_completed ? now() : null;
        $milestone->save();

        return response()->json([
            'success'   => true,
            'message'   => 'Milestone status updated.',
            'milestone' => [
                'id'           => $milestone->id,
                'title'        => $milestone->title,
                'target_week'  => $milestone->target_week,
                'is_completed' => $milestone->is_completed,
                'date_label'   => $milestone->date_label,
            ],
        ], 200);
    }

    /**
     * Miscarriage Modal Trigger: "Yes, I did"
     * POST /api/v1/pregnancy/report-loss
     */
    public function reportLoss(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }
        $userId = $user->id;

        $userWithProfile = User::with('profile.lifeJourneys', 'profile.lifeStage')->find($userId);

        // Verify that user stage is Pregnancy & Postpartum
        if ($userWithProfile && $userWithProfile->profile && $userWithProfile->profile->lifeJourneys && $userWithProfile->profile->lifeJourneys->isNotEmpty()) {
            $isPregnancyJourney = $userWithProfile->profile->lifeJourneys->contains(function ($journey) {
                return str_contains(strtolower($journey->title), 'pregnancy');
            }) || str_contains(strtolower($userWithProfile->profile->lifeStage?->title ?? ''), 'pregnancy');

            if (!$isPregnancyJourney) {
                return response()->json([
                    'success' => false,
                    'message' => 'User stage must be Pregnancy & Postpartum to report pregnancy loss.',
                ], 422);
            }
        }

        // Verify active pregnancy sub-stage
        $pregnancy = UserPregnancy::where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$pregnancy) {
            return response()->json([
                'success' => false,
                'message' => 'No active pregnancy found. User sub-stage must be active pregnancy to report miscarriage.',
            ], 422);
        }

        $pregnancy->status = 'miscarriage';
        $pregnancy->ended_at = now();
        $pregnancy->notes = $request->input('notes', 'Reported via Miscarriage dialog.');
        $pregnancy->save();

        return response()->json([
            'success' => true,
            'message' => 'We are deeply sorry for your loss. We have updated your space for compassionate care and healing.',
            'data'    => [
                'status'              => 'miscarriage',
                'active_tab'          => 'support',
                'healing_mode_active' => true,
                'support_resources'   => $this->formatSupportData((int) $userId),
            ],
        ], 200);
    }

    /**
     * Postpartum Journey Modal Trigger: "Yes, I did"
     * POST /api/v1/pregnancy/complete-journey
     */
    public function completeJourney(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }
        $userId = $user->id;

        $pregnancy = UserPregnancy::where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$pregnancy) {
            return response()->json([
                'success' => false,
                'message' => 'No active pregnancy found to complete journey.',
            ], 422);
        }

        $deliveryDate = $request->input('delivery_date') ?? now()->toDateString();

        $pregnancy->status = 'completed';
        $pregnancy->delivery_date = $deliveryDate;
        $pregnancy->save();

        $postpartum = PostpartumRecovery::updateOrCreate(
            ['user_id' => $userId],
            [
                'pregnancy_id'  => $pregnancy->id,
                'delivery_date' => $deliveryDate,
                'current_week'  => 1,
            ]
        );

        // Fetch fresh postpartum recovery intelligence from AI into DB
        $this->syncPostpartumFromAi((int) $userId);
        $postpartum->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Congratulations on your new arrival! Welcome to your postpartum recovery space.',
            'data'    => [
                'active_tab'  => 'postpartum',
                'postpartum'  => $this->formatPostpartumData($postpartum),
            ],
        ], 200);
    }

    /**
     * Check-in / Update Postpartum recovery metrics & mental health in Database.
     * POST /api/v1/postpartum/checkin
     */
    public function checkinPostpartum(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }
        $userId = $user->id;

        $postpartum = PostpartumRecovery::firstOrCreate(
            ['user_id' => $userId],
            [
                'delivery_date' => now()->subWeeks(6)->toDateString(),
                'current_week'  => 6,
            ]
        );

        $postpartum->fill($request->only([
            'delivery_date',
            'mood_stability',
            'anxiety_level',
            'physical_recovery_percent',
            'hormonal_balance_percent',
            'sleep_quality_percent',
            'sleep_change_diff',
            'energy_levels_percent',
            'screening_due',
            'notes',
        ]));

        if ($request->filled('delivery_date')) {
            $postpartum->current_week = $postpartum->weeks_since_delivery;
        }

        $postpartum->save();

        return response()->json([
            'success' => true,
            'message' => 'Postpartum recovery check-in saved successfully.',
            'data'    => $this->formatPostpartumData($postpartum),
        ], 200);
    }

    /**
     * Dedicated Endpoint for Support Insights.
     * GET /api/v1/support/insights
     */
    public function getSupportInsights(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }
        $userId = $user->id;

        $supportData = $this->fetchSupportInsightsFromAi((int) $userId);

        return response()->json([
            'success' => true,
            'data'    => $supportData,
        ], 200);
    }

    /**
     * Resolve authenticated user from Sanctum Bearer Token or request params.
     */
    private function resolveUser(Request $request): ?User
    {
        $user = auth('sanctum')->user()
            ?? $request->user();

        if (!$user) {
            $userId = $request->input('user_id') ?? $request->query('user_id');
            if ($userId) {
                $user = User::find($userId);
            }
        }

        return $user;
    }

    /**
     * Dedicated Care Communities Endpoint.
     * GET /api/v1/pregnancy/care-communities
     */
    public function getCareCommunities(Request $request): JsonResponse
    {
        $communities = $this->buildCareCommunitiesList();

        return response()->json([
            'success' => true,
            'message' => 'Care communities fetched successfully.',
            'data'    => $communities,
        ], 200);
    }

    // ==========================================
    // AI Synchronization & Database Persistence
    // ==========================================

    /**
     * Fetch Pregnancy Summary from AI and insert/update database records.
     */
    private function syncPregnancyFromAi(int $userId): ?UserPregnancy
    {
        if (!User::where('id', $userId)->exists()) {
            return null;
        }

        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
        $aiUrl = "{$baseUrl}/api/v1/pregnancy/summary";

        try {
            $response = Http::timeout(8)
                ->connectTimeout(4)
                ->acceptJson()
                ->get($aiUrl, ['user_id' => $userId]);

            if ($response->successful() && is_array($response->json())) {
                $ai = $response->json();

                // Cache full AI response for user
                Cache::put("user_pregnancy_ai_{$userId}", $ai, now()->addDays(7));

                if (!empty($ai['is_pregnant']) && $ai['is_pregnant']) {
                    $pregnancy = UserPregnancy::where('user_id', $userId)
                        ->where('status', 'active')
                        ->latest()
                        ->first();

                    $dueDate = $ai['due_date'] ?? null;
                    if (!$dueDate && !empty($ai['days_until_due'])) {
                        $dueDate = Carbon::now()->addDays((int) $ai['days_until_due'])->toDateString();
                    }

                    $currentWeek = (int) ($ai['current_week'] ?? 1);
                    $lmp = Carbon::now()->subWeeks($currentWeek)->toDateString();

                    $pregnancyFields = [
                        'user_id'                    => $userId,
                        'due_date'                   => $dueDate ?? Carbon::now()->addDays(280)->toDateString(),
                        'last_menstrual_period_date' => $lmp,
                        'status'                     => 'active',
                    ];
                    if (Schema::hasColumn('user_pregnancies', 'ai_data')) {
                        $pregnancyFields['ai_data'] = $ai;
                    }

                    if (!$pregnancy) {
                        $pregnancy = UserPregnancy::create($pregnancyFields);
                    } else {
                        if (!$pregnancy->due_date && $dueDate) {
                            $pregnancy->due_date = $dueDate;
                        }
                        if (Schema::hasColumn('user_pregnancies', 'ai_data')) {
                            $pregnancy->ai_data = $ai;
                        }
                        $pregnancy->save();
                    }

                    // 1. Sync & ensure clinical monitoring milestones (with dynamic dates based on user's due date)
                    $this->ensureMilestonesFromDbOrDefaults($pregnancy, $ai['clinical_monitoring'] ?? null);

                    // 2. Sync Weekly Guide Content into pregnancy_weekly_guides table in DB
                    if ($currentWeek > 0) {
                        $babySizeComparison = null;
                        if (!empty($ai['baby_development']) && preg_match('/size of (an? [^,\.\—]+)/i', $ai['baby_development'], $matches)) {
                            $babySizeComparison = trim($matches[1]);
                        }

                        $trimester = $ai['current_trimester'] ?? null;
                        if ($trimester && !str_contains(strtolower($trimester), 'trimester')) {
                            $trimester = ucfirst($trimester) . ' Trimester';
                        }

                        $guideData = array_filter([
                            'trimester'              => $trimester,
                            'baby_size_comparison'   => $babySizeComparison,
                            'baby_development'       => $ai['baby_development'] ?? null,
                            'your_body'              => $ai['your_body'] ?? null,
                            'nutrition_focus'        => $ai['nutrition_focus'] ?? null,
                            'safe_exercise'          => $ai['safe_exercises'] ?? null,
                            'clinical_warning_signs' => $ai['clinical_warning_signs'] ?? null,
                        ]);

                        if (!empty($guideData)) {
                            PregnancyWeeklyGuide::updateOrCreate(
                                ['week_number' => $currentWeek],
                                $guideData
                            );
                        }
                    }

                    return $pregnancy;
                } elseif (($ai['delivery_completed'] ?? false) || ($ai['phase'] ?? '') === 'postpartum') {
                    $pregnancy = UserPregnancy::where('user_id', $userId)
                        ->where('status', 'active')
                        ->latest()
                        ->first();

                    if ($pregnancy) {
                        $pregnancy->status = 'completed';
                        $pregnancy->delivery_date = $ai['delivery_date'] ?? now()->toDateString();
                        $pregnancy->save();
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AI Pregnancy Summary sync exception: ' . $e->getMessage());
        }

        return UserPregnancy::where('user_id', $userId)->where('status', 'active')->latest()->first();
    }

    /**
     * Fetch Postpartum Recovery from AI and insert/update database records.
     */
    private function syncPostpartumFromAi(int $userId): ?PostpartumRecovery
    {
        if (!User::where('id', $userId)->exists()) {
            return null;
        }

        // Guard: Never create or sync postpartum records for an actively pregnant user
        $hasActivePregnancy = UserPregnancy::where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if ($hasActivePregnancy) {
            return null;
        }

        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
        $aiUrl = "{$baseUrl}/api/v1/postpartum/recovery";

        try {
            $response = Http::timeout(8)
                ->connectTimeout(4)
                ->acceptJson()
                ->get($aiUrl, ['user_id' => $userId]);

            if ($response->successful() && is_array($response->json())) {
                $ai = $response->json();

                // Cache full AI response for user
                Cache::put("user_postpartum_ai_{$userId}", $ai, now()->addDays(7));

                $currentWeek = (int) ($ai['postpartum_week'] ?? 1);
                $deliveryDate = $ai['delivery_date'] ?? now()->subWeeks($currentWeek)->toDateString();
                $physical = $ai['physical_health'] ?? $ai['recovery_metrics'] ?? [];
                $mentalUi = $ai['mental_health_ui'] ?? [];
                $mental = $ai['mental_health'] ?? [];

                $moodStability = 'Stable';
                $anxietyLevel = 'Mild';

                if (!empty($mentalUi['metrics']) && is_array($mentalUi['metrics'])) {
                    foreach ($mentalUi['metrics'] as $metric) {
                        $label = strtolower($metric['label'] ?? '');
                        if (str_contains($label, 'mood')) {
                            $moodStability = (string) ($metric['value'] ?? 'Stable');
                        } elseif (str_contains($label, 'anxiety')) {
                            $anxietyLevel = (string) ($metric['value'] ?? 'Mild');
                        }
                    }
                } elseif (isset($mental['mood_stability'])) {
                    $moodStability = is_numeric($mental['mood_stability'])
                        ? ($mental['mood_stability'] >= 50 ? 'Stable' : 'Needs attention')
                        : (string) $mental['mood_stability'];
                    $anxietyLevel = is_numeric($mental['anxiety_level'] ?? null)
                        ? ($mental['anxiety_level'] <= 3 ? 'Mild' : ($mental['anxiety_level'] <= 6 ? 'Moderate' : 'High'))
                        : (string) ($mental['anxiety_level'] ?? 'Mild');
                }

                $pregnancy = UserPregnancy::where('user_id', $userId)
                    ->whereIn('status', ['completed', 'active'])
                    ->latest()
                    ->first();

                if ($pregnancy && $pregnancy->status === 'active') {
                    $pregnancy->status = 'completed';
                    if ($deliveryDate) {
                        $pregnancy->delivery_date = $deliveryDate;
                    }
                    $pregnancy->save();
                }

                $postpartum = PostpartumRecovery::where('user_id', $userId)->latest()->first();

                $updateFields = [
                    'pregnancy_id'              => $pregnancy?->id,
                    'current_week'              => $currentWeek,
                    'physical_recovery_percent' => (int) ($physical['physical_recovery_percent'] ?? ($postpartum?->physical_recovery_percent ?? 0)),
                    'hormonal_balance_percent'  => (int) ($physical['hormonal_balance_percent'] ?? ($postpartum?->hormonal_balance_percent ?? 0)),
                    'sleep_quality_percent'     => (int) ($physical['sleep_quality_percent'] ?? ($postpartum?->sleep_quality_percent ?? 0)),
                    'energy_levels_percent'     => (int) ($physical['energy_level_percent'] ?? ($postpartum?->energy_levels_percent ?? 0)),
                    'screening_name'            => $mentalUi['title'] ?? ($mental['screening_name'] ?? 'Postpartum Wellness Screening'),
                    'screening_due'             => !empty($mentalUi['due']) || !empty($mental['screening_due']) || !empty($mentalUi['title']),
                    'screening_due_text'        => $mentalUi['due_text'] ?? ($mental['screening_due_text'] ?? 'due today'),
                    'mood_stability'            => $moodStability,
                    'anxiety_level'             => $anxietyLevel,
                ];

                if (Schema::hasColumn('postpartum_recoveries', 'ai_data')) {
                    $updateFields['ai_data'] = $ai;
                }

                if ($deliveryDate) {
                    $updateFields['delivery_date'] = $deliveryDate;
                }

                return PostpartumRecovery::updateOrCreate(
                    ['user_id' => $userId],
                    $updateFields
                );
            }
        } catch (\Throwable $e) {
            Log::warning('AI Postpartum Recovery sync exception: ' . $e->getMessage());
        }

        return PostpartumRecovery::where('user_id', $userId)->latest()->first();
    }

    // ==========================================
    // Internal Helper Methods
    // ==========================================

    private function getPregnancyRecord(int $userId): ?UserPregnancy
    {
        $pregnancy = UserPregnancy::where('user_id', $userId)
            ->whereIn('status', ['active', 'miscarriage'])
            ->latest()
            ->first();

        if ($pregnancy) {
            if ($pregnancy->status === 'active') {
                $this->ensureMilestonesFromDbOrDefaults($pregnancy);
            }
            return $pregnancy;
        }

        // Try syncing from AI API if available
        $synced = $this->syncPregnancyFromAi($userId);
        if ($synced) {
            $this->ensureMilestonesFromDbOrDefaults($synced);
            return $synced;
        }

        // No dummy record created! Return null if no active pregnancy exists.
        return null;
    }

    private function ensureMilestonesFromDbOrDefaults(UserPregnancy $pregnancy, ?array $aiClinical = null): void
    {
        // Calculate dynamic dates based on user's actual due date
        $dueDate = Carbon::parse($pregnancy->due_date);
        $conceptionDate = $dueDate->copy()->subWeeks(40);
        $currentWeek = $pregnancy->current_week;

        $milestoneTemplates = [
            ['title' => 'Anatomy Scan', 'target_week' => 20],
            ['title' => 'Glucose Tolerance Test', 'target_week' => 24],
            ['title' => 'Anti-D Injection', 'target_week' => 28],
            ['title' => 'Growth Scan', 'target_week' => 32],
            ['title' => 'GBS Swab + Birth Plan', 'target_week' => 36],
        ];

        if (!empty($aiClinical) && is_array($aiClinical)) {
            foreach ($aiClinical as $cm) {
                $name = $cm['name'] ?? null;
                if (!$name) continue;
                $tw = isset($cm['week']) ? (int) preg_replace('/[^0-9]/', '', (string) $cm['week']) : $currentWeek;
                $exists = collect($milestoneTemplates)->contains(fn($t) => strcasecmp($t['title'], $name) === 0);
                if (!$exists) {
                    $milestoneTemplates[] = ['title' => $name, 'target_week' => $tw ?: $currentWeek];
                }
            }
        }

        foreach ($milestoneTemplates as $t) {
            $targetWeek = $t['target_week'];
            $milestoneDate = $conceptionDate->copy()->addWeeks($targetWeek);
            $dateStr = $milestoneDate->format('M j');

            if ($targetWeek == $currentWeek) {
                $dateLabel = "W{$targetWeek} · {$dateStr} (Today)";
            } else {
                $dateLabel = "W{$targetWeek} · {$dateStr}";
            }

            $existing = PregnancyMilestone::where('pregnancy_id', $pregnancy->id)
                ->where('title', $t['title'])
                ->first();

            if (!$existing) {
                PregnancyMilestone::create([
                    'pregnancy_id'   => $pregnancy->id,
                    'user_id'        => $pregnancy->user_id,
                    'title'          => $t['title'],
                    'target_week'    => $targetWeek,
                    'date_label'     => $dateLabel,
                    'scheduled_date' => $milestoneDate->toDateString(),
                    'is_completed'   => ($targetWeek <= $currentWeek),
                    'completed_at'   => ($targetWeek <= $currentWeek) ? now() : null,
                ]);
            } else {
                $existing->target_week = $targetWeek;
                $existing->date_label = $dateLabel;
                $existing->scheduled_date = $milestoneDate->toDateString();
                if ($targetWeek <= $currentWeek && !$existing->is_completed) {
                    $existing->is_completed = true;
                    $existing->completed_at = now();
                }
                $existing->save();
            }
        }
    }

    private function getPostpartumRecord(int $userId): ?PostpartumRecovery
    {
        // Guard: A user with an active pregnancy is pregnant, NOT in postpartum!
        $hasActivePregnancy = UserPregnancy::where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if ($hasActivePregnancy) {
            return null;
        }

        $postpartum = PostpartumRecovery::where('user_id', $userId)->latest()->first();
        if ($postpartum) {
            return $postpartum;
        }

        // Try syncing from AI API only if user has no active pregnancy
        $synced = $this->syncPostpartumFromAi($userId);
        if ($synced) {
            return $synced;
        }

        // No dummy record created! Return null if user has no postpartum record.
        return null;
    }

    /**
     * Fully Dynamic Maternal Guide Engine.
     * Content is sourced exclusively from AI API responses or DB weekly guides synced from AI.
     * No static hardcoded weekly data or fallback dummy arrays are used.
     */
    private function getWeeklyMaternalGuide(int $week, ?array $aiData = null): array
    {
        $week = max(1, min(40, $week));

        if ($aiData && !empty($aiData['current_trimester']) && (!isset($aiData['current_week']) || (int) $aiData['current_week'] === $week)) {
            $trimester = $aiData['current_trimester'];
        } elseif ($week <= 12) {
            $trimester = 'First';
        } elseif ($week <= 27) {
            $trimester = 'Second';
        } else {
            $trimester = 'Third';
        }
        if (!str_contains(strtolower($trimester), 'trimester')) {
            $trimester = ucfirst($trimester) . ' Trimester';
        }

        // Check if a PregnancyWeeklyGuide exists in DB (synced from AI)
        $dbGuide = null;
        if (empty($aiData['baby_development'])) {
            $dbGuide = PregnancyWeeklyGuide::where('week_number', $week)->first();
        }

        $babyDev = $aiData['baby_development'] ?? $dbGuide?->baby_development ?? null;
        $yourBody = $aiData['your_body'] ?? $dbGuide?->your_body ?? null;
        $nutrition = $aiData['nutrition_focus'] ?? $dbGuide?->nutrition_focus ?? null;
        $exercise = $aiData['safe_exercises'] ?? $aiData['safe_exercise'] ?? $dbGuide?->safe_exercise ?? null;
        $warningSigns = $aiData['clinical_warning_signs'] ?? $dbGuide?->clinical_warning_signs ?? null;

        // Extract baby size comparison dynamically
        $babyComparison = $aiData['baby_size_comparison'] ?? $dbGuide?->baby_size_comparison ?? null;
        if (empty($babyComparison) && !empty($babyDev)) {
            if (preg_match('/size of (an? [^,\.\—\n]+?)(?: and|\.|\,|—|\n|$)/i', $babyDev, $m)) {
                $babyComparison = trim($m[1]);
            }
        }

        $approxSize = $aiData['approx_size_text'] ?? null;
        $babySizeText = $babyComparison ? "Baby is the size of {$babyComparison}" . ($approxSize ? " — {$approxSize}" : "") : null;

        return [
            'week'                     => $week,
            'trimester'                => $trimester,
            'baby_size_comparison'     => $babyComparison,
            'approx_size_text'         => $approxSize,
            'baby_size_text'           => $babySizeText,
            'baby_development'         => $babyDev,
            'baby_development_summary' => $this->extractFirstSentence($babyDev),
            'your_body'                => $yourBody,
            'your_body_summary'        => $this->extractFirstSentence($yourBody),
            'nutrition_focus'          => $nutrition,
            'nutrition_focus_summary'  => $this->extractFirstSentence($nutrition),
            'safe_exercise'            => $exercise,
            'safe_exercise_summary'    => $this->extractFirstSentence($exercise),
            'clinical_warning_signs'   => $warningSigns,
        ];
    }

    /**
     * Dynamic sentence extractor for clean educational summary cards.
     */
    private function extractFirstSentence(?string $text): ?string
    {
        if (empty($text)) {
            return null;
        }
        $clean = trim(strip_tags($text));
        $parts = preg_split('/(?<=[.?!])\s+/', $clean, 2);
        return !empty($parts[0]) ? trim($parts[0]) : $clean;
    }

    private function formatPregnancyData(UserPregnancy $pregnancy): array
    {
        $week = (int) $pregnancy->current_week;
        $totalWeeks = 40;

        // Check if cached AI data exists
        $aiData = $pregnancy->ai_data ?? Cache::get("user_pregnancy_ai_{$pregnancy->user_id}", []);

        $maternalGuide = $this->getWeeklyMaternalGuide($week, $aiData);

        // Baby size & comparison
        $babyComparison = $maternalGuide['baby_size_comparison'];
        $approxSize = $maternalGuide['approx_size_text'];
        $babySizeText = $maternalGuide['baby_size_text'];

        // Trimester formatting
        $trimester = $maternalGuide['trimester'];

        // Due date & days
        $dueDate = Carbon::parse($pregnancy->due_date);
        $dueDateFormatted = $dueDate->format('F j, Y');
        $daysUntilDue = $pregnancy->days_to_due_date;
        $progressPercentage = min(100, (int) round(($week / $totalWeeks) * 100));

        // Educational cards: Short summary + Full AI/Maternal detail
        $babyDevFull = $maternalGuide['baby_development'];
        $yourBodyFull = $maternalGuide['your_body'];
        $nutritionFull = $maternalGuide['nutrition_focus'];
        $exerciseFull = $maternalGuide['safe_exercise'];

        $babyDevSummary = $maternalGuide['baby_development_summary'];
        $yourBodySummary = $maternalGuide['your_body_summary'];
        $nutritionSummary = $maternalGuide['nutrition_focus_summary'];
        $exerciseSummary = $maternalGuide['safe_exercise_summary'];

        // Checklist / Milestones
        $this->ensureMilestonesFromDbOrDefaults($pregnancy, $aiData['clinical_monitoring'] ?? null);
        $milestones = PregnancyMilestone::where('pregnancy_id', $pregnancy->id)
            ->orderBy('target_week', 'asc')
            ->get()
            ->map(function ($m) use ($week) {
                $isCurrent = ($m->target_week == $week);
                $dateStr = $m->scheduled_date ? Carbon::parse($m->scheduled_date)->format('M j') : 'TBD';
                $displayDate = $isCurrent ? "{$dateStr} (Today)" : $dateStr;
                $dateLabel = "W{$m->target_week} · {$displayDate}";

                return [
                    'id'           => $m->id,
                    'title'        => $m->title,
                    'name'         => $m->title,
                    'target_week'  => $m->target_week,
                    'week'         => "W{$m->target_week}",
                    'week_label'   => "W{$m->target_week}",
                    'date'         => $displayDate,
                    'date_label'   => $dateLabel,
                    'is_completed' => (bool) $m->is_completed,
                    'is_current'   => $isCurrent,
                    'status'       => $m->is_completed ? 'completed' : ($isCurrent ? 'current' : 'upcoming'),
                ];
            });

        // Clinical monitoring list matching AI API
        $clinicalMonitoring = !empty($aiData['clinical_monitoring']) && is_array($aiData['clinical_monitoring'])
            ? $aiData['clinical_monitoring']
            : $milestones->map(fn($m) => [
                'name' => $m['title'],
                'week' => "W{$m['target_week']}",
                'date' => "Week {$m['target_week']}",
            ])->values()->all();

        // Clinical warning signs matching AI API
        $warningSignsStr = !empty($aiData['clinical_warning_signs'])
            ? (is_string($aiData['clinical_warning_signs']) ? $aiData['clinical_warning_signs'] : json_encode($aiData['clinical_warning_signs']))
            : ($maternalGuide['clinical_warning_signs'] ?? null);

        $alerts = isset($aiData['alerts']) && is_array($aiData['alerts'])
            ? $aiData['alerts']
            : [];

        return [
            // Exact AI Response Keys (Matching https://ai.fightthenumber.com/api/v1/pregnancy/summary)
            'is_pregnant'             => true,
            'current_week'            => $week,
            'current_trimester'       => (!empty($aiData['current_trimester']) && (!isset($aiData['current_week']) || (int) $aiData['current_week'] === $week)) ? $aiData['current_trimester'] : ($week <= 12 ? 'First' : ($week <= 27 ? 'Second' : 'Third')),
            'due_date'                => $dueDate->toDateString(),
            'days_until_due'          => $daysUntilDue,
            'days_remaining'          => $daysUntilDue,
            'last_prenatal_visit'     => $aiData['last_prenatal_visit'] ?? null,
            'next_appointment'        => $aiData['next_appointment'] ?? null,
            'health_status'           => $aiData['health_status'] ?? 'good',
            'alerts'                  => $alerts,
            'baby_development'        => $babyDevFull,
            'your_body'               => $yourBodyFull,
            'nutrition_focus'         => $nutritionFull,
            'safe_exercises'          => $exerciseFull,
            'safe_exercise'           => $exerciseFull,
            'clinical_monitoring'     => $clinicalMonitoring,
            'clinical_warning_signs'  => $warningSignsStr,
            'pregnancy_status'        => $aiData['pregnancy_status'] ?? 'active_pregnancy',
            'requires_confirmation'   => (bool) ($aiData['requires_confirmation'] ?? false),
            'confirmation_needed_for' => $aiData['confirmation_needed_for'] ?? null,
            'confirmation_message'    => $aiData['confirmation_message'] ?? null,
            'phase'                   => $aiData['phase'] ?? 'pregnancy',
            'profile_id'              => $aiData['profile_id'] ?? ($pregnancy->user?->profile?->id ?? null),
            'journey_id'              => $aiData['journey_id'] ?? 5,
            'journey_title'           => 'Pregnancy & Postpartum',
            'pregnancy_id'            => $pregnancy->id,

            // Gauge / Trimester Card matching UI
            'card' => [
                'current_week'         => $week,
                'total_weeks'          => $totalWeeks,
                'week_label'           => "W{$week}",
                'week_sub_label'       => "of {$totalWeeks}",
                'progress_percentage'  => $progressPercentage,
                'trimester'            => $trimester,
                'baby_size_comparison' => $babyComparison,
                'baby_size_text'       => $babySizeText,
                'due_date'             => $dueDate->toDateString(),
                'due_date_display'     => "Due date: {$dueDateFormatted}",
                'days_until_due'       => $daysUntilDue,
                'week_circle'          => [
                    'current'    => $week,
                    'total'      => $totalWeeks,
                    'label'      => "W{$week}",
                    'sub_label'  => "of {$totalWeeks}",
                    'percentage' => $progressPercentage,
                ],
            ],

            // Action Buttons matching UI
            'action_buttons' => [
                [
                    'id'    => 'pregnancy_loss',
                    'label' => 'Pregnancy Loss',
                    'theme' => 'purple_filled',
                    'modal' => [
                        'title'               => 'Have you experience a miscarriage?',
                        'description'         => "We're so sorry if you did. We can update your space to support your healing journey.",
                        'confirm_button_text' => 'Yes, I did',
                        'cancel_button_text'  => 'No, continue as normal',
                        'endpoint'            => '/api/v1/pregnancy/report-loss',
                    ],
                ],
                [
                    'id'    => 'postpartum_journey',
                    'label' => 'Postpartum Jouney',
                    'theme' => 'purple_outline',
                    'modal' => [
                        'title'               => 'Have you recently completed your pregnancy?',
                        'description'         => 'Congratulations! We can update your journey to support you through the postpartum phase.',
                        'confirm_button_text' => 'Yes, I did',
                        'cancel_button_text'  => 'No, continue as normal',
                        'endpoint'            => '/api/v1/pregnancy/complete-journey',
                    ],
                ],
            ],

            // Section 1: WEEK MILESTONES (Educational Cards matching UI)
            'week_milestones' => [
                'section_title' => "WEEK {$week} MILESTONES",
                'items' => [
                    [
                        'id'          => 'baby_development',
                        'title'       => 'Baby Development',
                        'icon_emoji'  => '👶',
                        'description' => $babyDevSummary,
                        'full_text'   => $babyDevFull,
                    ],
                    [
                        'id'          => 'your_body',
                        'title'       => 'Your Body',
                        'icon_emoji'  => '💙',
                        'description' => $yourBodySummary,
                        'full_text'   => $yourBodyFull,
                    ],
                    [
                        'id'          => 'nutrition_focus',
                        'title'       => 'Nutrition Focus',
                        'icon_emoji'  => '🥦',
                        'description' => $nutritionSummary,
                        'full_text'   => $nutritionFull,
                    ],
                    [
                        'id'          => 'safe_exercise',
                        'title'       => 'Safe Exercise',
                        'icon_emoji'  => '🏃‍♀️',
                        'description' => $exerciseSummary,
                        'full_text'   => $exerciseFull,
                    ],
                ],
                'baby_development' => [
                    'id'          => 'baby_development',
                    'title'       => 'Baby Development',
                    'icon_emoji'  => '👶',
                    'desc'        => $babyDevSummary,
                    'description' => $babyDevSummary,
                    'summary'     => $babyDevSummary,
                    'full_text'   => $babyDevFull,
                ],
                'your_body' => [
                    'id'          => 'your_body',
                    'title'       => 'Your Body',
                    'icon_emoji'  => '💙',
                    'desc'        => $yourBodySummary,
                    'description' => $yourBodySummary,
                    'summary'     => $yourBodySummary,
                    'full_text'   => $yourBodyFull,
                ],
                'nutrition_focus' => [
                    'id'          => 'nutrition_focus',
                    'title'       => 'Nutrition Focus',
                    'icon_emoji'  => '🥦',
                    'desc'        => $nutritionSummary,
                    'description' => $nutritionSummary,
                    'summary'     => $nutritionSummary,
                    'full_text'   => $nutritionFull,
                ],
                'safe_exercise' => [
                    'id'          => 'safe_exercise',
                    'title'       => 'Safe Exercise',
                    'icon_emoji'  => '🏃‍♀️',
                    'desc'        => $exerciseSummary,
                    'description' => $exerciseSummary,
                    'summary'     => $exerciseSummary,
                    'full_text'   => $exerciseFull,
                ],
            ],

            // Section 2: WEEK MILESTONES (Clinical Checklist Timeline matching UI)
            'clinical_checklist' => [
                'section_title' => "WEEK {$week} MILESTONES",
                'items'         => $milestones,
            ],

            // Warning signs matching UI
            'warning_signs' => [
                'title'       => 'Clinical Warning Signs',
                'description' => "Seek immediate care for: severe headache, vision changes, sudden swelling, decreased fetal movement, or vaginal bleeding.",
                'full_text'   => $warningSignsStr,
            ],
        ];
    }

    private function formatPostpartumData(PostpartumRecovery $postpartum): array
    {
        $userId = $postpartum->user_id;
        $aiData = $postpartum->ai_data ?? Cache::get("user_postpartum_ai_{$userId}", []);

        $currentWeek = isset($aiData['postpartum_week']) ? (int) $aiData['postpartum_week'] : $postpartum->weeks_since_delivery;
        $daysPostpartum = isset($aiData['days_postpartum']) ? (int) $aiData['days_postpartum'] : $postpartum->days_postpartum;
        $deliveryDate = $aiData['delivery_date'] ?? ($postpartum->delivery_date ? $postpartum->delivery_date->toDateString() : null);

        $physicalHealth = $aiData['physical_health'] ?? [
            'physical_recovery_percent' => (int) $postpartum->physical_recovery_percent,
            'bleeding_level'            => null,
            'incision_healing'          => null,
            'pelvic_floor_status'       => 'healing',
            'hormonal_balance_percent'  => (int) $postpartum->hormonal_balance_percent,
            'energy_level_percent'      => (int) $postpartum->energy_levels_percent,
            'sleep_quality_percent'     => (int) $postpartum->sleep_quality_percent,
        ];

        $mentalHealth = $aiData['mental_health'] ?? [
            'mood_stability'       => 60,
            'anxiety_level'        => 5,
            'depression_screening' => 'low_risk',
            'last_mood_entry'      => null,
            'mood_trend'           => 'stable',
            'supportive_resources' => [
                'Postpartum Support Group',
                'Mental Health Hotline',
            ],
        ];

        $mentalHealthUi = $aiData['mental_health_ui'] ?? [
            'screening_type' => 'mental_health_check_in',
            'title'          => $postpartum->screening_name ?? 'Postpartum Wellness Screening',
            'week'           => $currentWeek,
            'risk_level'     => 'moderate',
            'trend'          => 'new',
            'metrics'        => [
                [
                    'label'       => 'Mood stability',
                    'value'       => $postpartum->mood_stability ?? 'Stable',
                    'score'       => 60,
                    'trend_arrow' => '→',
                ],
                [
                    'label'   => 'Anxiety levels',
                    'value'   => $postpartum->anxiety_level ?? 'Mild',
                    'score'   => 5,
                    'warning' => false,
                ],
                [
                    'label'          => 'Depression risk',
                    'value'          => 'low_risk',
                    'risk_increased' => false,
                ],
            ],
        ];

        $physicalRecoveryPercent = (int) ($physicalHealth['physical_recovery_percent'] ?? $postpartum->physical_recovery_percent);
        $hormonalBalancePercent = (int) ($physicalHealth['hormonal_balance_percent'] ?? $postpartum->hormonal_balance_percent);
        $sleepQualityPercent = (int) ($physicalHealth['sleep_quality_percent'] ?? $postpartum->sleep_quality_percent);
        $energyLevelPercent = (int) ($physicalHealth['energy_level_percent'] ?? $postpartum->energy_levels_percent);

        $metricsList = [
            [
                'id'              => 'physical_recovery',
                'title'           => 'Physical Recovery',
                'percentage'      => $physicalRecoveryPercent,
                'percentage_text' => "{$physicalRecoveryPercent}%",
                'icon'            => 'muscle',
                'icon_emoji'      => '💪',
            ],
            [
                'id'              => 'hormonal_balance',
                'title'           => 'Hormonal Balance',
                'percentage'      => $hormonalBalancePercent,
                'percentage_text' => "{$hormonalBalancePercent}%",
                'icon'            => 'scale',
                'icon_emoji'      => '⚖️',
            ],
            [
                'id'              => 'sleep_quality',
                'title'           => 'Sleep Quality',
                'percentage'      => $sleepQualityPercent,
                'percentage_text' => "{$sleepQualityPercent}%",
                'change_diff'     => $postpartum->sleep_change_diff ? "+{$postpartum->sleep_change_diff}" : null,
                'badge'           => $postpartum->sleep_change_diff ? "+{$postpartum->sleep_change_diff}" : null,
                'icon'            => 'sleep',
                'icon_emoji'      => '😴',
            ],
            [
                'id'              => 'energy_levels',
                'title'           => 'Energy Levels',
                'percentage'      => $energyLevelPercent,
                'percentage_text' => "{$energyLevelPercent}%",
                'icon'            => 'lightning',
                'icon_emoji'      => '⚡',
            ],
        ];

        return [
            // Exact AI Response Keys (Matching https://ai.fightthenumber.com/api/v1/postpartum/recovery)
            'phase'            => $aiData['phase'] ?? 'postpartum',
            'profile_id'       => $aiData['profile_id'] ?? ($postpartum->user?->profile?->id ?? null),
            'journey_id'       => $aiData['journey_id'] ?? 5,
            'journey_title'    => $aiData['journey_title'] ?? 'Pregnancy & Postpartum',
            'delivery_date'    => $deliveryDate,
            'days_postpartum'  => $daysPostpartum,
            'postpartum_week'  => $currentWeek,
            'recovery_status'  => $aiData['recovery_status'] ?? 'early',
            'delivery_method'  => $aiData['delivery_method'] ?? 'vaginal',
            'physical_health'  => $physicalHealth,
            'mental_health'    => $mentalHealth,
            'mental_health_ui' => $mentalHealthUi,

            // UI Cards matching existing frontend expectations
            'card' => [
                'tag'        => 'Recovery Progress',
                'week_title' => "Week {$currentWeek}",
                'subtitle'   => "Postpartum recovery — you're doing amazing 💙",
            ],
            'banner' => [
                'tag'       => 'Recovery Progress',
                'week'      => "Week {$currentWeek}",
                'subtitle'  => "Postpartum recovery — you're doing amazing 💙",
            ],
            'recovery_metrics' => [
                'section_title' => 'RECOVERY METRICS',
                'items' => $metricsList,
                'physical_recovery' => [
                    'title'           => 'Physical Recovery',
                    'percentage'      => $physicalRecoveryPercent,
                    'percentage_text' => "{$physicalRecoveryPercent}%",
                    'icon'            => 'muscle',
                    'icon_emoji'      => '💪',
                ],
                'hormonal_balance' => [
                    'title'           => 'Hormonal Balance',
                    'percentage'      => $hormonalBalancePercent,
                    'percentage_text' => "{$hormonalBalancePercent}%",
                    'icon'            => 'scale',
                    'icon_emoji'      => '⚖️',
                ],
                'sleep_quality' => [
                    'title'           => 'Sleep Quality',
                    'percentage'      => $sleepQualityPercent,
                    'percentage_text' => "{$sleepQualityPercent}%",
                    'change_diff'     => $postpartum->sleep_change_diff ? "+{$postpartum->sleep_change_diff}" : null,
                    'badge'           => $postpartum->sleep_change_diff ? "+{$postpartum->sleep_change_diff}" : null,
                    'icon'            => 'sleep',
                    'icon_emoji'      => '😴',
                ],
                'energy_levels' => [
                    'title'           => 'Energy Levels',
                    'percentage'      => $energyLevelPercent,
                    'percentage_text' => "{$energyLevelPercent}%",
                    'icon'            => 'lightning',
                    'icon_emoji'      => '⚡',
                ],
            ],
            'mental_health_checkin' => [
                'title'              => $mentalHealthUi['title'] ?? 'Mental Health Check-In',
                'card_title'         => $mentalHealthUi['title'] ?? 'Postpartum Wellness Screening',
                'screening_name'     => $mentalHealthUi['title'] ?? ($postpartum->screening_name ?? 'Postpartum Wellness Screening'),
                'screening_scale'    => 'Edinburgh Postnatal Depression Scale',
                'screening_subtitle' => ($mentalHealthUi['title'] ?? 'Postpartum Wellness Screening') . ' — due today',
                'screening_note'     => ($mentalHealthUi['title'] ?? 'Postpartum Wellness Screening') . ' — due today',
                'due'                => (bool) ($postpartum->screening_due ?? true),
                'mood_stability'     => $postpartum->mood_stability ?? 'Stable',
                'anxiety_levels'     => $postpartum->anxiety_level ?? 'Mild',
                'metrics'            => $mentalHealthUi['metrics'] ?? [
                    [
                        'label' => 'Mood stability',
                        'value' => $postpartum->mood_stability ?? 'Stable',
                    ],
                    [
                        'label' => 'Anxiety levels',
                        'value' => $postpartum->anxiety_level ?? 'Mild',
                    ],
                ],
            ],
        ];
    }

    private function formatSupportData(int $userId): array
    {
        $aiData = $this->fetchSupportInsightsFromAi($userId);

        $insights = $aiData['insights'] ?? [];
        $firstInsight = $insights[0] ?? null;

        $title = $firstInsight['title'] ?? 'Loss Support';
        $description = $firstInsight['content']
            ?? "We hold space for all pregnancy journeys. If you've experienced a loss, compassionate resources and personalized logging modes are available.";

        return [
            'phase'          => $aiData['phase'] ?? 'support',
            'loss_support'   => [
                'title'       => $title,
                'description' => $description,
            ],
            'insights'       => $insights,
            'care_community' => $this->buildCareCommunitiesList(),
        ];
    }

    private function buildCareCommunitiesList(): array
    {
        $groups = [
            [
                'id'           => 1,
                'title'        => 'Prenatal Support Group',
                'tag'          => 'prenatal',
                'aliases'      => ['prenatal', 'pregnancy', 'prenatal_support'],
                'icon'         => 'group_flower',
                'description'  => 'Connect with expecting mothers on the pregnancy journey.',
                'base_members' => 2847,
            ],
            [
                'id'           => 2,
                'title'        => 'New Parent Circle',
                'tag'          => 'new_parent',
                'aliases'      => ['new_parent', 'postpartum', 'new_parents'],
                'icon'         => 'group_baby',
                'description'  => 'Support, advice, and shared experiences for the fourth trimester.',
                'base_members' => 5124,
            ],
            [
                'id'           => 3,
                'title'        => 'Loss & Healing Space',
                'tag'          => 'loss_healing',
                'aliases'      => ['loss_healing', 'loss', 'miscarriage', 'healing'],
                'icon'         => 'group_dove',
                'description'  => 'A gentle, safe haven for healing after pregnancy loss.',
                'base_members' => 892,
            ],
            [
                'id'           => 4,
                'title'        => 'Birth Trauma Support',
                'tag'          => 'birth_trauma',
                'aliases'      => ['birth_trauma', 'trauma', 'birth_recovery'],
                'icon'         => 'group_heart',
                'description'  => 'Compassionate resources for navigating difficult birth experiences.',
                'base_members' => 1203,
            ],
        ];

        return array_map(function ($group) {
            $tags = $group['aliases'];

            $postsQuery = CommunityPost::where('is_approved', true)
                ->where(function ($q) use ($tags) {
                    foreach ($tags as $t) {
                        $q->orWhereJsonContains('tags', $t)
                          ->orWhere('tags', 'like', "%\"{$t}\"%");
                    }
                });

            $postsCount = (clone $postsQuery)->count();

            // Total unique active participants (post authors + commenters)
            $postIds = (clone $postsQuery)->pluck('id');
            $postAuthors = CommunityPost::whereIn('id', $postIds)->pluck('user_id');
            $commenters = CommunityComment::whereIn('post_id', $postIds)->pluck('user_id');
            $uniqueUsersCount = $postAuthors->merge($commenters)->unique()->filter()->count();

            $totalMembers = $group['base_members'] + $uniqueUsersCount;
            $membersCountText = number_format($totalMembers) . ' members';

            $latestPost = (clone $postsQuery)->latest('posted_at')->first(['id', 'title', 'content', 'posted_at']);

            return [
                'id'            => $group['id'],
                'title'         => $group['title'],
                'tag'           => $group['tag'],
                'icon'          => $group['icon'],
                'description'   => $group['description'],
                'members_count' => $membersCountText,
                'total_members' => $totalMembers,
                'posts_count'   => $postsCount,
                'latest_post'   => $latestPost ? [
                    'id'        => $latestPost->id,
                    'title'     => $latestPost->title,
                    'snippet'   => Str::limit(strip_tags($latestPost->content), 80),
                    'posted_at' => $latestPost->posted_at?->toIso8601String(),
                ] : null,
                'endpoint'      => "/api/community/posts?tag={$group['tag']}",
            ];
        }, $groups);
    }

    private function fetchSupportInsightsFromAi(int $userId): array
    {
        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
        $aiUrl = "{$baseUrl}/api/v1/support/insights";

        try {
            $response = Http::timeout(8)
                ->connectTimeout(4)
                ->acceptJson()
                ->get($aiUrl, [
                    'user_id' => $userId,
                ]);

            if ($response->successful() && is_array($response->json())) {
                return $response->json();
            }

            Log::warning('AI Support Insights returned unsuccessful status', [
                'user_id' => $userId,
                'status'  => $response->status(),
                'body'    => substr((string) $response->body(), 0, 200),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI Support Insights connection exception: ' . $e->getMessage());
        }

        return $this->generateLocalSupportInsightsFallback($userId);
    }

    private function generateLocalSupportInsightsFallback(int $userId): array
    {
        $latestPregnancy = UserPregnancy::where('user_id', $userId)->latest()->first();
        $isMiscarriage = $latestPregnancy && $latestPregnancy->status === 'miscarriage';
        $postpartum = PostpartumRecovery::where('user_id', $userId)->latest()->first();
        $isPostpartum = ($latestPregnancy && $latestPregnancy->status === 'completed')
            || ($postpartum && (!$latestPregnancy || $latestPregnancy->status !== 'active'));

        if ($isMiscarriage) {
            return [
                'user_id'   => $userId,
                'phase'     => 'loss',
                'loss_type' => 'miscarriage',
                'insights'  => [
                    [
                        'type'      => 'grief_support',
                        'title'     => 'Loss Support',
                        'content'   => "We're deeply sorry for your loss. Your grief is completely valid and we are here to support your healing journey.",
                        'sentiment' => 'empathetic',
                        'priority'  => 'critical',
                    ],
                    [
                        'type'      => 'healing_guidance',
                        'title'     => 'Healing Journey',
                        'content'   => "Grief is not linear. Take all the gentle care you need for physical and emotional recovery.",
                        'sentiment' => 'supportive',
                        'priority'  => 'high',
                    ],
                ],
                'generated_at' => now()->toDateString(),
            ];
        }

        if ($isPostpartum) {
            $week = $postpartum ? $postpartum->weeks_since_delivery : 6;
            return [
                'user_id'  => $userId,
                'phase'    => 'postpartum',
                'week'     => $week,
                'insights' => [
                    [
                        'type'      => 'recovery_progress',
                        'category'  => 'physical',
                        'title'     => 'Your Recovery Progress',
                        'content'   => "At week {$week} postpartum, your body is healing. Prioritize rest, hydration, and gentle movement.",
                        'sentiment' => 'supportive',
                        'priority'  => 'high',
                    ],
                ],
                'generated_at' => now()->toDateString(),
            ];
        }

        $week = $latestPregnancy ? $latestPregnancy->current_week : 24;
        return [
            'user_id'  => $userId,
            'phase'    => 'pregnancy',
            'week'     => $week,
            'insights' => [
                [
                    'type'      => 'emotional_support',
                    'title'     => 'Your Pregnancy Journey',
                    'content'   => "At week {$week}, your body is doing extraordinary work. Stay hydrated and rest whenever you feel tired.",
                    'sentiment' => 'supportive',
                    'priority'  => 'high',
                ],
            ],
            'generated_at' => now()->toDateString(),
        ];
    }
}
