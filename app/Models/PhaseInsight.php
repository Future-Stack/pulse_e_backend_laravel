<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhaseInsight extends Model
{
    protected $fillable = [
        'cycle_id',
        'insight_date',
        'phase',
        'education',
        'energy_note',
        'hormone_note',
        'focus_note',
        'skin_note',
        'nutrition_note',
        'exercise_note',
        'ai_generated',
    ];

    protected function casts(): array
    {
        return [
            'insight_date' => 'date',
            'ai_generated' => 'boolean',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }

    public function isAiGenerated(): bool
    {
        return $this->ai_generated;
    }
}
