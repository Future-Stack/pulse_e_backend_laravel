<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HormoneSnapshot extends Model
{
    protected $fillable = [
        'cycle_id',
        'snapshot_date',
        'estrogen',
        'progesterone',
        'lh',
        'fsh',
        'modeled',
        'source',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'modeled' => 'boolean',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }

    public function isModeled(): bool
    {
        return $this->modeled;
    }

    public function isLabResult(): bool
    {
        return $this->source === 'lab';
    }
}
