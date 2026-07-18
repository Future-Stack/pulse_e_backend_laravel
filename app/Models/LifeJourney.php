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
    
}
