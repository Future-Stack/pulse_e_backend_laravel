<?php

namespace App\Jobs;

use App\Models\CategoryPlaceQuery;
use App\Models\Metro;
use App\Models\Provider;
use App\Models\ProviderCategory;
use App\Services\GooglePlacesClient;
use App\Support\AddressNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * places:discover — weekly (spec 2.3).
 * For each active metro x category, runs Google Places Text Search over the
 * category_place_queries recipes across a grid derived from the metro
 * centroid/radius. Stores place_id + geocode, attempts the NPPES join
 * (normalized phone + fuzzy name match). Runs only from batch jobs, never
 * from a live user request (spec 2.1 privacy boundary).
 *
 * Dispatch one job per (metro, category), e.g. from a console command:
 *   foreach (Metro::active()->get() as $metro)
 *     foreach (ProviderCategory::active()->get() as $category)
 *       DiscoverPlacesJob::dispatch($metro, $category);
 */
class DiscoverPlacesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Grid tile radius. Google Text Search location bias works best with tiles
    // roughly 5-8km across; a metro with radius_km=15 gets a 3x3-ish grid.
    private const TILE_RADIUS_KM = 6;

    public $timeout = 900;

    public function __construct(private readonly Metro $metro, private readonly ProviderCategory $category)
    {
    }

    public function handle(GooglePlacesClient $client): void
    {
        $queries = CategoryPlaceQuery::where('category_id', $this->category->id)->get();

        foreach ($queries as $query) {
            foreach ($this->searchGrid() as $point) {
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
     * Text Search (which returns at most ~20 results per call) covers the whole
     * metro instead of just its center.
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

        return $points;
    }

    private function upsertPlace(array $place, string $matchedKeyword): void
    {
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

        $provider = Provider::updateOrCreate(
            $existing ? ['id' => $existing->id] : ['google_place_id' => $place['place_id']],
            array_filter([
                'google_place_id' => $place['place_id'],
                'display_name' => $existing?->display_name ?? $place['display_name'],
                'phone_e164' => $existing?->phone_e164 ?? $place['phone_e164'],
                'location' => new Point($place['lat'], $place['lng']),
                'metro_id' => $this->metro->id,
                'source_places' => true,
                'match_confidence' => $matchConfidence,
                'status' => $existing?->status,
            ], fn ($v) => $v !== null) + ['status' => $existing?->status ?? 'candidate']
        );

        $provider->categories()->syncWithoutDetaching([
            $this->category->id => ['source' => $matchedByNppes ? 'nppes_taxonomy' : 'places_match'],
        ]);
    }

    /**
     * Blended fuzzy-match score (0.00-1.00) between an existing NPPES record and
     * a Places search result: 60% name similarity + 40% normalized-address
     * similarity (spec 2.2: addresses are "Normalized (libpostal or equivalent)
     * before matching" — see App\Support\AddressNormalizer). Records below
     * threshold should route to a manual review queue rather than auto-merge;
     * wire that queue view in your admin UI keyed off providers.match_confidence.
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
