<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPregnancy;
use App\Models\PregnancyMilestone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PregnancyPostpartumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
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
    public function test_pregnancy_overview_returns_correct_data(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->getJson("/api/v1/pregnancy-postpartum/overview?user_id={$user->id}&tab=pregnancy");

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
    }

    public function test_postpartum_tab_returns_recovery_metrics(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->getJson("/api/v1/pregnancy-postpartum/overview?user_id={$user->id}&tab=postpartum");

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

    public function test_support_tab_returns_care_community(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->getJson("/api/v1/pregnancy-postpartum/overview?user_id={$user->id}&tab=support");

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
        $user = User::first() ?? User::factory()->create();
        $this->getJson("/api/v1/pregnancy-postpartum/overview?user_id={$user->id}&tab=pregnancy");

        $milestone = PregnancyMilestone::where('user_id', $user->id)->first();
        $this->assertNotNull($milestone);

        $initialStatus = $milestone->is_completed;

        $response = $this->postJson("/api/v1/pregnancy/milestones/{$milestone->id}/toggle", [
            'user_id' => $user->id,
        ]);

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
        $user = User::first() ?? User::factory()->create();
        $this->getJson("/api/v1/pregnancy-postpartum/overview?user_id={$user->id}&tab=pregnancy");

        $response = $this->postJson("/api/v1/pregnancy/report-loss", [
            'user_id' => $user->id,
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
        $user = User::first() ?? User::factory()->create();
        $this->getJson("/api/v1/pregnancy-postpartum/overview?user_id={$user->id}&tab=pregnancy");

        $response = $this->postJson("/api/v1/pregnancy/complete-journey", [
            'user_id' => $user->id,
        ]);

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
        $user = User::first() ?? User::factory()->create();

        // Ensure no active pregnancy exists
        UserPregnancy::where('user_id', $user->id)->delete();

        $response = $this->postJson("/api/v1/pregnancy/report-loss", [
            'user_id' => $user->id,
            'notes' => 'Attempted report without active pregnancy',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_overview_maintains_miscarriage_state_and_healing_mode(): void
    {
        $user = User::first() ?? User::factory()->create();
        $this->getJson("/api/v1/pregnancy-postpartum/overview?user_id={$user->id}&tab=pregnancy");

        // Report loss
        $this->postJson("/api/v1/pregnancy/report-loss", [
            'user_id' => $user->id,
        ]);

        // Fetch overview after miscarriage
        $overviewResponse = $this->getJson("/api/v1/pregnancy-postpartum/overview?user_id={$user->id}");

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
        $user = User::first() ?? User::factory()->create();
        $this->getJson("/api/v1/pregnancy-postpartum/overview?user_id={$user->id}&tab=pregnancy");

        // Transition to postpartum
        $this->postJson("/api/v1/pregnancy/complete-journey", [
            'user_id' => $user->id,
        ]);

        // Fetch overview without passing any tab param
        $overviewResponse = $this->getJson("/api/v1/pregnancy-postpartum/overview?user_id={$user->id}");

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
        $user = User::first() ?? User::factory()->create();

        $response = $this->getJson("/api/v1/support/insights?user_id={$user->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
        $this->assertArrayHasKey('insights', $response->json('data'));
    }

    public function test_care_communities_endpoint_returns_data_with_counts(): void
    {
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
}
