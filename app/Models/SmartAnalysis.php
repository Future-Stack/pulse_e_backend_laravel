<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmartAnalysis extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'alerts',
        'status',
    ];

    protected $casts = [
        'alerts' => 'array',
    ];

    /**
     * Smart Analysis belongs to a User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}