<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleConsent extends Model
{
    protected $fillable = [
        'user_id',
        'consent_version',
        'has_consented',
        'consented_at',
        'ip_address',
        'device',
    ];

    protected function casts(): array
    {
        return [
            'has_consented' => 'boolean',
            'consented_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasConsent(): bool
    {
        return $this->has_consented;
    }
}
