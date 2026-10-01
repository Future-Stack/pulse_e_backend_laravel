<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenopauseInsightSnapshot extends Model
{
    use HasFactory;

    protected $table = 'menopause_insight_snapshots';

    protected $fillable = [
        'user_id',
        'snapshot_date',
        'period',
        'symptom_matrix',
        'period_selected',
        'tabs',
        'journey_active',
        'message',
        'last_updated_ai',
    ];

    protected $casts = [
        'snapshot_date'  => 'date',
        'symptom_matrix' => 'array',
        'tabs'           => 'array',
        'journey_active' => 'boolean',
    ];

    /**
     * Relationship: Menopause Insight snapshot belongs to a User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
