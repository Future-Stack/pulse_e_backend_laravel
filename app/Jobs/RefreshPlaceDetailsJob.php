<?php

namespace App\Jobs;

use App\Models\PlaceDetailsCache;
use App\Models\Provider;
use App\Services\GooglePlacesClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * places:refresh — rolling 14-day cycle (spec 2.3).
 * Place Details calls to rehydrate place_details_cache for servable providers only
 * (controls API spend). A separate daily purge deletes anything older than 30 days
 * regardless — see PurgeStalePlaceCacheCommand.
 *
 * Dispatch per servable provider, e.g. from a scheduled command:
 *   Provider::whereIn('status', ['active', 'vetted'])
 *     ->whereNotNull('google_place_id')
 *     ->whereDoesntHave('placeDetailsCache', fn ($q) => $q->where('fetched_at', '>', now()->subDays(14)))
 *     ->each(fn ($p) => RefreshPlaceDetailsJob::dispatch($p));
 */
class RefreshPlaceDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly Provider $provider)
    {
    }

    public function handle(GooglePlacesClient $client): void
    {
        if (! in_array($this->provider->status, ['active', 'vetted'], true) || ! $this->provider->google_place_id) {
            return;
        }

        $details = $client->placeDetails($this->provider->google_place_id);

        if (! $details) {
            return;
        }

        PlaceDetailsCache::updateOrCreate(
            ['provider_id' => $this->provider->id],
            [
                'rating' => $details['rating'],
                'review_count' => $details['review_count'],
                'hours_json' => $details['hours_json'],
                'business_status' => $details['business_status'],
                'fetched_at' => now(),
                'expires_at' => now()->addDays(30), // hard ToS backstop regardless of the 14-day refresh cadence
            ]
        );
    }
}
