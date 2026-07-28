<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MenstrualCycle extends Model
{
    protected $fillable = [
        'user_id',
        'period_start_date',
        'period_end_date',
        'cycle_length',
        'period_length',
        'predicted_ovulation_day',
        'confirmed_ovulation_day',
        'fertile_start_day',
        'fertile_end_day',
        'current_phase',
        'prediction_source',
        'is_confirmed',
        'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'period_start_date' => 'date',
            'period_end_date' => 'date',
            'is_confirmed' => 'boolean',
            'is_completed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function periodLogs(): HasMany
    {
        return $this->hasMany(PeriodLog::class, 'cycle_id');
    }

    public function dailyLogs(): HasMany
    {
        return $this->hasMany(CycleDailyLog::class, 'cycle_id');
    }

    public function bbtLogs(): HasMany
    {
        return $this->hasMany(BbtLog::class, 'cycle_id');
    }

    public function opkLogs(): HasMany
    {
        return $this->hasMany(OpkLog::class, 'cycle_id');
    }

    public function cervicalMucusLogs(): HasMany
    {
        return $this->hasMany(CervicalMucusLog::class, 'cycle_id');
    }

    public function symptomLogs(): HasMany
    {
        return $this->hasMany(SymptomLog::class, 'cycle_id');
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(OvulationReconciliation::class, 'cycle_id');
    }

    public function predictionCaches(): HasMany
    {
        return $this->hasMany(CyclePredictionCache::class, 'cycle_id');
    }

    public function signalHistories(): HasMany
    {
        return $this->hasMany(SignalHistory::class, 'cycle_id');
    }

    public function intercourseLogs(): HasMany
    {
        return $this->hasMany(IntercourseLog::class, 'cycle_id');
    }

    public function pregnancyTestLogs(): HasMany
    {
        return $this->hasMany(PregnancyTestLog::class, 'cycle_id');
    }

    public function fertilityEvents(): HasMany
    {
        return $this->hasMany(FertilityEvent::class, 'cycle_id');
    }

    public function hormoneSnapshots(): HasMany
    {
        return $this->hasMany(HormoneSnapshot::class, 'cycle_id');
    }

    public function phaseInsights(): HasMany
    {
        return $this->hasMany(PhaseInsight::class, 'cycle_id');
    }

    public function isActive(): bool
    {
        return !$this->is_completed;
    }








}
