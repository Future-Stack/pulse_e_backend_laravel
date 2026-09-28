<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerimenopauseTest extends TestCase
{
    use RefreshDatabase;
    public function test_perimenopause_overview_symptoms_tab(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->getJson("/api/v1/perimenopause/overview?user_id={$user->id}&tab=symptoms");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_tab' => 'symptoms',
                    'header' => [
                        'title' => 'Perimenopause',
                    ],
                ],
            ]);

        $this->assertArrayHasKey('vasomotor_tracker', $response->json('data.symptoms'));
        $this->assertArrayHasKey('gsm', $response->json('data.symptoms'));
    }

    public function test_perimenopause_overview_insights_tab(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->getJson("/api/v1/perimenopause/overview?user_id={$user->id}&tab=insights");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_tab' => 'insights',
                ],
            ]);

        $this->assertArrayHasKey('items', $response->json('data.insights'));
    }

    public function test_perimenopause_overview_export_tab(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->getJson("/api/v1/perimenopause/overview?user_id={$user->id}&tab=export");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_tab' => 'export',
                ],
            ]);

        $this->assertArrayHasKey('report_preview', $response->json('data.export'));
    }

    public function test_gsm_checkin_can_be_saved(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->postJson("/api/v1/perimenopause/gsm-checkin", [
            'user_id' => $user->id,
            'vaginal_dryness' => 'moderate',
            'urinary_frequency' => 'mild',
            'pelvic_discomfort' => 'mild',
            'libido_impact' => 'moderate',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'GSM check-in saved successfully.',
            ]);
    }
}
