<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Profile extends Model
{
    protected $fillable = [
        'user_id',
        'life_stage_id',
        'activity_id',
        'bio',
        'profile_img',
        'age',
        'height',
        'weight',
        'stripe_account_id',
    ];

    protected $casts = [
        'height' => 'float',
        'weight' => 'float',
        'age' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lifeStage(): BelongsTo
    {
        return $this->belongsTo(LifeStage::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function healthGoals(): BelongsToMany
    {
        return $this->belongsToMany(
            HealthGoal::class,
            'health_goal_profile'
        );
    }

    public function lifeJourneys(): BelongsToMany
    {
        return $this->belongsToMany(
            LifeJourney::class,
            'life_journey_profile'
        );
    }
}