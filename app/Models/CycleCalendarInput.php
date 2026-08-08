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

    protected function endDate(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value) {
                if (!$value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
                    return null;
                }
                return \Carbon\Carbon::parse($value);
            }
        );
    }

    protected function startDate(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value) {
                if (!$value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
                    return null;
                }
                return \Carbon\Carbon::parse($value);
            }
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}