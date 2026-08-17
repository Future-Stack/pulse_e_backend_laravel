<?php

namespace Tests\Feature;

use App\Jobs\RebuildSlateJob;
use App\Models\Metro;
use App\Models\Provider;
use App\Models\ProviderCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class RebuildSlateJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_rebuild_slate_job_caches_top_organic_providers_in_redis(): void
    {
        $metro = Metro::factory()->create(['centroid' => new Point(37.7749, -122.4194), 'radius_km' => 50, 'active' => true]);
        $category = ProviderCategory::factory()->create(['slug' => 'obgyn', 'active' => true]);

        $provider = Provider::factory()->create([
            'metro_id' => $metro->id,
            'location' => new Point(37.78, -122.42),
            'status' => 'active',
        ]);
        $provider->categories()->attach($category->id, ['source' => 'manual']);

        (new RebuildSlateJob())->handle($this->app->make(\App\Services\MarketplaceSlateService::class));

        $cachedIds = Cache::get("slate:{$metro->id}:{$category->id}");

        $this->assertIsArray($cachedIds);
        $this->assertContains($provider->id, $cachedIds);
    }
}
