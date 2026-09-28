<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TerraActivityData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthDataSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_data_sync_with_hydration_ml_persists_successfully(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/health-data/sync', [
            'steps'        => 7500,
            'heart_rate'   => 68,
            'sleep_hours'  => 8.0,
            'hydration_ml' => 2500,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('synced.hydration_ml', 2500)
            ->assertJsonPath('synced.steps', 7500);

        $activity = TerraActivityData::where('user_id', $user->id)
            ->where('type', 'daily')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals(2500, $activity->payload['hydration_ml']);
        $this->assertEquals(2500, $activity->payload['hydration']['amount_ml']);
        $this->assertEquals(2500, $activity->payload['data'][0]['hydration_data']['hydration_ml']);
    }

    public function test_health_data_sync_supports_user_id_fallback(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/health-data/sync', [
            'user_id'      => $user->id,
            'hydration_ml' => 1800,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('synced.hydration_ml', 1800);
    }

    public function test_today_scores_includes_hydration_ml(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/health-data/sync', [
            'steps'        => 5000,
            'hydration_ml' => 2100,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/terra/today-scores');

        $response->assertStatus(200)
            ->assertJsonPath('data.hydration_ml', 2100)
            ->assertJsonPath('data.step', 5000);

        // Test history endpoint
        $scoresResponse = $this->actingAs($user, 'sanctum')->getJson('/api/v1/terra/scores?type=hydration_ml');
        $scoresResponse->assertStatus(200)
            ->assertJsonPath('data.0.type', 'hydration_ml')
            ->assertJsonPath('data.0.value', 2100);
    }
}
