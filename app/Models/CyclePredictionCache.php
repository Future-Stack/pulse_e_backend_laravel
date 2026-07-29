<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CyclePredictionCache extends Model
{
    protected $fillable = [
        'cycle_id',
        'cache_key',
        'endpoint',
        'request_payload',
        'prediction',
        'prediction_version',
        'ai_generated',
        'ai_cached',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'prediction' => 'array',
            'ai_generated' => 'boolean',
            'ai_cached' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at &&
            now()->greaterThan($this->expires_at);
    }
}
