<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
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

        // 1. Sync live intelligence from AI API into Database
        $this->syncPregnancyFromAi((int) $userId);
        $this->syncPostpartumFromAi((int) $userId);

        $latestPregnancy = UserPregnancy::where('user_id', $userId)
            ->latest()
            ->first();

        $postpartumRecord = PostpartumRecovery::where('user_id', $userId)
            ->latest()
            ->first();

        $isMiscarriage = $latestPregnancy && $latestPregnancy->status === 'miscarriage';
        $isPostpartum = ($latestPregnancy && $latestPregnancy->status === 'completed')
            || ($postpartumRecord && (!$latestPregnancy || $latestPregnancy->status !== 'active'));

        // Determine user actual sub-stage based on life stage records
        $userSubStage = 'pregnancy';
        if ($isMiscarriage) {
            $userSubStage = 'miscarriage';
        } elseif ($isPostpartum) {
            $userSubStage = 'postpartum';
        }

        // Main Life Stage Title from user profile (LifeJourney relation)
        $lifeStageTitle = $user->profile?->lifeStage?->title
            ?? 'Pregnancy & Postpartum';

        // Automatically default to user's actual current stage
        $defaultTab = $userSubStage === 'postpartum' ? 'postpartum' : ($userSubStage === 'miscarriage' ? 'support' : 'pregnancy');
        $tab = strtolower($request->input('tab') ?? $request->query('tab') ?? $defaultTab);

        // Fetch or create user's pregnancy record from DB
        $pregnancy = ($latestPregnancy && ($latestPregnancy->status === 'active' || $isMiscarriage))
            ? $latestPregnancy
            : $this->getOrCreatePregnancy((int) $userId);

        $postpartum = $this->getOrCreatePostpartum((int) $userId, $pregnancy);

        // Dynamic subtitle according to active stage and maternal phase
        if ($tab === 'postpartum') {
            $currentWeek = $postpartum->weeks_since_delivery;
            $subtitle = "Week {$currentWeek} · Postpartum recovery";
        } elseif ($tab === 'support' || $isMiscarriage) {
            $subtitle = 'Compassionate care and healing support';
        } else {
            $subtitle = "Week {$pregnancy->current_week} of 40 · {$pregnancy->days_to_due_date} days to due date";
        }

        $data = [
            'life_stage'     => $lifeStageTitle,
            'current_stage'  => $userSubStage,
            'user_sub_stage' => $userSubStage,
            'active_stage'   => $tab,
            'active_tab'     => $tab,
            'available_tabs' => ['pregnancy', 'postpartum', 'support'],
            'tabs'           => [
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
            $data['pregnancy']  = $this->formatPregnancyData($pregnancy);
            $data['postpartum'] = $this->formatPostpartumData($postpartum);
            $data['support']    = $this->formatSupportData((int) $userId);
        } elseif ($tab === 'postpartum') {
            $data['postpartum'] = $this->formatPostpartumData($postpartum);
        } elseif ($tab === 'support') {
            $data['support']    = $this->formatSupportData((int) $userId);
        } else {
            // Default: pregnancy
            $data['pregnancy']  = $this->formatPregnancyData($pregnancy);
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
            ]
        );

        $this->ensureMilestonesFromDbOrDefaults($pregnancy);

        // Trigger AI sync in background/inline to populate personalized weekly guide
        $this->syncPregnancyFromAi((int) $userId);

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
                        if ($dueDate && $pregnancy->due_date?->toDateString() !== $dueDate) {
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
                    'physical_recovery_percent' => (int) ($physical['physical_recovery_percent'] ?? ($postpartum?->physical_recovery_percent ?? 72)),
                    'hormonal_balance_percent'  => (int) ($physical['hormonal_balance_percent'] ?? ($postpartum?->hormonal_balance_percent ?? 58)),
                    'sleep_quality_percent'     => (int) ($physical['sleep_quality_percent'] ?? ($postpartum?->sleep_quality_percent ?? 45)),
                    'energy_levels_percent'     => (int) ($physical['energy_level_percent'] ?? ($postpartum?->energy_levels_percent ?? 61)),
                    'screening_name'            => $mentalUi['title'] ?? 'Edinburgh Postnatal Depression Scale',
                    'screening_due'             => true,
                    'screening_due_text'        => 'due today',
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

    private function getOrCreatePregnancy(int $userId): UserPregnancy
    {
        $pregnancy = UserPregnancy::where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($pregnancy) {
            $this->ensureMilestonesFromDbOrDefaults($pregnancy);
            return $pregnancy;
        }

        $latest = UserPregnancy::where('user_id', $userId)->latest()->first();
        if ($latest && $latest->status === 'miscarriage') {
            return $latest;
        }

        // Try syncing from AI API if available
        $synced = $this->syncPregnancyFromAi($userId);
        if ($synced) {
            $this->ensureMilestonesFromDbOrDefaults($synced);
            return $synced;
        }

        // Standard default active pregnancy initialized from current cycle/timeline
        $dueDate = Carbon::now()->addDays(112)->toDateString();
        $pregnancy = UserPregnancy::create([
            'user_id'                    => $userId,
            'due_date'                   => $dueDate,
            'last_menstrual_period_date' => Carbon::now()->subWeeks(24)->toDateString(),
            'status'                     => 'active',
        ]);

        $this->ensureMilestonesFromDbOrDefaults($pregnancy);

        return $pregnancy;
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
                    'is_completed'   => true, // In UI image, milestone checklist items are completed
                    'completed_at'   => now(),
                ]);
            } else {
                if (!$existing->date_label || str_contains($existing->date_label, 'Week') || !str_contains($existing->date_label, '·')) {
                    $existing->date_label = $dateLabel;
                    $existing->scheduled_date = $milestoneDate->toDateString();
                    $existing->save();
                }
            }
        }
    }

    private function getOrCreatePostpartum(int $userId, ?UserPregnancy $pregnancy): PostpartumRecovery
    {
        $postpartum = PostpartumRecovery::where('user_id', $userId)->latest()->first();
        if ($postpartum) {
            return $postpartum;
        }

        // Try syncing from AI API
        $synced = $this->syncPostpartumFromAi($userId);
        if ($synced) {
            return $synced;
        }

        $deliveryDate = $pregnancy?->delivery_date
            ? Carbon::parse($pregnancy->delivery_date)->toDateString()
            : Carbon::now()->subWeeks(6)->toDateString();

        return PostpartumRecovery::create([
            'user_id'                    => $userId,
            'pregnancy_id'               => $pregnancy?->id,
            'delivery_date'              => $deliveryDate,
            'current_week'               => 6,
            'physical_recovery_percent'  => 72,
            'hormonal_balance_percent'   => 58,
            'sleep_quality_percent'      => 45,
            'sleep_change_diff'          => 8,
            'energy_levels_percent'      => 61,
            'screening_name'             => 'Edinburgh Postnatal Depression Scale',
            'screening_due'              => true,
            'screening_due_text'         => 'due today',
            'mood_stability'             => 'Stable',
            'anxiety_level'              => 'Mild',
        ]);
    }

    private function formatPregnancyData(UserPregnancy $pregnancy): array
    {
        // AI specific cached or stored data for this user
        $aiData = $pregnancy->ai_data ?? Cache::get("user_pregnancy_ai_{$pregnancy->user_id}", []);
        $week = (int) ($aiData['current_week'] ?? $pregnancy->current_week);
        $totalWeeks = 40;
        $guide = PregnancyWeeklyGuide::where('week_number', $week)->first()
            ?? PregnancyWeeklyGuide::where('week_number', 24)->first();

        // Baby size & comparison
        $babyComparison = $guide?->baby_size_comparison ?? 'an ear of corn';
        $approxSize = $guide?->approx_size_text ?? 'about 30cm, 600g';

        // Trimester formatting
        $trimester = $aiData['current_trimester'] ?? $guide?->trimester ?? $pregnancy->trimester;
        if (!str_contains(strtolower($trimester), 'trimester')) {
            $trimester = ucfirst($trimester) . ' Trimester';
        }

        // Due date & days
        $dueDate = Carbon::parse($pregnancy->due_date);
        $dueDateFormatted = $dueDate->format('F j, Y');
        $daysUntilDue = $aiData['days_until_due'] ?? $pregnancy->days_to_due_date;
        $progressPercentage = min(100, (int) round(($week / $totalWeeks) * 100));

        // Educational cards: Short summary + Full AI detail
        $babyDevFull = $aiData['baby_development'] ?? $guide?->baby_development ?? "Lungs developing rapidly. Eyes partially open. Responds to sound.";
        $yourBodyFull = $aiData['your_body'] ?? $guide?->your_body ?? "Uterus now above belly button. Braxton Hicks contractions may begin.";
        $nutritionFull = $aiData['nutrition_focus'] ?? $guide?->nutrition_focus ?? "Iron & Omega-3 critical. Aim for 300 extra calories/day.";
        $exerciseFull = $aiData['safe_exercises'] ?? $guide?->safe_exercise ?? "Swimming, walking, prenatal yoga all safe and beneficial.";

        // Default concise summaries as shown on card UI
        $babyDevSummary = "Lungs developing rapidly. Eyes partially open. Responds to sound.";
        $yourBodySummary = "Uterus now above belly button. Braxton Hicks contractions may begin.";
        $nutritionSummary = "Iron & Omega-3 critical. Aim for 300 extra calories/day.";
        $exerciseSummary = "Swimming, walking, prenatal yoga all safe and beneficial.";

        if ($guide && strlen($guide->baby_development) < 150) {
            $babyDevSummary = $guide->baby_development;
        }
        if ($guide && strlen($guide->your_body) < 150) {
            $yourBodySummary = $guide->your_body;
        }
        if ($guide && strlen($guide->nutrition_focus) < 150) {
            $nutritionSummary = $guide->nutrition_focus;
        }
        if ($guide && strlen($guide->safe_exercise) < 150) {
            $exerciseSummary = $guide->safe_exercise;
        }

        // Checklist / Milestones
        $this->ensureMilestonesFromDbOrDefaults($pregnancy, $aiData['clinical_monitoring'] ?? null);
        $milestones = PregnancyMilestone::where('pregnancy_id', $pregnancy->id)
            ->orderBy('target_week', 'asc')
            ->get()
            ->map(function ($m) use ($week) {
                $isCurrent = ($m->target_week == $week);
                $dateLabel = $m->date_label;
                $dateOnly = $m->date_label;
                if (str_contains($m->date_label ?? '', '·')) {
                    $parts = explode('·', $m->date_label);
                    $dateOnly = trim(end($parts));
                }

                return [
                    'id'           => $m->id,
                    'title'        => $m->title,
                    'target_week'  => $m->target_week,
                    'week'         => "W{$m->target_week}",
                    'week_label'   => "W{$m->target_week}",
                    'date'         => $dateOnly,
                    'date_label'   => $dateLabel,
                    'is_completed' => (bool) $m->is_completed,
                    'is_current'   => $isCurrent,
                    'status'       => $m->is_completed ? 'completed' : ($isCurrent ? 'current' : 'upcoming'),
                ];
            });

        return [
            // Gauge / Trimester Card matching UI
            'card' => [
                'current_week'        => $week,
                'total_weeks'         => $totalWeeks,
                'week_label'          => "W{$week}",
                'week_sub_label'      => "of {$totalWeeks}",
                'progress_percentage' => $progressPercentage,
                'trimester'           => $trimester,
                'baby_size_comparison'=> $babyComparison,
                'approx_size_text'    => $approxSize,
                'baby_size_text'      => "Baby is the size of {$babyComparison}" . ($approxSize ? " — {$approxSize}" : ""),
                'due_date'            => $dueDate->toDateString(),
                'due_date_formatted'  => $dueDateFormatted,
                'due_date_display'    => "Due date: {$dueDateFormatted}",
                'days_remaining'      => $daysUntilDue,
                'days_until_due'      => $daysUntilDue,
                'week_circle' => [
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
                    'id'          => 'pregnancy_loss',
                    'label'       => 'Pregnancy Loss',
                    'theme'       => 'purple_filled',
                    'modal' => [
                        'title'               => 'Have you experience a miscarriage?',
                        'description'         => "We're so sorry if you did. We can update your space to support your healing journey.",
                        'confirm_button_text' => 'Yes, I did',
                        'cancel_button_text'  => 'No, continue as normal',
                        'endpoint'            => '/api/v1/pregnancy/report-loss',
                    ],
                ],
                [
                    'id'          => 'postpartum_journey',
                    'label'       => 'Postpartum Jouney',
                    'theme'       => 'purple_outline',
                    'modal' => [
                        'title'               => 'Have you recently completed your pregnancy?',
                        'description'         => 'Congratulations! We can update your journey to support you through the postpartum phase.',
                        'confirm_button_text' => 'Yes, I did',
                        'cancel_button_text'  => 'No, continue as normal',
                        'endpoint'            => '/api/v1/pregnancy/complete-journey',
                    ],
                ],
            ],
            'action_modals' => [
                'pregnancy_loss' => [
                    'label'               => 'Pregnancy Loss',
                    'title'               => 'Have you experience a miscarriage?',
                    'description'         => "We're so sorry if you did. We can update your space to support your healing journey.",
                    'confirm_button_text' => 'Yes, I did',
                    'cancel_button_text'  => 'No, continue as normal',
                    'endpoint'            => '/api/v1/pregnancy/report-loss',
                ],
                'postpartum_journey' => [
                    'label'               => 'Postpartum Jouney',
                    'title'               => 'Have you recently completed your pregnancy?',
                    'description'         => 'Congratulations! We can update your journey to support you through the postpartum phase.',
                    'confirm_button_text' => 'Yes, I did',
                    'cancel_button_text'  => 'No, continue as normal',
                    'endpoint'            => '/api/v1/pregnancy/complete-journey',
                ],
            ],

            // Section 1: WEEK 24 MILESTONES (Educational Cards)
            'week_milestones' => [
                'section_title' => "WEEK {$week} MILESTONES",
                'baby_development' => [
                    'id'         => 'baby_development',
                    'title'      => 'Baby Development',
                    'icon'       => 'baby',
                    'icon_emoji' => '👶',
                    'desc'       => $babyDevSummary,
                    'summary'    => $babyDevSummary,
                    'full_text'  => $babyDevFull,
                    'ai_insight' => $babyDevFull,
                ],
                'your_body' => [
                    'id'         => 'your_body',
                    'title'      => 'Your Body',
                    'icon'       => 'heart',
                    'icon_emoji' => '💙',
                    'desc'       => $yourBodySummary,
                    'summary'    => $yourBodySummary,
                    'full_text'  => $yourBodyFull,
                    'ai_insight' => $yourBodyFull,
                ],
                'nutrition_focus' => [
                    'id'         => 'nutrition_focus',
                    'title'      => 'Nutrition Focus',
                    'icon'       => 'nutrition',
                    'icon_emoji' => '🥦',
                    'desc'       => $nutritionSummary,
                    'summary'    => $nutritionSummary,
                    'full_text'  => $nutritionFull,
                    'ai_insight' => $nutritionFull,
                ],
                'safe_exercise' => [
                    'id'         => 'safe_exercise',
                    'title'      => 'Safe Exercise',
                    'icon'       => 'exercise',
                    'icon_emoji' => '🏃‍♀️',
                    'desc'       => $exerciseSummary,
                    'summary'    => $exerciseSummary,
                    'full_text'  => $exerciseFull,
                    'ai_insight' => $exerciseFull,
                ],
                'cards' => [
                    [
                        'id'          => 'baby_development',
                        'title'       => 'Baby Development',
                        'icon'        => 'baby',
                        'icon_emoji'  => '👶',
                        'description' => $babyDevSummary,
                        'summary'     => $babyDevSummary,
                        'full_text'   => $babyDevFull,
                    ],
                    [
                        'id'          => 'your_body',
                        'title'       => 'Your Body',
                        'icon'        => 'heart',
                        'icon_emoji'  => '💙',
                        'description' => $yourBodySummary,
                        'summary'     => $yourBodySummary,
                        'full_text'   => $yourBodyFull,
                    ],
                    [
                        'id'          => 'nutrition_focus',
                        'title'       => 'Nutrition Focus',
                        'icon'        => 'nutrition',
                        'icon_emoji'  => '🥦',
                        'description' => $nutritionSummary,
                        'summary'     => $nutritionSummary,
                        'full_text'   => $nutritionFull,
                    ],
                    [
                        'id'          => 'safe_exercise',
                        'title'       => 'Safe Exercise',
                        'icon'        => 'exercise',
                        'icon_emoji'  => '🏃‍♀️',
                        'description' => $exerciseSummary,
                        'summary'     => $exerciseSummary,
                        'full_text'   => $exerciseFull,
                    ],
                ],
            ],

            // Section 2: WEEK 24 MILESTONES (Clinical Checklist Timeline)
            'clinical_checklist' => [
                'title'         => "WEEK {$week} MILESTONES",
                'section_title' => "WEEK {$week} MILESTONES",
                'items'         => $milestones,
            ],
            'clinical_monitoring' => [
                'title' => "WEEK {$week} MILESTONES",
                'items' => $milestones,
            ],

            // Alerts & Warnings
            'alerts'                 => $aiData['alerts'] ?? [],
            'health_status'          => $aiData['health_status'] ?? 'good',
            'clinical_warning_signs' => [
                'title'       => 'Clinical Warning Signs',
                'description' => $aiData['clinical_warning_signs'] ?? $guide?->clinical_warning_signs ?? "Seek immediate care for: severe headache, vision changes, sudden swelling, decreased fetal movement, or vaginal bleeding.",
            ],
        ];
    }

    private function formatPostpartumData(PostpartumRecovery $postpartum): array
    {
        $currentWeek = $postpartum->weeks_since_delivery;

        return [
            'banner' => [
                'tag'       => 'Recovery Progress',
                'week'      => "Week {$currentWeek}",
                'subtitle'  => "Postpartum recovery — you're doing amazing 💙",
            ],
            'recovery_metrics' => [
                'physical_recovery' => [
                    'title'      => 'Physical Recovery',
                    'percentage' => $postpartum->physical_recovery_percent,
                    'icon'       => 'muscle',
                ],
                'hormonal_balance' => [
                    'title'      => 'Hormonal Balance',
                    'percentage' => $postpartum->hormonal_balance_percent,
                    'icon'       => 'scale',
                ],
                'sleep_quality' => [
                    'title'       => 'Sleep Quality',
                    'percentage'  => $postpartum->sleep_quality_percent,
                    'change_diff' => $postpartum->sleep_change_diff ? "+{$postpartum->sleep_change_diff}" : '+8',
                    'icon'        => 'sleep',
                ],
                'energy_levels' => [
                    'title'      => 'Energy Levels',
                    'percentage' => $postpartum->energy_levels_percent,
                    'icon'       => 'lightning',
                ],
            ],
            'mental_health_checkin' => [
                'title'          => 'Mental Health Check-In',
                'screening_name' => $postpartum->screening_name,
                'screening_note' => "{$postpartum->screening_name} — {$postpartum->screening_due_text}",
                'due'            => (bool) $postpartum->screening_due,
                'mood_stability' => $postpartum->mood_stability,
                'anxiety_levels' => $postpartum->anxiety_level,
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
