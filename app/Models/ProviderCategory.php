<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderCategory extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'display_name', 'vetting_tier', 'requires_npi', 'active'];

    protected $casts = [
        'requires_npi' => 'boolean',
        'active' => 'boolean',
    ];

    public function marketplaceLifeStages(): BelongsToMany
    {
        return $this->belongsToMany(
            MarketplaceLifeStage::class,
            'marketplace_life_stage_category',
            'category_id',
            'marketplace_life_stage_id'
        )
            ->withPivot('display_priority');
    }

    public function taxonomyCodes(): HasMany
    {
        return $this->hasMany(CategoryTaxonomyCode::class, 'category_id');
    }

    public function placeQueries(): HasMany
    {
        return $this->hasMany(CategoryPlaceQuery::class, 'category_id');
    }

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'provider_category', 'category_id', 'provider_id')
            ->withPivot('source');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /** Mandatory vetting check types this category requires before status can reach 'vetted'. */
    public function requiredCheckTypes(): array
    {
        return match ($this->vetting_tier) {
            'medical' => ['license', 'leie'],
            'licensed_nonmedical' => ['license'],
            'consumer' => ['reputation'],
        };
    }
}
