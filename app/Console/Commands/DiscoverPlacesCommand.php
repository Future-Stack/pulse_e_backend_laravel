<?php

namespace App\Console\Commands;

use App\Jobs\DiscoverPlacesJob;
use App\Models\Metro;
use App\Models\ProviderCategory;
use Illuminate\Console\Command;

/**
 * Console command to run Google Places discovery directly from CLI on demand.
 * Usage: php artisan marketplace:discover-places
 */
class DiscoverPlacesCommand extends Command
{
    protected $signature = 'marketplace:discover-places {--metro_id= : Specific metro ID to discover} {--category_id= : Specific category ID to discover} {--queue : Dispatch discovery jobs to the queue worker}';
    protected $description = 'Run Google Places API discovery for active metros and categories to match NPPES records and populate google_place_id';

    public function handle(): int
    {
        $metroId = $this->option('metro_id');
        $categoryId = $this->option('category_id');

        $metros = $metroId ? Metro::where('id', $metroId)->get() : Metro::where('active', true)->get();
        $categories = $categoryId ? ProviderCategory::where('id', $categoryId)->get() : ProviderCategory::where('active', true)->get();

        if ($metros->isEmpty()) {
            $this->error('No active metros found.');
            return self::FAILURE;
        }

        if ($categories->isEmpty()) {
            $this->error('No active categories found.');
            return self::FAILURE;
        }

        $apiKey = config('marketplace.google_places_api_key');
        if (empty($apiKey)) {
            $this->warn('Warning: GOOGLE_PLACES_API_KEY is not set in .env. API calls to Google Places will fail unless a valid key is provided.');
        }

        $initialCount = \App\Models\Provider::count();

        $count = 0;
        $asQueue = (bool) $this->option('queue');

        foreach ($metros as $metro) {
            foreach ($categories as $category) {
                if ($asQueue) {
                    DiscoverPlacesJob::dispatch($metro, $category);
                } else {
                    $this->info("Running Places discovery for metro [{$metro->name}] x category [{$category->display_name}]...");
                    DiscoverPlacesJob::dispatchSync($metro, $category);
                }
                $count++;
            }
        }

        if ($asQueue) {
            $this->info("Dispatched {$count} Google Places discovery jobs to the queue.");
            return self::SUCCESS;
        }

        $newCount = \App\Models\Provider::count();
        $added = $newCount - $initialCount;

        $this->info("Completed Google Places discovery across {$count} metro x category pair(s). Total providers in DB: {$newCount} ({$added} new added).");

        if (empty($apiKey)) {
            $this->warn("Note: To pull live places from Google Places API, add GOOGLE_PLACES_API_KEY=your_key to your .env file.");
            $this->info("For local testing without an API key, run: php artisan marketplace:seed-test-provider");
        }

        return self::SUCCESS;
    }
}
