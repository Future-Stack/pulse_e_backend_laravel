<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodLog extends Model
{
    protected $fillable = [
        'cycle_id',
        'log_date',
        'flow',
        'clotting',
        'pain_level',
        'cramps',
        'headache',
        'fatigue',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'clotting' => 'boolean',
            'cramps' => 'boolean',
            'headache' => 'boolean',
            'fatigue' => 'boolean',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }
}
