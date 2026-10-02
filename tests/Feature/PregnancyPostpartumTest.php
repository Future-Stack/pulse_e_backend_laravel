<?php

namespace Tests\Feature;

use App\Models\LifeJourney;
use App\Models\PregnancyMilestone;
use App\Models\PregnancyWeeklyGuide;
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
                    'is_pregnant'       => true,
                    'current_week'      => 24,
                    'current_trimester' => 'Second',
                    'due_date'          => '2027-01-18',
                    'days_until_due'    => 109,
                ],
            ]);

        $this->assertNotEmpty($response->json('data.baby_development'));
        $this->assertNotEmpty($response->json('data.your_body'));
        $this->assertNotEmpty($response->json('data.nutrition_focus'));
        $this->assertNotEmpty($response->json('data.safe_exercises'));
        $this->assertNotEmpty($response->json('data.clinical_monitoring'));
        $this->assertArrayHasKey('alerts', $response->json('data'));
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
                    'is_pregnant'       => true,
                    'current_week'      => 24,
                    'current_trimester' => 'Second',
                    'due_date'          => '2027-01-18',
                    'days_until_due'    => 109,
                ],
            ]);

        $preg = $response->json('data');
        $this->assertTrue($preg['is_pregnant']);
        $this->assertEquals(24, $preg['current_week']);
        $this->assertEquals('Second', $preg['current_trimester']);
        $this->assertEquals('2027-01-18', $preg['due_date']);
        $this->assertEquals(109, $preg['days_until_due']);
        $this->assertStringContainsString('ear of corn', $preg['baby_development']);
        $this->assertStringContainsString('uterus', $preg['your_body']);
        $this->assertStringContainsString('iron-rich', $preg['nutrition_focus']);
        $this->assertStringContainsString('walking', $preg['safe_exercises']);
        $this->assertNotEmpty($preg['clinical_monitoring']);
        $this->assertEquals('Glucose Tolerance Test', $preg['clinical_monitoring'][0]['name']);
        $this->assertStringContainsString('vaginal bleeding', $preg['clinical_warning_signs']);
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
                    'phase'           => 'postpartum',
                    'postpartum_week' => 6,
                ],
            ]);

        $this->assertArrayHasKey('physical_health', $response->json('data'));
        $this->assertArrayHasKey('mental_health_ui', $response->json('data'));
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
                    'phase'           => 'postpartum',
                    'postpartum_week' => 6,
                ],
            ]);

        $postpartum = $response->json('data');
        $this->assertEquals('postpartum', $postpartum['phase']);
        $this->assertEquals(6, $postpartum['postpartum_week']);
        $this->assertEquals(72, $postpartum['physical_health']['physical_recovery_percent']);
        $this->assertEquals(58, $postpartum['physical_health']['hormonal_balance_percent']);
        $this->assertEquals(45, $postpartum['physical_health']['sleep_quality_percent']);
        $this->assertEquals(61, $postpartum['physical_health']['energy_level_percent']);
        $this->assertEquals('Postpartum Wellness Screening', $postpartum['mental_health_ui']['title']);
        $this->assertCount(2, $postpartum['mental_health_ui']['metrics']);
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

        $this->assertArrayHasKey('loss_support', $response->json('data'));
        $this->assertArrayHasKey('care_community', $response->json('data'));
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
                    'phase' => 'postpartum',
                ],
            ]);
        $this->assertEquals(6, $overviewResponse->json('data.postpartum_week'));
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
        \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([
            'https://ai.fightthenumber.com/api/v1/pregnancy/summary*' => Http::response([
                'is_pregnant' => true,
                'current_week' => 28,
                'current_trimester' => 'Third',
                'due_date' => '2026-12-18',
                'days_until_due' => 78,
                'health_status' => 'good',
                'alerts' => [],
                'baby_development' => 'At 28 weeks, your baby is about the size of an eggplant and can open and close their eyes, blink, and has eyelashes.',
                'your_body' => 'At 28 weeks, your uterus has expanded.',
                'nutrition_focus' => 'Iron-rich foods.',
                'safe_exercises' => 'Walking.',
                'clinical_monitoring' => [
                    ['name' => 'Growth Scan', 'week' => 'W32', 'date' => 'Week 32'],
                ],
                'clinical_warning_signs' => 'Contact provider if warning signs appear.',
                'pregnancy_status' => 'active_pregnancy',
                'phase' => 'pregnancy',
                'profile_id' => 39,
                'journey_id' => 5,
                'journey_title' => 'Pregnancy & Postpartum',
                'pregnancy_id' => 7,
            ], 200),
        ]);

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
                    'current_week' => 28,
                    'is_pregnant'  => true,
                ],
            ]);

        $this->assertEquals(28, $summaryResponse->json('data.current_week'));
        $this->assertStringContainsString('eggplant', strtolower($summaryResponse->json('data.baby_development')));
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
                'data'    => null,
            ]);

        // Verify NO dummy record was inserted into database
        $this->assertDatabaseMissing('user_pregnancies', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('postpartum_recoveries', ['user_id' => $user->id]);

        // 2. GET /api/v1/postpartum/recovery
        $postpartumResponse = $this->getJson('/api/v1/postpartum/recovery');
        $postpartumResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => null,
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

        $response = $this->getJson('/api/v1/pregnancy-postpartum/overview?legacy=true');
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

    public function test_user_64_pregnancy_overview_returns_only_pregnancy_data_and_exact_ai_keys(): void
    {
        $user = User::factory()->create(['id' => 64]);
        Sanctum::actingAs($user);

        \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([
            'https://ai.fightthenumber.com/api/v1/pregnancy/summary*' => Http::response([
                'is_pregnant' => true,
                'current_week' => 29,
                'current_trimester' => 'Third',
                'due_date' => '2026-12-18',
                'days_until_due' => 78,
                'last_prenatal_visit' => null,
                'next_appointment' => null,
                'health_status' => 'good',
                'alerts' => [],
                'baby_development' => "At 29 weeks, your little one is about the size of a butternut squash and busy packing on healthy fat, strengthening those tiny muscles, and fine-tuning their brain for all the learning ahead. You might feel more powerful kicks and stretches now — a beautiful reminder of just how strong and capable both of you are becoming. You're doing an amazing job nurturing this incredible life. 💛",
                'your_body' => "At 29 weeks, you may notice your belly feeling tighter as your uterus expands up near your ribs, which can bring on heartburn, shortness of breath, or occasional Braxton Hicks \"practice\" contractions. You might also experience swollen ankles, varicose veins, or trouble sleeping as your body works hard to support your growing baby. Be gentle with yourself—rest when you can, stay hydrated, and remember that these changes are a sign your body is do",
                'nutrition_focus' => "At week 29, prioritize iron-rich foods (lean red meat, lentils, spinach) paired with vitamin C sources like bell peppers or citrus to boost absorption and support your increasing blood volume. Aim for 200mg of DHA daily through salmon, sardines, or an algae-based supplement to support your baby's rapid brain development, and include calcium-rich foods like yogurt or fortified plant milk at each meal (about 1,000mg total)",
                'safe_exercises' => "At 29 weeks, aim for 20-30 minutes of gentle, low-impact activity most days—great options include walking, prenatal yoga, swimming, or stationary cycling. Listen to your body, stay well-hydrated, and avoid exercises lying flat on your back or anything with a fall risk. You're doing an amazing job staying active for you and your baby—keep it up! 💛\n\n*Always check with your healthcare provider before starting or continuing any exercise",
                'clinical_monitoring' => [
                    [
                        'name' => 'Growth Scan',
                        'week' => 'W32',
                        'date' => '2026-10-23',
                    ],
                    [
                        'name' => 'GBS Swab + Birth Plan',
                        'week' => 'W36',
                        'date' => '2026-11-20',
                    ],
                ],
                'clinical_warning_signs' => "# Warning Signs to Watch For at 29 Weeks\n\nContact your healthcare provider promptly if you experience **severe headaches, sudden swelling in your face or hands, vision changes, upper abdominal pain, decreased fetal movement, vaginal bleeding, fluid leakage, or regular contractions**. These can be signs of conditions like preeclampsia or preterm labor that are very treatable when caught early.\n\nTrust your instincts—if something fe",
                'pregnancy_status' => 'active_pregnancy',
                'requires_confirmation' => false,
                'confirmation_needed_for' => null,
                'confirmation_message' => null,
                'phase' => 'pregnancy',
                'profile_id' => 46,
                'journey_id' => 5,
                'journey_title' => 'Pregnancy & Postpartum',
                'pregnancy_id' => 18,
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/pregnancy-postpartum/overview');
        $response->assertStatus(200);

        $preg = $response->json('data');
        $this->assertTrue($preg['is_pregnant']);
        $this->assertEquals(29, $preg['current_week']);
        $this->assertEquals('Third', $preg['current_trimester']);
        $this->assertEquals('2026-12-18', $preg['due_date']);
        $this->assertEquals(78, $preg['days_until_due']);
        $this->assertEmpty($preg['alerts']);
        $this->assertStringContainsString('butternut squash', $preg['baby_development']);
        $this->assertStringContainsString('uterus expands', $preg['your_body']);
        $this->assertStringContainsString('iron-rich', $preg['nutrition_focus']);
        $this->assertStringContainsString('prenatal yoga', $preg['safe_exercises']);
        $this->assertCount(2, $preg['clinical_monitoring']);
        $this->assertEquals('Growth Scan', $preg['clinical_monitoring'][0]['name']);
        $this->assertStringContainsString('severe headaches', $preg['clinical_warning_signs']);

        // Assert postpartum_recoveries table was NEVER touched for User 64
        $this->assertDatabaseMissing('postpartum_recoveries', ['user_id' => 64]);
    }

    public function test_user_59_postpartum_overview_returns_only_postpartum_data_and_exact_ai_keys(): void
    {
        $user = User::factory()->create(['id' => 59]);
        Sanctum::actingAs($user);

        \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([
            'https://ai.fightthenumber.com/api/v1/pregnancy/summary*' => Http::response([
                'status' => 'delivery_completed',
                'phase' => 'postpartum',
                'is_pregnant' => false,
                'pregnancy_completed' => true,
                'delivery_completed' => true,
                'delivery_date' => '2026-09-30',
                'profile_id' => 41,
                'journey_id' => 5,
                'journey_title' => 'Pregnancy & Postpartum',
                'redirect_to' => '/api/v1/postpartum/recovery',
            ], 200),
            'https://ai.fightthenumber.com/api/v1/postpartum/recovery*' => Http::response([
                'phase' => 'postpartum',
                'profile_id' => 41,
                'journey_id' => 5,
                'journey_title' => 'Pregnancy & Postpartum',
                'delivery_date' => '2026-09-30',
                'days_postpartum' => 1,
                'postpartum_week' => 0,
                'recovery_status' => 'early',
                'delivery_method' => 'vaginal',
                'physical_health' => [
                    'physical_recovery_percent' => 20,
                    'bleeding_level' => 'moderate',
                    'incision_healing' => null,
                    'pelvic_floor_status' => 'healing',
                    'hormonal_balance_percent' => 30,
                    'energy_level_percent' => 40,
                    'sleep_quality_percent' => 45,
                ],
                'mental_health' => [
                    'mood_stability' => 60,
                    'anxiety_level' => 5,
                    'depression_screening' => 'low_risk',
                    'last_mood_entry' => null,
                    'mood_trend' => 'stable',
                    'supportive_resources' => [
                        'Postpartum Support Group',
                        'Mental Health Hotline',
                    ],
                ],
                'mental_health_ui' => [
                    'screening_type' => 'mental_health_check_in',
                    'title' => 'Postpartum Wellness Screening',
                    'week' => 0,
                    'risk_level' => 'moderate',
                    'trend' => 'new',
                    'metrics' => [
                        [
                            'label' => 'Mood stability',
                            'value' => 'Stable',
                            'score' => 60,
                            'trend_arrow' => '→',
                        ],
                        [
                            'label' => 'Anxiety levels',
                            'value' => 'Mild',
                            'score' => 5,
                            'warning' => false,
                        ],
                        [
                            'label' => 'Depression risk',
                            'value' => 'low_risk',
                            'risk_increased' => false,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/pregnancy-postpartum/overview');
        $response->assertStatus(200);

        $post = $response->json('data');
        $this->assertEquals('postpartum', $post['phase']);
        $this->assertEquals(41, $post['profile_id']);
        $this->assertEquals(5, $post['journey_id']);
        $this->assertEquals('Pregnancy & Postpartum', $post['journey_title']);
        $this->assertEquals('2026-09-30', $post['delivery_date']);
        $this->assertEquals(1, $post['days_postpartum']);
        $this->assertEquals(0, $post['postpartum_week']);
        $this->assertEquals('early', $post['recovery_status']);
        $this->assertEquals('vaginal', $post['delivery_method']);

        // Physical health dynamic values
        $this->assertEquals(20, $post['physical_health']['physical_recovery_percent']);
        $this->assertEquals('moderate', $post['physical_health']['bleeding_level']);
        $this->assertEquals(30, $post['physical_health']['hormonal_balance_percent']);
        $this->assertEquals(40, $post['physical_health']['energy_level_percent']);
        $this->assertEquals(45, $post['physical_health']['sleep_quality_percent']);

        // Mental health dynamic values
        $this->assertEquals(60, $post['mental_health']['mood_stability']);
        $this->assertEquals(5, $post['mental_health']['anxiety_level']);
        $this->assertEquals('low_risk', $post['mental_health']['depression_screening']);
        $this->assertCount(2, $post['mental_health']['supportive_resources']);

        // Mental health UI metrics
        $this->assertEquals('Postpartum Wellness Screening', $post['mental_health_ui']['title']);
        $this->assertCount(3, $post['mental_health_ui']['metrics']);
    }
}
