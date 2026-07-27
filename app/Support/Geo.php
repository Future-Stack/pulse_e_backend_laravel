<?php

namespace App\Support;

/**
 * Small geo math helper shared by MarketplaceSlateService (proximity scoring)
 * and ZipMetroResolver (nearest-metro lookup), so distance math lives in one place.
 */
class Geo
{
    private const EARTH_RADIUS_KM = 6371;

    public static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $dLat = $lat2 - $lat1;
        $dLon = $lon2 - $lon1;

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    /**
     * Offsets a lat/lng point by a distance in km along a bearing (degrees).
     * Used to build a search grid across a metro's radius for Places discovery.
     */
    public static function offset(float $lat, float $lng, float $distanceKm, float $bearingDeg): array
    {
        $bearing = deg2rad($bearingDeg);
        $latRad = deg2rad($lat);
        $lngRad = deg2rad($lng);
        $angularDistance = $distanceKm / self::EARTH_RADIUS_KM;

        $newLat = asin(
            sin($latRad) * cos($angularDistance) + cos($latRad) * sin($angularDistance) * cos($bearing)
        );

        $newLng = $lngRad + atan2(
            sin($bearing) * sin($angularDistance) * cos($latRad),
            cos($angularDistance) - sin($latRad) * sin($newLat)
        );

        return ['lat' => rad2deg($newLat), 'lng' => rad2deg($newLng)];
    }
}
