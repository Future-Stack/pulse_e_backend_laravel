<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AwarenessSnapshot extends Model
{
    protected $fillable = [
        'user_id',
        'cycle_id',

        'phase',
        'day_range',
        'current_cycle_day',
        'dominant_hormone_note',
        'energy',
        'skin',
        'mood',

        'estrogen',
        'progesterone',
        'lh',
        'modeled',
        'source',
        'note',

        'bbt_note',
        'energy_note',
        'hormone_note',
        'focus_note',

        'current_phase',
        'phases',

        'ai_generated',
        'ai_cached',
    ];

    protected function casts(): array
    {
        return [
            'phases' => 'array',
            'modeled' => 'boolean',
            'ai_generated' => 'boolean',
            'ai_cached' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }
}