<?php

namespace App\Services;

use App\Models\Metro;
use App\Models\Provider;
use App\Models\ProviderCategory;
use App\Models\SponsoredSlot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MarketplaceSlateService
{
    private const BAYESIAN_PRIOR_WEIGHT = 20; // ~20-review weight

    public function buildSlate(ProviderCategory $category, Metro $metro, int $limit = 3): Collection
    {
        $cacheKey = "slate:{$metro->id}:{$category->id}";

        $organicIds = Cache::get($cacheKey);

        if ($organicIds === null) {
            $organicIds = $this->computeOrganicRanking($category, $metro)->pluck('id')->all();
        }

        $sponsored = SponsoredSlot::query()
            ->with('provider.categories', 'provider.placeDetailsCache')
            ->where('metro_id', $metro->id)
            ->where('category_id', $category->id)
            ->currentlyActive()
            ->whereHas('provider', fn ($q) => $q->whereIn('status', ['active', 'candidate']))
            ->orderBy('slot_number')
            ->get();

        $sponsoredProviderIds = $sponsored->pluck('provider_id')->all();

        $remainingIds = array_values(array_diff($organicIds, $sponsoredProviderIds));

        $organicProviders = Provider::query()
            ->with('categories', 'placeDetailsCache')
            ->whereIn('id', $remainingIds)
            ->whereIn('status', ['active', 'candidate'])
            ->get()
            ->sortBy(fn ($provider) => array_search($provider->id, $remainingIds))
            ->values();

        $slate = collect();
        $position = 1;

        foreach ($sponsored as $slot) {
            $slate->push([
                'provider' => $slot->provider,
                'distance_km' => $this->distanceKm($metro, $slot->provider),
                'sponsored' => true,
                'position' => $position++,
            ]);
        }

        foreach ($organicProviders as $provider) {
            if ($slate->count() >= $limit) {
                break;
            }
            $slate->push([
                'provider' => $provider,
                'distance_km' => $this->distanceKm($metro, $provider),
                'sponsored' => false,
                'position' => $position++,
            ]);
        }

        return $slate->take($limit);
    }

    public function computeOrganicRanking(ProviderCategory $category, Metro $metro): Collection
    {
        $globalMean = DB::table('place_details_cache')
            ->join('provider_category', 'place_details_cache.provider_id', '=', 'provider_category.provider_id')
            ->where('provider_category.category_id', $category->id)
            ->avg('rating') ?? 4.0;

        $providers = Provider::query()
            ->join('provider_category', 'providers.id', '=', 'provider_category.provider_id')
            ->leftJoin('place_details_cache', 'providers.id', '=', 'place_details_cache.provider_id')
            ->where('provider_category.category_id', $category->id)
            ->where('providers.metro_id', $metro->id)
            ->whereIn('providers.status', ['active', 'candidate'])
            ->select(
                'providers.*',
                'place_details_cache.rating as pd_rating',
                'place_details_cache.review_count as pd_review_count'
            )
            ->get();

        $metroRadiusKm = max((float) $metro->radius_km, 1.0);

        return $providers
            ->map(function ($provider) use ($metro, $globalMean, $metroRadiusKm) {
                $distanceKm = $this->distanceKm($metro, $provider);
                $proximityNorm = 1 - min($distanceKm / $metroRadiusKm, 1.0);

                $reviewCount = (int) ($provider->pd_review_count ?? 0);
                $rating = (float) ($provider->pd_rating ?? 0);

                // Bayesian average
                $bayesianRating = (
                    self::BAYESIAN_PRIOR_WEIGHT * $globalMean + $reviewCount * $rating
                ) / (self::BAYESIAN_PRIOR_WEIGHT + $reviewCount);

                $reviewCountNorm = log($reviewCount + 1) / log(1000);

                $score = 0.5 * $proximityNorm
                    + 0.3 * ($bayesianRating / 5)
                    + 0.2 * min($reviewCountNorm, 1);

                $provider->setAttribute('_score', $score);

                return $provider;
            })
            ->sortByDesc('_score')
            ->values();
    }

    private function distanceKm(Metro $metro, Provider $provider): float
    {
        if (! $provider->location || ! $metro->centroid) {
            return 0.0;
        }

        // Haversine distance
        $lat1 = deg2rad($metro->centroid->latitude);
        $lon1 = deg2rad($metro->centroid->longitude);
        $lat2 = deg2rad($provider->location->latitude);
        $lon2 = deg2rad($provider->location->longitude);

        $dLat = $lat2 - $lat1;
        $dLon = $lon2 - $lon1;

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return 6371 * $c; // Earth radius in km
    }
}