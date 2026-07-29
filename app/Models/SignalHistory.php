<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignalHistory extends Model
{
    protected $fillable = [
        'cycle_id',
        'log_date',
        'calendar_logged',
        'bbt_logged',
        'opk_logged',
        'mucus_logged',
        'symptoms_logged',
        'signal_strength',
        'status_message',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'calendar_logged' => 'boolean',
            'bbt_logged' => 'boolean',
            'opk_logged' => 'boolean',
            'mucus_logged' => 'boolean',
            'symptoms_logged' => 'boolean',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }

    public function totalSignals(): int
    {
        return collect([
            $this->calendar_logged,
            $this->bbt_logged,
            $this->opk_logged,
            $this->mucus_logged,
            $this->symptoms_logged,
        ])->filter()->count();
    }
}
