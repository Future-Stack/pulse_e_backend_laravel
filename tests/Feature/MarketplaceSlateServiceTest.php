<?php

namespace Tests\Feature;

use App\Models\Metro;
use App\Models\PlaceDetailsCache;
use App\Models\Provider;
use App\Models\ProviderCategory;
use App\Services\MarketplaceSlateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

/**
 * Requires this project's real Laravel test bootstrap (Tests\TestCase,
 * RefreshDatabase, an sqlite/mysql testing connection). Copy into your app's
 * tests/Feature directory alongside the rest of this module.
 */
class MarketplaceSlateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_closer_provider_with_similar_rating_ranks_higher(): void
    {
        $metro = Metro::factory()->create(['centroid' => new Point(37.7749, -122.4194), 'radius_km' => 50]);
        $category = ProviderCategory::factory()->create(['vetting_tier' => 'medical']);

        $close = Provider::factory()->create([
            'metro_id' => $metro->id,
            'location' => new Point(37.78, -122.42), // ~1km from centroid
            'status' => 'active',
        ]);
        $far = Provider::factory()->create([
            'metro_id' => $metro->id,
            'location' => new Point(38.2, -122.9), // ~60km from centroid
            'status' => 'active',
        ]);

        $close->categories()->attach($category->id, ['source' => 'manual']);
        $far->categories()->attach($category->id, ['source' => 'manual']);

        PlaceDetailsCache::create(['provider_id' => $close->id, 'rating' => 4.5, 'review_count' => 50]);
        PlaceDetailsCache::create(['provider_id' => $far->id, 'rating' => 4.6, 'review_count' => 50]);

        $ranked = (new MarketplaceSlateService())->computeOrganicRanking($category, $metro);

        $this->assertSame($close->id, $ranked->first()->id);
    }

    public function test_sponsored_slots_fill_positions_before_organic_results(): void
    {
        $metro = Metro::factory()->create();
        $category = ProviderCategory::factory()->create();

        $organic = Provider::factory()->create(['metro_id' => $metro->id, 'status' => 'active']);
        $organic->categories()->attach($category->id, ['source' => 'manual']);

        $sponsoredProvider = Provider::factory()->create(['metro_id' => $metro->id, 'status' => 'active']);
        $sponsoredProvider->categories()->attach($category->id, ['source' => 'manual']);

        \App\Models\SponsoredSlot::create([
            'metro_id' => $metro->id,
            'category_id' => $category->id,
            'slot_number' => 1,
            'provider_id' => $sponsoredProvider->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        $slate = (new MarketplaceSlateService())->buildSlate($category, $metro, 3);

        $this->assertTrue($slate->first()['sponsored']);
        $this->assertSame($sponsoredProvider->id, $slate->first()['provider']->id);
    }

    public function test_suspended_sponsor_never_appears_even_with_an_active_slot_row(): void
    {
        $metro = Metro::factory()->create();
        $category = ProviderCategory::factory()->create();

        $suspended = Provider::factory()->create(['metro_id' => $metro->id, 'status' => 'suspended']);
        $suspended->categories()->attach($category->id, ['source' => 'manual']);

        \App\Models\SponsoredSlot::create([
            'metro_id' => $metro->id,
            'category_id' => $category->id,
            'slot_number' => 1,
            'provider_id' => $suspended->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        $slate = (new MarketplaceSlateService())->buildSlate($category, $metro, 3);

        $this->assertTrue($slate->pluck('provider.id')->doesntContain($suspended->id));
    }
}
