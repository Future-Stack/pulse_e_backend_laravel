<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceDetailsCache extends Model
{
    protected $table = 'place_details_cache';
    protected $primaryKey = 'provider_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'provider_id', 'rating', 'review_count', 'hours_json', 'business_status', 'fetched_at', 'expires_at',
    ];

    protected $casts = [
        'hours_json' => 'array',
        'fetched_at' => 'datetime',
        'expires_at' => 'datetime',
        'rating' => 'decimal:1',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
