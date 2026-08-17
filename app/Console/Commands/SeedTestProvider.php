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
    protected $description = 'Seed realistic sample metros, providers, Place IDs, ratings, and vetting records across categories';

    public function handle(): int
    {
        $metroSF = Metro::updateOrCreate(
            ['name' => 'San Francisco', 'state' => 'CA'],
            [
                'centroid' => new Point(37.7749, -122.4194),
                'radius_km' => 40,
                'density_tier' => 3,
                'active' => true,
            ]
        );

        $metroSLC = Metro::updateOrCreate(
            ['name' => 'Salt Lake City', 'state' => 'UT'],
            [
                'centroid' => new Point(40.7608, -111.8910),
                'radius_km' => 35,
                'density_tier' => 2,
                'active' => true,
            ]
        );

        $samples = [
            [
                'metro' => $metroSF,
                'category_slug' => 'obgyn',
                'npi' => '1234567890',
                'google_place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
                'display_name' => 'SF Women\'s OB-GYN Health Center',
                'phone_e164' => '+14155551234',
                'addr_line1' => '123 Market St',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94105',
                'lat' => 37.7800,
                'lng' => -122.4200,
                'rating' => 4.8,
                'review_count' => 124,
            ],
            [
                'metro' => $metroSF,
                'category_slug' => 'dermatology',
                'npi' => '1982524955',
                'google_place_id' => 'ChIJ3S-g4_SAhYAR3w81tQ8Vd4Y',
                'display_name' => 'Bay Area Dermatology & Laser',
                'phone_e164' => '+14155552345',
                'addr_line1' => '400 Parnassus Ave',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94143',
                'lat' => 37.7630,
                'lng' => -122.4570,
                'rating' => 4.9,
                'review_count' => 210,
            ],
            [
                'metro' => $metroSF,
                'category_slug' => 'physical-therapist-sports',
                'npi' => '1013837095',
                'google_place_id' => 'ChIJmQ9xS4mAhYARJ5Zg50M1Z2k',
                'display_name' => 'Apex Sports Physical Therapy',
                'phone_e164' => '+14155553456',
                'addr_line1' => '500 Post St',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94102',
                'lat' => 37.7880,
                'lng' => -122.4100,
                'rating' => 4.7,
                'review_count' => 88,
            ],
            [
                'metro' => $metroSF,
                'category_slug' => 'pelvic-floor-pt',
                'npi' => '1255251245',
                'google_place_id' => 'ChIJw_U3yIqAhYARwZ9N62W3A5M',
                'display_name' => 'Golden Gate Pelvic Health PT',
                'phone_e164' => '+14155554567',
                'addr_line1' => '2200 Webster St',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94115',
                'lat' => 37.7910,
                'lng' => -122.4330,
                'rating' => 4.9,
                'review_count' => 65,
            ],
            [
                'metro' => $metroSF,
                'category_slug' => 'primary-care',
                'npi' => '1417340704',
                'google_place_id' => 'ChIJ44N_g4mAhYARn_kZ1_k8Y2Q',
                'display_name' => 'Pacific Heights Primary Care',
                'phone_e164' => '+14155555678',
                'addr_line1' => '2100 Webster St',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94115',
                'lat' => 37.7900,
                'lng' => -122.4320,
                'rating' => 4.6,
                'review_count' => 142,
            ],
            [
                'metro' => $metroSLC,
                'category_slug' => 'obgyn',
                'npi' => '1477960284',
                'google_place_id' => 'ChIJ2d1qQ7e1U4cR3M93j7A8Y1Q',
                'display_name' => 'Salt Lake Women\'s Clinic',
                'phone_e164' => '+18015551122',
                'addr_line1' => '100 S Main St',
                'city' => 'Salt Lake City',
                'state' => 'UT',
                'zip' => '84101',
                'lat' => 40.7610,
                'lng' => -111.8900,
                'rating' => 4.7,
                'review_count' => 110,
            ],
            [
                'metro' => $metroSLC,
                'category_slug' => 'sports-medicine-physician',
                'npi' => '1386564334',
                'google_place_id' => 'ChIJ2d1qQ7e1U4cR3M93j7A8Y1R',
                'display_name' => 'Wasatch Sports Medicine Institute',
                'phone_e164' => '+18015553344',
                'addr_line1' => '50 S Foothill Dr',
                'city' => 'Salt Lake City',
                'state' => 'UT',
                'zip' => '84112',
                'lat' => 40.7650,
                'lng' => -111.8400,
                'rating' => 4.9,
                'review_count' => 180,
            ],
        ];

        $count = 0;
        foreach ($samples as $item) {
            $category = ProviderCategory::where('slug', $item['category_slug'])->first();
            if (! $category) {
                continue;
            }

            $provider = Provider::updateOrCreate(
                ['npi' => $item['npi']],
                [
                    'google_place_id' => $item['google_place_id'],
                    'display_name' => $item['display_name'],
                    'phone_e164' => $item['phone_e164'],
                    'addr_line1' => $item['addr_line1'],
                    'city' => $item['city'],
                    'state' => $item['state'],
                    'zip' => $item['zip'],
                    'location' => new Point($item['lat'], $item['lng']),
                    'metro_id' => $item['metro']->id,
                    'source_nppes' => true,
                    'source_places' => true,
                    'match_confidence' => 0.95,
                    'status' => 'active',
                ]
            );

            $provider->touch();

            $provider->categories()->syncWithoutDetaching([$category->id => ['source' => 'manual']]);

            // Add Place Details Cache
            \App\Models\PlaceDetailsCache::updateOrCreate(
                ['provider_id' => $provider->id],
                [
                    'rating' => $item['rating'],
                    'review_count' => $item['review_count'],
                    'business_status' => 'OPERATIONAL',
                    'fetched_at' => now(),
                    'expires_at' => now()->addDays(30),
                ]
            );

            // Add Vetting Records
            foreach ($category->requiredCheckTypes() as $checkType) {
                \App\Models\VettingRecord::updateOrCreate(
                    ['provider_id' => $provider->id, 'check_type' => $checkType],
                    [
                        'status' => 'pass',
                        'checked_at' => now(),
                        'next_due_at' => now()->addYear(),
                        'checked_by' => 'seeder',
                    ]
                );
            }

            $count++;
        }

        $this->info("Seeded {$count} sample active providers with Google Place IDs, ratings, and passing vetting checks.");

        return self::SUCCESS;
    }
}
