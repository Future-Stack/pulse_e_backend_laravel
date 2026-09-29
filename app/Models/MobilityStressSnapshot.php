<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobilityStressSnapshot extends Model
{
    use HasFactory;

    protected $table = 'mobility_stress_snapshots';

    protected $fillable = [
        'user_id',
        'snapshot_date',
        'indicators',
        'overall_mobility_score',
        'overall_stress_status',
        'last_updated_ai',
    ];

    protected $casts = [
        'snapshot_date'          => 'date',
        'indicators'             => 'array',
        'overall_mobility_score' => 'float',
    ];

    /**
     * Relationship: Mobility Stress snapshot belongs to a User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
