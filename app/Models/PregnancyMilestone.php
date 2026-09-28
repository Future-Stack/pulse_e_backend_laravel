<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PregnancyMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'pregnancy_id',
        'user_id',
        'title',
        'target_week',
        'date_label',
        'scheduled_date',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'scheduled_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function pregnancy(): BelongsTo
    {
        return $this->belongsTo(UserPregnancy::class, 'pregnancy_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
