<?php

namespace Tests\Feature;

use App\Models\LifeJourney;
use App\Models\PregnancyMilestone;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserPregnancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PregnancyPostpartumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'https://ai.fightthenumber.com/api/v1/pregnancy/summary*' => Http::response([
                'is_pregnant' => true,
                'current_week' => 24,
                'current_trimester' => 'Second',
                'due_date' => '2027-01-18',
                'days_until_due' => 109,
                'last_prenatal_visit' => null,
                'next_appointment' => null,
                'health_status' => 'good',
                'alerts' => [
                    [
                        'message' => 'Glucose tolerance test due this week',
                        'severity' => 'medium',
                        'action_required' => true,
                    ],
                ],
                'baby_development' => "At 24 weeks, your baby is about the size of an ear of corn and is busy developing tiny taste buds, practicing breathing movements, and even responding to the sound of your voice! Their inner ear is now fully formed, so they can sense balance and may react to your movements with little kicks and wiggles. You're doing an amazing job nurturing this incredible life—every day, your body is creating something truly extraordinary. 💛",
                'your_body' => "At 24 weeks, your uterus is now about the size of a soccer ball, reaching above your belly button, and you may notice your belly growing more rounded and pronounced. You might also experience some new sensations like mild swelling in your feet, occasional Braxton Hicks contractions, or slight changes in balance as your center of gravity shifts. Remember, every body adjusts to pregnancy differently, so be gentle with yourself and celebrate the amazing work your body is",
                'nutrition_focus' => "At week 24, focus on iron-rich foods (lean red meat, spinach, lentils) paired with vitamin C sources like bell peppers or citrus to boost absorption and support your increasing blood volume. Aim for 25-30g of fiber daily from whole grains, fruits, and vegetables to combat common constipation, and include calcium-rich foods like yogurt or fortified plant milks (about 1,000mg daily) for your baby's rapid bone development",
                'safe_exercises' => "At 24 weeks, aim for about 20-30 minutes of gentle, low-impact activity most days—walking, swimming, prenatal yoga, or stationary cycling are all excellent choices that support your changing body. Avoid exercises lying flat on your back, contact sports, or anything with a fall risk, and listen to your body by slowing down if you feel dizzy, breathless, or uncomfortable. You're doing something wonderful for both you and your baby—ke",
                'clinical_monitoring' => [
                    ['name' => 'Glucose Tolerance Test', 'week' => 'W24', 'date' => 'Week 24'],
                    ['name' => 'Anti-D Injection', 'week' => 'W28', 'date' => 'Week 28'],
                    ['name' => 'Growth Scan', 'week' => 'W32', 'date' => 'Week 32'],
                    ['name' => 'GBS Swab + Birth Plan', 'week' => 'W36', 'date' => 'Week 36'],
                ],
                'clinical_warning_signs' => "Contact your healthcare provider promptly if you experience heavy vaginal bleeding, severe abdominal pain, or reduced movements.",
                'pregnancy_status' => 'active_pregnancy',
                'phase' => 'pregnancy',
                'profile_id' => 39,
                'journey_id' => 5,
                'journey_title' => 'Pregnancy & Postpartum',
                'pregnancy_id' => 7,
            ], 200),
            'https://ai.fightthenumber.com/api/v1/postpartum/recovery*' => Http::response([
                'postpartum_week' => 6,
                'delivery_date' => '2026-08-15',
                'physical_health' => [
                    'physical_recovery_percent' => 72,
                    'hormonal_balance_percent' => 58,
                    'sleep_quality_percent' => 45,
                    'energy_level_percent' => 61,
                ],
                'mental_health_ui' => [
                    'title' => 'Postpartum Wellness Screening',
                    'metrics' => [
                        ['label' => 'Mood Stability', 'value' => 'Stable'],
                        ['label' => 'Anxiety Level', 'value' => 'Mild'],
                    ],
                ],
            ], 200),
            'https://ai.fightthenumber.com/api/v1/support/insights*' => Http::response([
                'user_id' => 16,
                'phase' => 'postpartum',
                'week' => 6,
                'insights' => [
                    [
                        'type' => 'recovery_progress',
                        'category' => 'physical',
                        'title' => 'Your Recovery Progress',
                        'content' => 'At six weeks postpartum, your physical recovery is tracking beautifully at 72%.',
                        'sentiment' => 'supportive',
                        'priority' => 'high',
                    ],
                ],
                'generated_at' => '2026-09-29',
            ], 200),
        ]);
    }

    public function test_unauthenticated_requests_are_blocked(): void
    {
        $response = $this->getJson("/api/v1/pregnancy/summary");
        $response->assertStatus(401);

        $overviewResponse = $this->getJson("/api/v1/pregnancy-postpartum/overview");
        $overviewResponse->assertStatus(401);
    }

    public function test_pregnancy_overview_returns_correct_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Access overview authenticated via Sanctum token, no user_id param required
        $response = $this->getJson("/api/v1/pregnancy-postpartum/overview?tab=pregnancy");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_tab' => 'pregnancy',
                    'header' => [
                        'title' => 'Pregnancy & Postpartum',
                    ],
                ],
            ]);

        $this->assertArrayHasKey('card', $response->json('data.pregnancy'));
        $this->assertArrayHasKey('week_milestones', $response->json('data.pregnancy'));
        $this->assertArrayHasKey('clinical_checklist', $response->json('data.pregnancy'));
        $this->assertArrayHasKey('action_buttons', $response->json('data.pregnancy'));
        $this->assertArrayHasKey('alerts', $response->json('data.pregnancy'));
    }

    public function test_pregnancy_summary_endpoint_works(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Access summary authenticated via Sanctum token
        $response = $this->getJson("/api/v1/pregnancy/summary");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_tab' => 'pregnancy',
                    'header' => [
                        'title' => 'Pregnancy & Postpartum',
                    ],
                ],
            ]);

        $preg = $response->json('data.pregnancy');
        $this->assertArrayHasKey('card', $preg);
        $this->assertEquals(24, $preg['card']['current_week']);
        $this->assertEquals('Second Trimester', $preg['card']['trimester']);
        $this->assertEquals(60, $preg['card']['progress_percentage']);
        $this->assertStringContainsString('ear of corn', $preg['card']['baby_size_text']);
        $this->assertArrayHasKey('week_circle', $preg['card']);

        // Action buttons
        $this->assertArrayHasKey('action_buttons', $preg);
        $this->assertEquals('pregnancy_loss', $preg['action_buttons'][0]['id']);
        $this->assertEquals('postpartum_journey', $preg['action_buttons'][1]['id']);

        // 4 milestone guidance cards
        $this->assertArrayHasKey('week_milestones', $preg);
        $this->assertArrayHasKey('baby_development', $preg['week_milestones']);
        $this->assertArrayHasKey('your_body', $preg['week_milestones']);
        $this->assertArrayHasKey('nutrition_focus', $preg['week_milestones']);
        $this->assertArrayHasKey('safe_exercise', $preg['week_milestones']);

        // Clinical timeline items
        $this->assertNotEmpty($preg['clinical_checklist']['items']);
        $titles = collect($preg['clinical_checklist']['items'])->pluck('title')->toArray();
        $this->assertContains('Anatomy Scan', $titles);
        $this->assertContains('Glucose Tolerance Test', $titles);
        $this->assertContains('Anti-D Injection', $titles);
        $this->assertContains('Growth Scan', $titles);
        $this->assertContains('GBS Swab + Birth Plan', $titles);

        // Alerts & warning signs
        $this->assertNotEmpty($preg['alerts']);
        $this->assertArrayHasKey('clinical_warning_signs', $preg);

        // Exact AI response keys directly on data
        $data = $response->json('data');
        $this->assertTrue($data['is_pregnant']);
        $this->assertEquals(24, $data['current_week']);
        $this->assertEquals('Second', $data['current_trimester']);
        $this->assertEquals('2027-01-18', $data['due_date']);
        $this->assertEquals(109, $data['days_until_due']);
        $this->assertStringContainsString('ear of corn', $data['baby_development']);
        $this->assertStringContainsString('uterus', $data['your_body']);
        $this->assertStringContainsString('iron-rich', $data['nutrition_focus']);
        $this->assertStringContainsString('walking', $data['safe_exercises']);
        $this->assertNotEmpty($data['clinical_monitoring']);
        $this->assertEquals('Glucose Tolerance Test', $data['clinical_monitoring'][0]['name']);
        $this->assertStringContainsString('vaginal bleeding', $data['clinical_warning_signs']);
    }

    public function test_postpartum_tab_returns_recovery_metrics(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/pregnancy-postpartum/overview?tab=postpartum");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_tab' => 'postpartum',
                ],
            ]);

        $this->assertArrayHasKey('recovery_metrics', $response->json('data.postpartum'));
        $this->assertArrayHasKey('mental_health_checkin', $response->json('data.postpartum'));
    }

    public function test_postpartum_recovery_endpoint_works(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/postpartum/recovery");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_tab' => 'postpartum',
                ],
            ]);

        $postpartum = $response->json('data.postpartum');
        $this->assertArrayHasKey('banner', $postpartum);
        $this->assertEquals('Recovery Progress', $postpartum['banner']['tag']);
        $this->assertStringContainsString("you're doing amazing", $postpartum['banner']['subtitle']);

        // 4 Recovery Metrics matching UI
        $this->assertArrayHasKey('recovery_metrics', $postpartum);
        $this->assertArrayHasKey('physical_recovery', $postpartum['recovery_metrics']);
        $this->assertArrayHasKey('hormonal_balance', $postpartum['recovery_metrics']);
        $this->assertArrayHasKey('sleep_quality', $postpartum['recovery_metrics']);
        $this->assertArrayHasKey('energy_levels', $postpartum['recovery_metrics']);
        $this->assertNotEmpty($postpartum['recovery_metrics']['items']);

        // Mental Health Check-In matching UI
        $this->assertArrayHasKey('mental_health_checkin', $postpartum);
        $this->assertEquals('Postpartum Wellness Screening', $postpartum['mental_health_checkin']['screening_name']);
        $this->assertEquals('Stable', $postpartum['mental_health_checkin']['mood_stability']);
        $this->assertEquals('Mild', $postpartum['mental_health_checkin']['anxiety_levels']);
    }

    public function test_support_tab_returns_care_community(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/pregnancy-postpartum/overview?tab=support");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_tab' => 'support',
                ],
            ]);

        $this->assertArrayHasKey('loss_support', $response->json('data.support'));
        $this->assertArrayHasKey('care_community', $response->json('data.support'));
    }

    public function test_milestone_checklist_can_be_toggled(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/pregnancy-postpartum/overview?tab=pregnancy");

        $milestone = PregnancyMilestone::where('user_id', $user->id)->first();
        $this->assertNotNull($milestone);

        $initialStatus = $milestone->is_completed;

        $response = $this->postJson("/api/v1/pregnancy/milestones/{$milestone->id}/toggle");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'milestone' => [
                    'id' => $milestone->id,
                    'is_completed' => !$initialStatus,
                ],
            ]);
    }

    public function test_report_loss_modal_handles_miscarriage(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/pregnancy-postpartum/overview?tab=pregnancy");

        $response = $this->postJson("/api/v1/pregnancy/report-loss", [
            'notes' => 'Miscarriage confirmed by doctor.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'miscarriage',
                    'active_tab' => 'support',
                    'healing_mode_active' => true,
                ],
            ]);
    }

    public function test_complete_journey_modal_transitions_to_postpartum(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/pregnancy-postpartum/overview?tab=pregnancy");

        $response = $this->postJson("/api/v1/pregnancy/complete-journey");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_tab' => 'postpartum',
                ],
            ]);
    }

    public function test_report_loss_fails_without_active_pregnancy(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Ensure no active pregnancy exists
        UserPregnancy::where('user_id', $user->id)->delete();

        $response = $this->postJson("/api/v1/pregnancy/report-loss", [
            'notes' => 'Attempted report without active pregnancy',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_overview_maintains_miscarriage_state_and_healing_mode(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/pregnancy-postpartum/overview?tab=pregnancy");

        // Report loss
        $this->postJson("/api/v1/pregnancy/report-loss");

        // Fetch overview after miscarriage
        $overviewResponse = $this->getJson("/api/v1/pregnancy-postpartum/overview");

        $overviewResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_tab' => 'support',
                    'status' => 'miscarriage',
                    'healing_mode_active' => true,
                ],
            ]);
    }

    public function test_overview_defaults_to_postpartum_for_postpartum_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/pregnancy-postpartum/overview?tab=pregnancy");

        // Transition to postpartum
        $this->postJson("/api/v1/pregnancy/complete-journey");

        // Fetch overview without passing any tab param
        $overviewResponse = $this->getJson("/api/v1/pregnancy-postpartum/overview");

        $overviewResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_sub_stage' => 'postpartum',
                    'active_tab'     => 'postpartum',
                ],
            ]);
        $this->assertArrayHasKey('postpartum', $overviewResponse->json('data'));
    }

    public function test_support_insights_endpoint_returns_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/support/insights");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
        $this->assertArrayHasKey('insights', $response->json('data'));
    }

    public function test_care_communities_endpoint_returns_data_with_counts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/pregnancy/care-communities");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
        $this->assertIsArray($response->json('data'));
        $this->assertNotEmpty($response->json('data'));
        $this->assertArrayHasKey('posts_count', $response->json('data.0'));
        $this->assertArrayHasKey('members_count', $response->json('data.0'));
        $this->assertArrayHasKey('tag', $response->json('data.0'));
    }

    public function test_pregnancy_setup_dynamically_updates_week_baby_size_and_milestones(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 1. Initially set up pregnancy at Week 24 (due_date ~ 112 days in future)
        $this->getJson("/api/v1/pregnancy/summary");

        // 2. User updates pregnancy with due_date: 2026-12-18 (Week 28)
        $setupResponse = $this->postJson("/api/v1/pregnancy/setup", [
            'due_date' => '2026-12-18',
            'last_menstrual_period_date' => '2026-03-10',
            'conception_date' => '2026-03-24',
        ]);

        $setupResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'card' => [
                        'current_week' => 28,
                        'trimester' => 'Third Trimester',
                        'progress_percentage' => 70,
                        'due_date' => '2026-12-18',
                    ],
                ],
            ]);

        $card = $setupResponse->json('data.card');
        // Dynamic baby size for Week 28 must be eggplant, NOT ear of corn!
        $this->assertStringContainsString('eggplant', strtolower($card['baby_size_comparison']));
        $this->assertStringContainsString('eggplant', strtolower($card['baby_size_text']));

        // Week milestones section title and cards must be Week 28
        $milestones = $setupResponse->json('data.week_milestones');
        $this->assertEquals('WEEK 28 MILESTONES', $milestones['section_title']);
        $this->assertStringContainsString('blink', strtolower($milestones['baby_development']['desc']));

        // Clinical checklist dates must be dynamically recalculated for the new due date
        $items = $setupResponse->json('data.clinical_checklist.items');
        $this->assertNotEmpty($items);

        $w24 = collect($items)->firstWhere('target_week', 24);
        $w28 = collect($items)->firstWhere('target_week', 28);
        $w32 = collect($items)->firstWhere('target_week', 32);

        $this->assertNotNull($w24);
        $this->assertNotNull($w28);
        $this->assertNotNull($w32);

        // W28 Anti-D Injection must be current milestone
        $this->assertTrue($w28['is_current']);
        $this->assertStringContainsString('(Today)', $w28['date']);

        // W24 Glucose Tolerance Test must NOT be current
        $this->assertFalse($w24['is_current']);
        $this->assertStringNotContainsString('(Today)', $w24['date']);

        // 3. GET /api/v1/pregnancy/summary must return the dynamically updated week 28 state
        $summaryResponse = $this->getJson("/api/v1/pregnancy/summary");
        $summaryResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'life_stage' => 'Pregnancy & Postpartum',
                    'current_stage' => 'pregnancy',
                    'user_sub_stage' => 'pregnancy',
                    'active_tab' => 'pregnancy',
                    'header' => [
                        'title' => 'Pregnancy & Postpartum',
                    ],
                ],
            ]);

        $this->assertEquals(28, $summaryResponse->json('data.pregnancy.card.current_week'));
    }

    public function test_null_state_returns_null_without_dummy_data_when_user_has_no_pregnancy(): void
    {
        // Reset Http fake callbacks from setUp()
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([
            'https://ai.fightthenumber.com/api/v1/pregnancy/summary*' => Http::response([
                'is_pregnant' => false,
                'phase' => null,
            ], 200),
            'https://ai.fightthenumber.com/api/v1/postpartum/recovery*' => Http::response([], 404),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 1. GET /api/v1/pregnancy/summary
        $summaryResponse = $this->getJson('/api/v1/pregnancy/summary');
        $summaryResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_tab'  => 'pregnancy',
                    'is_pregnant' => false,
                    'pregnancy'   => null,
                    'header' => [
                        'subtitle' => null,
                    ],
                ],
            ]);

        // Verify NO dummy record was inserted into database
        $this->assertDatabaseMissing('user_pregnancies', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('postpartum_recoveries', ['user_id' => $user->id]);

        // 2. GET /api/v1/postpartum/recovery
        $postpartumResponse = $this->getJson('/api/v1/postpartum/recovery');
        $postpartumResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_tab' => 'postpartum',
                    'postpartum' => null,
                    'header' => [
                        'subtitle' => null,
                    ],
                ],
            ]);

        $this->assertDatabaseMissing('postpartum_recoveries', ['user_id' => $user->id]);
    }

    public function test_multiple_life_stages_from_life_journey_profile_resolves_correctly_without_wrong_fallback(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create 2 life journeys in DB: Beauty & Radiance AND Pregnancy & Postpartum
        $beautyJourney = LifeJourney::create([
            'id'    => 1,
            'title' => 'Beauty & Radiance',
        ]);
        $pregnancyJourney = LifeJourney::create([
            'id'    => 5,
            'title' => 'Pregnancy & Postpartum',
        ]);

        // User has a profile, and selected MULTIPLE journeys in life_journey_profile:
        // [Beauty & Radiance, Pregnancy & Postpartum]
        $profile = Profile::create([
            'user_id' => $user->id,
        ]);
        $profile->lifeJourneys()->attach([$beautyJourney->id, $pregnancyJourney->id]);

        $response = $this->getJson('/api/v1/pregnancy-postpartum/overview');
        $response->assertStatus(200);

        // life_stage and header title must NEVER be "Beauty & Radiance"!
        $this->assertEquals('Pregnancy & Postpartum', $response->json('data.life_stage'));
        $this->assertEquals('Pregnancy & Postpartum', $response->json('data.header.title'));

        // life_stages must contain both user life stages from life_journey_profile
        $lifeStages = $response->json('data.life_stages');
        $this->assertCount(2, $lifeStages);
        $titles = collect($lifeStages)->pluck('title')->toArray();
        $this->assertContains('Beauty & Radiance', $titles);
        $this->assertContains('Pregnancy & Postpartum', $titles);

        // The pregnancy journey must have is_current = true
        $pregItem = collect($lifeStages)->firstWhere('title', 'Pregnancy & Postpartum');
        $this->assertTrue($pregItem['is_current']);

        $beautyItem = collect($lifeStages)->firstWhere('title', 'Beauty & Radiance');
        $this->assertFalse($beautyItem['is_current']);
    }
}
