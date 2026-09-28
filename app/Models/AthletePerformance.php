<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AthletePerformance extends Model
{
    use HasFactory;

    protected $table = 'athlete_performances';

    protected $fillable = [
        'user_id',
        'performance_date',
        'readiness_score',
        'readiness_level',
        'hrv',
        'recovery',
        'training_load',
        'metrics',
        'fatigue_alerts',
        'cycle_info',
        'phase_cards',
        'next_update',
    ];

    protected $casts = [
        'performance_date' => 'date',
        'hrv'              => 'array',
        'recovery'         => 'array',
        'training_load'    => 'array',
        'metrics'          => 'array',
        'fatigue_alerts'   => 'array',
        'cycle_info'       => 'array',
        'phase_cards'      => 'array',
        'next_update'      => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
