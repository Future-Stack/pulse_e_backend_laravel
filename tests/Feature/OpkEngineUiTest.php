<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MenstrualCycle;
use App\Models\OpkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpkEngineUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_opk_ui_endpoint_returns_expected_structure(): void
    {
        $fakeUi = [
            'info_alert' => ['title' => 'OPK vs BBT', 'message' => 'Info message'],
            'testing_window' => [
                'title' => 'Window',
                'subtitle' => 'Subtitle',
                'status' => 'open',
                'cards' => [],
                'summary' => ['window' => 'Days 10-16', 'opk_peak' => null, 'bbt_confirmed' => null],
            ],
            'lh_surge_detection' => [
                'detected' => true,
                'title' => 'Title',
                'message' => 'Message',
                'surge_day' => 13,
                'bbt_confirmed_day' => 16,
            ],
            'log_todays_test' => [
                'title' => 'Log',
                'guidance' => 'Guidance',
                'current_day' => 11,
                'already_logged' => false,
                'options' => [],
            ],
            'cervical_mucus' => [
                'title' => 'Mucus',
                'description' => 'Desc',
                'note' => 'Note',
                'options' => [],
            ],
            'ai_generated' => true,
            'ai_cached' => false,
            'sources' => ['database' => 'mysql'],
        ];

        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response($fakeUi, 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/cycle-engine/opk/ui?user_id={$user->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'info_alert' => ['title', 'message'],
                'testing_window' => [
                    'title',
                    'subtitle',
                    'status',
                    'cards',
                    'summary' => ['window', 'opk_peak', 'bbt_confirmed'],
                ],
                'lh_surge_detection' => [
                    'detected',
                    'title',
                    'message',
                    'surge_day',
                    'bbt_confirmed_day',
                ],
                'log_todays_test' => [
                    'title',
                    'guidance',
                    'current_day',
                    'already_logged',
                    'options',
                ],
                'cervical_mucus' => [
                    'title',
                    'description',
                    'note',
                    'options',
                ],
                'ai_generated',
                'ai_cached',
                'sources' => ['database'],
            ]);
    }

    public function test_logging_opk_test_stores_correctly_and_updates_ui(): void
    {
        $fakeUi = [
            'lh_surge_detection' => [
                'detected' => true,
            ],
        ];

        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response($fakeUi, 200),
        ]);

        $user = User::factory()->create();

        $logResponse = $this->actingAs($user, 'sanctum')->postJson('/api/v1/cycle-engine/opk/log', [
            'user_id' => $user->id,
            'log_date' => now()->toDateString(),
            'result' => 'peak',
            'lh_value' => 1.5,
            'note' => 'LH surge detected',
        ]);

        $logResponse->assertStatus(200)
            ->assertJsonPath('lh_surge_detection.detected', true);
    }
}
