<?php

namespace Tests\Feature;

use App\Models\SkinScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BeautyOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_can_save_and_retrieve_beauty_overview_data()
    {
        $user = User::factory()->create();

        $aiPayload = [
            'today' => [
                'user_id'            => $user->id,
                'image_path'         => 'https://example.com/images/scan_test.jpg',
                'overall_score'      => 76,
                'hydration_score'    => 70,
                'redness_score'      => 82,
                'texture_score'      => 80,
                'glow_index'         => 75,
                'pore_health_score'  => 68,
                'elasticity_score'   => 85,
                'hydration_status'   => 'Good',
                'redness_status'     => 'Optimal',
                'texture_status'     => 'Good',
                'glow_status'        => 'Good',
                'pore_health_status' => 'Moderate',
                'elasticity_status'  => 'Optimal',
                'neumera_insight'    => 'Your skin is holding up beautifully during your period.',
                'status_label'       => 'Radiant',
                'findings'           => [
                    [
                        'finding' => 'Moisture barrier',
                        'status'  => 'Hydration levels are moderately adequate at 70/100.',
                        'badge'   => 'good',
                        'score'   => 70,
                    ],
                    [
                        'finding' => 'Pore congestion',
                        'status'  => 'Pore health measures 68/100, suggesting mild congestion.',
                        'badge'   => 'good',
                        'score'   => 68,
                    ],
                    [
                        'finding' => 'Inflammation markers',
                        'status'  => 'Redness score of 82/100 reflects low inflammatory activity.',
                        'badge'   => 'healthy',
                        'score'   => 82,
                    ],
                    [
                        'finding' => 'Melanin uniformity',
                        'status'  => 'Glow rating of 75/100 combined with strong texture.',
                        'badge'   => 'healthy',
                        'score'   => 80,
                    ],
                ],
                'score_change'    => 0,
                'comparison_text' => '+0pts vs last scan',
            ],
            'correlations' => [
                'sleep_skin' => [
                    'correlation_detected' => true,
                    'correlation_strength' => 75,
                    'correlation_direction' => 'positive',
                    'insight' => 'Monitoring sleep and skin correlation.',
                    'chart_data' => [
                        [
                            'day' => 'Mon',
                            'date' => '2026-09-21',
                            'skin_score' => 76,
                            'sleep_hours' => null,
                        ],
                    ],
                ],
                'cycle_phases' => [
                    'phase_breakdown' => [
                        'menstrual' => [
                            'label' => 'Menstrual (D1-5)',
                            'score' => 0,
                            'description' => 'Scan your skin during your menstrual phase.',
                        ],
                    ],
                    'best_phase' => 'ovulation',
                    'worst_phase' => 'menstrual',
                ],
            ],
            'ai_insights' => [
                'overall_assessment' => 'Your skin is performing well overall at 76/100.',
                'phase_impact' => 'On day 1 of your menstrual phase.',
                'sleep_correlation' => 'Quality sleep is critical during menstruation.',
                'key_focus_areas' => [
                    'pore health',
                    'hydration',
                    'glow enhancement',
                ],
                'recommendations' => [
                    'Use a BHA (salicylic acid) exfoliant 2-3x weekly to unclog pores and refine texture',
                    'Layer a hyaluronic acid serum on damp skin to boost hydration during your period',
                ],
                'routine_suggestion' => 'AM: Gentle cream cleanser. PM: Double cleanse.',
                'confidence_score' => 88,
            ],
            'tabs' => [
                'Today',
                'History',
                'Correlations',
            ],
        ];

        // 1. Test Saving the AI beauty overview payload
        $saveResponse = $this->postJson('/api/v1/beauty-overview/save', $aiPayload);
        $saveResponse->assertStatus(200);
        $saveResponse->assertJson(['success' => true]);

        // Verify in skin_scans database table
        $this->assertDatabaseHas('skin_scans', [
            'user_id'         => $user->id,
            'overall_score'   => 76,
            'hydration_score' => 70,
            'status_label'    => 'Radiant',
            'score_change'    => 0,
            'comparison_text' => '+0pts vs last scan',
        ]);

        $savedScan = SkinScan::where('user_id', $user->id)->first();
        $this->assertNotNull($savedScan);
        $this->assertNotEmpty($savedScan->findings);
        $this->assertNotEmpty($savedScan->correlations);
        $this->assertNotEmpty($savedScan->ai_insights);

        // Verify skin_scan_recommendations table
        $this->assertDatabaseHas('skin_scan_recommendations', [
            'skin_scan_id' => $savedScan->id,
        ]);

        // 2. Test Getting beauty overview from skin_scans table
        $getResponse = $this->getJson("/api/v1/beauty-overview?user_id={$user->id}");
        $getResponse->assertStatus(200);
        $getResponse->assertJsonStructure([
            'success',
            'today' => [
                'id',
                'user_id',
                'image_path',
                'overall_score',
                'hydration_score',
                'redness_score',
                'texture_score',
                'glow_index',
                'pore_health_score',
                'elasticity_score',
                'hydration_status',
                'redness_status',
                'texture_status',
                'glow_status',
                'pore_health_status',
                'elasticity_status',
                'neumera_insight',
                'status_label',
                'findings',
                'score_change',
                'comparison_text',
            ],
            'history',
            'correlations',
            'ai_insights',
            'tabs',
        ]);

        $data = $getResponse->json();
        $this->assertEquals(76, $data['today']['overall_score']);
        $this->assertEquals('Radiant', $data['today']['status_label']);
        $this->assertEquals('+0pts vs last scan', $data['today']['comparison_text']);
        $this->assertCount(4, $data['today']['findings']);

        // 3. Test Authenticated access
        Sanctum::actingAs($user);
        $authResponse = $this->getJson('/api/v1/skin-scans/beauty-overview');
        $authResponse->assertStatus(200);
        $authResponse->assertJsonPath('today.id', $savedScan->id);

        // 4. Test POST /api/v1/beauty-overview with {"user_id": ..., "days": 30, "include_correlations": true}
        $postQueryResponse = $this->postJson('/api/v1/beauty-overview', [
            'user_id' => $user->id,
            'days' => 30,
            'include_correlations' => true,
        ]);
        $postQueryResponse->assertStatus(200);
        $postQueryResponse->assertJsonPath('today.id', $savedScan->id);
        $postQueryResponse->assertJsonPath('today.overall_score', 76);
    }

    public function test_skin_scan_analyze_store_calculates_beauty_overview_fields()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'scan' => [
                'overall_score'      => 79,
                'hydration_score'    => 82,
                'redness_score'      => 65,
                'texture_score'      => 68,
                'glow_index'         => 74,
                'pore_health_score'  => 72,
                'elasticity_score'   => 80,
                'neumera_insight'    => 'Your skin is performing well.',
            ]
        ];

        $response = $this->postJson('/api/v1/skin-scans/analyze', $payload);
        $response->assertStatus(201);

        $this->assertDatabaseHas('skin_scans', [
            'user_id'       => $user->id,
            'overall_score' => 79,
            'status_label'  => 'Radiant',
        ]);

        $scan = SkinScan::where('user_id', $user->id)->first();
        $this->assertNotNull($scan);
        $this->assertNotEmpty($scan->findings);
        $this->assertEquals('Radiant', $scan->status_label);
    }
}
