<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleSetting extends Model
{
    protected $fillable = [
        'user_id',
        'average_cycle_length',
        'average_period_length',
        'luteal_phase_length',
        'cycle_variance_days',
        'prediction_method',
        'track_bbt',
        'track_opk',
        'track_mucus',
        'notifications_enabled',
    ];

    protected function casts(): array
    {
        return [
            'track_bbt' => 'boolean',
            'track_opk' => 'boolean',
            'track_mucus' => 'boolean',
            'notifications_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
