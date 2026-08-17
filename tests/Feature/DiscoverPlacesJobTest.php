<?php

namespace Tests\Feature;

use App\Jobs\DiscoverPlacesJob;
use App\Models\CategoryPlaceQuery;
use App\Models\Metro;
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
                    'lat' => 37.775,
                    'lng' => -122.419,
                    'business_status' => 'OPERATIONAL',
                    'formatted_address' => '100 Main St, San Francisco, CA',
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
}
