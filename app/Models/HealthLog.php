<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthLog extends Model
{
    protected $fillable = [
        'user_id',
        'log_date',
        'mood',
        'energy_level',
        'symptoms',
        'notes',
    ];

    protected $casts = [
        'log_date' => 'date',
        'symptoms' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}