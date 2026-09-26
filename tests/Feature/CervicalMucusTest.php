<?php

namespace Tests\Feature;

use App\Models\CervicalMucusLog;
use App\Models\MenstrualCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CervicalMucusTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_cervical_mucus_successfully()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'consistency' => 'egg_white',
            'log_date'    => now()->toDateString(),
            'amount'      => 'high',
            'color'       => 'clear',
            'stretch_cm'  => 4.5,
            'notes'       => 'High fertility observed.',
        ];

        $response = $this->postJson('/api/v1/cycle-engine/cervical-mucus/log', $payload);
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data'    => [
                'consistency'       => 'egg_white',
                'consistency_label' => 'Egg white',
                'fertility_level'   => 'Peak fertility',
                'fertility_score'   => 100,
                'is_peak_fertility' => true,
            ],
        ]);

        $this->assertDatabaseHas('cervical_mucus_logs', [
            'user_id'         => $user->id,
            'consistency'     => 'egg_white',
            'fertility_score' => 100,
        ]);
    }

    public function test_user_can_log_using_human_readable_label()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // UI button passes "Egg white" or "Creamy"
        $payload = [
            'consistency' => 'Creamy',
            'log_date'    => now()->toDateString(),
        ];

        $response = $this->postJson('/api/v1/cycle-engine/cervical-mucus/log', $payload);
        $response->assertStatus(200);
        $response->assertJsonPath('data.consistency', 'creamy');
        $response->assertJsonPath('data.fertility_score', 50);
        $response->assertJsonPath('data.fertility_level', 'Moderate');
    }

    public function test_can_get_cervical_mucus_options()
    {
        $response = $this->getJson('/api/v1/cycle-engine/cervical-mucus/options');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'title',
            'subtitle',
            'options' => [
                '*' => [
                    'key',
                    'label',
                    'description',
                    'fertility_level',
                    'fertility_score',
                ]
            ]
        ]);

        $options = $response->json('options');
        $this->assertCount(5, $options);
        $this->assertEquals('Dry', $options[0]['label']);
        $this->assertEquals('Sticky', $options[1]['label']);
        $this->assertEquals('Creamy', $options[2]['label']);
        $this->assertEquals('Watery', $options[3]['label']);
        $this->assertEquals('Egg white', $options[4]['label']);
    }

    public function test_can_fetch_today_cervical_mucus_log()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $cycle = MenstrualCycle::create([
            'user_id'           => $user->id,
            'period_start_date' => now()->toDateString(),
            'is_completed'      => false,
        ]);

        CervicalMucusLog::create([
            'user_id'         => $user->id,
            'cycle_id'        => $cycle->id,
            'log_date'        => now()->toDateString(),
            'consistency'     => 'watery',
            'fertility_score' => 75,
        ]);

        $response = $this->getJson('/api/v1/cycle-engine/cervical-mucus/today');
        $response->assertStatus(200);
        $response->assertJsonPath('logged', true);
        $response->assertJsonPath('data.consistency', 'watery');
        $response->assertJsonPath('data.fertility_level', 'High fertility');
    }

    public function test_logging_opk_with_cervical_mucus_saves_both()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'result'          => 'positive',
            'log_date'        => now()->toDateString(),
            'cervical_mucus'  => 'egg_white',
        ];

        $response = $this->postJson('/api/v1/cycle-engine/opk/log', $payload);
        $response->assertStatus(200);

        $this->assertDatabaseHas('cervical_mucus_logs', [
            'user_id'     => $user->id,
            'consistency' => 'egg_white',
        ]);
    }

    public function test_logging_cervical_mucus_returns_hormone_trends()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'consistency' => 'egg_white',
            'log_date'    => now()->toDateString(),
        ];

        $response = $this->postJson('/api/v1/cycle-engine/cervical-mucus/log', $payload);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
            'hormone_trends' => [
                '*' => [
                    'name',
                    'value',
                    'numeric_value',
                    'unit',
                    'status',
                    'percentage',
                ]
            ],
        ]);

        $hormones = $response->json('hormone_trends');
        $this->assertCount(3, $hormones);
        $this->assertEquals('Estrogen (E2)', $hormones[0]['name']);
        $this->assertEquals('284 pg/mL', $hormones[0]['value']);
        $this->assertEquals('Progesterone', $hormones[1]['name']);
        $this->assertEquals('12.4 ng/mL', $hormones[1]['value']);
        $this->assertEquals('LH Surge', $hormones[2]['name']);
        $this->assertEquals('68 mIU/mL', $hormones[2]['value']);
    }

    public function test_can_fetch_hormone_trends_directly()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cycle-engine/hormone-trends');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'date',
            'hormone_trends',
        ]);
    }
}

