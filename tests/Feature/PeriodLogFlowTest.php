<?php

namespace Tests\Feature;

use App\Models\MenstrualCycle;
use App\Models\PeriodLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PeriodLogFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake external AI requests so tests are fully isolated and deterministic
        Http::fake([
            'https://ai.fightthenumber.com/*' => Http::response([
                'success' => true,
                'data' => [],
            ], 200),
        ]);
    }

    public function test_unauthenticated_user_cannot_access_period_log_endpoints(): void
    {
        $response = $this->postJson('/api/v1/period/log', [
            'flow' => 'medium',
            'date' => '2026-10-18',
        ]);
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/period/log?date=2026-10-18');
        $response->assertStatus(401);

        $response = $this->deleteJson('/api/v1/period/log?date=2026-10-18');
        $response->assertStatus(401);
    }

    public function test_store_period_log_creates_menstrual_cycle_if_not_present(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/period/log', [
            'flow'       => 'medium',
            'date'       => '2026-10-18',
            'color'      => 'Red',
            'clotting'   => true,
            'cramps'     => true,
            'pain_level' => 6,
            'notes'      => 'First day heavy flow and cramps',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.flow', 'medium')
            ->assertJsonPath('data.color', 'red')
            ->assertJsonPath('data.clotting', true)
            ->assertJsonPath('data.cramps', true)
            ->assertJsonPath('data.pain_level', 6);

        // MenstrualCycle auto-created
        $cycle = MenstrualCycle::where('user_id', $user->id)->first();
        $this->assertNotNull($cycle);
        $this->assertFalse((bool)$cycle->is_completed);

        // Period log in DB
        $this->assertDatabaseHas('period_logs', [
            'user_id'    => $user->id,
            'cycle_id'   => $cycle->id,
            'log_date'   => '2026-10-18',
            'flow'       => 'medium',
            'color'      => 'red',
            'clotting'   => 1,
            'cramps'     => 1,
            'pain_level' => 6,
        ]);
    }

    public function test_update_existing_period_log_for_same_date(): void
    {
        $user = User::factory()->create();

        // 1. Initial log
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/period/log', [
            'flow'       => 'light',
            'date'       => '2026-10-18',
            'pain_level' => 2,
        ])->assertStatus(201);

        $this->assertEquals(1, PeriodLog::where('user_id', $user->id)->count());

        // 2. Update log on same date
        $updateResponse = $this->actingAs($user, 'sanctum')->postJson('/api/v1/period/log', [
            'flow'       => 'heavy',
            'date'       => '2026-10-18',
            'pain_level' => 8,
            'headache'   => true,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.flow', 'heavy')
            ->assertJsonPath('data.pain_level', 8)
            ->assertJsonPath('data.headache', true);

        // Verify count is still 1 (no duplicate rows)
        $this->assertEquals(1, PeriodLog::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('period_logs', [
            'user_id'    => $user->id,
            'log_date'   => '2026-10-18',
            'flow'       => 'heavy',
            'pain_level' => 8,
            'headache'   => 1,
        ]);
    }

    public function test_store_spotting_with_has_spotting_false_removes_entry(): void
    {
        $user = User::factory()->create();

        // 1. Create entry
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/cycle-engine/spotting/log', [
            'flow'  => 'spotting',
            'color' => 'pink',
            'date'  => '2026-10-18',
        ])->assertStatus(201);

        $this->assertEquals(1, PeriodLog::where('user_id', $user->id)->count());

        // 2. Submit has_spotting = false
        $removeResponse = $this->actingAs($user, 'sanctum')->postJson('/api/v1/cycle-engine/spotting/log', [
            'has_spotting' => false,
            'date'         => '2026-10-18',
        ]);

        $removeResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(0, PeriodLog::where('user_id', $user->id)->count());
    }

    public function test_store_with_remove_flag_true_removes_entry(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/period/log', [
            'flow' => 'medium',
            'date' => '2026-10-18',
        ])->assertStatus(201);

        $removeResponse = $this->actingAs($user, 'sanctum')->postJson('/api/v1/period/log', [
            'date'   => '2026-10-18',
            'remove' => true,
        ]);

        $removeResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(0, PeriodLog::where('user_id', $user->id)->count());
    }

    public function test_get_log_by_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/period/log', [
            'flow'  => 'spotting',
            'color' => 'brown',
            'date'  => '2026-10-18',
        ])->assertStatus(201);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/period/log?date=2026-10-18');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.flow', 'spotting')
            ->assertJsonPath('data.color', 'brown')
            ->assertJsonPath('data.has_spotting', true);

        // Also test GET /cycle-engine/spotting/log alias
        $spottingRes = $this->actingAs($user, 'sanctum')->getJson('/api/v1/cycle-engine/spotting/log?date=2026-10-18');
        $spottingRes->assertStatus(200)
            ->assertJsonPath('data.flow', 'spotting')
            ->assertJsonPath('data.has_spotting', true);

        // Also test filter by flow=spotting
        $flowFilterRes = $this->actingAs($user, 'sanctum')->getJson('/api/v1/period-logs?flow=spotting');
        $flowFilterRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertCount(1, $flowFilterRes->json('data'));
    }

    public function test_get_logs_by_month(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/period/log', [
            'flow' => 'heavy',
            'date' => '2026-10-01',
        ])->assertStatus(201);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/period/log', [
            'flow' => 'medium',
            'date' => '2026-10-02',
        ])->assertStatus(201);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/period/log', [
            'flow' => 'spotting',
            'date' => '2026-09-15',
        ])->assertStatus(201);

        // Fetch October only
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/period-logs?month=2026-10');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_get_log_by_id_and_show_endpoint(): void
    {
        $user = User::factory()->create();

        $createRes = $this->actingAs($user, 'sanctum')->postJson('/api/v1/period-logs', [
            'flow' => 'light',
            'date' => '2026-10-18',
        ]);
        $logId = $createRes->json('data.id');

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/period-logs/{$logId}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $logId)
            ->assertJsonPath('data.flow', 'light');
    }

    public function test_delete_endpoint_by_id(): void
    {
        $user = User::factory()->create();

        $createRes = $this->actingAs($user, 'sanctum')->postJson('/api/v1/period-logs', [
            'flow' => 'medium',
            'date' => '2026-10-18',
        ]);
        $logId = $createRes->json('data.id');

        $delResponse = $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/period-logs/{$logId}");

        $delResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNull(PeriodLog::find($logId));
    }

    public function test_delete_endpoint_by_date_and_post_delete(): void
    {
        $user = User::factory()->create();

        // 1. DELETE /api/v1/period/log?date=...
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/period/log', [
            'flow' => 'medium',
            'date' => '2026-10-18',
        ])->assertStatus(201);

        $delRes1 = $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/period/log?date=2026-10-18');
        $delRes1->assertStatus(200)->assertJsonPath('success', true);
        $this->assertEquals(0, PeriodLog::where('user_id', $user->id)->count());

        // 2. POST /api/v1/period/log/delete
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/period/log', [
            'flow' => 'medium',
            'date' => '2026-10-19',
        ])->assertStatus(201);

        $delRes2 = $this->actingAs($user, 'sanctum')->postJson('/api/v1/period/log/delete', [
            'date' => '2026-10-19',
        ]);
        $delRes2->assertStatus(200)->assertJsonPath('success', true);
        $this->assertEquals(0, PeriodLog::where('user_id', $user->id)->count());
    }

    public function test_validation_rejects_invalid_values(): void
    {
        $user = User::factory()->create();

        // Invalid flow
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/period/log', [
            'flow' => 'super_ultra_heavy',
            'date' => '2026-10-18',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['flow']);

        // Invalid color
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/period/log', [
            'flow'  => 'medium',
            'color' => 'blue',
            'date'  => '2026-10-18',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['color']);

        // Invalid pain_level (> 10)
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/period/log', [
            'flow'       => 'medium',
            'pain_level' => 11,
            'date'       => '2026-10-18',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pain_level']);
    }
}
