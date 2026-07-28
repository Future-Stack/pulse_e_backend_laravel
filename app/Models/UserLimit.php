<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLimit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'subscription_expires_at' => 'datetime',
    ];
}
