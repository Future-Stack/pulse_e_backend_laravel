<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_calendar_month(): void
    {
        $response = $this->getJson('/api/v1/cycle-calendar/month');
        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_sync_month_forwards_query_params_and_returns_data(): void
    {
        $user = User::factory()->create();

        Http::fake([
            '*/api/v1/cycle-engine/calendar/month*' => Http::response([
                'status' => 'success',
                'month' => '2026-08',
                'days' => [],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/cycle-calendar/month?month=2026-08');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.month', '2026-08');

        Http::assertSent(function ($request) use ($user) {
            return str_contains($request->url(), '/api/v1/cycle-engine/calendar/month')
                && $request['user_id'] == $user->id
                && $request['month'] == '2026-08';
        });
    }

    public function test_sync_next_period_saves_statistic_and_returns_data(): void
    {
        $user = User::factory()->create();

        Http::fake([
            '*/api/v1/cycle-engine/calendar/next-period*' => Http::response([
                'status' => 'success',
                'predicted_date' => '2026-08-25',
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/cycle-calendar/next-period');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.predicted_date', '2026-08-25');

        $this->assertDatabaseHas('cycle_statistics', [
            'user_id' => $user->id,
            'predicted_next_period' => '2026-08-25 00:00:00',
        ]);
    }

    public function test_sync_month_uses_local_fallback_when_ai_fails(): void
    {
        $user = User::factory()->create();

        Http::fake([
            '*/api/v1/cycle-engine/calendar/month*' => Http::response([
                'detail' => 'Unable to load cycle calendar inputs for user ' . $user->id,
            ], 502),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/cycle-calendar/month');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('ai_fallback', true);
    }

    public function test_sync_next_period_uses_local_fallback_when_ai_fails(): void
    {
        $user = User::factory()->create();
        \App\Models\CycleCalendarInput::create([
            'user_id' => $user->id,
            'start_date' => '2026-08-01',
            'is_day_n' => false,
        ]);

        Http::fake([
            '*/api/v1/cycle-engine/calendar/next-period*' => Http::response([
                'detail' => 'Unable to load cycle calendar inputs for user ' . $user->id,
            ], 502),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/cycle-calendar/next-period');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('ai_fallback', true)
            ->assertJsonPath('data.predicted_date', '2026-08-29');

        $this->assertDatabaseHas('cycle_statistics', [
            'user_id' => $user->id,
            'predicted_next_period' => '2026-08-29 00:00:00',
        ]);
    }
}
