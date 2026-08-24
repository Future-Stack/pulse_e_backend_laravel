<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MenstrualCycle;
use App\Models\CycleCalendarInput;
use App\Models\PeriodLog;
use App\Models\BbtLog;
use App\Models\OpkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CycleDataPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cycle_calendar_input_persists_to_menstrual_cycles_and_period_logs(): void
    {
        Http::fake([
            'https://ai.fightthenumber.com/api/v1/cycle-engine/engine/overview*' => Http::response([
                'summary' => ['status' => 'empty'],
                'signal_status' => ['signals' => []],
                'discrepancy_note' => ['active' => false],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/cycle-calendar-inputs', [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-05',
            'is_day_n' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('cycle_calendar_inputs', [
            'user_id' => $user->id,
            'start_date' => '2026-08-01 00:00:00',
        ]);

        $this->assertDatabaseHas('menstrual_cycles', [
            'user_id' => $user->id,
            'period_start_date' => '2026-08-01 00:00:00',
            'is_completed' => false,
        ]);

        $cycle = MenstrualCycle::where('user_id', $user->id)->first();
        $this->assertDatabaseHas('period_logs', [
            'user_id' => $user->id,
            'cycle_id' => $cycle->id,
            'log_date' => '2026-08-01 00:00:00',
        ]);
    }

    public function test_bbt_logging_associates_cycle_id_and_saves_bbt_log(): void
    {
        $fakeBbtResponse = [
            'bbt_chart' => [
                'points' => [
                    ['day' => 1, 'date' => '2026-08-07', 'temperature_f' => 97.5, 'is_excluded' => false, 'flags' => []]
                ],
                'coverline_value' => 97.2,
            ],
            'coverline_algorithm' => [
                'summary' => [
                    'coverline' => '97.2',
                    'phase' => 'follicular'
                ]
            ]
        ];

        Http::fake([
            'https://ai.fightthenumber.com/api/v1/cycle-engine/bbt/ui*' => Http::response($fakeBbtResponse, 200),
            'https://ai.fightthenumber.com/api/v1/cycle-engine/engine/overview*' => Http::response([
                'summary' => ['status' => 'empty'],
                'signal_status' => ['signals' => []],
                'discrepancy_note' => ['active' => false],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/bbt/logs', [
            'temperature_f' => 97.5,
            'flags' => [],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $cycle = MenstrualCycle::where('user_id', $user->id)->first();
        $this->assertNotNull($cycle);

        $this->assertDatabaseHas('bbt_logs', [
            'user_id' => $user->id,
            'cycle_id' => $cycle->id,
            'log_date' => '2026-08-07 00:00:00',
            'temperature' => 97.50,
        ]);
    }
}
