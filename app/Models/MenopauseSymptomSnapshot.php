<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenopauseSymptomSnapshot extends Model
{
    use HasFactory;

    protected $table = 'menopause_symptom_snapshots';

    protected $fillable = [
        'user_id',
        'snapshot_date',
        'period',
        'transition_stage_tracker',
        'vasomotor_tracker',
        'gsm_health',
        'period_selected',
        'tabs',
        'journey_active',
        'message',
        'last_updated_ai',
    ];

    protected $casts = [
        'snapshot_date'            => 'date',
        'transition_stage_tracker' => 'array',
        'vasomotor_tracker'        => 'array',
        'gsm_health'               => 'array',
        'tabs'                     => 'array',
        'journey_active'           => 'boolean',
    ];

    /**
     * Relationship: Menopause Symptom snapshot belongs to a User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
