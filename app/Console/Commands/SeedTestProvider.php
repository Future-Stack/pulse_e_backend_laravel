<?php

namespace App\Console\Commands;

use App\Models\Metro;
use App\Models\Provider;
use App\Models\ProviderCategory;
use Illuminate\Console\Command;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * One-off dev/staging helper: creates a single test Metro + Provider so you
 * can hit GET /v1/marketplace/slate and see a real result without needing
 * `php artisan tinker` (useful if Tinker/PsySH is broken locally) or waiting
 * on the NPPES/Places ingestion jobs to run.
 *
 * Safe to run more than once — it upserts on npi so it won't create duplicates.
 * Delete this command before shipping to real production.
 */
class SeedTestProvider extends Command
{
    protected $signature = 'marketplace:seed-test-provider';
    protected $description = 'Create a test Metro + Provider (San Francisco / OB-GYN) for manual slate testing';

    public function handle(): int
    {
        $category = ProviderCategory::where('slug', 'obgyn')->first();

        if (! $category) {
            $this->error('No "obgyn" category found. Run the seeder first: php artisan db:seed --class=Database\\Seeders\\ProviderTaxonomySeeder');
            return self::FAILURE;
        }

        $metro = Metro::firstOrCreate(
            ['name' => 'San Francisco', 'state' => 'CA'],
            [
                'centroid' => new Point(37.7749, -122.4194),
                'radius_km' => 40,
                'density_tier' => 3,
                'active' => true,
            ]
        );

        $provider = Provider::updateOrCreate(
            ['npi' => '1234567890'],
            [
                'display_name' => 'Test OB-GYN Clinic',
                'phone_e164' => '+14155551234',
                'addr_line1' => '123 Market St',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94105',
                'location' => new Point(37.78, -122.42),
                'metro_id' => $metro->id,
                'source_nppes' => true,
                'status' => 'active',
            ]
        );

        $provider->categories()->syncWithoutDetaching([$category->id => ['source' => 'manual']]);

        $this->info("Metro ID: {$metro->id}");
        $this->info("Provider ID: {$provider->id}");
        $this->info('');
        $this->info("Test it: GET {your-app-url}/api/v1/marketplace/slate?category=obgyn&metro_id={$metro->id}");

        return self::SUCCESS;
    }
}
