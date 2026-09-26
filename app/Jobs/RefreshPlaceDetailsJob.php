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
 * regardless — see PurgeStalePlaceCache.
 */
class RefreshPlaceDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * [SAFETY FIX FOR GOOGLE BILLING]:
     * Hard-capped at 1 to prevent runaway queue retries against Google APIs.
     * To revert:
     * // (original: no explicit $tries limit)
     */
    public $tries = 1;

    public function __construct(private readonly Provider $provider)
    {
    }

    public function handle(GooglePlacesClient $client): void
    {
        if (! in_array($this->provider->status, ['active', 'vetted', 'candidate'], true) || ! $this->provider->google_place_id) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | [SAFETY FIX FOR GOOGLE BILLING]: 14-day Cache Guard
        |--------------------------------------------------------------------------
        | Avoids querying Google Place Details if refreshed within the last 14 days.
        | If client wants to force-refresh every time (original behavior), comment out:
        */
        $existingCache = PlaceDetailsCache::where('provider_id', $this->provider->id)->first();
        if ($existingCache && $existingCache->fetched_at && $existingCache->fetched_at->gt(now()->subDays(14))) {
            return;
        }
        // [ORIGINAL CODE]: Directly invoked $client->placeDetails(...) without prior cache check.

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

        // Fill in any missing provider fields if available from Place Details
        $updates = [];
        if (empty($this->provider->org_name) && ! empty($this->provider->display_name)) {
            $updates['org_name'] = mb_substr($this->provider->display_name, 0, 160);
        }
        if (empty($this->provider->phone_e164) && ! empty($details['phone_e164'])) {
            $updates['phone_e164'] = mb_substr($details['phone_e164'], 0, 20);
        }
        if (empty($this->provider->website) && ! empty($details['website'])) {
            $updates['website'] = mb_substr($details['website'], 0, 255);
        }
        if (empty($this->provider->addr_line1) && ! empty($details['addr_line1'])) {
            $updates['addr_line1'] = mb_substr($details['addr_line1'], 0, 255);
        }
        if (empty($this->provider->city) && ! empty($details['city'])) {
            $updates['city'] = mb_substr($details['city'], 0, 255);
        }
        if (empty($this->provider->state) && ! empty($details['state'])) {
            $updates['state'] = mb_substr($details['state'], 0, 255);
        }
        if (empty($this->provider->zip) && ! empty($details['zip'])) {
            $updates['zip'] = mb_substr($details['zip'], 0, 255);
        }

        if (! empty($updates)) {
            $this->provider->update($updates);
        }
    }
}
