<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CycleFertilityTest extends TestCase
{
    use RefreshDatabase;

    protected array $sampleAiResponse = [
        'current_metrics' => [
            'current_cycle_day'       => 9,
            'cycle_length'            => 28,
            'current_phase'           => 'follicular',
            'period_start_date'       => '2026-09-18',
            'period_end_date'         => '2026-09-22',
            'predicted_ovulation_day' => null,
            'confirmed_ovulation_day' => null,
            'is_confirmed'            => false,
        ],
        'fertile_window' => [
            'fertile_start_day'     => 10,
            'fertile_end_day'       => 15,
            'days_until_ovulation'  => 6,
            'ovulation_probability' => 75.0,
            'is_fertile_now'        => false,
        ],
        'bbt_analysis' => null,
        'cycle_history' => [
            'previous_cycles_count'  => 0,
            'avg_cycle_length'       => null,
            'avg_period_length'      => null,
            'cycle_regularity_score' => 0.0,
        ],
        'hormone_trends' => [
            [
                'name'        => 'Estrogen (E2)',
                'value'       => 150.0,
                'unit'        => 'pg/mL',
                'status'      => 'Rising',
                'bar_percent' => 43,
            ],
            [
                'name'        => 'Progesterone',
                'value'       => 4.0,
                'unit'        => 'ng/mL',
                'status'      => 'Normal',
                'bar_percent' => 20,
            ],
            [
                'name'        => 'LH Surge',
                'value'       => 20.0,
                'unit'        => 'mIU/mL',
                'status'      => 'Moderate',
                'bar_percent' => 25,
            ],
        ],
        'ai_insights' => [
            'cycle_assessment' => 'You are currently on Day 9 of a 28-day cycle in the follicular phase.',
            'optimal_timing'   => 'Your peak fertility window is Days 10-15, with ovulation most likely around Day 15.',
            'phase_explanation'=> 'You are in the follicular phase, when your ovaries are maturing follicles.',
            'symptom_tracking' => 'Track cervical mucus daily (watching for clear, stretchy egg-white consistency), basal body temperature each morning.',
            'key_insights'     => [
                'Cycle length of 28 days falls within the healthy 21-35 day range',
                'You are 6 days away from predicted ovulation',
                'Fertility window opens tomorrow (Day 10)',
                'No historical data yet limits prediction accuracy',
                'Ovulation probability is a strong 75% based on current phase indicators',
            ],
            'recommendations'  => [
                'Begin daily basal body temperature tracking first thing in the morning',
                'Start monitoring cervical mucus changes to confirm approaching ovulation',
                'Consider ovulation predictor kits (OPKs) starting Day 10 for precise timing',
            ],
            'next_steps'       => 'Log your symptoms and BBT daily over the next 7 days.',
            'confidence_score' => 65,
        ],
    ];

    public function test_cycle_fertility_overview_with_mocked_ai_response(): void
    {
        $user = User::factory()->create(['id' => 2]);

        Http::fake([
            'https://ai.fightthenumber.com/api/cycle-overview*' => Http::response($this->sampleAiResponse, 200),
        ]);

        $response = $this->getJson('/api/v1/cycle-fertility/overview?user_id=2&mode=standard&include_bbt=false');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_metrics.current_cycle_day', 9)
            ->assertJsonPath('data.current_metrics.current_phase', 'follicular')
            ->assertJsonPath('data.fertile_window.fertile_start_day', 10)
            ->assertJsonPath('data.fertile_window.fertile_end_day', 15);

        // Check Hormone Trends
        $hormones = $response->json('data.hormone_trends');
        $this->assertCount(3, $hormones);
        $this->assertEquals('Estrogen (E2)', $hormones[0]['name']);
        $this->assertEquals(150.0, $hormones[0]['value']);
        $this->assertEquals('150 pg/mL', $hormones[0]['formatted_value']);
        $this->assertEquals('Rising', $hormones[0]['status']);
        $this->assertEquals('optimal', $hormones[0]['status_color']);
        $this->assertEquals(43, $hormones[0]['bar_percent']);

        $this->assertEquals('Progesterone', $hormones[1]['name']);
        $this->assertEquals(4.0, $hormones[1]['value']);
        $this->assertEquals('normal', $hormones[1]['status_color']);

        $this->assertEquals('LH Surge', $hormones[2]['name']);
        $this->assertEquals(20.0, $hormones[2]['value']);
        $this->assertEquals('moderate', $hormones[2]['status_color']);

        // Check Today's Insights Cards
        $todayInsights = $response->json('data.today_insights');
        $this->assertNotEmpty($todayInsights);

        // First 3 cards must correspond to LH / Fertility (🌸), Cervical Mucus (💧), and BBT (🌡️)
        $this->assertEquals('🌸', $todayInsights[0]['icon']);
        $this->assertEquals('lh_surge', $todayInsights[0]['type']);

        $this->assertEquals('💧', $todayInsights[1]['icon']);
        $this->assertEquals('cervical_mucus', $todayInsights[1]['type']);

        $this->assertEquals('🌡️', $todayInsights[2]['icon']);
        $this->assertEquals('bbt', $todayInsights[2]['type']);

        // Check Fertile Window Prediction block
        $prediction = $response->json('data.fertile_window_prediction');
        $this->assertEquals(10, $prediction['window_opens_day']);
        $this->assertEquals(15, $prediction['window_closes_day']);
        $this->assertEquals(6, $prediction['span_days']);
        $this->assertStringContainsString('Range-based prediction model', $prediction['description']);

        // Verify Database Persistence
        $this->assertDatabaseHas('cycle_fertility_overviews', [
            'user_id'           => 2,
            'current_cycle_day' => 9,
            'current_phase'     => 'follicular',
        ]);

        $dbRecord = \App\Models\CycleFertilityOverview::where('user_id', 2)->first();
        $this->assertNotNull($dbRecord);
        $this->assertNotEmpty($dbRecord->hormone_trends);
        $this->assertNotEmpty($dbRecord->today_insights);
        $this->assertEquals('Estrogen (E2)', $dbRecord->hormone_trends[0]['name']);
        $this->assertEquals('🌸', $dbRecord->today_insights[0]['icon']);
    }

    public function test_cycle_overview_matches_root_api_path(): void
    {
        Http::fake([
            'https://ai.fightthenumber.com/api/cycle-overview*' => Http::response($this->sampleAiResponse, 200),
        ]);

        $response = $this->getJson('/api/cycle-overview?user_id=2&mode=standard&include_bbt=false');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_metrics.current_cycle_day', 9);
    }

    public function test_hormone_trends_dedicated_endpoint(): void
    {
        Http::fake([
            'https://ai.fightthenumber.com/api/cycle-overview*' => Http::response($this->sampleAiResponse, 200),
        ]);

        $response = $this->getJson('/api/v1/cycle-fertility/hormone-trends?user_id=2');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('current_cycle_day', 9)
            ->assertJsonPath('current_phase', 'follicular')
            ->assertJsonCount(3, 'hormone_trends');
    }

    public function test_today_insights_dedicated_endpoint(): void
    {
        Http::fake([
            'https://ai.fightthenumber.com/api/cycle-overview*' => Http::response($this->sampleAiResponse, 200),
        ]);

        $response = $this->getJson('/api/v1/cycle-fertility/today-insights?user_id=2');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('today_insights'));
        $this->assertNotEmpty($response->json('ai_insights.key_insights'));
    }

    public function test_authenticated_user_access(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://ai.fightthenumber.com/api/cycle-overview*' => Http::response($this->sampleAiResponse, 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/cycle-fertility/overview');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_metrics.current_cycle_day', 9);
    }

    public function test_fallback_when_ai_service_fails(): void
    {
        Http::fake([
            'https://ai.fightthenumber.com/api/cycle-overview*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $response = $this->getJson('/api/v1/cycle-fertility/overview?user_id=999');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('data.hormone_trends'));
        $this->assertNotEmpty($response->json('data.today_insights'));
        $this->assertNotEmpty($response->json('data.current_metrics'));
    }
}
