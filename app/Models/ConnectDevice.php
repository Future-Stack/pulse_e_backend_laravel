<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConnectDevice extends Model
{
    public function profiles()
    {
        return $this->belongsToMany(Profile::class, 'life_journey_profile');
    }
}
