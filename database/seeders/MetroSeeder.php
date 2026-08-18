<?php

namespace Database\Seeders;

use App\Models\Metro;
use Illuminate\Database\Seeder;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * Seeds major active metropolitan areas into the metros table so that
 * NPPES synchronization (SyncNppesJob) and Google Places discovery
 * (DiscoverPlacesJob) find active metros to associate providers with.
 */
class MetroSeeder extends Seeder
{
    public function run(): void
    {
        $metros = [
            [
                'name' => 'San Francisco Bay Area',
                'state' => 'CA',
                'lat' => 37.7749,
                'lng' => -122.4194,
                'radius_km' => 40,
                'density_tier' => 3,
                'active' => true,
            ],
            [
                'name' => 'Salt Lake City',
                'state' => 'UT',
                'lat' => 40.7608,
                'lng' => -111.8910,
                'radius_km' => 35,
                'density_tier' => 2,
                'active' => true,
            ],
            [
                'name' => 'New York City',
                'state' => 'NY',
                'lat' => 40.7128,
                'lng' => -74.0060,
                'radius_km' => 45,
                'density_tier' => 3,
                'active' => true,
            ],
            [
                'name' => 'Los Angeles',
                'state' => 'CA',
                'lat' => 34.0522,
                'lng' => -118.2437,
                'radius_km' => 50,
                'density_tier' => 3,
                'active' => true,
            ],
            [
                'name' => 'Chicago',
                'state' => 'IL',
                'lat' => 41.8781,
                'lng' => -87.6298,
                'radius_km' => 40,
                'density_tier' => 3,
                'active' => true,
            ],
            [
                'name' => 'Miami',
                'state' => 'FL',
                'lat' => 25.7617,
                'lng' => -80.1918,
                'radius_km' => 35,
                'density_tier' => 2,
                'active' => true,
            ],
            [
                'name' => 'Dallas-Fort Worth',
                'state' => 'TX',
                'lat' => 32.7767,
                'lng' => -96.7970,
                'radius_km' => 45,
                'density_tier' => 3,
                'active' => true,
            ],
            [
                'name' => 'Seattle',
                'state' => 'WA',
                'lat' => 47.6062,
                'lng' => -122.3321,
                'radius_km' => 35,
                'density_tier' => 2,
                'active' => true,
            ],
        ];

        foreach ($metros as $data) {
            Metro::updateOrCreate(
                ['name' => $data['name'], 'state' => $data['state']],
                [
                    'centroid' => new Point($data['lat'], $data['lng'], 4326),
                    'radius_km' => $data['radius_km'],
                    'density_tier' => $data['density_tier'],
                    'active' => $data['active'],
                ]
            );
        }
    }
}
