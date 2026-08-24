<?php

namespace Tests\Feature;

use App\Models\MenstrualCycle;
use App\Models\TtcPrediction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TryingToConceiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_sync_ttc_data(): void
    {
        $response = $this->getJson('/api/v1/ttc/sync');
        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_sync_returns_404_when_user_has_no_active_cycle(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/ttc/sync');
        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No active menstrual cycle found.');
    }

    public function test_sync_ttc_data_successfully_fetches_and_saves_predictions(): void
    {
        $user = User::factory()->create();
        $cycle = MenstrualCycle::create([
            'user_id' => $user->id,
            'period_start_date' => now(),
            'is_completed' => false,
        ]);

        Http::fake([
            '*/api/v1/cycle-engine/ttc/overview*' => Http::response([
                'surge_banner' => [
                    'cycle_day' => 12,
                    'active' => true,
                    'message' => 'LH Surge detected!',
                    'hours_remaining_estimate' => 24,
                    'lh_surge_day' => 12,
                    'ai_generated' => true,
                    'ai_cached' => false,
                ],
                'priority_map' => [
                    'cycle_day' => 12,
                    'ranges' => [['start' => 10, 'end' => 14, 'level' => 'high']],
                    'ai_generated' => true,
                    'ai_cached' => false,
                ],
                'priority_banner' => [
                    'cycle_day' => 12,
                    'priority' => 'High',
                    'label' => 'Peak Fertility',
                    'message' => 'Optimal time for TTC',
                    'ai_generated' => true,
                    'ai_cached' => false,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/ttc/sync');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.surge_active', true)
            ->assertJsonPath('data.surge_message', 'LH Surge detected!')
            ->assertJsonPath('data.priority', 'High')
            ->assertJsonPath('data.label', 'Peak Fertility');

        $this->assertDatabaseHas('ttc_predictions', [
            'user_id' => $user->id,
            'cycle_id' => $cycle->id,
            'surge_active' => true,
            'surge_message' => 'LH Surge detected!',
            'priority' => 'High',
            'label' => 'Peak Fertility',
            'priority_message' => 'Optimal time for TTC',
        ]);
    }

    public function test_legacy_endpoints_delegate_to_sync_function(): void
    {
        $user = User::factory()->create();
        $cycle = MenstrualCycle::create([
            'user_id' => $user->id,
            'period_start_date' => now(),
            'is_completed' => false,
        ]);

        Http::fake([
            '*/api/v1/cycle-engine/ttc/overview*' => Http::response([
                'surge_banner' => [
                    'cycle_day' => 14,
                    'active' => false,
                    'message' => 'Normal LH level',
                    'ai_generated' => true,
                    'ai_cached' => false,
                ],
                'priority_map' => [
                    'cycle_day' => 14,
                    'ranges' => [],
                    'ai_generated' => true,
                    'ai_cached' => false,
                ],
                'priority_banner' => [
                    'cycle_day' => 14,
                    'priority' => 'Medium',
                    'label' => 'Moderate Fertility',
                    'message' => 'Secondary window',
                    'ai_generated' => true,
                    'ai_cached' => false,
                ],
            ], 200),
        ]);

        $surgeRes = $this->actingAs($user, 'sanctum')->getJson('/api/v1/ttc/surge-banner');
        $surgeRes->assertStatus(200)->assertJsonPath('success', true);

        $mapRes = $this->actingAs($user, 'sanctum')->getJson('/api/v1/ttc/priority-map');
        $mapRes->assertStatus(200)->assertJsonPath('success', true);

        $bannerRes = $this->actingAs($user, 'sanctum')->getJson('/api/v1/ttc/priority-banner');
        $bannerRes->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_sync_ttc_data_uses_fallback_when_ai_service_fails(): void
    {
        $user = User::factory()->create();
        $cycle = MenstrualCycle::create([
            'user_id' => $user->id,
            'period_start_date' => now(),
            'is_completed' => false,
        ]);

        Http::fake([
            '*/api/v1/cycle-engine/ttc/*' => Http::response([
                'detail' => 'Unable to load cycle calendar inputs for user ' . $user->id,
            ], 500),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/ttc/sync');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ai_fallback', true);

        $this->assertDatabaseHas('ttc_predictions', [
            'user_id' => $user->id,
            'cycle_id' => $cycle->id,
            'ai_fallback' => true,
        ]);
    }
}
