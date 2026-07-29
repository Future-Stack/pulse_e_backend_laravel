<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleMode extends Model
{
    protected $fillable = [
        'user_id',
        'mode',
        'is_active',
        'activated_at',
        'has_consented',
        'consent_version',
        'consented_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'has_consented' => 'boolean',
            'activated_at' => 'datetime',
            'consented_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}