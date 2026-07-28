<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CervicalMucusLog extends Model
{
    protected $fillable = [
        'cycle_id',
        'log_date',
        'consistency',
        'amount',
        'color',
        'stretch_cm',
        'fertility_score',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'stretch_cm' => 'decimal:1',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }

    public function isPeakFertility(): bool
    {
        return $this->consistency === 'egg_white';
    }
}
