<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class OvulationReconciliation extends Model
{
    protected $fillable = [
        'user_id',
        'cycle_id',

        // AI Reconciliation
        'calendar_predicted_day',
        'bbt_confirmed_day',
        'lh_surge_day',
        'mucus_peak_day',

        // Final Decision
        'final_confirmed_day',
        'final_source',

        // Difference
        'offset_days',
        'luteal_phase_length',

        // Discrepancy
        'has_discrepancy',
        'discrepancy_note',

        // Status
        'is_reconciled',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'has_discrepancy' => 'boolean',
            'is_reconciled'   => 'boolean',
            'reconciled_at'   => 'datetime',
        ];
    }

    /**
     * User Relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Menstrual Cycle Relationship
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }

    /**
     * Check if ovulation is confirmed
     */
    public function isConfirmed(): bool
    {
        return ! is_null($this->final_confirmed_day);
    }

    /**
     * Check if prediction has offset
     */
    public function hasOffset(): bool
    {
        return $this->offset_days !== 0;
    }

    /**
     * Check if discrepancy exists
     */
    public function hasDiscrepancy(): bool
    {
        return $this->has_discrepancy;
    }
}