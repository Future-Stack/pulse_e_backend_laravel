<?php

namespace Tests\Feature;

use App\Models\LifeJourney;
use App\Models\MarketplaceLifeStage;
use App\Models\Metro;
use App\Models\Profile;
use App\Models\Provider;
use App\Models\ProviderCategory;
use App\Models\User;
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

    public function test_slate_endpoint_supports_direct_lat_lng_coordinates_and_calculates_real_distance(): void
    {
        $metro = Metro::factory()->create([
            'centroid' => new Point(37.7749, -122.4194),
            'radius_km' => 50,
            'active' => true,
        ]);
        $category = ProviderCategory::factory()->create(['slug' => 'obgyn', 'active' => true]);
        $provider = Provider::factory()->create([
            'metro_id' => $metro->id,
            'location' => new Point(37.7800, -122.4200),
            'status' => 'active',
        ]);
        $provider->categories()->attach($category->id, ['source' => 'manual']);

        $response = $this->getJson(route('marketplace.slate', [
            'category' => 'obgyn',
            'lat' => 37.7805,
            'lng' => -122.4205,
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $provider->id);
        $this->assertNotNull($response->json('data.0.distance_miles'));
    }

    public function test_slate_endpoint_returns_no_metro_match_when_outside_active_metro_radius_without_fallback_data(): void
    {
        Metro::factory()->create([
            'centroid' => new Point(37.7749, -122.4194),
            'radius_km' => 50,
            'active' => true,
        ]);
        ProviderCategory::factory()->create(['slug' => 'obgyn', 'active' => true]);

        // Far away coordinates (e.g. New York when only SF metro exists)
        $response = $this->getJson(route('marketplace.slate', [
            'category' => 'obgyn',
            'lat' => 40.7128,
            'lng' => -74.0060,
        ]));

        $response->assertOk();
        $response->assertJsonPath('data', []);
        $response->assertJsonPath('message_key', 'marketplace.no_metro_match');
    }

    public function test_marketplace_categories_endpoint_returns_categories_for_journey(): void
    {
        $stage = MarketplaceLifeStage::create(['slug' => 'cycle-fertility', 'name' => 'Cycle & Fertility']);
        $cat1 = ProviderCategory::factory()->create(['slug' => 'obgyn', 'display_name' => 'OB-GYN', 'active' => true]);
        $cat2 = ProviderCategory::factory()->create(['slug' => 'fertility-clinic', 'display_name' => 'Fertility Clinic', 'active' => true]);

        $stage->categories()->attach($cat1->id, ['display_priority' => 1]);
        $stage->categories()->attach($cat2->id, ['display_priority' => 2]);

        $journey = LifeJourney::create(['title' => 'Cycle & Fertility']);

        $response = $this->getJson(route('marketplace.categories', [
            'life_journey_id' => $journey->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.life_stage.slug', 'cycle-fertility');
        $this->assertCount(2, $response->json('data.categories'));
        $response->assertJsonPath('data.categories.0.slug', 'obgyn');
    }

    public function test_slate_endpoint_works_with_life_journey_and_defaults_category(): void
    {
        $metro = Metro::factory()->create([
            'centroid' => new Point(37.7749, -122.4194),
            'radius_km' => 50,
            'active' => true,
        ]);
        $stage = MarketplaceLifeStage::create(['slug' => 'cycle-fertility', 'name' => 'Cycle & Fertility']);
        $cat = ProviderCategory::factory()->create(['slug' => 'obgyn', 'display_name' => 'OB-GYN', 'active' => true]);
        $stage->categories()->attach($cat->id, ['display_priority' => 1]);

        $provider = Provider::factory()->create([
            'metro_id' => $metro->id,
            'location' => new Point(37.7800, -122.4200),
            'status' => 'active',
        ]);
        $provider->categories()->attach($cat->id, ['source' => 'manual']);

        $journey = LifeJourney::create(['title' => 'Cycle & Fertility']);

        // Call slate without category, but with life_journey_id and lat/lng
        $response = $this->getJson(route('marketplace.slate', [
            'life_journey_id' => $journey->id,
            'lat' => 37.7805,
            'lng' => -122.4205,
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $provider->id);
        $response->assertJsonPath('meta.category', 'obgyn');
        $response->assertJsonPath('meta.life_stage', 'cycle-fertility');
    }

    public function test_slate_endpoint_resolves_authenticated_user_life_journey(): void
    {
        $metro = Metro::factory()->create([
            'centroid' => new Point(37.7749, -122.4194),
            'radius_km' => 50,
            'active' => true,
        ]);
        $stage = MarketplaceLifeStage::create(['slug' => 'pregnancy-postpartum', 'name' => 'Pregnancy & Postpartum']);
        $cat = ProviderCategory::factory()->create(['slug' => 'obgyn', 'display_name' => 'OB-GYN', 'active' => true]);
        $stage->categories()->attach($cat->id, ['display_priority' => 1]);

        $provider = Provider::factory()->create([
            'metro_id' => $metro->id,
            'location' => new Point(37.7800, -122.4200),
            'status' => 'active',
        ]);
        $provider->categories()->attach($cat->id, ['source' => 'manual']);

        $user = User::factory()->create();
        $profile = Profile::create(['user_id' => $user->id]);
        $journey = LifeJourney::create(['title' => 'Pregnancy & Postpartum']);
        $profile->lifeJourneys()->attach($journey->id);

        $response = $this->actingAs($user, 'sanctum')->getJson(route('marketplace.slate', [
            'lat' => 37.7805,
            'lng' => -122.4205,
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $provider->id);
        $response->assertJsonPath('meta.category', 'obgyn');
        $response->assertJsonPath('meta.life_stage', 'pregnancy-postpartum');
    }

    public function test_user_with_multiple_life_journeys_gets_all_categories_and_slate_matches(): void
    {
        $metro = Metro::factory()->create([
            'centroid' => new Point(37.7749, -122.4194),
            'radius_km' => 50,
            'active' => true,
        ]);

        // Stage 1: Beauty & Radiance
        $stage1 = MarketplaceLifeStage::create(['id' => 1, 'slug' => 'beauty-radiance', 'name' => 'Beauty & Radiance']);
        $cat1 = ProviderCategory::factory()->create(['slug' => 'dermatology', 'display_name' => 'Dermatology', 'active' => true]);
        $stage1->categories()->attach($cat1->id, ['display_priority' => 1]);

        // Stage 5: Pregnancy & Postpartum
        $stage5 = MarketplaceLifeStage::create(['id' => 5, 'slug' => 'pregnancy-postpartum', 'name' => 'Pregnancy & Postpartum']);
        $cat5 = ProviderCategory::factory()->create(['slug' => 'obgyn', 'display_name' => 'OB-GYN', 'active' => true]);
        $stage5->categories()->attach($cat5->id, ['display_priority' => 1]);

        // Stage 6: Lifelong Thriving
        $stage6 = MarketplaceLifeStage::create(['id' => 6, 'slug' => 'lifelong-thriving', 'name' => 'Lifelong Thriving']);
        $cat6 = ProviderCategory::factory()->create(['slug' => 'primary-care', 'display_name' => 'Primary Care', 'active' => true]);
        $stage6->categories()->attach($cat6->id, ['display_priority' => 1]);

        $journey1 = LifeJourney::create(['id' => 1, 'title' => 'Beauty & Radiance']);
        $journey5 = LifeJourney::create(['id' => 5, 'title' => 'Pregnancy & Postpartum']);
        $journey6 = LifeJourney::create(['id' => 6, 'title' => 'Lifelong Thriving']);

        // Providers
        $dermProvider = Provider::factory()->create(['metro_id' => $metro->id, 'location' => new Point(37.7800, -122.4200), 'status' => 'active']);
        $dermProvider->categories()->attach($cat1->id, ['source' => 'manual']);

        $obgynProvider = Provider::factory()->create(['metro_id' => $metro->id, 'location' => new Point(37.7801, -122.4201), 'status' => 'active']);
        $obgynProvider->categories()->attach($cat5->id, ['source' => 'manual']);

        // User with journeys 1, 5, 6 (matching profile 3 in screenshot)
        $user = User::factory()->create();
        $profile = Profile::create(['user_id' => $user->id]);
        $profile->lifeJourneys()->attach([$journey1->id, $journey5->id, $journey6->id]);

        // 1. categories endpoint returns all 3 journeys with categories
        $catResponse = $this->actingAs($user, 'sanctum')->getJson(route('marketplace.categories'));
        $catResponse->assertOk();
        $this->assertCount(3, $catResponse->json('data'));

        // 2. slate endpoint for dermatology (from journey 1) works
        $dermResponse = $this->actingAs($user, 'sanctum')->getJson(route('marketplace.slate', [
            'category' => 'dermatology',
            'lat' => 37.7805,
            'lng' => -122.4205,
        ]));
        $dermResponse->assertOk();
        $dermResponse->assertJsonPath('data.0.id', $dermProvider->id);
        $dermResponse->assertJsonPath('meta.life_stage', 'beauty-radiance');

        // 3. slate endpoint for obgyn (from journey 5) works
        $obgynResponse = $this->actingAs($user, 'sanctum')->getJson(route('marketplace.slate', [
            'category' => 'obgyn',
            'lat' => 37.7805,
            'lng' => -122.4205,
        ]));
        $obgynResponse->assertOk();
        $obgynResponse->assertJsonPath('data.0.id', $obgynProvider->id);
        $obgynResponse->assertJsonPath('meta.life_stage', 'pregnancy-postpartum');
    }
}
