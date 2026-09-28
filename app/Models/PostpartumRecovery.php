<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostpartumRecovery extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pregnancy_id',
        'delivery_date',
        'current_week',
        'physical_recovery_percent',
        'hormonal_balance_percent',
        'sleep_quality_percent',
        'sleep_change_diff',
        'energy_levels_percent',
        'screening_name',
        'screening_due',
        'screening_due_text',
        'mood_stability',
        'anxiety_level',
        'notes',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'screening_due' => 'boolean',
        'physical_recovery_percent' => 'integer',
        'hormonal_balance_percent' => 'integer',
        'sleep_quality_percent' => 'integer',
        'sleep_change_diff' => 'integer',
        'energy_levels_percent' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pregnancy(): BelongsTo
    {
        return $this->belongsTo(UserPregnancy::class, 'pregnancy_id');
    }

    /**
     * Compute postpartum weeks since delivery.
     */
    public function getWeeksSinceDeliveryAttribute(): int
    {
        if (!$this->delivery_date) {
            return $this->current_week ?: 6;
        }

        $delivery = Carbon::parse($this->delivery_date);
        $weeks = (int) ceil(Carbon::now()->diffInDays($delivery) / 7);

        return max(1, $weeks);
    }
}
