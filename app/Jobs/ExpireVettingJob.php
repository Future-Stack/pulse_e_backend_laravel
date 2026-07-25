<?php

namespace App\Jobs;

use App\Models\Provider;
use App\Models\VettingRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * vetting:expire — daily (spec 2.3).
 * Flips vetting_records past next_due_at to expired; providers whose mandatory
 * checks lapse drop from active to vetted (unservable) until re-verified.
 */
class ExpireVettingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        VettingRecord::expired()->update(['status' => 'expired']);

        Provider::where('status', 'active')
            ->with('categories', 'vettingRecords')
            ->get()
            ->each(function (Provider $provider) {
                if (! $provider->isFullyVetted()) {
                    $provider->update(['status' => 'vetted']); // unservable until re-verified
                }
            });
    }
}
