<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PregnancyTestLog extends Model
{
    protected $fillable = [
        'cycle_id',
        'test_date',
        'result',
        'brand',
        'test_time',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'test_date' => 'date',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }

    public function isPositive(): bool
    {
        return $this->result === 'positive';
    }

    public function isNegative(): bool
    {
        return $this->result === 'negative';
    }
}
