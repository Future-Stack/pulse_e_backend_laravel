<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalitySnapshot extends Model
{
    use HasFactory;

    protected $table = 'vitality_snapshots';

    protected $fillable = [
        'user_id',
        'snapshot_date',
        'vitality_index',
        'vitality_level',
        'personal_best',
        'trend_6_years',
        'dimensions',
        'ai_insights',
        'last_updated_ai',
    ];

    protected $casts = [
        'snapshot_date'  => 'date',
        'vitality_index' => 'float',
        'trend_6_years'  => 'array',
        'dimensions'     => 'array',
        'ai_insights'    => 'array',
    ];

    /**
     * Relationship: Vitality snapshot belongs to a User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
