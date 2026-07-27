<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlateEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'occurred_at', 'metro_id', 'category_id', 'marketplace_life_stage_id',
        'provider_id', 'slot_position', 'sponsored', 'event_type', 'session_token',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'sponsored' => 'boolean',
    ];

    // Guard: only these keys are ever accepted from the client. Anything else is rejected
    // upstream in StoreSlateEventsRequest — this is the deny-by-default guard against
    // health data leaking into analytics.
    public const ALLOWED_EVENT_TYPES = ['impression', 'tap', 'call', 'directions', 'website', 'share'];

    public function metro(): BelongsTo
    {
        return $this->belongsTo(Metro::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProviderCategory::class, 'category_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function marketplaceLifeStage(): BelongsTo
    {
        return $this->belongsTo(MarketplaceLifeStage::class, 'marketplace_life_stage_id');
    }
}
