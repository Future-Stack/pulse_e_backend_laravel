<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SymptomLog extends Model
{
    protected $fillable = [
        'cycle_id',
        'log_date',
        'pain_level',
        'mood',
        'energy',
        'cramps',
        'bloating',
        'headache',
        'fatigue',
        'acne',
        'breast_tenderness',
        'nausea',
        'insomnia',
        'libido',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'cramps' => 'boolean',
            'bloating' => 'boolean',
            'headache' => 'boolean',
            'fatigue' => 'boolean',
            'acne' => 'boolean',
            'breast_tenderness' => 'boolean',
            'nausea' => 'boolean',
            'insomnia' => 'boolean',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }

    public function hasSeverePain(): bool
    {
        return $this->pain_level >= 8;
    }
}
