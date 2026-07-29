<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleDailyLog extends Model
{
    protected $fillable = [
        'cycle_id',
        'log_date',
        'cycle_day',
        'phase',
        'tag',
        'is_prediction',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'is_prediction' => 'boolean',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }

    public function isFertile(): bool
    {
        return $this->tag === 'fertile';
    }

    public function isOvulation(): bool
    {
        return $this->tag === 'ovulation';
    }

    public function isPeriod(): bool
    {
        return $this->tag === 'period';
    }
}
