<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Named MarketplaceLifeStage (not LifeStage) to avoid colliding with this
 * app's existing App\Models\LifeStage / life_stages table, which belongs to
 * an unrelated health-domain onboarding feature. This model backs the six
 * spec Part 1 life stages (beauty-radiance, cycle-fertility, etc.) via the
 * marketplace_life_stages table.
 */
class MarketplaceLifeStage extends Model
{
    protected $table = 'marketplace_life_stages';

    protected $fillable = ['slug', 'name'];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            ProviderCategory::class,
            'marketplace_life_stage_category',
            'marketplace_life_stage_id',
            'category_id'
        )
            ->withPivot('display_priority')
            ->orderBy('marketplace_life_stage_category.display_priority');
    }
}
