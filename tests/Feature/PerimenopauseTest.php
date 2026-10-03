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

    public function test_unauthenticated_request_without_user_id_returns_401(): void
    {
        $response = $this->getJson('/api/v1/perimenopause/overview');
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated or user_id is missing.',
            ]);
    }

    public function test_empty_user_has_no_fallback_hardcoded_data(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/v1/perimenopause/overview?user_id={$user->id}&tab=symptoms");
        $response->assertStatus(200);

        $vasomotor = $response->json('data.symptoms.vasomotor_tracker');
        $this->assertEquals(0, $vasomotor['summary']['total_episodes']);
        $this->assertEquals('0/10', $vasomotor['summary']['avg_intensity']);
        $this->assertEquals('None recorded', $vasomotor['summary']['peak']);
        $this->assertEquals('This week: 0 episodes recorded', $vasomotor['summary']['summary_text']);

        // GSM symptoms should be 'None' when not logged, not fake 'Moderate' or 'Mild'
        $gsmSymptoms = collect($response->json('data.symptoms.gsm.symptoms'))->pluck('value', 'key');
        $this->assertEquals('None', $gsmSymptoms['vaginal_dryness']);
        $this->assertEquals('None', $gsmSymptoms['urinary_frequency']);
        $this->assertEquals('None', $gsmSymptoms['pelvic_discomfort']);
        $this->assertEquals('None', $gsmSymptoms['libido_impact']);

        // Stage card should be dynamic
        $stageCard = $response->json('data.header.stage_card');
        $this->assertNotEquals('Perimenopause — Year 2', $stageCard['stage']);
        $this->assertNotEquals('Irregular cycles for 18 months · FSH elevated', $stageCard['subtitle']);
    }

    public function test_vasomotor_intensity_is_calculated_dynamically(): void
    {
        $user = User::factory()->create();

        // 2 mild (2.5), 1 intense (7.5) -> (2*2.5 + 1*7.5)/3 = 12.5/3 = 4.2
        $response = $this->postJson('/api/v1/perimenopause/vasomotor-log', [
            'user_id'        => $user->id,
            'log_date'       => now()->toDateString(),
            'mild_count'     => 2,
            'moderate_count' => 0,
            'intense_count'  => 1,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(4.2, (float) $response->json('data.avg_intensity'));
        $this->assertNotEquals(4.3, (float) $response->json('data.avg_intensity'));
    }

    public function test_export_report_reflects_actual_logged_data_without_hardcoded_strings(): void
    {
        $user = User::factory()->create();

        // Save a GSM checkin
        $this->postJson('/api/v1/perimenopause/gsm-checkin', [
            'user_id'           => $user->id,
            'vaginal_dryness'   => 'severe',
            'urinary_frequency' => 'very_severe',
            'pelvic_discomfort' => 'moderate',
            'libido_impact'     => 'none',
        ]);

        $response = $this->getJson("/api/v1/perimenopause/export-report?user_id={$user->id}");
        $response->assertStatus(200);

        $preview = $response->json('data.report_preview');
        $this->assertNotEquals('Year 2 (confirmed)', $preview['perimenopause_stage']);
        $this->assertStringContainsString('Severe dryness', $preview['gsm_symptoms']);
        $this->assertStringContainsString('very severe frequency', $preview['gsm_symptoms']);
    }

    public function test_dedicated_tab_endpoints_return_matching_ui_contract(): void
    {
        $user = User::factory()->create();

        // 1. Symptoms endpoint
        $symptomsRes = $this->getJson("/api/v1/perimenopause/symptoms?user_id={$user->id}");
        $symptomsRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_tab' => 'symptoms',
                ],
            ]);
        $this->assertArrayHasKey('vasomotor_tracker', $symptomsRes->json('data.symptoms'));
        $this->assertArrayHasKey('gsm', $symptomsRes->json('data.symptoms'));

        // 2. Insights endpoint
        $insightsRes = $this->getJson("/api/v1/perimenopause/insights?user_id={$user->id}");
        $insightsRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_tab' => 'insights',
                ],
            ]);
        $this->assertArrayHasKey('items', $insightsRes->json('data.insights'));

        // 3. Export endpoint
        $exportRes = $this->getJson("/api/v1/perimenopause/export?user_id={$user->id}");
        $exportRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_tab' => 'export',
                ],
            ]);
        $this->assertArrayHasKey('report_preview', $exportRes->json('data.export'));
    }
}
