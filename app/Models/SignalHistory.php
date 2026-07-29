<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignalHistory extends Model
{
    protected $fillable = [
        'user_id',
        'cycle_id',
        'log_date',

        'calendar_logged',
        'bbt_logged',
        'opk_logged',
        'mucus_logged',
        'symptoms_logged',

        'signal_strength',

        'signals',
        'ai_generated',
        'ai_cached',
        'sources',
        'backend_errors',

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

            'signals' => 'array',
            'sources' => 'array',
            'backend_errors' => 'array',

            'ai_generated' => 'boolean',
            'ai_cached' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

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

    public function signalPercentage(): int
    {
        return (int) round(($this->totalSignals() / 5) * 100);
    }

    public function hasCompleteSignals(): bool
    {
        return $this->totalSignals() === 5;
    }
}