<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Robust client for Google Places API.
 * Supports Places API (New) and automatically falls back to Legacy Places API
 * if Places API (New) is not enabled on the Google Cloud project.
 *
 * Used by DiscoverPlacesJob (Text Search) and RefreshPlaceDetailsJob (Place Details).
 * All calls originate strictly from batch background jobs to maintain privacy.
 */
class GooglePlacesClient
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('marketplace.google_places_api_key');
    }

    /**
     * Search places by query, location coordinates, radius, and optional type.
     * Tries Places API (New) first; falls back to Legacy Text Search if needed.
     *
     * @return array<int, array{
     *     place_id: string,
     *     display_name: string,
     *     phone_e164: ?string,
     *     website: ?string,
     *     lat: float,
     *     lng: float,
     *     business_status: ?string,
     *     formatted_address: ?string,
     *     addr_line1: ?string,
     *     addr_line2: ?string,
     *     city: ?string,
     *     state: ?string,
     *     zip: ?string,
     *     rating: ?float,
     *     review_count: ?int,
     *     hours_json: ?array
     * }>
     */
    public function textSearch(string $textQuery, float $lat, float $lng, int $radiusMeters, ?string $includedType = null): array
    {
        if (empty($this->apiKey)) {
            Log::warning('GooglePlacesClient: Cannot search places because GOOGLE_PLACES_API_KEY is not set.');
            return [];
        }

        // 1. Try Places API (New)
        $newResults = $this->textSearchNew($textQuery, $lat, $lng, $radiusMeters, $includedType);
        if ($newResults !== null) {
            return $newResults;
        }

        // 2. Fallback to Legacy Places API Text Search
        return $this->textSearchLegacy($textQuery, $lat, $lng, $radiusMeters, $includedType);
    }

    /**
     * Places API (New) Text Search
     * POST https://places.googleapis.com/v1/places:searchText
     */
    private function textSearchNew(string $textQuery, float $lat, float $lng, int $radiusMeters, ?string $includedType = null): ?array
    {
        try {
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

            if (! empty($includedType)) {
                $payload['includedType'] = $includedType;
            }

            $response = Http::timeout(10)->withHeaders([
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => implode(',', [
                    'places.id',
                    'places.displayName',
                    'places.internationalPhoneNumber',
                    'places.nationalPhoneNumber',
                    'places.websiteUri',
                    'places.location',
                    'places.businessStatus',
                    'places.formattedAddress',
                    'places.addressComponents',
                    'places.rating',
                    'places.userRatingCount',
                    'places.regularOpeningHours',
                ]),
            ])->post('https://places.googleapis.com/v1/places:searchText', $payload);

            if ($response->status() === 403 || $response->status() === 404) {
                Log::info('GooglePlacesClient: Places API (New) not available (status ' . $response->status() . '). Falling back to Legacy Places API.');
                return null;
            }

            if (! $response->successful()) {
                Log::warning('GooglePlacesClient::textSearchNew failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return collect($response->json('places', []))->map(function ($place) {
                $addr = $this->parseAddressComponentsNew($place['addressComponents'] ?? [], $place['formattedAddress'] ?? null);
                $phone = $this->normalizePhone(
                    $place['internationalPhoneNumber'] ?? $place['nationalPhoneNumber'] ?? null
                );

                return [
                    'place_id' => (string) ($place['id'] ?? ''),
                    'display_name' => (string) ($place['displayName']['text'] ?? ''),
                    'phone_e164' => $phone,
                    'website' => $place['websiteUri'] ?? null,
                    'lat' => isset($place['location']['latitude']) ? (float) $place['location']['latitude'] : null,
                    'lng' => isset($place['location']['longitude']) ? (float) $place['location']['longitude'] : null,
                    'business_status' => $place['businessStatus'] ?? null,
                    'formatted_address' => $place['formattedAddress'] ?? null,
                    'addr_line1' => $addr['addr_line1'],
                    'addr_line2' => $addr['addr_line2'],
                    'city' => $addr['city'],
                    'state' => $addr['state'],
                    'zip' => $addr['zip'],
                    'rating' => isset($place['rating']) ? (float) $place['rating'] : null,
                    'review_count' => isset($place['userRatingCount']) ? (int) $place['userRatingCount'] : null,
                    'hours_json' => $place['regularOpeningHours']['weekdayDescriptions'] ?? null,
                ];
            })->filter(fn ($p) => ! empty($p['place_id']) && $p['lat'] !== null && $p['lng'] !== null)->values()->all();
        } catch (\Throwable $e) {
            Log::warning('GooglePlacesClient: Exception in textSearchNew: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Legacy Places API Text Search
     * GET https://maps.googleapis.com/maps/api/place/textsearch/json
     */
    private function textSearchLegacy(string $textQuery, float $lat, float $lng, int $radiusMeters, ?string $includedType = null): array
    {
        try {
            $params = [
                'query' => $textQuery,
                'location' => "{$lat},{$lng}",
                'radius' => $radiusMeters,
                'key' => $this->apiKey,
            ];

            if (! empty($includedType)) {
                $params['type'] = $includedType;
            }

            $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/place/textsearch/json', $params);

            if (! $response->successful()) {
                Log::warning('GooglePlacesClient::textSearchLegacy HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $json = $response->json();
            $status = $json['status'] ?? 'UNKNOWN';

            if ($status !== 'OK' && $status !== 'ZERO_RESULTS') {
                Log::warning('GooglePlacesClient::textSearchLegacy API status error', [
                    'status' => $status,
                    'error_message' => $json['error_message'] ?? null,
                ]);
                return [];
            }

            return collect($json['results'] ?? [])->map(function ($place) {
                $formattedAddress = $place['formatted_address'] ?? null;
                $addr = $this->parseFormattedAddress($formattedAddress);

                return [
                    'place_id' => (string) ($place['place_id'] ?? ''),
                    'display_name' => (string) ($place['name'] ?? ''),
                    'phone_e164' => null, // populated during details refresh or if available
                    'website' => null,
                    'lat' => isset($place['geometry']['location']['lat']) ? (float) $place['geometry']['location']['lat'] : null,
                    'lng' => isset($place['geometry']['location']['lng']) ? (float) $place['geometry']['location']['lng'] : null,
                    'business_status' => $place['business_status'] ?? null,
                    'formatted_address' => $formattedAddress,
                    'addr_line1' => $addr['addr_line1'],
                    'addr_line2' => $addr['addr_line2'],
                    'city' => $addr['city'],
                    'state' => $addr['state'],
                    'zip' => $addr['zip'],
                    'rating' => isset($place['rating']) ? (float) $place['rating'] : null,
                    'review_count' => isset($place['user_ratings_total']) ? (int) $place['user_ratings_total'] : null,
                    'hours_json' => isset($place['opening_hours']['weekday_text']) ? $place['opening_hours']['weekday_text'] : null,
                ];
            })->filter(fn ($p) => ! empty($p['place_id']) && $p['lat'] !== null && $p['lng'] !== null)->values()->all();
        } catch (\Throwable $e) {
            Log::warning('GooglePlacesClient: Exception in textSearchLegacy: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Parse structured addressComponents from Places API (New)
     */
    private function parseAddressComponentsNew(array $components, ?string $formattedAddress = null): array
    {
        $byType = [];
        foreach ($components as $component) {
            foreach ($component['types'] ?? [] as $type) {
                $byType[$type] = $component;
            }
        }

        $streetNumber = $byType['street_number']['longText'] ?? $byType['street_number']['shortText'] ?? null;
        $route = $byType['route']['longText'] ?? $byType['route']['shortText'] ?? null;
        $addrLine1 = trim(($streetNumber ? $streetNumber . ' ' : '') . ($route ?? ''));

        $subpremise = $byType['subpremise']['longText'] ?? $byType['subpremise']['shortText'] ?? null;
        $city = $byType['locality']['longText'] ?? $byType['sublocality']['longText'] ?? $byType['postal_town']['longText'] ?? $byType['administrative_area_level_2']['longText'] ?? null;
        $state = $byType['administrative_area_level_1']['shortText'] ?? $byType['administrative_area_level_1']['longText'] ?? null;
        $zip = $byType['postal_code']['longText'] ?? $byType['postal_code']['shortText'] ?? null;

        if (empty($addrLine1) && ! empty($formattedAddress)) {
            $fallback = $this->parseFormattedAddress($formattedAddress);
            $addrLine1 = $fallback['addr_line1'];
            $city = $city ?? $fallback['city'];
            $state = $state ?? $fallback['state'];
            $zip = $zip ?? $fallback['zip'];
        }

        return [
            'addr_line1' => $addrLine1 !== '' ? $addrLine1 : null,
            'addr_line2' => $subpremise,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
        ];
    }

    /**
     * Parse structured addressComponents from Legacy Places API
     */
    private function parseAddressComponentsLegacy(array $components, ?string $formattedAddress = null): array
    {
        $byType = [];
        foreach ($components as $component) {
            foreach ($component['types'] ?? [] as $type) {
                $byType[$type] = $component;
            }
        }

        $streetNumber = $byType['street_number']['long_name'] ?? $byType['street_number']['short_name'] ?? null;
        $route = $byType['route']['long_name'] ?? $byType['route']['short_name'] ?? null;
        $addrLine1 = trim(($streetNumber ? $streetNumber . ' ' : '') . ($route ?? ''));

        $subpremise = $byType['subpremise']['long_name'] ?? $byType['subpremise']['short_name'] ?? null;
        $city = $byType['locality']['long_name'] ?? $byType['sublocality']['long_name'] ?? $byType['postal_town']['long_name'] ?? $byType['administrative_area_level_2']['long_name'] ?? null;
        $state = $byType['administrative_area_level_1']['short_name'] ?? $byType['administrative_area_level_1']['long_name'] ?? null;
        $zip = $byType['postal_code']['long_name'] ?? $byType['postal_code']['short_name'] ?? null;

        if (empty($addrLine1) && ! empty($formattedAddress)) {
            $fallback = $this->parseFormattedAddress($formattedAddress);
            $addrLine1 = $fallback['addr_line1'];
            $city = $city ?? $fallback['city'];
            $state = $state ?? $fallback['state'];
            $zip = $zip ?? $fallback['zip'];
        }

        return [
            'addr_line1' => $addrLine1 !== '' ? $addrLine1 : null,
            'addr_line2' => $subpremise,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
        ];
    }

    /**
     * Fallback parser for standard US formatted addresses (e.g. "490 Post St Ste 320, San Francisco, CA 94102, USA")
     */
    private function parseFormattedAddress(?string $formattedAddress): array
    {
        if (empty($formattedAddress)) {
            return [
                'addr_line1' => null,
                'addr_line2' => null,
                'city' => null,
                'state' => null,
                'zip' => null,
            ];
        }

        $parts = array_map('trim', explode(',', $formattedAddress));
        // Remove trailing country if present (e.g., USA, US)
        if (count($parts) > 1 && in_array(end($parts), ['USA', 'US', 'United States'], true)) {
            array_pop($parts);
        }

        $addrLine1 = $parts[0] ?? null;
        $city = null;
        $state = null;
        $zip = null;

        if (count($parts) >= 3) {
            $city = $parts[1];
            $stateZip = $parts[2];
            if (preg_match('/^([A-Z]{2})\s+(\d{5}(?:-\d{4})?)$/i', $stateZip, $m)) {
                $state = strtoupper($m[1]);
                $zip = $m[2];
            } else {
                $state = $stateZip;
            }
        } elseif (count($parts) === 2) {
            $stateZip = $parts[1];
            if (preg_match('/^([A-Z]{2})\s+(\d{5}(?:-\d{4})?)$/i', $stateZip, $m)) {
                $state = strtoupper($m[1]);
                $zip = $m[2];
            } else {
                $city = $parts[1];
            }
        }

        return [
            'addr_line1' => $addrLine1,
            'addr_line2' => null,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
        ];
    }

    /**
     * Normalize phone numbers to E.164 format (+1XXXXXXXXXX for US)
     */
    private function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $digits = preg_replace('/[^\d+]/', '', $phone);
        if (str_starts_with($digits, '+')) {
            return substr($digits, 0, 20);
        }

        $cleanDigits = preg_replace('/\D/', '', $phone);
        if (strlen($cleanDigits) === 10) {
            return '+1' . $cleanDigits;
        }
        if (strlen($cleanDigits) === 11 && str_starts_with($cleanDigits, '1')) {
            return '+' . $cleanDigits;
        }

        return substr($digits, 0, 20);
    }

    /**
     * Places API Place Details — used by the rolling 14-day refresh job.
     * Supports both Places API (New) and Legacy Place Details.
     *
     * @return array{
     *     rating: ?float,
     *     review_count: ?int,
     *     hours_json: ?array,
     *     business_status: ?string,
     *     phone_e164: ?string,
     *     website: ?string,
     *     addr_line1: ?string,
     *     addr_line2: ?string,
     *     city: ?string,
     *     state: ?string,
     *     zip: ?string
     * }|null
     */
    public function placeDetails(string $placeId): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        // 1. Try Places API (New)
        try {
            $response = Http::timeout(10)->withHeaders([
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => 'rating,userRatingCount,regularOpeningHours,businessStatus,internationalPhoneNumber,nationalPhoneNumber,websiteUri,formattedAddress,addressComponents',
            ])->get("https://places.googleapis.com/v1/places/{$placeId}");

            if ($response->successful()) {
                $data = $response->json();
                $addr = $this->parseAddressComponentsNew($data['addressComponents'] ?? [], $data['formattedAddress'] ?? null);
                $phone = $this->normalizePhone($data['internationalPhoneNumber'] ?? $data['nationalPhoneNumber'] ?? null);

                return [
                    'rating' => isset($data['rating']) ? (float) $data['rating'] : null,
                    'review_count' => isset($data['userRatingCount']) ? (int) $data['userRatingCount'] : null,
                    'hours_json' => $data['regularOpeningHours']['weekdayDescriptions'] ?? null,
                    'business_status' => $data['businessStatus'] ?? null,
                    'phone_e164' => $phone,
                    'website' => $data['websiteUri'] ?? null,
                    'addr_line1' => $addr['addr_line1'],
                    'addr_line2' => $addr['addr_line2'],
                    'city' => $addr['city'],
                    'state' => $addr['state'],
                    'zip' => $addr['zip'],
                ];
            }
        } catch (\Throwable $e) {
            Log::info('GooglePlacesClient: Places API (New) details failed; trying legacy. ' . $e->getMessage());
        }

        // 2. Fallback to Legacy Place Details
        try {
            $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/place/details/json', [
                'place_id' => $placeId,
                'fields' => 'name,formatted_address,formatted_phone_number,international_phone_number,geometry,website,rating,user_ratings_total,opening_hours,business_status,address_components',
                'key' => $this->apiKey,
            ]);

            if ($response->successful() && ($response->json('status') === 'OK')) {
                $result = $response->json('result', []);
                $addr = $this->parseAddressComponentsLegacy($result['address_components'] ?? [], $result['formatted_address'] ?? null);
                $phone = $this->normalizePhone($result['international_phone_number'] ?? $result['formatted_phone_number'] ?? null);

                return [
                    'rating' => isset($result['rating']) ? (float) $result['rating'] : null,
                    'review_count' => isset($result['user_ratings_total']) ? (int) $result['user_ratings_total'] : null,
                    'hours_json' => $result['opening_hours']['weekday_text'] ?? null,
                    'business_status' => $result['business_status'] ?? null,
                    'phone_e164' => $phone,
                    'website' => $result['website'] ?? null,
                    'addr_line1' => $addr['addr_line1'],
                    'addr_line2' => $addr['addr_line2'],
                    'city' => $addr['city'],
                    'state' => $addr['state'],
                    'zip' => $addr['zip'],
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('GooglePlacesClient: Exception in Legacy Place Details: ' . $e->getMessage());
        }

        return null;
    }
}