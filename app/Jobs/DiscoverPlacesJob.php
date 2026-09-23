<?php

namespace App\Jobs;

use App\Models\CategoryPlaceQuery;
use App\Models\Metro;
use App\Models\PlaceDetailsCache;
use App\Models\Provider;
use App\Models\ProviderCategory;
use App\Services\GooglePlacesClient;
use App\Support\AddressNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * places:discover — weekly (spec 2.3).
 * For each active metro x category, runs Google Places Text Search over the
 * category_place_queries recipes across a grid derived from the metro
 * centroid/radius. Stores place_id + geocode, address, phone, and website,
 * attempts the NPPES join (normalized phone + fuzzy name match).
 * Runs only from batch jobs, never from a live user request (spec 2.1 privacy boundary).
 */
class DiscoverPlacesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Grid tile radius. Google Text Search location bias works best with tiles
    // roughly 5-8km across; a metro with radius_km=15 gets a 3x3-ish grid.
    private const TILE_RADIUS_KM = 6;

    /**
     * [SAFETY FIX FOR GOOGLE BILLING]:
     * Hard-capped at 1 to prevent queue worker retry loops against external APIs.
     * To revert to default Laravel worker behavior, comment out $tries:
     * // (original: no explicit $tries limit)
     */
    public $tries = 1;

    public $timeout = 900;

    public function __construct(private readonly Metro $metro, private readonly ProviderCategory $category)
    {
    }

    public function handle(GooglePlacesClient $client): void
    {
        /*
        |--------------------------------------------------------------------------
        | SAFETY GUARDS (GOOGLE BILLING & QUOTA PROTECTION)
        |--------------------------------------------------------------------------
        | Protects against accidental discovery sweeps outside production.
        | If the client ever wants the original behavior without environment checks,
        | simply comment out the 2 guard blocks below or set MARKETPLACE_ALLOW_LOCAL_DISCOVERY=true in .env
        |
        | [ORIGINAL CODE]: Ran directly into $queries without environment checking.
        */
        // Guard 1: Master switch in configuration
        if (! config('marketplace.enable_places_discovery', true)) {
            Log::info('DiscoverPlacesJob: Skipped because marketplace.enable_places_discovery is false.');
            return;
        }

        // Guard 2: Restrict sweeps outside production (local/staging) unless explicitly allowed or in testing
        if (! app()->isProduction() && ! app()->runningUnitTests() && ! config('marketplace.allow_local_places_discovery', false)) {
            Log::info("DiscoverPlacesJob: Skipped discovery sweep outside production for Metro [{$this->metro->id}] Category [{$this->category->id}] to prevent accidental API consumption.");
            return;
        }

        $queries = CategoryPlaceQuery::where('category_id', $this->category->id)->get();

        if ($queries->isEmpty()) {
            // Fallback default query if no category_place_queries exist
            $queries = collect([
                (object) [
                    'keyword' => $this->category->display_name,
                    'places_type' => null,
                ],
            ]);
        }

        $grid = $this->searchGrid();

        foreach ($queries as $query) {
            foreach ($grid as $point) {
                $results = $client->textSearch(
                    textQuery: $query->keyword,
                    lat: $point['lat'],
                    lng: $point['lng'],
                    radiusMeters: self::TILE_RADIUS_KM * 1000,
                    includedType: $query->places_type,
                );

                foreach ($results as $place) {
                    $this->upsertPlace($place, $query->keyword);
                }
            }
        }
    }

    /**
     * Tiles the metro's centroid/radius_km into a square grid of search points so
     * Text Search covers the whole metro instead of just its center.
     *
     * @return array<array{lat: float, lng: float}>
     */
    private function searchGrid(): array
    {
        $centroid = $this->metro->centroid;

        if (! $centroid) {
            return [];
        }

        $radiusKm = (float) $this->metro->radius_km;
        $tilesPerSide = max(1, (int) ceil(($radiusKm * 2) / self::TILE_RADIUS_KM));
        $stepKm = ($radiusKm * 2) / $tilesPerSide;

        $points = [];
        $kmPerDegreeLat = 110.574;
        $kmPerDegreeLng = 111.320 * cos(deg2rad($centroid->latitude));

        for ($i = 0; $i < $tilesPerSide; $i++) {
            for ($j = 0; $j < $tilesPerSide; $j++) {
                $offsetKmLat = -$radiusKm + $stepKm * $i + $stepKm / 2;
                $offsetKmLng = -$radiusKm + $stepKm * $j + $stepKm / 2;

                $lat = $centroid->latitude + ($offsetKmLat / $kmPerDegreeLat);
                $lng = $centroid->longitude + ($offsetKmLng / $kmPerDegreeLng);

                // Skip tiles whose center falls clearly outside the metro's circle.
                if (sqrt($offsetKmLat ** 2 + $offsetKmLng ** 2) > $radiusKm * 1.05) {
                    continue;
                }

                $points[] = ['lat' => $lat, 'lng' => $lng];
            }
        }

        // Always ensure at least the metro centroid is included
        if (empty($points)) {
            $points[] = ['lat' => $centroid->latitude, 'lng' => $centroid->longitude];
        }

        return $points;
    }

    private function upsertPlace(array $place, string $matchedKeyword): void
    {
        if (empty($place['place_id']) || empty($place['display_name']) || $place['lat'] === null || $place['lng'] === null) {
            return;
        }

        $existing = Provider::where('google_place_id', $place['place_id'])->first();

        if (! $existing && ! empty($place['phone_e164'])) {
            $existing = Provider::where('phone_e164', $place['phone_e164'])
                ->whereNull('google_place_id')
                ->first();
        }

        // Fuzzy name + address fallback match for NPPES records in this metro with null google_place_id
        if (! $existing) {
            $candidates = Provider::where('metro_id', $this->metro->id)
                ->whereNull('google_place_id')
                ->where('source_nppes', true)
                ->get();

            $bestCandidate = null;
            $bestScore = 0.0;

            foreach ($candidates as $candidate) {
                $score = $this->matchConfidence($candidate, $place);
                if ($score >= 0.70 && $score > $bestScore) {
                    $bestScore = $score;
                    $bestCandidate = $candidate;
                }
            }

            if ($bestCandidate) {
                $existing = $bestCandidate;
            }
        }

        $matchedByNppes = $existing && $existing->source_nppes;

        $matchConfidence = $matchedByNppes
            ? $this->matchConfidence($existing, $place)
            : null;

        $displayName = mb_substr(trim($existing?->display_name ?? $place['display_name']), 0, 160);
        $phone = ! empty($place['phone_e164']) ? mb_substr(trim($place['phone_e164']), 0, 20) : ($existing?->phone_e164);
        $website = ! empty($place['website']) ? mb_substr(trim($place['website']), 0, 255) : ($existing?->website);
        $addrLine1 = ! empty($place['addr_line1']) ? mb_substr(trim($place['addr_line1']), 0, 255) : ($existing?->addr_line1);
        $addrLine2 = ! empty($place['addr_line2']) ? mb_substr(trim($place['addr_line2']), 0, 255) : ($existing?->addr_line2);
        $city = ! empty($place['city']) ? mb_substr(trim($place['city']), 0, 255) : ($existing?->city);
        $state = ! empty($place['state']) ? mb_substr(trim($place['state']), 0, 255) : ($existing?->state);
        $zip = ! empty($place['zip']) ? mb_substr(trim($place['zip']), 0, 255) : ($existing?->zip);

        $orgName = mb_substr(trim($existing?->org_name ?? $displayName), 0, 160);

        $provider = Provider::updateOrCreate(
            $existing ? ['id' => $existing->id] : ['google_place_id' => $place['place_id']],
            array_filter([
                'google_place_id' => $place['place_id'],
                'display_name' => $displayName,
                'org_name' => $orgName,
                'phone_e164' => $phone,
                'website' => $website,
                'addr_line1' => $addrLine1,
                'addr_line2' => $addrLine2,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'location' => new Point((float) $place['lat'], (float) $place['lng'], 4326),
                'metro_id' => $this->metro->id,
                'source_places' => true,
                'match_confidence' => $matchConfidence,
                'status' => $existing?->status,
            ], fn ($v) => $v !== null) + ['status' => $existing?->status ?? 'candidate']
        );

        $provider->categories()->syncWithoutDetaching([
            $this->category->id => ['source' => $matchedByNppes ? 'nppes_taxonomy' : 'places_match'],
        ]);

        // Save rating/reviews/hours into place_details_cache if returned by Google
        if (isset($place['rating']) || isset($place['review_count']) || ! empty($place['hours_json']) || ! empty($place['business_status'])) {
            PlaceDetailsCache::updateOrCreate(
                ['provider_id' => $provider->id],
                [
                    'rating' => $place['rating'] ?? null,
                    'review_count' => $place['review_count'] ?? null,
                    'hours_json' => $place['hours_json'] ?? null,
                    'business_status' => $place['business_status'] ?? null,
                    'fetched_at' => now(),
                    'expires_at' => now()->addDays(30),
                ]
            );
        }

        // If phone or website is missing from the search payload, queue Place Details refresh
        if (empty($provider->phone_e164) || empty($provider->website)) {
            RefreshPlaceDetailsJob::dispatch($provider);
        }
    }

    /**
     * Blended fuzzy-match score (0.00-1.00) between an existing NPPES record and
     * a Places search result: 60% name similarity + 40% normalized-address
     * similarity.
     */
    private function matchConfidence(Provider $existing, array $place): float
    {
        $nameA = strtolower(trim($existing->display_name));
        $nameB = strtolower(trim($place['display_name']));
        similar_text($nameA, $nameB, $namePercent);
        $nameScore = $nameA === '' || $nameB === '' ? 0.0 : $namePercent / 100;

        $existingAddress = trim(($existing->addr_line1 ?? '') . ' ' . ($existing->city ?? '') . ' ' . ($existing->state ?? ''));
        $addressScore = AddressNormalizer::similarity($existingAddress, $place['formatted_address'] ?? null);

        return round(($nameScore * 0.6) + ($addressScore * 0.4), 2);
    }
}
