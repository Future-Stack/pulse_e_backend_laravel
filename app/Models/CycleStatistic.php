<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleStatistic extends Model
{
    protected $fillable = [
        'user_id',
        'completed_cycles',
        'average_cycle_length',
        'shortest_cycle',
        'longest_cycle',
        'average_period_length',
        'cycle_variance_days',
        'last_period_date',
        'predicted_next_period',
        'reliability_level',
    ];

    protected function casts(): array
    {
        return [
            'last_period_date' => 'date',
            'predicted_next_period' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isReliable(): bool
    {
        return $this->completed_cycles >= 3;
    }
}
