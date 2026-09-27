<?php

namespace Tests\Feature;

use App\Models\AthletePerformance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AthletePerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected array $sampleAthleteResponse = [
        'date'            => '2026-09-27',
        'readiness_score' => 78,
        'readiness_level' => 'Ready',
        'hrv'             => [
            'value'  => 68,
            'unit'   => 'ms',
            'trend'  => 4,
            'status' => 'good',
        ],
        'recovery'        => [
            'percentage' => 74,
            'trend'      => 5,
            'status'     => 'moderate',
        ],
        'training_load'   => [
            'value'  => 185.5,
            'unit'   => 'AU',
            'trend'  => 12,
            'status' => 'moderate',
        ],
        'metrics'         => [
            'hrv'           => ['value' => 68, 'unit' => 'ms', 'trend' => 4, 'status' => 'good'],
            'sleep'         => ['percentage' => 95, 'trend' => 3, 'status' => 'good'],
            'recovery'      => ['percentage' => 74, 'trend' => 5, 'status' => 'moderate'],
            'training_load' => ['value' => 185.5, 'unit' => 'AU', 'trend' => 12, 'status' => 'moderate'],
        ],
        'fatigue_alerts'  => [
            ['type' => 'overtraining_risk', 'level' => 'moderate', 'message' => ''],
            ['type' => 'injury_risk_index', 'level' => 'low', 'message' => ''],
            ['type' => 'cumulative_fatigue', 'level' => 'low', 'message' => ''],
        ],
        'cycle_info'      => [
            'phase'              => 'follicular',
            'cycle_day'          => 10,
            'days_to_next_phase' => 4,
            'phase_boost'        => 2,
            'phase_description'  => 'Follicular phase - building energy, good for strength training',
        ],
        'phase_cards'     => [
            [
                'phase'           => 'menstrual',
                'focus'           => 'Restore and Recharge Gently',
                'recommendations' => ['Prioritize deep sleep', 'Walk instead of run'],
            ],
            [
                'phase'           => 'follicular',
                'focus'           => 'Build Strength, Embrace Challenges',
                'recommendations' => ['Lift heavier weights', 'Try new workouts'],
            ],
            [
                'phase'           => 'ovulation',
                'focus'           => 'Peak Power Performance',
                'recommendations' => ['Hit heavy PRs', 'Maximize sprint intervals'],
            ],
            [
                'phase'           => 'luteal',
                'focus'           => 'Steady endurance and recovery',
                'recommendations' => ['Prioritize moderate steady cardio'],
            ],
        ],
        'next_update'     => '2026-09-28T10:13:55.246476+00:00',
    ];

    public function test_athlete_unified_performance_fetches_and_saves_to_database(): void
    {
        $user = User::factory()->create(['id' => 2]);

        Http::fake([
            'https://ai.fightthenumber.com/api/v1/athlete/unified-performance*' => Http::response($this->sampleAthleteResponse, 200),
        ]);

        $response = $this->getJson('/api/v1/athlete/unified-performance?user_id=2');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.readiness_score', 78)
            ->assertJsonPath('data.readiness_level', 'Ready')
            ->assertJsonPath('data.metrics.hrv.value', 68)
            ->assertJsonPath('data.metrics.training_load.value', 185.5)
            ->assertJsonPath('data.metrics.sleep.percentage', 95)
            ->assertJsonCount(4, 'data.phase_cards');

        // Verify Database Persistence
        $record = AthletePerformance::where('user_id', 2)
            ->whereDate('performance_date', '2026-09-27')
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals(78, $record->readiness_score);
        $this->assertEquals('Ready', $record->readiness_level);
        $this->assertEquals(68, $record->hrv['value']);
        $this->assertEquals(185.5, $record->training_load['value']);
        $this->assertEquals(95, $record->metrics['sleep']['percentage']);
        $this->assertCount(4, $record->phase_cards);
    }

    public function test_athlete_unified_performance_matches_root_api_path(): void
    {
        $user = User::factory()->create(['id' => 2]);

        Http::fake([
            'https://ai.fightthenumber.com/api/v1/athlete/unified-performance*' => Http::response($this->sampleAthleteResponse, 200),
        ]);

        $response = $this->getJson('/api/athlete/unified-performance?user_id=2');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.readiness_score', 78);
    }

    public function test_athlete_performance_history_endpoint(): void
    {
        $user = User::factory()->create(['id' => 2]);

        // Insert test records
        AthletePerformance::create([
            'user_id'          => 2,
            'performance_date' => today()->subDays(1)->toDateString(),
            'readiness_score'  => 82,
            'readiness_level'  => 'Peak Ready',
        ]);
        AthletePerformance::create([
            'user_id'          => 2,
            'performance_date' => today()->toDateString(),
            'readiness_score'  => 85,
            'readiness_level'  => 'Peak Ready',
        ]);

        $response = $this->getJson('/api/v1/athlete/history?user_id=2');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_athlete_performance_local_fallback_when_ai_down(): void
    {
        $user = User::factory()->create(['id' => 2]);

        Http::fake([
            'https://ai.fightthenumber.com/api/v1/athlete/unified-performance*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $response = $this->getJson('/api/v1/athlete/unified-performance?user_id=2');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertNotNull($response->json('data.readiness_score'));
        $this->assertNotEmpty($response->json('data.phase_cards'));

        // Assert record saved in database
        $this->assertDatabaseHas('athlete_performances', [
            'user_id' => 2,
        ]);
    }
}
