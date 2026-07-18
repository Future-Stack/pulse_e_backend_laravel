<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkinScanRecommendation extends Model
{
    protected $fillable = ['skin_scan_id', 'icon_type', 'recommendation_text'];

    public function skinScan(): BelongsTo
    {
        return $this->belongsTo(SkinScan::class);
    }
}
