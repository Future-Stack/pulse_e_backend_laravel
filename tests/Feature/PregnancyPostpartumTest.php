<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPregnancy;
use App\Models\PregnancyMilestone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PregnancyPostpartumTest extends TestCase
{
    use RefreshDatabase;
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
}
