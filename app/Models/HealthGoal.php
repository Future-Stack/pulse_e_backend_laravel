<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthGoal extends Model
{
    protected $guarded = [];

    public function profiles()
    {
        return $this->belongsToMany(Profile::class, 'health_goal_profile');
    }
}
