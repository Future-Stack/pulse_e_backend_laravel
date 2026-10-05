<?php

namespace Tests\Feature;

use App\Models\CycleCalendarInput;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FertileWindowPredictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_with_token_gets_fertile_window_prediction(): void
    {
        $user = User::factory()->create();

        CycleCalendarInput::create([
            'user_id' => $user->id,
            'start_date' => '2026-08-20',
            'end_date' => null,
            'is_day_n' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/cycle-calendar/fertile-window-prediction');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'FERTILE WINDOW PREDICTION')
            ->assertJsonPath('data.window_opens.day', 'Day 11')
            ->assertJsonPath('data.window_opens.date', 'Aug 30')
            ->assertJsonPath('data.peak_day.day', 'Day 14')
            ->assertJsonPath('data.peak_day.date', 'Sep 02')
            ->assertJsonPath('data.window_closes.day', 'Day 17')
            ->assertJsonPath('data.window_closes.date', 'Sep 05')
            ->assertJsonPath('data.description', 'Range-based prediction model — avoids single-day assumptions. Your window spans 7 days for maximum accuracy.');
    }

    public function test_short_alias_endpoint_with_auth_token(): void
    {
        $user = User::factory()->create();

        CycleCalendarInput::create([
            'user_id' => $user->id,
            'start_date' => '2026-08-20',
            'end_date' => null,
            'is_day_n' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/fertile-window-prediction');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.window_opens.date', 'Aug 30')
            ->assertJsonPath('data.peak_day.date', 'Sep 02')
            ->assertJsonPath('data.window_closes.date', 'Sep 05');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/cycle-calendar/fertile-window-prediction');

        $response->assertStatus(401);
    }

    public function test_returns_404_when_authenticated_user_has_no_cycle_calendar_input(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/cycle-calendar/fertile-window-prediction');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No cycle calendar input found for this user.');
    }
}
