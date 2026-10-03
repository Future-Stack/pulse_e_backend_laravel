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

    public function test_vasomotor_logs_are_automatically_synced_from_terra_activity_data(): void
    {
        $user = User::factory()->create();

        // Simulate wearable data with skin temperature elevation and night awake events
        \App\Models\TerraActivityData::create([
            'user_id'           => $user->id,
            'terra_user_id'     => 'terra_user_1',
            'type'              => 'sleep',
            'payload'           => [
                'data' => [
                    [
                        'metadata' => [
                            'start_time' => now()->startOfWeek()->addHours(23)->toIso8601String(),
                        ],
                        'temperature_data' => [
                            'temperature_delta' => 1.2, // Intense hot flash spike
                        ],
                        'heart_rate_data' => [
                            'summary' => [
                                'resting_hr_bpm' => 62,
                                'max_hr_bpm'     => 92, // +30 bpm surge
                            ],
                        ],
                        'sleep_durations_data' => [
                            'awake' => [
                                'num_awake_events' => 3,
                                'duration_seconds' => 2000,
                            ],
                        ],
                    ],
                ],
            ],
            'data_generated_at' => now()->startOfWeek()->toDateString(),
        ]);

        $response = $this->getJson("/api/v1/perimenopause/overview?user_id={$user->id}&tab=symptoms");

        $response->assertStatus(200);
        $chart = $response->json('data.symptoms.vasomotor_tracker.chart');
        $this->assertNotEmpty($chart);

        // Verify that the first day has detected episodes
        $mondayLog = collect($chart)->firstWhere('date', now()->startOfWeek()->toDateString());
        $this->assertNotNull($mondayLog);
        $this->assertGreaterThan(0, $mondayLog['total']);
        $this->assertGreaterThan(0, $mondayLog['intense']);
    }
}
