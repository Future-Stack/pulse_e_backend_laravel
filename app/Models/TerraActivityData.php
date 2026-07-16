<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TerraActivityData extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'terra_user_id',
        'type',
        'payload',
        'data_generated_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'data_generated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}