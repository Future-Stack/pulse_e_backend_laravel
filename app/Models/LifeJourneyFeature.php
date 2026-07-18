<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LifeJourneyFeature extends Model
{
    protected $fillable = ['life_journey_id', 'feature_name'];

    public function lifeJourney(): BelongsTo
    {
        return $this->belongsTo(LifeJourney::class);
    }
}
