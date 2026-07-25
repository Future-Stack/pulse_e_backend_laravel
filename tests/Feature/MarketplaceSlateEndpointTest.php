<?php

namespace Tests\Feature;

use App\Models\Metro;
use App\Models\Provider;
use App\Models\ProviderCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class MarketplaceSlateEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_slate_endpoint_returns_providers_for_a_metro_id(): void
    {
        $metro = Metro::factory()->create(['centroid' => new Point(37.7749, -122.4194)]);
        $category = ProviderCategory::factory()->create(['slug' => 'obgyn', 'active' => true]);
        $provider = Provider::factory()->create([
            'metro_id' => $metro->id,
            'location' => new Point(37.78, -122.42),
            'status' => 'active',
        ]);
        $provider->categories()->attach($category->id, ['source' => 'manual']);

        $response = $this->getJson(route('marketplace.slate', [
            'category' => 'obgyn',
            'metro_id' => $metro->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $provider->id);
    }

    public function test_slate_endpoint_rejects_a_request_with_no_zip_or_metro(): void
    {
        ProviderCategory::factory()->create(['slug' => 'obgyn', 'active' => true]);

        $response = $this->getJson(route('marketplace.slate', ['category' => 'obgyn']));

        $response->assertStatus(422);
    }

    public function test_slate_endpoint_only_ever_accepts_the_documented_parameters(): void
    {
        // Regression guard for the spec 2.1 privacy boundary: passing an
        // arbitrary extra field (e.g. a would-be user/insight identifier)
        // must not break or get silently absorbed into the request contract.
        $metro = Metro::factory()->create();
        ProviderCategory::factory()->create(['slug' => 'obgyn', 'active' => true]);

        $response = $this->getJson(route('marketplace.slate', [
            'category' => 'obgyn',
            'metro_id' => $metro->id,
            'user_id' => 12345, // should simply be ignored by SlateRequest::validated()
        ]));

        $response->assertOk();
    }
}
