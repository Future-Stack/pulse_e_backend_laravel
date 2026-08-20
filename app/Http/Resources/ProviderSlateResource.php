<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a single provider for the GET /v1/marketplace/slate response.
 * $this->resource is expected to be a stdClass/array built by MarketplaceSlateService
 * carrying: provider, distance_km, position, sponsored.
 */
class ProviderSlateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $provider = $this->resource['provider'];
        $cache = $provider->placeDetailsCache;

        return [
            'id' => $provider->id,
            'display_name' => $provider->display_name,
            'org_name' => $provider->org_name,
            'categories' => $provider->categories->pluck('slug'),
            'address' => [
                'line1' => $provider->addr_line1,
                'line2' => $provider->addr_line2,
                'city' => $provider->city,
                'state' => $provider->state,
                'zip' => $provider->zip,
            ],
            'phone' => $provider->phone_e164,
            'website' => $provider->website,
            'distance_miles' => round($this->resource['distance_km'] * 0.621371, 1),
            'rating' => $cache?->rating,
            'review_count' => $cache?->review_count,
            'rating_attribution' => $cache?->rating !== null ? 'Ratings provided by Google' : null,
            'hours' => $cache?->hours_json,
            'business_status' => $cache?->business_status,
            'sponsored' => (bool) $this->resource['sponsored'],
            'position' => $this->resource['position'],
        ];
    }
}
