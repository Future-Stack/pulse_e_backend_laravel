<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewAwarenessSnapshot extends Model
{
    protected $table = 'new_awareness_snapshots';

    protected $fillable = [
        'user_id',
        'cycle_id',

        'title',
        'cycle_context',
        'current_phase',
        'luteal_phase',
        'hormone_levels',
        'what_to_know',
        'four_phase_cycle',

        'ai_response',

        'ai_generated',
        'ai_cached',
    ];

    protected function casts(): array
    {
        return [
            'cycle_context' => 'array',
            'current_phase' => 'array',
            'luteal_phase' => 'array',
            'hormone_levels' => 'array',
            'what_to_know' => 'array',
            'four_phase_cycle' => 'array',
            'ai_response' => 'array',

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
        return $this->belongsTo(
            MenstrualCycle::class,
            'cycle_id'
        );
    }
}