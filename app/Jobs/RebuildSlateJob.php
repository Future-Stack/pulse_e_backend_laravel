<?php

namespace App\Jobs;

use App\Models\Metro;
use App\Models\ProviderCategory;
use App\Services\MarketplaceSlateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * slate:rebuild — nightly (spec 2.3).
 * Precomputes ranked top-N organic providers per (metro, category) into a
 * Redis/ElastiCache key so serve-time reads are O(1). Sponsored slots are
 * resolved at read time in MarketplaceSlateService so contract changes
 * apply immediately without waiting on this job.
 */
class RebuildSlateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MarketplaceSlateService $slateService): void
    {
        Metro::where('active', true)->chunk(50, function ($metros) use ($slateService) {
            foreach ($metros as $metro) {
                foreach (ProviderCategory::where('active', true)->get() as $category) {
                    $ranked = $slateService->computeOrganicRanking($category, $metro);

                    Cache::put(
                        "slate:{$metro->id}:{$category->id}",
                        $ranked->pluck('id')->all(),
                        now()->addHours(26) // outlives the nightly cadence with margin
                    );
                }
            }
        });
    }
}
