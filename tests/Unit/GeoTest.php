<?php

namespace Tests\Unit;

use App\Support\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    public function test_haversine_distance_between_identical_points_is_zero(): void
    {
        $this->assertEqualsWithDelta(0.0, Geo::haversineKm(37.7749, -122.4194, 37.7749, -122.4194), 0.001);
    }

    public function test_haversine_distance_sf_to_oakland_is_roughly_correct(): void
    {
        // San Francisco to Oakland is ~13km as the crow flies.
        $distance = Geo::haversineKm(37.7749, -122.4194, 37.8044, -122.2712);

        $this->assertEqualsWithDelta(13, $distance, 2);
    }

    public function test_offset_moves_a_point_by_roughly_the_requested_distance(): void
    {
        $origin = ['lat' => 37.7749, 'lng' => -122.4194];
        $offset = Geo::offset($origin['lat'], $origin['lng'], 10, 90); // 10km due east

        $roundTrip = Geo::haversineKm($origin['lat'], $origin['lng'], $offset['lat'], $offset['lng']);

        $this->assertEqualsWithDelta(10, $roundTrip, 0.5);
    }
}
