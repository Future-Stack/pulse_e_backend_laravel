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
        } elseif ($requestedTab === 'pregnancy' || empty($requestedTab)) {
            $this->syncPregnancyFromAi((int) $userId);
        } else {
            $this->syncPregnancyFromAi((int) $userId);
            $this->syncPostpartumFromAi((int) $userId);
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

        // Fetch postpartum record ONLY if requested tab is postpartum/all or user stage is postpartum
        $postpartum = ($tab === 'postpartum' || $tab === 'all' || $userSubStage === 'postpartum')
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
     * Comprehensive 40-Week Dynamic Maternal Knowledge Engine.
     * Provides medical-grade weekly milestone guidance, fruit/vegetable comparisons,
     * maternal bodily changes, nutritional focus, safe exercise, and clinical warning signs.
     */
    private function getWeeklyMaternalGuide(int $week): array
    {
        $week = max(1, min(40, $week));

        if ($week <= 12) {
            $trimester = 'First Trimester';
        } elseif ($week <= 27) {
            $trimester = 'Second Trimester';
        } else {
            $trimester = 'Third Trimester';
        }

        $guides = [
            1 => [
                'comparison' => 'a single cell',
                'size' => 'microscopic, <0.1mm',
                'baby' => 'Fertilization occurs. Genetic code is set, including eye color, blood type, and gender.',
                'baby_summary' => 'Fertilization occurs and genetic code is completely established.',
                'body' => 'Your menstrual cycle marks the start of your 40-week journey as your uterine lining prepares for implantation.',
                'body_summary' => 'Uterine lining prepares for embryo implantation.',
                'nutrition' => 'Begin taking 400mcg folic acid daily to prevent neural tube defects. Drink plenty of fresh water.',
                'nutrition_summary' => '400mcg folic acid daily essential. Stay hydrated.',
                'exercise' => 'Maintain your regular exercise routine: brisk walking, light swimming, or gentle yoga.',
                'exercise_summary' => 'Maintain regular low-impact exercise routine.',
            ],
            2 => [
                'comparison' => 'a blastocyst',
                'size' => 'about 0.2mm',
                'baby' => 'The fertilized egg divides rapidly as it travels down the fallopian tube toward your uterus.',
                'baby_summary' => 'Rapid cell division as blastocyst journeys to uterus.',
                'body' => 'Ovulation occurs. Estrogen and progesterone surge to prepare the uterine wall.',
                'body_summary' => 'Hormonal surge prepares the uterine wall for implantation.',
                'nutrition' => 'Focus on leafy greens, citrus fruits, and legumes rich in folate and B-complex vitamins.',
                'nutrition_summary' => 'Leafy greens and legumes rich in natural folate.',
                'exercise' => 'Gentle aerobic exercise, light walking, and pelvic floor awareness.',
                'exercise_summary' => 'Gentle walking and pelvic floor awareness.',
            ],
            3 => [
                'comparison' => 'a tiny pinhead',
                'size' => 'about 0.5mm',
                'baby' => 'Implantation takes place in the uterine wall. The amniotic sac and placenta begin to form.',
                'baby_summary' => 'Implantation occurs; amniotic sac and placenta begin forming.',
                'body' => 'You might notice mild implantation spotting or very light cramping.',
                'body_summary' => 'Light implantation spotting or mild cramping may occur.',
                'nutrition' => 'Increase zinc and vitamin C to support rapid cellular division and immunity.',
                'nutrition_summary' => 'Zinc and vitamin C for cellular division and immunity.',
                'exercise' => 'Low-impact movement. Listen to your body and rest when fatigue hits.',
                'exercise_summary' => 'Low-impact movement; rest when feeling fatigued.',
            ],
            4 => [
                'comparison' => 'a poppy seed',
                'size' => 'about 1mm, <1g',
                'baby' => 'Embryo develops two layers: epiblast and hypoblast, laying the foundation for all vital organs.',
                'baby_summary' => 'Embryo begins foundational development of all vital organs.',
                'body' => 'A missed period and possible tender breasts, fatigue, and heightened sense of smell.',
                'body_summary' => 'Missed period, tender breasts, fatigue, and smell sensitivity.',
                'nutrition' => 'Keep up prenatal vitamins with iron and DHA. Eat small, nutrient-dense meals.',
                'nutrition_summary' => 'Prenatal vitamins with iron and DHA daily.',
                'exercise' => 'Walking, stationary cycling, and gentle stretching are ideal.',
                'exercise_summary' => 'Walking, stationary cycling, and gentle stretching.',
            ],
            5 => [
                'comparison' => 'a sesame seed',
                'size' => 'about 3mm, 1g',
                'baby' => 'Heart begins to form and beat. Neural tube and brain vesicles take shape.',
                'baby_summary' => 'Tiny heart begins beating; neural tube is developing rapidly.',
                'body' => 'Morning sickness and frequent urination may start as hCG levels multiply rapidly.',
                'body_summary' => 'Morning sickness and frequent urination as hCG rises.',
                'nutrition' => 'Ginger tea, dry crackers, and small frequent meals help combat early morning sickness.',
                'nutrition_summary' => 'Ginger, crackers, and frequent small meals for nausea.',
                'exercise' => 'Short 20-minute daily walks and light prenatal yoga. Avoid overheating.',
                'exercise_summary' => '20-minute walks and light prenatal yoga. Avoid overheating.',
            ],
            6 => [
                'comparison' => 'a sweet pea',
                'size' => 'about 6mm, 1g',
                'baby' => 'Facial features begin to form: tiny dark spots for eyes and small openings for ears and nostrils.',
                'baby_summary' => 'Facial features emerging; eye spots and nostril openings form.',
                'body' => 'Hormonal swings, breast changes, and fatigue. Your blood volume is already increasing.',
                'body_summary' => 'Blood volume increases, leading to fatigue and breast fullness.',
                'nutrition' => 'Complex carbohydrates like oats, brown rice, and quinoa provide steady energy.',
                'nutrition_summary' => 'Complex carbohydrates for sustained energy and nausea relief.',
                'exercise' => 'Gentle walking and pelvic tilts. Avoid high-impact or contact sports.',
                'exercise_summary' => 'Gentle walking and pelvic tilts. No high-impact activities.',
            ],
            7 => [
                'comparison' => 'a blueberry',
                'size' => 'about 1.3cm, 1g',
                'baby' => 'Arm and leg buds lengthen into paddle-like hands and feet. Brain is developing 100 new cells every minute!',
                'baby_summary' => 'Arm and leg buds form paddles; rapid brain cell growth.',
                'body' => 'Nausea may peak. Uterus has expanded to the size of a lemon.',
                'body_summary' => 'Nausea may peak; uterus reaches the size of a lemon.',
                'nutrition' => 'Calcium-rich foods like Greek yogurt, fortified plant milks, and almonds support bone buds.',
                'nutrition_summary' => 'Calcium from Greek yogurt and almonds for budding bones.',
                'exercise' => 'Prenatal Pilates focusing on core breathing and pelvic stability.',
                'exercise_summary' => 'Core breathing and pelvic floor stabilization.',
            ],
            8 => [
                'comparison' => 'a kidney bean',
                'size' => 'about 1.6cm, 1g',
                'baby' => 'Tiny webbed fingers and toes emerge. Baby moves constantly, though you cannot feel it yet.',
                'baby_summary' => 'Webbed fingers and toes develop; spontaneous movements begin.',
                'body' => 'Mood swings and heightened sense of taste or food aversions. Bras may feel tight.',
                'body_summary' => 'Mood swings, taste aversions, and noticeable breast growth.',
                'nutrition' => 'Lean proteins: poultry, eggs, tofu, and legumes to supply amino acids for tissue growth.',
                'nutrition_summary' => 'Lean poultry, eggs, and legumes for tissue construction.',
                'exercise' => 'Swimming is wonderful now—it supports weight and cools your body.',
                'exercise_summary' => 'Swimming supports joints and relieves nausea.',
            ],
            9 => [
                'comparison' => 'a green grape',
                'size' => 'about 2.3cm, 2g',
                'baby' => 'Embryonic tail disappears. Joints in elbows, knees, and shoulders are working.',
                'baby_summary' => 'Tail disappears; joint articulation in elbows and knees begins.',
                'body' => 'Increased blood volume can cause visible veins on breasts and legs. Mild dizziness if standing fast.',
                'body_summary' => 'Increased circulation causes visible veins and occasional dizziness.',
                'nutrition' => 'Eat iron-rich foods with vitamin C to avoid early anemia and fatigue.',
                'nutrition_summary' => 'Iron paired with vitamin C to safeguard red blood cells.',
                'exercise' => 'Low-impact cardiovascular exercise: 30 minutes of brisk walking.',
                'exercise_summary' => '30 minutes of brisk, level walking.',
            ],
            10 => [
                'comparison' => 'a kumquat',
                'size' => 'about 3.1cm, 4g',
                'baby' => 'Now officially called a fetus! Vital organs—kidneys, intestines, brain, and liver—are functioning.',
                'baby_summary' => 'Now officially a fetus; vital organs begin functioning.',
                'body' => 'Clothes might feel snug around the waist. Round ligament aches may start.',
                'body_summary' => 'Waistline expands; mild round ligament stretching aches.',
                'nutrition' => 'Magnesium-rich pumpkin seeds and bananas help with muscle relaxation and cramps.',
                'nutrition_summary' => 'Magnesium from seeds and bananas prevents muscle cramps.',
                'exercise' => 'Pelvic floor exercises (Kegels) and prenatal yoga for hip flexibility.',
                'exercise_summary' => 'Kegels and hip flexibility yoga routines.',
            ],
            11 => [
                'comparison' => 'a fig',
                'size' => 'about 4.1cm, 7g',
                'baby' => 'Tooth buds form inside the gums. Baby can open and close fists and stretch.',
                'baby_summary' => 'Tooth buds form in gums; baby opens and closes tiny fists.',
                'body' => 'Nausea starts tapering off for many. Hair and nails may grow faster due to estrogen.',
                'body_summary' => 'Nausea begins easing; hair and nails grow rapidly.',
                'nutrition' => 'Incorporate vitamin D and zinc for immune support and skeletal ossification.',
                'nutrition_summary' => 'Vitamin D and zinc for skeletal ossification.',
                'exercise' => 'Stationary cycling or elliptical trainer with moderate resistance.',
                'exercise_summary' => 'Stationary cycling with moderate resistance.',
            ],
            12 => [
                'comparison' => 'a lime',
                'size' => 'about 5.4cm, 14g',
                'baby' => 'Reflexes develop: baby curls toes, suckles, and squints. All organ systems are formed.',
                'baby_summary' => 'Reflexes appear; all organ systems are fully in place.',
                'body' => 'End of first trimester! Risk of miscarriage drops dramatically. Uterus moves above pelvic bone.',
                'body_summary' => 'First trimester ends; miscarriage risk drops dramatically.',
                'nutrition' => 'Boost fiber to 25-30g daily through flaxseed, chia, and whole grains.',
                'nutrition_summary' => '25-30g dietary fiber daily to prevent digestive slowing.',
                'exercise' => 'Continue aerobic conditioning and gentle resistance bands for upper back.',
                'exercise_summary' => 'Light resistance bands for upper back and posture.',
            ],
            13 => [
                'comparison' => 'a pea pod',
                'size' => 'about 7.4cm, 23g',
                'baby' => 'Welcome to Second Trimester! Baby has unique fingerprints and vocal cords begin to form.',
                'baby_summary' => 'Second trimester starts; unique fingerprints and vocal cords form.',
                'body' => 'Energy surge returns! Libido may increase as morning sickness fades.',
                'body_summary' => 'Second trimester energy boost and appetite restoration.',
                'nutrition' => 'Add an extra 300 nutrient-rich calories daily—avocados, nuts, and dairy.',
                'nutrition_summary' => 'Add 300 high-nutrient calories daily for growth surge.',
                'exercise' => 'Prenatal strength workouts and moderate walking are great now.',
                'exercise_summary' => 'Prenatal strength workouts with light dumbbells.',
            ],
            14 => [
                'comparison' => 'a lemon',
                'size' => 'about 8.7cm, 43g',
                'baby' => 'Baby makes facial expressions, grimacing and squinting. Lanugo (fine downy hair) covers body.',
                'baby_summary' => 'Facial expressions, grimacing, and lanugo hair appears.',
                'body' => 'Belly bump begins to show. Ligaments soften; maintain good posture.',
                'body_summary' => 'Baby bump emerges; ligaments soften.',
                'nutrition' => 'Choline from egg yolks and broccoli supports baby memory and brain development.',
                'nutrition_summary' => 'Choline from eggs and broccoli for baby brain architecture.',
                'exercise' => 'Incline walking, light squats, and posture-strengthening exercises.',
                'exercise_summary' => 'Incline walking and bodyweight squats for hip strength.',
            ],
            15 => [
                'comparison' => 'an apple',
                'size' => 'about 10.1cm, 70g',
                'baby' => 'Baby senses light through eyelids and practices breathing movements with amniotic fluid.',
                'baby_summary' => 'Eyelids sense light; breathing motions practiced in fluid.',
                'body' => 'Nasal congestion and sensitive gums are common due to increased blood flow.',
                'body_summary' => 'Nasal congestion and sensitive gums from increased blood flow.',
                'nutrition' => 'Omega-3 fatty acids (DHA/EPA) from wild salmon or algal oil supplements.',
                'nutrition_summary' => 'Omega-3 DHA for retinal and nervous system development.',
                'exercise' => 'Swimming and water aerobics are exceptionally soothing for the spine.',
                'exercise_summary' => 'Water aerobics relieve spinal and pelvic compression.',
            ],
            16 => [
                'comparison' => 'an avocado',
                'size' => 'about 11.6cm, 100g',
                'baby' => 'Tiny kicks called "quickening" may feel like butterflies or bubbles in your lower belly.',
                'baby_summary' => 'First subtle flutter kicks (quickening) may be felt.',
                'body' => 'Pregnancy glow is in full effect! Keep well-hydrated to support amniotic fluid volume.',
                'body_summary' => 'Vibrant pregnancy glow; staying hydrated is essential.',
                'nutrition' => 'Potassium-rich foods like sweet potatoes and avocados ward off muscle cramps.',
                'nutrition_summary' => 'Potassium from avocados and sweet potatoes.',
                'exercise' => 'Pelvic tilts and side-lying leg lifts to strengthen stabilizer muscles.',
                'exercise_summary' => 'Side-lying leg lifts and pelvic tilts.',
            ],
            17 => [
                'comparison' => 'a pomegranate',
                'size' => 'about 13cm, 140g',
                'baby' => 'Baby is accumulating adipose fat tissue for warmth and energy storage. Skeleton turns from cartilage to bone.',
                'baby_summary' => 'Adipose fat tissue forms; skeleton hardens into bone.',
                'body' => 'Center of gravity begins shifting forward. Sciatic nerve sensitivity may appear.',
                'body_summary' => 'Center of gravity shifts forward; watch posture and footwear.',
                'nutrition' => 'Pair iron sources (lentils, lean meat) with bell peppers or citrus for maximum absorption.',
                'nutrition_summary' => 'Iron paired with citrus for blood volume expansion.',
                'exercise' => 'Cat-cow stretches, gentle hip circles, and walking on even surfaces.',
                'exercise_summary' => 'Cat-cow stretches and gentle hip circles for spine relief.',
            ],
            18 => [
                'comparison' => 'a bell pepper',
                'size' => 'about 14.2cm, 190g',
                'baby' => 'Ears are fully in place and baby can hear your heartbeat, digestive gurgles, and outside sounds.',
                'baby_summary' => 'Ears fully positioned; baby listens to heartbeat and voices.',
                'body' => 'Dizziness can happen if you change positions too quickly. Sleep on your side with a pillow.',
                'body_summary' => 'Sleep on your side with a supportive pregnancy pillow.',
                'nutrition' => 'Magnesium and calcium together in evening meals improve restful sleep.',
                'nutrition_summary' => 'Evening magnesium and calcium for deep sleep.',
                'exercise' => 'Low-impact prenatal yoga focusing on opening the chest and hips.',
                'exercise_summary' => 'Chest-opening and hip-opening prenatal yoga.',
            ],
            19 => [
                'comparison' => 'a mango',
                'size' => 'about 15.3cm, 240g',
                'baby' => 'A protective creamy coating called vernix caseosa covers baby skin to protect it from amniotic fluid.',
                'baby_summary' => 'Vernix caseosa coats baby skin for moisture protection.',
                'body' => 'Round ligament pain: sharp jabbing sensations in hips or lower abdomen when turning quickly.',
                'body_summary' => 'Round ligament stretching can cause brief sharp hip twinges.',
                'nutrition' => 'B-vitamins and antioxidant-rich berries protect growing maternal cells.',
                'nutrition_summary' => 'Antioxidant berries and B-vitamins for cellular health.',
                'exercise' => 'Gentle hamstring and piriformis stretches to relieve hip tightness.',
                'exercise_summary' => 'Hamstring and piriformis stretches relieve hip tension.',
            ],
            20 => [
                'comparison' => 'a banana',
                'size' => 'about 16.4cm, 300g',
                'baby' => 'Halfway mark! Anatomy ultrasound scan checks baby development from head to toe.',
                'baby_summary' => 'Halfway milestone! Comprehensive anatomy scan completed.',
                'body' => 'Top of your uterus (fundus) is now level with your belly button.',
                'body_summary' => 'Uterine fundus reaches the level of your belly button.',
                'nutrition' => 'Nutrient-rich meals with dark leafy greens, salmon, and Greek yogurt.',
                'nutrition_summary' => 'Dark leafy greens, salmon, and Greek yogurt for bone mass.',
                'exercise' => 'Continue walking, swimming, and prenatal pilates 3-4 days a week.',
                'exercise_summary' => '30 minutes walking, swimming, or pilates 3-4 times a week.',
            ],
            21 => [
                'comparison' => 'a large carrot',
                'size' => 'about 26.7cm, 360g',
                'baby' => 'Baby swallows amniotic fluid daily for hydration and nutrition and practices tasting different flavors!',
                'baby_summary' => 'Baby swallows amniotic fluid, tasting subtle food flavors.',
                'body' => 'Mild Braxton Hicks practice contractions may begin. Feet might feel slightly swollen at night.',
                'body_summary' => 'Mild Braxton Hicks contractions and slight evening ankle puffiness.',
                'nutrition' => 'Hydrate with at least 2.5-3 liters of water daily to support circulation.',
                'nutrition_summary' => 'Drink 2.5-3 liters of water to support increased blood volume.',
                'exercise' => 'Ankle circles, calf stretches, and elevated leg rest after movement.',
                'exercise_summary' => 'Calf stretches and elevating legs after light walking.',
            ],
            22 => [
                'comparison' => 'a papaya',
                'size' => 'about 27.8cm, 430g',
                'baby' => 'Eyebrows, eyelashes, and fingernails are completely formed. Senses touch by exploring the womb wall.',
                'baby_summary' => 'Eyebrows and eyelashes clearly visible; baby explores touch.',
                'body' => 'Stretch marks may start appearing on belly, thighs, or breasts. Skin may feel itchy as it expands.',
                'body_summary' => 'Skin stretches and may itch; use natural moisturizing oils.',
                'nutrition' => 'Vitamin E and collagen-supporting foods (avocado, bone broth, seeds) nourish expanding skin.',
                'nutrition_summary' => 'Vitamin E and healthy fats nourish expanding dermal layers.',
                'exercise' => 'Prenatal stationary cycling and pelvic tilts.',
                'exercise_summary' => 'Stationary cycling with zero risk of falling.',
            ],
            23 => [
                'comparison' => 'a grapefruit',
                'size' => 'about 28.9cm, 500g',
                'baby' => 'Baby can discern your voice clearly from outside noise! Rapid eye movement (REM) sleep starts.',
                'baby_summary' => 'Baby recognizes maternal voice; REM dream sleep begins.',
                'body' => 'You might feel rhythmic tapping when baby gets the hiccups! Swollen ankles may occur.',
                'body_summary' => 'Rhythmic hiccups from baby; elevate feet when resting.',
                'nutrition' => 'Reduce excess sodium; focus on potassium and magnesium to reduce fluid retention.',
                'nutrition_summary' => 'Lower sodium and boost potassium to mitigate fluid retention.',
                'exercise' => 'Water aerobics or gentle lap swimming provides buoyant decompression.',
                'exercise_summary' => 'Water buoyancy removes pressure from pelvis and lower back.',
            ],
            24 => [
                'comparison' => 'an ear of corn',
                'size' => 'about 30cm, 600g',
                'baby' => "At 24 weeks, your baby is about the size of an ear of corn and is busy developing tiny taste buds, practicing breathing movements, and even responding to the sound of your voice! Their inner ear is now fully formed, so they can sense balance and may react to your movements with little kicks and wiggles. You're doing an amazing job nurturing this incredible life—every day, your body is creating something truly extraordinary. 💛",
                'baby_summary' => 'Lungs developing rapidly. Eyes partially open. Responds to sound.',
                'body' => "At 24 weeks, your uterus is now about the size of a soccer ball, reaching above your belly button, and you may notice your belly growing more rounded and pronounced. You might also experience some new sensations like mild swelling in your feet, occasional Braxton Hicks contractions, or slight changes in balance as your center of gravity shifts. Remember, every body adjusts to pregnancy differently, so be gentle with yourself and celebrate the amazing work your body is",
                'body_summary' => 'Uterus now above belly button. Braxton Hicks contractions may begin.',
                'nutrition' => "At week 24, focus on iron-rich foods (lean red meat, spinach, lentils) paired with vitamin C sources like bell peppers or citrus to boost absorption and support your increasing blood volume. Aim for 25-30g of fiber daily from whole grains, fruits, and vegetables to combat common constipation, and include calcium-rich foods like yogurt or fortified plant milks (about 1,000mg daily) for your baby's rapid bone development",
                'nutrition_summary' => 'Iron & Omega-3 critical. Aim for 300 extra calories/day.',
                'exercise' => "At 24 weeks, aim for about 20-30 minutes of gentle, low-impact activity most days—walking, swimming, prenatal yoga, or stationary cycling are all excellent choices that support your changing body. Avoid exercises lying flat on your back, contact sports, or anything with a fall risk, and listen to your body by slowing down if you feel dizzy, breathless, or uncomfortable. You're doing something wonderful for both you and your baby—ke",
                'exercise_summary' => 'Swimming, walking, prenatal yoga all safe and beneficial.',
            ],
            25 => [
                'comparison' => 'a rutabaga',
                'size' => 'about 34.6cm, 660g',
                'baby' => 'Baby develops hair color and texture. Capillaries form under the skin, giving baby a healthy pink glow.',
                'baby_summary' => 'Capillaries form under skin; baby hair texture is developing.',
                'body' => 'Heartburn or indigestion can happen as uterus presses stomach upward. Eat smaller portions.',
                'body_summary' => 'Heartburn as stomach is displaced; eat smaller, frequent meals.',
                'nutrition' => 'Avoid spicy or greasy foods near bedtime; almonds and papaya enzymes aid digestion.',
                'nutrition_summary' => 'Small, non-spicy meals; almonds and water aid digestion.',
                'exercise' => 'Gentle torso twists while seated, and walking at a comfortable pace.',
                'exercise_summary' => 'Seated gentle torso stretches and level walking.',
            ],
            26 => [
                'comparison' => 'a scallion',
                'size' => 'about 35.6cm, 760g',
                'baby' => 'Baby opens eyes for the first time! Responds to bright lights shone on the belly with kicks.',
                'baby_summary' => 'Eyes open; baby reacts to external light with movement.',
                'body' => 'Restless legs or trouble sleeping. Blood pressure may slightly rise back to normal levels.',
                'body_summary' => 'Restless legs or sleep disruption; use side-sleeping pillows.',
                'nutrition' => 'Magnesium glycinate from pumpkin seeds, spinach, and nuts relaxes night-time muscles.',
                'nutrition_summary' => 'Magnesium-rich foods in evening relax leg muscles.',
                'exercise' => 'Pelvic floor relaxation and deep diaphragmatic breathing.',
                'exercise_summary' => 'Diaphragmatic breathing and pelvic floor release exercises.',
            ],
            27 => [
                'comparison' => 'a cauliflower',
                'size' => 'about 36.6cm, 875g',
                'baby' => 'End of Second Trimester! Baby has regular sleep and wake cycles and can suck their thumb.',
                'baby_summary' => 'Second trimester concludes; regular sleep cycles and thumb sucking.',
                'body' => 'Weight gain accelerates. Mild swelling in fingers and ankles. Celebrate this milestone!',
                'body_summary' => 'Weight gain accelerates; prepare for third trimester transition.',
                'nutrition' => 'High-protein snacks like hard-boiled eggs, hummus, and Greek yogurt.',
                'nutrition_summary' => 'Protein-packed snacks sustain steady metabolic energy.',
                'exercise' => 'Light prenatal aerobics and spinal decompression stretches.',
                'exercise_summary' => 'Light aerobics and spine decompression stretches.',
            ],
            28 => [
                'comparison' => 'a large eggplant',
                'size' => 'about 37.6cm, 1000g',
                'baby' => 'Welcome to Third Trimester! Baby can open eyes and blink. Rapid brain tissue development with REM sleep cycles.',
                'baby_summary' => 'Third trimester begins! Eyes open and blink; rapid brain growth.',
                'body' => 'Uterus now 3 inches above navel. Mild backaches, shortness of breath, and leg cramps common.',
                'body_summary' => 'Uterus 3 inches above navel; mild backaches and leg cramps.',
                'nutrition' => 'Increase iron & calcium intake. Prioritize 75-100g daily protein & electrolytes.',
                'nutrition_summary' => '75-100g protein daily, plus iron and calcium for bone mineralization.',
                'exercise' => 'Low-impact prenatal yoga, walking & pelvic floor tilts. Avoid lying flat on back.',
                'exercise_summary' => 'Low-impact walking, prenatal yoga, and pelvic floor tilts.',
            ],
            29 => [
                'comparison' => 'a butternut squash',
                'size' => 'about 38.6cm, 1.15kg',
                'baby' => 'Baby kicks and stretches are very strong and visible on your belly surface. Muscles and lungs continue maturing.',
                'baby_summary' => 'Vigorous kicks visible on belly; lungs and muscles mature.',
                'body' => 'Shortness of breath as uterus pushes against your diaphragm. Frequent bathroom breaks.',
                'body_summary' => 'Diaphragm compression causes shortness of breath; take deep breaths.',
                'nutrition' => 'Calcium intake is vital now—about 1,000-1,200mg daily for baby skeletal hardening.',
                'nutrition_summary' => '1,000-1,200mg calcium daily as baby bones rapidly ossify.',
                'exercise' => 'Chest-opening stretches, shoulder rolls, and gentle walking.',
                'exercise_summary' => 'Shoulder rolls and chest-opening stretches relieve breathlessness.',
            ],
            30 => [
                'comparison' => 'a head of cabbage',
                'size' => 'about 39.9cm, 1.3kg',
                'baby' => 'Baby brain surface is developing distinctive grooves and folds. Baby regulates its own body temperature now.',
                'baby_summary' => 'Brain develops deep cerebral grooves; baby regulates body temperature.',
                'body' => 'Fatigue may return similar to first trimester. Heartburn and loose joints from relaxin hormone.',
                'body_summary' => 'Third-trimester fatigue returns; relaxin hormone loosens joints.',
                'nutrition' => 'Complex carbohydrates, chia seeds, and oats provide sustained stamina and bowel regularity.',
                'nutrition_summary' => 'Oats and chia seeds for stamina and healthy bowel motility.',
                'exercise' => 'Tailor sitting (butterfly pose) and pelvic rocking on a birthing ball.',
                'exercise_summary' => 'Birthing ball pelvic rocking and butterfly sitting.',
            ],
            31 => [
                'comparison' => 'a coconut',
                'size' => 'about 41.1cm, 1.5kg',
                'baby' => 'All five senses are active! Baby turns head towards light and recognizes voices and soothing music.',
                'baby_summary' => 'All five senses active; baby turns toward light and voices.',
                'body' => 'More frequent Braxton Hicks contractions. Colostrum (early breast milk) may leak.',
                'body_summary' => 'Braxton Hicks practice contractions; colostrum may begin leaking.',
                'nutrition' => 'Hydrate with coconut water and lemon water to replenish natural electrolytes.',
                'nutrition_summary' => 'Electrolyte-rich hydration to soothe uterine muscle irritability.',
                'exercise' => 'Gentle walking and pelvic floor release exercises on a stability ball.',
                'exercise_summary' => 'Gentle walking and stability ball pelvic circles.',
            ],
            32 => [
                'comparison' => 'a jicama',
                'size' => 'about 42.4cm, 1.7kg',
                'baby' => 'Baby is practicing breathing movements constantly and gaining about half a pound per week.',
                'baby_summary' => 'Practicing continuous breathing movements; rapid weight gain.',
                'body' => 'Your belly is expanding noticeably. Growth scan ultrasound is typically scheduled around now.',
                'body_summary' => 'Growth scan window; lower back support and side sleeping essential.',
                'nutrition' => 'Healthy fats from avocados, walnuts, and olive oil fuel baby brain myelin sheath coating.',
                'nutrition_summary' => 'Avocados and walnuts fuel baby brain myelin sheath development.',
                'exercise' => 'Supported squats with a chair or wall and slow prenatal walking.',
                'exercise_summary' => 'Wall-supported squats to prepare pelvic outlet for birth.',
            ],
            33 => [
                'comparison' => 'a pineapple',
                'size' => 'about 43.7cm, 1.9kg',
                'baby' => 'Immune system gets a major boost as maternal antibodies transfer across the placenta.',
                'baby_summary' => 'Maternal antibodies transfer across placenta to build immunity.',
                'body' => 'Pelvic pressure and clumsiness as center of gravity shifts. Rest often and keep feet elevated.',
                'body_summary' => 'Pelvic pressure and looseness; wear supportive comfortable shoes.',
                'nutrition' => 'Probiotics from kefir or yogurt support maternal gut health and baby microbiome seeding.',
                'nutrition_summary' => 'Probiotics from yogurt support microbiome development.',
                'exercise' => 'Water floating, gentle prenatal swimming, and pelvic floor stretches.',
                'exercise_summary' => 'Water floating and gentle swimming take weight off the pelvis.',
            ],
            34 => [
                'comparison' => 'a cantaloupe',
                'size' => 'about 45cm, 2.1kg',
                'baby' => 'Central nervous system and lungs are almost fully mature. Baby sleeps with eyes closed and wakes with eyes open.',
                'baby_summary' => 'Central nervous system mature; baby has distinct sleep/wake cycles.',
                'body' => 'Vision may feel slightly drier or blurry due to fluid retention and hormones. Keep eye drops handy.',
                'body_summary' => 'Fluid retention can affect vision slightly; stay hydrated.',
                'nutrition' => 'Eat small, frequent protein-rich meals to avoid severe heartburn and acid reflux.',
                'nutrition_summary' => 'Small, high-protein meals prevent acid reflux and stomach pressure.',
                'exercise' => 'Seated stretching on an exercise ball and gentle walks in cool weather.',
                'exercise_summary' => 'Exercise ball pelvic rolls and slow walks.',
            ],
            35 => [
                'comparison' => 'a honeydew melon',
                'size' => 'about 46.2cm, 2.4kg',
                'baby' => 'Kidneys are fully mature and liver can process waste products. Most baby physical development is complete!',
                'baby_summary' => 'Kidneys and liver fully functional; physical development nearly complete.',
                'body' => 'Frequent urges to urinate as baby head presses against your bladder. Perineal massage can begin.',
                'body_summary' => 'Baby presses down on bladder; perineal massage can begin.',
                'nutrition' => 'Red raspberry leaf tea and dates (3-6 daily) can support uterine muscle toning for labor.',
                'nutrition_summary' => 'Dates and red raspberry leaf tea support uterine tone.',
                'exercise' => 'Perineal stretching, gentle pelvic opening yoga poses (child pose, butterfly).',
                'exercise_summary' => 'Child pose and butterfly stretches to open the birth canal.',
            ],
            36 => [
                'comparison' => 'a head of romaine lettuce',
                'size' => 'about 47.4cm, 2.6kg',
                'baby' => 'Baby may drop into your pelvis ("lightening"). GBS swab test and birth plan finalized.',
                'baby_summary' => 'Baby drops into pelvis (lightening); GBS swab milestone week.',
                'body' => 'Easier to breathe once baby drops, but increased pelvic pressure and frequent bathroom trips.',
                'body_summary' => 'Breathing feels easier after baby drops; increased pelvic pressure.',
                'nutrition' => 'Light, digestible meals rich in magnesium and vitamin K to prepare for birth.',
                'nutrition_summary' => 'Vitamin K and magnesium support healthy labor coagulation.',
                'exercise' => 'Slow walks and birthing ball hip circles to encourage optimal fetal positioning.',
                'exercise_summary' => 'Birthing ball figure-8 circles encourage optimal fetal positioning.',
            ],
            37 => [
                'comparison' => 'a bunch of Swiss chard',
                'size' => 'about 48.6cm, 2.9kg',
                'baby' => 'Full Term Early! Baby lungs and digestive tract are fully prepared for life outside.',
                'baby_summary' => 'Early full-term milestone reached! Lungs and reflexes ready.',
                'body' => 'Cervix may begin to soften (ripen) and efface. Pack your hospital bag if not already done.',
                'body_summary' => 'Cervix begins softening; hospital bag should be packed.',
                'nutrition' => 'Eat nourishing, easily digestible soups, broths, and high-energy snacks.',
                'nutrition_summary' => 'Warm bone broths, oats, and nutrient-dense recovery foods.',
                'exercise' => 'Gentle walking, curb walking, and resting as much as you desire.',
                'exercise_summary' => 'Gentle walking and ample rest to store energy for labor.',
            ],
            38 => [
                'comparison' => 'a leek',
                'size' => 'about 49.8cm, 3.1kg',
                'baby' => 'Firm grasp reflex developed. Baby has shed most vernix and lanugo and is ready to arrive any day!',
                'baby_summary' => 'Firm grasp reflex; lanugo shed; baby ready for birth.',
                'body' => 'Nesting urge to clean and organize. Watch for bloody show or mucus plug loss.',
                'body_summary' => 'Strong nesting instincts; watch for mucus plug or bloody show.',
                'nutrition' => 'Hydrating liquids, electrolyte drinks, and small nutrient-packed snacks.',
                'nutrition_summary' => 'High-electrolyte fluids and easily digestible carb snacks.',
                'exercise' => 'Relaxing breathing techniques, pelvic rocking, and resting.',
                'exercise_summary' => 'Labor breathing practice and restorative rest.',
            ],
            39 => [
                'comparison' => 'a mini watermelon',
                'size' => 'about 50.7cm, 3.3kg',
                'baby' => 'Full Term! Baby brain and lungs are fully developed, and baby continues adding fat stores.',
                'baby_summary' => 'Full term milestone! Baby is fully formed and ready to meet you.',
                'body' => 'Braxton Hicks may become stronger and rhythmic. Watch for water breaking or regular contractions.',
                'body_summary' => 'Watch for regular contractions (5-1-1 rule) or water breaking.',
                'nutrition' => 'Light carb snacks (bananas, honey, toast) for sustained labor stamina.',
                'nutrition_summary' => 'Easily accessible quick carbs to fuel labor endurance.',
                'exercise' => 'Slow walking and gentle swaying to encourage labor onset.',
                'exercise_summary' => 'Gentle swaying and pelvic rocking on birthing ball.',
            ],
            40 => [
                'comparison' => 'a small pumpkin',
                'size' => 'about 51.2cm, 3.5kg',
                'baby' => 'Your Due Date! Baby is fully ready. Only about 5% of babies arrive exactly on their due date.',
                'baby_summary' => 'Due date reached! Baby is completely prepared to enter the world.',
                'body' => 'High anticipation! Contact your doctor or midwife when contractions become regular.',
                'body_summary' => 'Celebrate reaching full 40 weeks! Monitor contraction timing.',
                'nutrition' => 'Drink plenty of water and clear broths; keep energy up with dates and fruits.',
                'nutrition_summary' => 'Clear broths and hydrating fluids for labor preparation.',
                'exercise' => 'Rest, deep breathing, and slow walking to stay relaxed and calm.',
                'exercise_summary' => 'Deep calm breathing and restful positions.',
            ],
        ];

        $data = $guides[$week] ?? $guides[24];

        return [
            'week'                    => $week,
            'trimester'               => $trimester,
            'baby_size_comparison'    => $data['comparison'],
            'approx_size_text'        => $data['size'],
            'baby_size_text'          => "Baby is the size of {$data['comparison']} — {$data['size']}",
            'baby_development'        => $data['baby'],
            'baby_development_summary'=> $data['baby_summary'],
            'your_body'               => $data['body'],
            'your_body_summary'       => $data['body_summary'],
            'nutrition_focus'         => $data['nutrition'],
            'nutrition_focus_summary' => $data['nutrition_summary'],
            'safe_exercise'           => $data['exercise'],
            'safe_exercise_summary'   => $data['exercise_summary'],
            'clinical_warning_signs'  => "Contact your healthcare provider promptly if you experience severe headaches, sudden vision changes, severe swelling in face/hands, vaginal bleeding, fluid leakage, or noticeably decreased fetal movement.",
        ];
    }

    private function formatPregnancyData(UserPregnancy $pregnancy): array
    {
        $week = (int) $pregnancy->current_week;
        $totalWeeks = 40;

        $maternalGuide = $this->getWeeklyMaternalGuide($week);

        // Check if cached AI data exists and matches the exact current week
        $aiData = $pregnancy->ai_data ?? Cache::get("user_pregnancy_ai_{$pregnancy->user_id}", []);
        $aiMatchesWeek = !empty($aiData['current_week']) && ((int) $aiData['current_week'] === $week);

        // Baby size & comparison
        $babyComparison = ($aiMatchesWeek && !empty($aiData['baby_size_comparison']))
            ? $aiData['baby_size_comparison']
            : $maternalGuide['baby_size_comparison'];

        $approxSize = ($aiMatchesWeek && !empty($aiData['approx_size_text']))
            ? $aiData['approx_size_text']
            : $maternalGuide['approx_size_text'];

        $babySizeText = "Baby is the size of {$babyComparison}" . ($approxSize ? " — {$approxSize}" : "");

        // Trimester formatting
        $trimester = ($aiMatchesWeek && !empty($aiData['current_trimester']))
            ? $aiData['current_trimester']
            : $maternalGuide['trimester'];

        if (!str_contains(strtolower($trimester), 'trimester')) {
            $trimester = ucfirst($trimester) . ' Trimester';
        }

        // Due date & days
        $dueDate = Carbon::parse($pregnancy->due_date);
        $dueDateFormatted = $dueDate->format('F j, Y');
        $daysUntilDue = $pregnancy->days_to_due_date;
        $progressPercentage = min(100, (int) round(($week / $totalWeeks) * 100));

        // Educational cards: Short summary + Full AI/Maternal detail
        $babyDevFull = ($aiMatchesWeek && !empty($aiData['baby_development']))
            ? $aiData['baby_development']
            : $maternalGuide['baby_development'];

        $yourBodyFull = ($aiMatchesWeek && !empty($aiData['your_body']))
            ? $aiData['your_body']
            : $maternalGuide['your_body'];

        $nutritionFull = ($aiMatchesWeek && !empty($aiData['nutrition_focus']))
            ? $aiData['nutrition_focus']
            : $maternalGuide['nutrition_focus'];

        $exerciseFull = ($aiMatchesWeek && !empty($aiData['safe_exercises']))
            ? $aiData['safe_exercises']
            : $maternalGuide['safe_exercise'];

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
            : "# Warning Signs to Watch For at {$week} Weeks\n\nContact your healthcare provider promptly if you experience **heavy vaginal bleeding, severe or persistent abdominal pain, regular contractions, leaking fluid, or a noticeable decrease in your baby's movements**. Also report **severe headaches, blurred vision, sudden swelling of the face or hands, or pain in the upper right abdomen**, as these can signal preeclampsia.";

        $alerts = !empty($aiData['alerts']) && is_array($aiData['alerts'])
            ? $aiData['alerts']
            : [
                [
                    'message'         => 'Glucose tolerance test due this week',
                    'severity'        => 'medium',
                    'action_required' => true,
                ],
            ];

        return [
            // Exact AI Response Keys (Matching https://ai.fightthenumber.com/api/v1/pregnancy/summary)
            'is_pregnant'             => true,
            'current_week'            => $week,
            'current_trimester'       => $aiData['current_trimester'] ?? ($week <= 12 ? 'First' : ($week <= 27 ? 'Second' : 'Third')),
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
            'profile_id'              => $pregnancy->user?->profile?->id ?? ($aiData['profile_id'] ?? 39),
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
        $currentWeek = $postpartum->weeks_since_delivery;

        $metricsList = [
            [
                'id'              => 'physical_recovery',
                'title'           => 'Physical Recovery',
                'percentage'      => (int) $postpartum->physical_recovery_percent,
                'percentage_text' => "{$postpartum->physical_recovery_percent}%",
                'icon'            => 'muscle',
                'icon_emoji'      => '💪',
            ],
            [
                'id'              => 'hormonal_balance',
                'title'           => 'Hormonal Balance',
                'percentage'      => (int) $postpartum->hormonal_balance_percent,
                'percentage_text' => "{$postpartum->hormonal_balance_percent}%",
                'icon'            => 'scale',
                'icon_emoji'      => '⚖️',
            ],
            [
                'id'              => 'sleep_quality',
                'title'           => 'Sleep Quality',
                'percentage'      => (int) $postpartum->sleep_quality_percent,
                'percentage_text' => "{$postpartum->sleep_quality_percent}%",
                'change_diff'     => $postpartum->sleep_change_diff ? "+{$postpartum->sleep_change_diff}" : null,
                'badge'           => $postpartum->sleep_change_diff ? "+{$postpartum->sleep_change_diff}" : null,
                'icon'            => 'sleep',
                'icon_emoji'      => '😴',
            ],
            [
                'id'              => 'energy_levels',
                'title'           => 'Energy Levels',
                'percentage'      => (int) $postpartum->energy_levels_percent,
                'percentage_text' => "{$postpartum->energy_levels_percent}%",
                'icon'            => 'lightning',
                'icon_emoji'      => '⚡',
            ],
        ];

        return [
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
                    'percentage'      => (int) $postpartum->physical_recovery_percent,
                    'percentage_text' => "{$postpartum->physical_recovery_percent}%",
                    'icon'            => 'muscle',
                    'icon_emoji'      => '💪',
                ],
                'hormonal_balance' => [
                    'title'           => 'Hormonal Balance',
                    'percentage'      => (int) $postpartum->hormonal_balance_percent,
                    'percentage_text' => "{$postpartum->hormonal_balance_percent}%",
                    'icon'            => 'scale',
                    'icon_emoji'      => '⚖️',
                ],
                'sleep_quality' => [
                    'title'           => 'Sleep Quality',
                    'percentage'      => (int) $postpartum->sleep_quality_percent,
                    'percentage_text' => "{$postpartum->sleep_quality_percent}%",
                    'change_diff'     => $postpartum->sleep_change_diff ? "+{$postpartum->sleep_change_diff}" : null,
                    'badge'           => $postpartum->sleep_change_diff ? "+{$postpartum->sleep_change_diff}" : null,
                    'icon'            => 'sleep',
                    'icon_emoji'      => '😴',
                ],
                'energy_levels' => [
                    'title'           => 'Energy Levels',
                    'percentage'      => (int) $postpartum->energy_levels_percent,
                    'percentage_text' => "{$postpartum->energy_levels_percent}%",
                    'icon'            => 'lightning',
                    'icon_emoji'      => '⚡',
                ],
            ],
            'mental_health_checkin' => [
                'title'              => 'Mental Health Check-In',
                'card_title'         => 'Postpartum Wellness Screening',
                'screening_name'     => $postpartum->screening_name ?? 'Postpartum Wellness Screening',
                'screening_scale'    => 'Edinburgh Postnatal Depression Scale',
                'screening_subtitle' => 'Edinburgh Postnatal Depression Scale — due today',
                'screening_note'     => 'Edinburgh Postnatal Depression Scale — due today',
                'due'                => (bool) $postpartum->screening_due,
                'mood_stability'     => $postpartum->mood_stability ?? 'Stable',
                'anxiety_levels'     => $postpartum->anxiety_level ?? 'Mild',
                'metrics' => [
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
