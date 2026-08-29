<?php

namespace Tests\Feature;

use App\Jobs\DiscoverPlacesJob;
use App\Models\CategoryPlaceQuery;
use App\Models\Metro;
use App\Models\PlaceDetailsCache;
use App\Models\Provider;
use App\Models\ProviderCategory;
use App\Services\GooglePlacesClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Mockery;
use Tests\TestCase;

class DiscoverPlacesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_discover_places_job_joins_existing_nppes_provider_and_updates_google_place_id(): void
    {
        $metro = Metro::factory()->create(['centroid' => new Point(37.7749, -122.4194), 'radius_km' => 20]);
        $category = ProviderCategory::factory()->create(['slug' => 'obgyn']);
        CategoryPlaceQuery::create([
            'category_id' => $category->id,
            'places_type' => 'doctor',
            'keyword' => 'obgyn',
        ]);

        // NPPES provider in DB with google_place_id = NULL
        $nppesProvider = Provider::factory()->create([
            'npi' => '1982524955',
            'google_place_id' => null,
            'display_name' => 'WINSTON HILL',
            'phone_e164' => '+14155559999',
            'addr_line1' => '100 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'metro_id' => $metro->id,
            'source_nppes' => true,
            'status' => 'candidate',
        ]);
        $nppesProvider->categories()->attach($category->id, ['source' => 'nppes_taxonomy']);

        $placesClient = Mockery::mock(GooglePlacesClient::class);
        $placesClient->shouldReceive('textSearch')
            ->andReturn([
                [
                    'place_id' => 'ChIJ123456789_SamplePlaceId',
                    'display_name' => 'Winston Hill MD',
                    'phone_e164' => '+14155559999',
                    'website' => 'https://winstonhill.com',
                    'lat' => 37.775,
                    'lng' => -122.419,
                    'business_status' => 'OPERATIONAL',
                    'formatted_address' => '100 Main St, San Francisco, CA',
                    'addr_line1' => '100 Main St',
                    'addr_line2' => null,
                    'city' => 'San Francisco',
                    'state' => 'CA',
                    'zip' => '94105',
                    'rating' => 4.8,
                    'review_count' => 120,
                    'hours_json' => ['Monday: 9am - 5pm'],
                ],
            ]);

        $job = new DiscoverPlacesJob($metro, $category);
        $job->handle($placesClient);

        // Verify google_place_id was populated on the existing NPPES record!
        $this->assertDatabaseHas('providers', [
            'id' => $nppesProvider->id,
            'google_place_id' => 'ChIJ123456789_SamplePlaceId',
            'source_places' => true,
        ]);
    }

    public function test_discover_places_job_creates_new_provider_with_full_address_and_cached_details(): void
    {
        $metro = Metro::factory()->create(['centroid' => new Point(37.7749, -122.4194), 'radius_km' => 20]);
        $category = ProviderCategory::factory()->create(['slug' => 'dermatology']);
        CategoryPlaceQuery::create([
            'category_id' => $category->id,
            'places_type' => 'doctor',
            'keyword' => 'dermatologist',
        ]);

        $placesClient = Mockery::mock(GooglePlacesClient::class);
        $placesClient->shouldReceive('textSearch')
            ->andReturn([
                [
                    'place_id' => 'ChIJNewDermPlace_987654',
                    'display_name' => 'Advanced Dermatology Clinic',
                    'phone_e164' => '+14155554321',
                    'website' => 'https://advancedderm.example.com',
                    'lat' => 37.785,
                    'lng' => -122.410,
                    'business_status' => 'OPERATIONAL',
                    'formatted_address' => '450 Sutter St Ste 1000, San Francisco, CA 94108, USA',
                    'addr_line1' => '450 Sutter St',
                    'addr_line2' => 'Ste 1000',
                    'city' => 'San Francisco',
                    'state' => 'CA',
                    'zip' => '94108',
                    'rating' => 4.9,
                    'review_count' => 350,
                    'hours_json' => ['Monday: 8am - 5pm'],
                ],
            ]);

        $job = new DiscoverPlacesJob($metro, $category);
        $job->handle($placesClient);

        $provider = Provider::where('google_place_id', 'ChIJNewDermPlace_987654')->first();
        $this->assertNotNull($provider);
        $this->assertEquals('Advanced Dermatology Clinic', $provider->display_name);
        $this->assertEquals('Advanced Dermatology Clinic', $provider->org_name);
        $this->assertEquals('+14155554321', $provider->phone_e164);
        $this->assertEquals('https://advancedderm.example.com', $provider->website);
        $this->assertEquals('450 Sutter St', $provider->addr_line1);
        $this->assertEquals('Ste 1000', $provider->addr_line2);
        $this->assertEquals('San Francisco', $provider->city);
        $this->assertEquals('CA', $provider->state);
        $this->assertEquals('94108', $provider->zip);
        $this->assertTrue($provider->source_places);

        // Verify PlaceDetailsCache was populated
        $this->assertDatabaseHas('place_details_cache', [
            'provider_id' => $provider->id,
            'rating' => 4.9,
            'review_count' => 350,
        ]);
    }
}
