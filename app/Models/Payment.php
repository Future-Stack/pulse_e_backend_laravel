<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = [];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function topupProduct()
    {
        return $this->belongsTo(TopupProduct::class);
    }

    public function userLimit()
    {
        return $this->hasOne(UserLimit::class);
    }

    //Limit Track
    public function subscriptionLimit()
    {
        return $this->hasOne(UserLimit::class)
            ->whereHas('payment', function ($q) {
                $q->where('type', 'subscription');
            });
    }

    public function topupLimit()
    {
        return $this->hasOne(UserLimit::class)
            ->whereHas('payment', function ($q) {
                $q->where('type', 'topup');
            });
    }
}
