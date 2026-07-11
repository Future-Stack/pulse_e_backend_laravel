<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $table = 'profiles';

    protected $fillable = [
        'user_id', 'address', 'profile_img', 'phone',
        'license_number', 'license_expiry', 'insurance_expiry',
        'stripe_account_id', 'stripe_customer_id', 'stripe_onboarding_completed'
    ];


    public function healthGoals()
    {
        return $this->belongsToMany(HealthGoal::class, 'health_goal_profile');
    }

    public function lifeJourneys()
    {
        return $this->belongsToMany(LifeJourney::class, 'life_journey_profile');
    }




}
