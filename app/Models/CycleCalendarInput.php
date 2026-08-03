<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleCalendarInput extends Model
{
    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'is_day_n',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_day_n' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}