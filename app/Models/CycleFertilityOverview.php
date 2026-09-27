<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleFertilityOverview extends Model
{
    use HasFactory;

    protected $table = 'cycle_fertility_overviews';

    protected $fillable = [
        'user_id',
        'cycle_id',
        'overview_date',
        'current_cycle_day',
        'current_phase',
        'period_start_date',
        'period_end_date',
        'fertile_window',
        'fertile_window_prediction',
        'hormone_trends',
        'today_insights',
        'ai_insights',
        'bbt_analysis',
        'cycle_history',
    ];

    protected $casts = [
        'overview_date'             => 'date',
        'period_start_date'         => 'date',
        'period_end_date'           => 'date',
        'fertile_window'            => 'array',
        'fertile_window_prediction' => 'array',
        'hormone_trends'            => 'array',
        'today_insights'            => 'array',
        'ai_insights'               => 'array',
        'bbt_analysis'              => 'array',
        'cycle_history'             => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }
}
