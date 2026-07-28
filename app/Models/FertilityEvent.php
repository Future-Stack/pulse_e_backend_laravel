<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FertilityEvent extends Model
{
    protected $fillable = [
        'cycle_id',
        'event_date',
        'event_type',
        'cycle_day',
        'source',
        'priority_level',
        'message',
        'is_confirmed',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'is_confirmed' => 'boolean',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }

    public function isHighPriority(): bool
    {
        return $this->priority_level >= 4;
    }

    public function isConfirmed(): bool
    {
        return $this->is_confirmed;
    }
}
