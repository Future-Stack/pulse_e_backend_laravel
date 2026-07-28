<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvulationReconciliation extends Model
{
    protected $fillable = [
        'cycle_id',
        'calendar_predicted_day',
        'bbt_confirmed_day',
        'lh_surge_day',
        'mucus_peak_day',
        'final_confirmed_day',
        'final_source',
        'offset_days',
        'luteal_phase_length',
        'is_reconciled',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'is_reconciled' => 'boolean',
            'reconciled_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }

    public function isConfirmed(): bool
    {
        return !is_null($this->final_confirmed_day);
    }

    public function hasOffset(): bool
    {
        return $this->offset_days != 0;
    }
}
