<?php

namespace App\Console\Commands;

use App\Models\SponsoredSlot;
use Illuminate\Console\Command;

/**
 * Nightly integrity check (spec 2.2 sponsored_slots / 2.6 sponsored integrity):
 * a slot must reference an 'active' provider and never exceed 3 slots per
 * metro+category. Auto-cancels any slot that has drifted out of compliance
 * (e.g. its provider was suspended or excluded after the slot was reserved).
 */
class EnforceSlotIntegrity extends Command
{
    protected $signature = 'marketplace:enforce-slot-integrity';
    protected $description = 'Cancel sponsored slots whose provider is no longer active, and flag metro+category slot overflows';

    public function handle(): int
    {
        $cancelled = SponsoredSlot::query()
            ->whereIn('status', ['reserved', 'active'])
            ->whereHas('provider', fn ($q) => $q->where('status', '!=', 'active'))
            ->get();

        foreach ($cancelled as $slot) {
            $slot->update(['status' => 'cancelled']);
            $this->warn("Cancelled slot #{$slot->id}: provider #{$slot->provider_id} is no longer active.");
        }

        $overflows = SponsoredSlot::query()
            ->select('metro_id', 'category_id')
            ->whereIn('status', ['reserved', 'active'])
            ->groupBy('metro_id', 'category_id')
            ->havingRaw('COUNT(*) > 3')
            ->get();

        foreach ($overflows as $overflow) {
            $this->error("Slot overflow for metro {$overflow->metro_id} / category {$overflow->category_id}: more than 3 active/reserved slots.");
        }

        $this->info("Integrity check complete. {$cancelled->count()} slot(s) cancelled, {$overflows->count()} overflow(s) flagged.");

        return self::SUCCESS;
    }
}
