<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LifeArcSnapshot extends Model
{
    use HasFactory;

    protected $table = 'life_arc_snapshots';

    protected $fillable = [
        'user_id',
        'snapshot_date',
        'milestones',
        'timeline_summary',
        'last_updated_ai',
    ];

    protected $casts = [
        'snapshot_date'    => 'date',
        'milestones'       => 'array',
        'timeline_summary' => 'array',
    ];

    /**
     * Relationship: Life Arc snapshot belongs to a User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
