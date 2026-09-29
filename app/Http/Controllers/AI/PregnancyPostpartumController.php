<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\PostpartumRecovery;
use App\Models\PregnancyMilestone;
use App\Models\PregnancyWeeklyGuide;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserPregnancy;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PregnancyPostpartumController extends Controller
{
    /**
     * Unified Overview for Pregnancy, Postpartum, and Support tabs.
     * GET /api/v1/pregnancy-postpartum/overview
     */
    public function overview(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? $request->query('user_id')
            ?? 1;

        $user = User::find($userId);
        $tab = strtolower($request->input('tab') ?? $request->query('tab') ?? 'pregnancy');

        // Fetch or create user's pregnancy record
        $pregnancy = $this->getOrCreatePregnancy((int) $userId);
        $postpartum = $this->getOrCreatePostpartum((int) $userId, $pregnancy);

        $data = [
            'active_tab' => $tab,
            'header' => [
                'title' => 'Pregnancy & Postpartum',
                'subtitle' => "Week {$pregnancy->current_week} of 40 · {$pregnancy->days_to_due_date} days to due date",
            ],
        ];

        if ($tab === 'postpartum') {
            $data['postpartum'] = $this->formatPostpartumData($postpartum);
        } elseif ($tab === 'support') {
            $data['support'] = $this->formatSupportData();
        } else {
            // Default: pregnancy
            $data['pregnancy'] = $this->formatPregnancyData($pregnancy);
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ], 200);
    }

    /**
     * Setup or Update Pregnancy Profile.
     * POST /api/v1/pregnancy/setup
     */
    public function setup(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? 1;

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

        $this->ensureDefaultMilestones($pregnancy);

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
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? 1;

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
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? 1;

        $pregnancy = UserPregnancy::where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($pregnancy) {
            $pregnancy->status = 'miscarriage';
            $pregnancy->ended_at = now();
            $pregnancy->notes = $request->input('notes', 'Reported via Miscarriage dialog.');
            $pregnancy->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'We are deeply sorry for your loss. We have updated your space for compassionate care and healing.',
            'data'    => [
                'status'              => 'miscarriage',
                'active_tab'          => 'support',
                'healing_mode_active' => true,
                'support_resources'   => $this->formatSupportData(),
            ],
        ], 200);
    }

    /**
     * Postpartum Journey Modal Trigger: "Yes, I did"
     * POST /api/v1/pregnancy/complete-journey
     */
    public function completeJourney(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? 1;

        $pregnancy = UserPregnancy::where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->first();

        $deliveryDate = $request->input('delivery_date') ?? now()->toDateString();

        if ($pregnancy) {
            $pregnancy->status = 'completed';
            $pregnancy->delivery_date = $deliveryDate;
            $pregnancy->save();
        }

        $postpartum = PostpartumRecovery::updateOrCreate(
            ['user_id' => $userId],
            [
                'pregnancy_id'  => $pregnancy?->id,
                'delivery_date' => $deliveryDate,
                'current_week'  => 6,
            ]
        );

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
     * Check-in / Update Postpartum recovery metrics & mental health.
     * POST /api/v1/postpartum/checkin
     */
    public function checkinPostpartum(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? 1;

        $postpartum = PostpartumRecovery::firstOrCreate(
            ['user_id' => $userId],
            [
                'delivery_date' => now()->subWeeks(6)->toDateString(),
                'current_week'  => 6,
            ]
        );

        $postpartum->fill($request->only([
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

        $postpartum->save();

        return response()->json([
            'success' => true,
            'message' => 'Postpartum recovery check-in saved successfully.',
            'data'    => $this->formatPostpartumData($postpartum),
        ], 200);
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

        if (!$pregnancy) {
            // Seed a realistic active pregnancy matching the design (Week 24, 112 days to due date)
            $dueDate = Carbon::now()->addDays(112)->toDateString();
            $pregnancy = UserPregnancy::create([
                'user_id'                    => $userId,
                'due_date'                   => $dueDate,
                'last_menstrual_period_date' => Carbon::now()->subWeeks(24)->toDateString(),
                'status'                     => 'active',
            ]);
        }

        $this->ensureDefaultMilestones($pregnancy);

        return $pregnancy;
    }

    private function ensureDefaultMilestones(UserPregnancy $pregnancy): void
    {
        $existingCount = PregnancyMilestone::where('pregnancy_id', $pregnancy->id)->count();
        if ($existingCount > 0) {
            return;
        }

        $milestones = [
            ['title' => 'Anatomy Scan', 'target_week' => 20, 'date_label' => 'W20 · Oct 2', 'is_completed' => true],
            ['title' => 'Glucose Tolerance Test', 'target_week' => 24, 'date_label' => 'W24 · Nov 8 (Today)', 'is_completed' => true],
            ['title' => 'Anti-D Injection', 'target_week' => 28, 'date_label' => 'W28 · Dec 6', 'is_completed' => true],
            ['title' => 'Growth Scan', 'target_week' => 32, 'date_label' => 'W32 · Jan 3', 'is_completed' => true],
            ['title' => 'GBS Swab + Birth Plan', 'target_week' => 36, 'date_label' => 'W36 · Jan 31', 'is_completed' => true],
        ];

        foreach ($milestones as $m) {
            PregnancyMilestone::create([
                'pregnancy_id' => $pregnancy->id,
                'user_id'      => $pregnancy->user_id,
                'title'        => $m['title'],
                'target_week'  => $m['target_week'],
                'date_label'   => $m['date_label'],
                'is_completed' => $m['is_completed'],
                'completed_at' => $m['is_completed'] ? now() : null,
            ]);
        }
    }

    private function getOrCreatePostpartum(int $userId, ?UserPregnancy $pregnancy): PostpartumRecovery
    {
        $postpartum = PostpartumRecovery::where('user_id', $userId)->latest()->first();
        if (!$postpartum) {
            $postpartum = PostpartumRecovery::create([
                'user_id'                    => $userId,
                'pregnancy_id'               => $pregnancy?->id,
                'delivery_date'              => Carbon::now()->subWeeks(6)->toDateString(),
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
        return $postpartum;
    }

    private function formatPregnancyData(UserPregnancy $pregnancy): array
    {
        $week = $pregnancy->current_week;
        $guide = PregnancyWeeklyGuide::where('week_number', $week)->first()
            ?? PregnancyWeeklyGuide::where('week_number', 24)->first();

        $babyComparison = $guide?->baby_size_comparison ?? 'an ear of corn';
        $approxSize = $guide?->approx_size_text ?? 'about 30cm, 600g';
        $babyDev = $guide?->baby_development ?? 'Lungs developing rapidly. Eyes partially open. Responds to sound.';
        $yourBody = $guide?->your_body ?? 'Uterus now above belly button. Braxton Hicks contractions may begin.';
        $nutrition = $guide?->nutrition_focus ?? 'Iron & Omega-3 critical. Aim for 300 extra calories/day.';
        $exercise = $guide?->safe_exercise ?? 'Swimming, walking, prenatal yoga all safe and beneficial.';
        $warning = $guide?->clinical_warning_signs ?? 'Seek immediate care for: severe headache, vision changes, sudden swelling, decreased fetal movement, or vaginal bleeding.';

        $milestones = PregnancyMilestone::where('pregnancy_id', $pregnancy->id)
            ->orderBy('target_week', 'asc')
            ->get()
            ->map(fn($m) => [
                'id'           => $m->id,
                'title'        => $m->title,
                'target_week'  => $m->target_week,
                'date_label'   => $m->date_label,
                'is_completed' => $m->is_completed,
            ]);

        return [
            'card' => [
                'week_label'        => "W{$week}",
                'total_weeks'       => 40,
                'trimester'         => $guide?->trimester ?? $pregnancy->trimester,
                'baby_size_text'    => "Baby is the size of {$babyComparison} — {$approxSize}",
                'due_date'          => Carbon::parse($pregnancy->due_date)->format('F d, Y'),
                'days_remaining'    => $pregnancy->days_to_due_date,
            ],
            'action_modals' => [
                'pregnancy_loss' => [
                    'title'               => 'Have you experience a miscarriage?',
                    'description'         => "We're so sorry if you did. We can update your space to support your healing journey.",
                    'confirm_button_text' => 'Yes, I did',
                    'cancel_button_text'  => 'No, continue as normal',
                    'endpoint'            => '/api/v1/pregnancy/report-loss',
                ],
                'postpartum_journey' => [
                    'title'               => 'Have you recently completed your pregnancy?',
                    'description'         => 'Congratulations! We can update your journey to support you through the postpartum phase.',
                    'confirm_button_text' => 'Yes, I did',
                    'cancel_button_text'  => 'No, continue as normal',
                    'endpoint'            => '/api/v1/pregnancy/complete-journey',
                ],
            ],
            'week_milestones' => [
                'baby_development' => [
                    'title' => 'Baby Development',
                    'icon'  => 'baby',
                    'desc'  => $babyDev,
                ],
                'your_body' => [
                    'title' => 'Your Body',
                    'icon'  => 'heart',
                    'desc'  => $yourBody,
                ],
                'nutrition_focus' => [
                    'title' => 'Nutrition Focus',
                    'icon'  => 'nutrition',
                    'desc'  => $nutrition,
                ],
                'safe_exercise' => [
                    'title' => 'Safe Exercise',
                    'icon'  => 'exercise',
                    'desc'  => $exercise,
                ],
            ],
            'clinical_checklist' => [
                'title' => "WEEK {$week} MILESTONES",
                'items' => $milestones,
            ],
            'clinical_warning_signs' => [
                'title'       => 'Clinical Warning Signs',
                'description' => $warning,
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
                    'change_diff' => "+{$postpartum->sleep_change_diff}",
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
                'due'            => $postpartum->screening_due,
                'mood_stability' => $postpartum->mood_stability,
                'anxiety_levels' => $postpartum->anxiety_level,
            ],
        ];
    }

    private function formatSupportData(): array
    {
        return [
            'loss_support' => [
                'title'       => 'Loss Support',
                'description' => "We hold space for all pregnancy journeys. If you've experienced a loss, compassionate resources and personalized logging modes are available.",
            ],
            'care_community' => [
                [
                    'id'            => 1,
                    'title'         => 'Prenatal Support Group',
                    'members_count' => '2,847 members',
                    'tag'           => 'prenatal',
                    'icon'          => 'group_flower',
                ],
                [
                    'id'            => 2,
                    'title'         => 'New Parent Circle',
                    'members_count' => '5,124 members',
                    'tag'           => 'new_parent',
                    'icon'          => 'group_baby',
                ],
                [
                    'id'            => 3,
                    'title'         => 'Loss & Healing Space',
                    'members_count' => '892 members',
                    'tag'           => 'loss_healing',
                    'icon'          => 'group_dove',
                ],
                [
                    'id'            => 4,
                    'title'         => 'Birth Trauma Support',
                    'members_count' => '1,203 members',
                    'tag'           => 'birth_trauma',
                    'icon'          => 'group_heart',
                ],
            ],
        ];
    }
}
