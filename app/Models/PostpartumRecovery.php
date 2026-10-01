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
        'ai_data',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'screening_due' => 'boolean',
        'physical_recovery_percent' => 'integer',
        'hormonal_balance_percent' => 'integer',
        'sleep_quality_percent' => 'integer',
        'sleep_change_diff' => 'integer',
        'energy_levels_percent' => 'integer',
        'ai_data' => 'array',
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
        if (isset($this->ai_data['postpartum_week'])) {
            return (int) $this->ai_data['postpartum_week'];
        }

        if (!$this->delivery_date) {
            return (int) ($this->current_week ?? 0);
        }

        $delivery = Carbon::parse($this->delivery_date);
        $days = max(0, (int) $delivery->diffInDays(Carbon::now()));

        return (int) floor($days / 7);
    }

    /**
     * Compute days postpartum since delivery.
     */
    public function getDaysPostpartumAttribute(): int
    {
        if (isset($this->ai_data['days_postpartum'])) {
            return (int) $this->ai_data['days_postpartum'];
        }

        if (!$this->delivery_date) {
            return 0;
        }

        return max(0, (int) Carbon::parse($this->delivery_date)->diffInDays(Carbon::now()));
    }
}
