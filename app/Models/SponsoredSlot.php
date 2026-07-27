<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsoredSlot extends Model
{
    protected $fillable = [
        'metro_id', 'category_id', 'slot_number', 'provider_id',
        'starts_at', 'ends_at', 'status', 'monthly_rate_cents',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

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

    public function scopeCurrentlyActive($query)
    {
        return $query->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }
}
