<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpkData extends Model
{
    protected $fillable = ['user_id', 'response_data'];

    protected $casts = [
        'response_data' => 'array',
    ];
}
