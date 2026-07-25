<?php

namespace App\Console\Commands;

use App\Models\PlaceDetailsCache;
use Illuminate\Console\Command;

/**
 * Daily purge — hard Google ToS backstop (spec 2.2 / 2.6).
 * Deletes any place_details_cache row older than 30 days regardless of the
 * 14-day refresh cadence.
 */
class PurgeStalePlaceCache extends Command
{
    protected $signature = 'marketplace:purge-place-cache';
    protected $description = 'Delete place_details_cache rows past the 30-day Google ToS TTL';

    public function handle(): int
    {
        $deleted = PlaceDetailsCache::where('expires_at', '<', now())->delete();

        $this->info("Purged {$deleted} stale place_details_cache rows.");

        return self::SUCCESS;
    }
}
