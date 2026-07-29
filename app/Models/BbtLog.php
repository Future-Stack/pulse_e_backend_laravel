<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BbtLog extends Model
{
    protected $fillable = [
        'cycle_id',
        'user_id',
        'log_date',
        'temperature',
        'unit',
        'logged_at',
        'illness',
        'poor_sleep',
        'alcohol',
        'late_wakeup',
        'travel',
        'is_excluded',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'logged_at' => 'datetime:H:i',
            'illness' => 'boolean',
            'poor_sleep' => 'boolean',
            'alcohol' => 'boolean',
            'late_wakeup' => 'boolean',
            'travel' => 'boolean',
            'is_excluded' => 'boolean',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }

    public function isValid(): bool
    {
        return !$this->is_excluded;
    }
}
