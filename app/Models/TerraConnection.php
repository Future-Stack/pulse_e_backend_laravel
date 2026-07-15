<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TerraConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'terra_user_id',
        'reference_id',
        'provider',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}