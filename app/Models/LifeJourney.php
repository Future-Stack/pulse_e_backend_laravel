<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LifeJourney extends Model
{
    protected $guarded = [];

    public function profiles()
    {
        return $this->belongsToMany(Profile::class, 'life_journey_profile');
    }

    public function features()
    {
        return $this->hasMany(LifeJourneyFeature::class);
    }

    public function marketplaceLifeStage(): ?MarketplaceLifeStage
    {
        return MarketplaceLifeStage::find($this->id)
            ?? MarketplaceLifeStage::where('slug', \Illuminate\Support\Str::slug($this->title))->first();
    }

    public function providerCategories()
    {
        return $this->marketplaceLifeStage()?->categories();
    }
}
