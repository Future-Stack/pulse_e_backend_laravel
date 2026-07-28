<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpkLog extends Model
{
    protected $fillable = [
        'cycle_id',
        'log_date',
        'result',
        'lh_value',
        'outside_window',
        'affects_prediction',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'outside_window' => 'boolean',
            'affects_prediction' => 'boolean',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }

    public function isPositive(): bool
    {
        return in_array($this->result, ['positive', 'peak']);
    }

    public function isPeak(): bool
    {
        return $this->result === 'peak';
    }
}
