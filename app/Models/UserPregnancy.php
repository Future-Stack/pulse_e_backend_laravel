<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserPregnancy extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'due_date',
        'last_menstrual_period_date',
        'conception_date',
        'status',
        'ended_at',
        'delivery_date',
        'delivery_type',
        'notes',
        'ai_data',
    ];

    protected $casts = [
        'due_date' => 'date',
        'last_menstrual_period_date' => 'date',
        'conception_date' => 'date',
        'ended_at' => 'date',
        'delivery_date' => 'date',
        'ai_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(PregnancyMilestone::class, 'pregnancy_id');
    }

    public function postpartumRecovery(): HasOne
    {
        return $this->hasOne(PostpartumRecovery::class, 'pregnancy_id');
    }

    /**
     * Calculate current gestational week (1 - 40).
     */
    public function getCurrentWeekAttribute(): int
    {
        if (!empty($this->ai_data['current_week']) && (!empty($this->ai_data['due_date']) && $this->due_date && $this->ai_data['due_date'] === $this->due_date->toDateString())) {
            return (int) $this->ai_data['current_week'];
        }

        if ($this->due_date) {
            $now = Carbon::now();
            $due = Carbon::parse($this->due_date);
            
            // Full term = 40 weeks = 280 days
            $daysRemaining = $now->diffInDays($due, false);
            $daysPassed = 280 - $daysRemaining;
            $week = (int) floor($daysPassed / 7);

            return max(1, min(42, $week));
        }

        if ($this->last_menstrual_period_date) {
            $now = Carbon::now();
            $lmp = Carbon::parse($this->last_menstrual_period_date);
            return max(1, min(42, (int) $lmp->diffInWeeks($now)));
        }

        return 0;
    }

    /**
     * Calculate remaining days to due date.
     */
    public function getDaysToDueDateAttribute(): int
    {
        if (!empty($this->ai_data['days_until_due']) && (!empty($this->ai_data['due_date']) && $this->due_date && $this->ai_data['due_date'] === $this->due_date->toDateString())) {
            return (int) $this->ai_data['days_until_due'];
        }

        if (!$this->due_date) {
            return 0;
        }

        $now = Carbon::now();
        $due = Carbon::parse($this->due_date);

        return max(0, (int) $now->diffInDays($due, false));
    }

    /**
     * Determine current trimester.
     */
    public function getTrimesterAttribute(): string
    {
        $week = $this->current_week;
        if ($week <= 13) {
            return 'First Trimester';
        } elseif ($week <= 27) {
            return 'Second Trimester';
        }
        return 'Third Trimester';
    }
}
