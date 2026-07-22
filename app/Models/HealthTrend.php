<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthTrend extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'period',
        'range_options',
        'sleep_energy_correlation_chart',
        'sleep_energy_correlation_diagram',
        'hormone_mood',
        'status',
    ];


    protected $casts = [
        'range_options' => 'array',
        'sleep_energy_correlation_chart' => 'array',
        'sleep_energy_correlation_diagram' => 'array',
        'hormone_mood' => 'array',
    ];


    /**
     * Health Trend belongs to a User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}