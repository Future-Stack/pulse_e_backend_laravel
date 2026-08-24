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
     * @return array<int, array{place_id: string, display_name: string, phone_e164: ?string, lat: float, lng: float, business_status: ?string, addr_line1: ?string, addr_line2: ?string, city: ?string, state: ?string, zip: ?string}>
     */
    public function textSearch(string $textQuery, float $lat, float $lng, int $radiusMeters, ?string $includedType = null): array
    {
        $payload = [
            'textQuery' => $textQuery,
            'locationBias' => [
                'circle' => [
                    'center' => [
                        'latitude' => $lat,
                        'longitude' => $lng,
                    ],
                    'radius' => (float) $radiusMeters,
                ],
            ],
            'maxResultCount' => 20,
        ];

        // includedType null বা ফাঁকা থাকলে গুগলে রিকোয়েস্ট বডিতে পাঠানো যাবে না
        if (! empty($includedType)) {
            $payload['includedType'] = $includedType;
        }

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
                'places.addressComponents',
            ]),
        ])->post('https://places.googleapis.com/v1/places:searchText', $payload);

        if (! $response->successful()) {
            Log::warning('GooglePlacesClient::textSearch failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        }

        return collect($response->json('places', []))->map(function ($place) {
            $addr = $this->parseAddressComponents($place['addressComponents'] ?? []);

            return [
                'place_id' => $place['id'],
                'display_name' => $place['displayName']['text'] ?? '',
                'phone_e164' => $place['internationalPhoneNumber'] ?? null,
                'lat' => $place['location']['latitude'] ?? null,
                'lng' => $place['location']['longitude'] ?? null,
                'business_status' => $place['businessStatus'] ?? null,
                'formatted_address' => $place['formattedAddress'] ?? null,
                'addr_line1' => $addr['addr_line1'],
                'addr_line2' => $addr['addr_line2'],
                'city' => $addr['city'],
                'state' => $addr['state'],
                'zip' => $addr['zip'],
            ];
        })->filter(fn ($p) => $p['lat'] !== null)->values()->all();
    }

    /**
     * Places API (New) returns structured addressComponents (type-tagged parts)
     * rather than a single string — parsing those is far more reliable than
     * splitting formattedAddress, whose comma layout varies by locale.
     *
     * @param array<int, array{longText?: string, shortText?: string, types?: array<string>}> $components
     * @return array{addr_line1: ?string, addr_line2: ?string, city: ?string, state: ?string, zip: ?string}
     */
    private function parseAddressComponents(array $components): array
    {
        $byType = [];
        foreach ($components as $component) {
            foreach ($component['types'] ?? [] as $type) {
                $byType[$type] = $component;
            }
        }

        $streetNumber = $byType['street_number']['longText'] ?? null;
        $route = $byType['route']['longText'] ?? null;
        $addrLine1 = trim(($streetNumber ? $streetNumber . ' ' : '') . ($route ?? ''));

        $subpremise = $byType['subpremise']['longText'] ?? null;

        return [
            'addr_line1' => $addrLine1 !== '' ? $addrLine1 : null,
            'addr_line2' => $subpremise,
            'city' => $byType['locality']['longText'] ?? $byType['postal_town']['longText'] ?? null,
            'state' => $byType['administrative_area_level_1']['shortText'] ?? null,
            'zip' => $byType['postal_code']['longText'] ?? null,
        ];
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
            Log::warning('GooglePlacesClient::placeDetails failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
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