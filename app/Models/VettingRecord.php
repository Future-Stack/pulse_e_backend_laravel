<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VettingRecord extends Model
{
    protected $fillable = [
        'provider_id', 'check_type', 'status', 'evidence_url', 'notes',
        'checked_at', 'next_due_at', 'checked_by',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
        'next_due_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', '!=', 'expired')
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<', now());
    }
}
