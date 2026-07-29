<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodLog extends Model
{
    protected $fillable = [
        'user_id',
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
            'log_date'   => 'date',
            'clotting'   => 'boolean',
            'cramps'     => 'boolean',
            'headache'   => 'boolean',
            'fatigue'    => 'boolean',
            'pain_level' => 'integer',
        ];
    }

    /**
     * Period Log belongs to a User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Period Log belongs to a Menstrual Cycle.
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class);
    }
}