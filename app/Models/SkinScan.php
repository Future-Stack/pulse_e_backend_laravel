<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class SkinScan extends Model
{
    protected $fillable = [
        'user_id', 'image_path', 'overall_score', 
        'hydration_score', 'redness_score', 'texture_score', 
        'glow_index', 'pore_health_score', 'elasticity_score',
        'hydration_status', 'redness_status', 'texture_status', 
        'glow_status', 'pore_health_status', 'elasticity_status',
        'neumera_insight', 'mask_urls',
        'status_label', 'score_change', 'comparison_text',
        'findings', 'correlations', 'ai_insights'
    ];

    protected function casts(): array
    {
        return [
            'mask_urls'    => 'array',
            'findings'     => 'array',
            'correlations' => 'array',
            'ai_insights'  => 'array',
            'score_change' => 'integer',
        ];
    }

    protected function imagePath(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value 
                ? (str_starts_with($value, 'http') ? $value : asset('storage/' . $value)) 
                : null,
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(SkinScanRecommendation::class);
    }
}
