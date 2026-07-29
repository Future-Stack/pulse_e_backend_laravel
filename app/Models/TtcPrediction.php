<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TtcPrediction extends Model
{
    protected $fillable = [
        'user_id',
        'cycle_id',

        'surge_active',
        'surge_message',
        'hours_remaining_estimate',

        'cycle_day',
        'lh_surge_day',

        'priority',
        'label',
        'priority_message',

        'priority_ranges',

        'ai_generated',
        'ai_fallback',
    ];

    protected function casts(): array
    {
        return [
            'surge_active' => 'boolean',
            'priority_ranges' => 'array',
            'ai_generated' => 'boolean',
            'ai_fallback' => 'boolean',
        ];
    }

    /**
     * User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Menstrual Cycle
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }
}