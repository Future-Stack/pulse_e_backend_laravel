<?php

namespace Tests\Feature;

use App\Jobs\ExpireVettingJob;
use App\Models\Provider;
use App\Models\ProviderCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class ExpireVettingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_expire_vetting_job_downgrades_lapsed_active_providers_to_vetted(): void
    {
        $category = ProviderCategory::factory()->create(['vetting_tier' => 'medical']);
        $provider = Provider::factory()->create(['status' => 'active', 'location' => new Point(37.77, -122.41)]);
        $provider->categories()->attach($category->id, ['source' => 'manual']);

        // Expired license check
        $provider->vettingRecords()->create([
            'check_type' => 'license',
            'status' => 'pass',
            'checked_at' => now()->subYears(2),
            'next_due_at' => now()->subDay(),
        ]);
        $provider->vettingRecords()->create([
            'check_type' => 'leie',
            'status' => 'pass',
            'checked_at' => now(),
        ]);

        (new ExpireVettingJob())->handle();

        $this->assertDatabaseHas('providers', [
            'id' => $provider->id,
            'status' => 'vetted',
        ]);
    }
}
