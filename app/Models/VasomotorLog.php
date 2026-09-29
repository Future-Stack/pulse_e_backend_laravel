<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VasomotorLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'log_date',
        'day_of_week',
        'mild_count',
        'moderate_count',
        'intense_count',
        'total_episodes',
        'avg_intensity',
        'peak_time',
    ];

    protected $casts = [
        'log_date' => 'date',
        'mild_count' => 'integer',
        'moderate_count' => 'integer',
        'intense_count' => 'integer',
        'total_episodes' => 'integer',
        'avg_intensity' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
