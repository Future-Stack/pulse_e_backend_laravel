<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenopauseExportSnapshot extends Model
{
    use HasFactory;

    protected $table = 'menopause_export_snapshots';

    protected $fillable = [
        'user_id',
        'snapshot_date',
        'period',
        'clinical_export',
        'tabs',
        'journey_active',
        'message',
        'last_updated_ai',
    ];

    protected $casts = [
        'snapshot_date'   => 'date',
        'clinical_export' => 'array',
        'tabs'            => 'array',
        'journey_active'  => 'boolean',
    ];

    /**
     * Relationship: Menopause Export snapshot belongs to a User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
