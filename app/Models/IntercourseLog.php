<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntercourseLog extends Model
{
    protected $fillable = [
        'cycle_id',
        'log_date',
        'protected',
        'ejaculation',
        'inside_fertile_window',
        'trying_to_conceive',
        'cycle_day',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'protected' => 'boolean',
            'ejaculation' => 'boolean',
            'inside_fertile_window' => 'boolean',
            'trying_to_conceive' => 'boolean',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }

    public function isHighChanceDay(): bool
    {
        return $this->inside_fertile_window;
    }
}
