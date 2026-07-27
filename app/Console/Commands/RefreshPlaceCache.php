<?php

namespace App\Console\Commands;

use App\Jobs\RefreshPlaceDetailsJob;
use App\Models\Provider;
use Illuminate\Console\Command;

/**
 * places:refresh wrapper — dispatches RefreshPlaceDetailsJob for every servable
 * provider whose cache is missing or older than 14 days (spec 2.3 rolling cycle).
 * Scheduled daily so the 14-day cadence is naturally maintained without a
 * single giant nightly batch.
 */
class RefreshPlaceCache extends Command
{
    protected $signature = 'marketplace:refresh-place-cache';
    protected $description = 'Dispatch RefreshPlaceDetailsJob for providers due for a Places refresh';

    public function handle(): int
    {
        $count = 0;

        Provider::whereIn('status', ['active', 'vetted'])
            ->whereNotNull('google_place_id')
            ->whereDoesntHave('placeDetailsCache', fn ($q) => $q->where('fetched_at', '>', now()->subDays(14)))
            ->chunkById(200, function ($providers) use (&$count) {
                foreach ($providers as $provider) {
                    RefreshPlaceDetailsJob::dispatch($provider);
                    $count++;
                }
            });

        $this->info("Dispatched RefreshPlaceDetailsJob for {$count} provider(s).");

        return self::SUCCESS;
    }
}
