<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistEntry extends Model
{
    protected $guarded = [];

    public function lifeJourney() :BelongsTo
    {
        return $this->belongsTo(LifeJourney::class);
    }
}
