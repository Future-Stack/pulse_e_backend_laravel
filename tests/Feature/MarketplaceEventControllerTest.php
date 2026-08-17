<?php

namespace Tests\Feature;

use App\Models\ProviderCategory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceEventControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_endpoint_stores_valid_batch_events(): void
    {
        $category = ProviderCategory::factory()->create(['slug' => 'obgyn']);

        $payload = [
            'session_token' => str_repeat('a', 32),
            'events' => [
                [
                    'event_type' => 'impression',
                    'category' => 'obgyn',
                    'slot_position' => 1,
                    'sponsored' => false,
                ],
            ],
        ];

        $response = $this->postJson(route('marketplace.events.store'), $payload);

        $response->assertStatus(202);
        $response->assertJson(['status' => 'accepted', 'count' => 1]);

        $this->assertDatabaseHas('slate_events', [
            'event_type' => 'impression',
            'session_token' => str_repeat('a', 32),
        ]);
    }

    public function test_events_endpoint_rejects_unallowed_fields_for_privacy_boundary(): void
    {
        ProviderCategory::factory()->create(['slug' => 'obgyn']);

        $payload = [
            'session_token' => str_repeat('a', 32),
            'events' => [
                [
                    'event_type' => 'impression',
                    'category' => 'obgyn',
                    'user_id' => 12345, // Prohibited field
                ],
            ],
        ];

        $response = $this->postJson(route('marketplace.events.store'), $payload);

        $response->assertStatus(422);
    }
}
