<?php

namespace App\Services;

use App\Models\Metro;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a ZIP code to the nearest active Metro, using the Google Geocoding
 * API (same Google Cloud project/key as GooglePlacesClient) to turn the ZIP
 * into lat/lng, then picking whichever active metro's centroid is closest —
 * capped by that metro's own radius_km so a ZIP far from every operating
 * market correctly returns "no match" rather than the nearest-but-irrelevant
 * metro.
 *
 * This keeps the spec 2.1 privacy boundary intact: the geocoding call only
 * ever sees a bare 5-digit ZIP, never a member identifier or precise address.
 * Results are cached for 30 days since ZIP centroids never move.
 */
class ZipGeocodingService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('marketplace.google_geocoding_api_key');
    }

    public function resolveMetro(string $zip): ?Metro
    {
        $coords = $this->geocodeZip($zip);

        if (! $coords) {
            return null;
        }

        return Metro::where('active', true)
            ->get()
            ->map(function (Metro $metro) use ($coords) {
                $metro->setAttribute('_distance_km', $this->haversineKm(
                    $coords['lat'], $coords['lng'],
                    $metro->centroid->latitude, $metro->centroid->longitude
                ));

                return $metro;
            })
            ->filter(fn (Metro $metro) => $metro->_distance_km <= $metro->radius_km)
            ->sortBy('_distance_km')
            ->first();
    }

    /** @return array{lat: float, lng: float}|null */
    private function geocodeZip(string $zip): ?array
    {
        return Cache::remember("zip-geocode:{$zip}", now()->addDays((int) config('marketplace.zip_metro_cache_days', 90)), function () use ($zip) {
            try {
                $response = Http::timeout(5)->retry(2, 100)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'address' => $zip,
                    'components' => 'country:US',
                    'key' => $this->apiKey,
                ]);

                if (! $response->successful() || $response->json('status') !== 'OK') {
                    Log::warning('ZipGeocodingService: geocode failed', ['zip' => $zip, 'status' => $response->json('status')]);
                    return null;
                }

                $location = $response->json('results.0.geometry.location');

                return $location ? ['lat' => $location['lat'], 'lng' => $location['lng']] : null;
            } catch (\Throwable $e) {
                Log::warning('ZipGeocodingService: network timeout/exception', ['zip' => $zip, 'error' => $e->getMessage()]);
                return null;
            }
        });
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1 = deg2rad($lat1);
        $lng1 = deg2rad($lng1);
        $lat2 = deg2rad($lat2);
        $lng2 = deg2rad($lng2);

        $dLat = $lat2 - $lat1;
        $dLng = $lng2 - $lng1;

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return 6371 * $c;
    }
}
