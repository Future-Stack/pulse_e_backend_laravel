<?php

namespace Tests\Feature;

use App\Jobs\SyncNppesJob;
use App\Models\CategoryTaxonomyCode;
use App\Models\Metro;
use App\Models\Provider;
use App\Models\ProviderCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class SyncNppesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_nppes_job_handles_missing_weekly_file_gracefully(): void
    {
        Http::fake([
            'https://download.cms.gov/nppes/NPI_Files.html' => Http::response('<html>No files</html>', 200),
        ]);

        (new SyncNppesJob())->handle($this->app->make(\App\Services\ZipGeocodingService::class));

        $this->assertDatabaseCount('providers', 0);
    }
}
