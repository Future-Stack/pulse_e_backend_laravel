<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the Places API (New) — https://places.googleapis.com.
 * Requires GOOGLE_PLACES_API_KEY in .env (config/marketplace.php reads it).
 *
 * Used by DiscoverPlacesJob (Text Search) and RefreshPlaceDetailsJob (Place Details).
 * Both calls originate only from batch jobs, never from a live user request —
 * this preserves the spec 2.1 privacy boundary (Google never observes that a
 * specific member was shown a category).
 */
class GooglePlacesClient
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('marketplace.google_places_api_key');
    }

    /**
     * Places API (New) Text Search.
     * https://developers.google.com/maps/documentation/places/web-service/text-search
     *
     * @return array<int, array{place_id: string, display_name: string, phone_e164: ?string, lat: float, lng: float, business_status: ?string}>
     */
    public function textSearch(string $textQuery, float $lat, float $lng, int $radiusMeters, ?string $includedType = null): array
    {
        $payload = array_filter([
            'textQuery' => $textQuery,
            'locationBias' => [
                'circle' => [
                    'center' => ['latitude' => $lat, 'longitude' => $lng],
                    'radius' => $radiusMeters,
                ],
            ],
            'includedType' => $includedType,
            'maxResultCount' => 20,
        ]);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => implode(',', [
                'places.id',
                'places.displayName',
                'places.internationalPhoneNumber',
                'places.location',
                'places.businessStatus',
                'places.formattedAddress',
            ]),
        ])->post('https://places.googleapis.com/v1/places:searchText', $payload);

        if (! $response->successful()) {
            Log::warning('GooglePlacesClient::textSearch failed', ['status' => $response->status(), 'body' => $response->body()]);
            return [];
        }

        return collect($response->json('places', []))->map(fn ($place) => [
            'place_id' => $place['id'],
            'display_name' => $place['displayName']['text'] ?? '',
            'phone_e164' => $place['internationalPhoneNumber'] ?? null,
            'lat' => $place['location']['latitude'] ?? null,
            'lng' => $place['location']['longitude'] ?? null,
            'business_status' => $place['businessStatus'] ?? null,
            'formatted_address' => $place['formattedAddress'] ?? null,
        ])->filter(fn ($p) => $p['lat'] !== null)->values()->all();
    }

    /**
     * Places API (New) Place Details — used by the rolling 14-day refresh job.
     * https://developers.google.com/maps/documentation/places/web-service/place-details
     *
     * @return array{rating: ?float, review_count: ?int, hours_json: ?array, business_status: ?string}|null
     */
    public function placeDetails(string $placeId): ?array
    {
        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => 'rating,userRatingCount,regularOpeningHours,businessStatus',
        ])->get("https://places.googleapis.com/v1/places/{$placeId}");

        if (! $response->successful()) {
            Log::warning('GooglePlacesClient::placeDetails failed', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        $data = $response->json();

        return [
            'rating' => $data['rating'] ?? null,
            'review_count' => $data['userRatingCount'] ?? null,
            'hours_json' => $data['regularOpeningHours']['weekdayDescriptions'] ?? null,
            'business_status' => $data['businessStatus'] ?? null,
        ];
    }
}
