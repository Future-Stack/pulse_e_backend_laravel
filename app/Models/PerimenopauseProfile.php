<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerimenopauseProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'stage_title',
        'stage_subtitle',
        'is_tracker_active',
        'irregular_cycles_months',
        'fsh_level',
        'fsh_status',
        'avg_hot_flashes_per_day',
        'sleep_disruption_nights_per_week',
        'mood_instability',
    ];

    protected $casts = [
        'is_tracker_active' => 'boolean',
        'irregular_cycles_months' => 'integer',
        'fsh_level' => 'float',
        'avg_hot_flashes_per_day' => 'float',
        'sleep_disruption_nights_per_week' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
