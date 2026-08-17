<?php

namespace App\Console\Commands;

use App\Models\Metro;
use App\Models\PlaceDetailsCache;
use App\Models\Provider;
use App\Models\ProviderCategory;
use App\Models\VettingRecord;
use Illuminate\Console\Command;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * Generates and inserts brand new mock provider records into the providers table.
 * Usage: php artisan marketplace:seed-more-providers {count=10}
 */
class SeedMoreProvidersCommand extends Command
{
    protected $signature = 'marketplace:seed-more-providers {count=10 : Number of new provider records to generate and insert}';
    protected $description = 'Generate and insert new mock provider records into the providers database table';

    public function handle(): int
    {
        $count = (int) $this->argument('count');

        $metros = Metro::where('active', true)->get();
        $categories = ProviderCategory::where('active', true)->get();

        if ($metros->isEmpty() || $categories->isEmpty()) {
            $this->error('Please run php artisan db:seed --class=ProviderTaxonomySeeder first.');
            return self::FAILURE;
        }

        $firstNames = ['Sarah', 'Michael', 'Emily', 'David', 'Jessica', 'James', 'Amanda', 'Robert', 'Jennifer', 'William'];
        $lastNames = ['Miller', 'Davis', 'Wilson', 'Anderson', 'Taylor', 'Thomas', 'Moore', 'Jackson', 'Martin', 'Lee'];
        $specialties = ['Clinic', 'Health Associates', 'Wellness Specialists', 'Medical Group', 'Care Center', 'Institute'];

        $inserted = 0;
        for ($i = 1; $i <= $count; $i++) {
            $metro = $metros->random();
            $category = $categories->random();

            $fn = $firstNames[array_rand($firstNames)];
            $ln = $lastNames[array_rand($lastNames)];
            $spec = $specialties[array_rand($specialties)];

            $npi = (string) (9000000000 + rand(100000, 999999));
            $placeId = 'ChIJ_mock_' . bin2hex(random_bytes(8));
            $displayName = "Dr. {$fn} {$ln} {$category->display_name} {$spec}";

            // Perturb lat/lng slightly around metro centroid
            $latOffset = (rand(-50, 50) / 1000);
            $lngOffset = (rand(-50, 50) / 1000);

            $lat = ($metro->centroid ? $metro->centroid->latitude : 37.7749) + $latOffset;
            $lng = ($metro->centroid ? $metro->centroid->longitude : -122.4194) + $lngOffset;

            $provider = Provider::create([
                'npi' => $npi,
                'google_place_id' => $placeId,
                'display_name' => $displayName,
                'org_name' => "{$ln} {$spec} LLC",
                'phone_e164' => '+1' . rand(200, 999) . rand(100, 999) . rand(1000, 9999),
                'addr_line1' => rand(100, 999) . ' Main St Suite ' . rand(100, 500),
                'city' => $metro->name,
                'state' => $metro->state ?? 'CA',
                'zip' => '941' . sprintf('%02d', rand(1, 30)),
                'location' => new Point($lat, $lng, 4326),
                'metro_id' => $metro->id,
                'source_nppes' => true,
                'source_places' => true,
                'match_confidence' => round(rand(80, 99) / 100, 2),
                'status' => 'active',
            ]);

            $provider->categories()->syncWithoutDetaching([$category->id => ['source' => 'manual']]);

            PlaceDetailsCache::create([
                'provider_id' => $provider->id,
                'rating' => round(rand(42, 50) / 10, 1),
                'review_count' => rand(15, 300),
                'business_status' => 'OPERATIONAL',
                'fetched_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);

            foreach ($category->requiredCheckTypes() as $checkType) {
                VettingRecord::create([
                    'provider_id' => $provider->id,
                    'check_type' => $checkType,
                    'status' => 'pass',
                    'checked_at' => now(),
                    'next_due_at' => now()->addYear(),
                    'checked_by' => 'seeder',
                ]);
            }

            $inserted++;
        }

        $total = Provider::count();
        $this->info("Successfully generated and inserted {$inserted} NEW provider records into the providers table! Total providers in DB: {$total}");

        return self::SUCCESS;
    }
}
