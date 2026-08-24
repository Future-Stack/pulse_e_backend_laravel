<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MenstrualCycle;
use App\Models\CycleStatistic;
use App\Models\SignalHistory;
use App\Models\OvulationReconciliation;
use App\Models\CyclePredictionCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CycleSummaryOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_sync_cycle_engine(): void
    {
        $response = $this->getJson('/api/v1/cycle-engine/engine/sync');
        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_cycle_sync_uses_combined_overview_endpoint_and_persists_data(): void
    {
        $user = User::factory()->create();

        $fakeOverviewResponse = [
            'summary' => [
                'cycle_summary' => [
                    'user_id' => $user->id,
                    'current_cycle_day' => 14,
                    'current_phase' => 'ovulatory',
                    'avg_cycle_length' => 28.0,
                    'cycle_variance_days' => 2,
                    'current_mode' => 'cycle_awareness',
                ],
                'fertile_window' => [
                    'start_day' => 10,
                    'end_day' => 15,
                    'label' => 'predicted',
                    'peak_day' => 14,
                    'peak_source' => 'calendar',
                    'mucus_peak_day' => null,
                    'lh_surge_day' => 14,
                    'bbt_confirmed_day' => null,
                ],
                'reliability' => [
                    'level' => 'medium',
                    'completed_cycles' => 3,
                    'text' => 'Predictions based on logged data.',
                ],
                'reconciliation' => [
                    'calendar_predicted_day' => 14,
                    'bbt_confirmed_day' => null,
                    'lh_surge_day' => 14,
                    'final_confirmed_day' => 14,
                    'final_source' => 'opk',
                    'offset_days' => 0,
                    'luteal_phase_length' => 14,
                ],
                'ai_generated' => true,
                'ai_cached' => true,
            ],
            'signal_status' => [
                'signals' => [
                    ['signal' => 'Calendar', 'logged_today' => true, 'status_text' => 'Cycle Day 14 · Ovulatory phase'],
                    ['signal' => 'OPK / LH', 'logged_today' => true, 'status_text' => 'OPK logged today'],
                    ['signal' => 'BBT', 'logged_today' => false, 'status_text' => 'No temperature logged today'],
                    ['signal' => 'Mucus', 'logged_today' => false, 'status_text' => 'No mucus observation logged'],
                ],
                'ai_generated' => true,
                'ai_cached' => true,
            ],
            'discrepancy_note' => [
                'active' => false,
                'message' => 'Calendar and biometric signals are aligned.',
                'ai_generated' => true,
                'ai_cached' => true,
            ],
        ];

        Http::fake([
            'https://ai.fightthenumber.com/api/v1/cycle-engine/engine/overview*' => Http::response($fakeOverviewResponse, 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/cycle-engine/engine/sync');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Cycle dashboard synced successfully.')
            ->assertJsonPath('data.summary.cycle_summary.current_cycle_day', 14)
            ->assertJsonPath('data.signal_status.signals.0.signal', 'Calendar')
            ->assertJsonPath('data.discrepancy_note.active', false);

        // Verify database persistence
        $this->assertDatabaseHas('cycle_statistics', [
            'user_id' => $user->id,
            'completed_cycles' => 3,
            'average_cycle_length' => 28,
            'reliability_level' => 'medium',
        ]);

        $this->assertDatabaseHas('menstrual_cycles', [
            'user_id' => $user->id,
            'current_cycle_day' => 14,
            'current_phase' => 'ovulatory',
            'predicted_peak_day' => 14,
        ]);

        $this->assertDatabaseHas('signal_histories', [
            'user_id' => $user->id,
            'calendar_logged' => true,
            'opk_logged' => true,
            'bbt_logged' => false,
        ]);

        $this->assertDatabaseHas('ovulation_reconciliations', [
            'user_id' => $user->id,
            'calendar_predicted_day' => 14,
            'lh_surge_day' => 14,
            'has_discrepancy' => false,
        ]);

        $this->assertDatabaseHas('cycle_prediction_caches', [
            'endpoint' => '/api/v1/cycle-engine/engine/overview',
        ]);
    }

    public function test_signal_status_endpoint_returns_signal_status_from_overview(): void
    {
        $user = User::factory()->create();

        $fakeOverviewResponse = [
            'summary' => ['cycle_summary' => ['current_cycle_day' => 1]],
            'signal_status' => [
                'signals' => [
                    ['signal' => 'Calendar', 'logged_today' => false, 'status_text' => 'Cycle Day 1'],
                ],
                'ai_generated' => true,
            ],
            'discrepancy_note' => ['active' => false],
        ];

        Http::fake([
            'https://ai.fightthenumber.com/api/v1/cycle-engine/engine/overview*' => Http::response($fakeOverviewResponse, 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/engine/signal-status-sync');

        $response->assertStatus(200)
            ->assertJsonPath('signals.0.signal', 'Calendar')
            ->assertJsonPath('ai_generated', true);
    }

    public function test_discrepancy_note_endpoint_returns_discrepancy_note_from_overview(): void
    {
        $user = User::factory()->create();

        $fakeOverviewResponse = [
            'summary' => ['cycle_summary' => ['current_cycle_day' => 1]],
            'signal_status' => ['signals' => []],
            'discrepancy_note' => [
                'active' => true,
                'message' => 'Discrepancy detected between BBT and LH.',
                'ai_generated' => true,
            ],
        ];

        Http::fake([
            'https://ai.fightthenumber.com/api/v1/cycle-engine/engine/overview*' => Http::response($fakeOverviewResponse, 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/engine/discrepancy-note-sync');

        $response->assertStatus(200)
            ->assertJsonPath('active', true)
            ->assertJsonPath('message', 'Discrepancy detected between BBT and LH.');
    }

    public function test_ai_summary_endpoint_returns_summary_from_overview(): void
    {
        $user = User::factory()->create();

        $fakeOverviewResponse = [
            'summary' => [
                'cycle_summary' => [
                    'user_id' => $user->id,
                    'current_cycle_day' => 5,
                    'current_phase' => 'menstrual',
                ],
            ],
            'signal_status' => ['signals' => []],
            'discrepancy_note' => ['active' => false],
        ];

        Http::fake([
            'https://ai.fightthenumber.com/api/v1/cycle-engine/engine/overview*' => Http::response($fakeOverviewResponse, 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/ai-summary');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cycle_summary.current_cycle_day', 5);
    }
}
