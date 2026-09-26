<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CervicalMucusLog extends Model
{
    protected $fillable = [
        'user_id',
        'cycle_id',
        'log_date',
        'consistency',
        'amount',
        'color',
        'stretch_cm',
        'fertility_score',
        'notes',
    ];

    protected $appends = [
        'consistency_label',
        'fertility_level',
        'description',
        'is_peak_fertility',
    ];

    protected function casts(): array
    {
        return [
            'log_date'        => 'date',
            'stretch_cm'      => 'decimal:1',
            'fertility_score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenstrualCycle::class, 'cycle_id');
    }

    public function getConsistencyLabelAttribute(): string
    {
        return match ($this->consistency) {
            'dry'       => 'Dry',
            'sticky'    => 'Sticky',
            'creamy'    => 'Creamy',
            'watery'    => 'Watery',
            'egg_white' => 'Egg white',
            default     => ucfirst(str_replace('_', ' ', (string) $this->consistency)),
        };
    }

    public function getFertilityLevelAttribute(): string
    {
        return match ($this->consistency) {
            'dry', 'sticky' => 'Low fertility',
            'creamy'        => 'Moderate',
            'watery'        => 'High fertility',
            'egg_white'     => 'Peak fertility',
            default         => 'Unknown',
        };
    }

    public function getDescriptionAttribute(): string
    {
        return match ($this->consistency) {
            'dry'       => 'No moisture',
            'sticky'    => 'Thick, crumbly',
            'creamy'    => 'Lotion-like',
            'watery'    => 'Clear, thin',
            'egg_white' => 'Clear, stretchy',
            default     => '',
        };
    }

    public function getIsPeakFertilityAttribute(): bool
    {
        return $this->consistency === 'egg_white';
    }

    public function isPeakFertility(): bool
    {
        return $this->consistency === 'egg_white';
    }

    /**
     * UI Option definitions matching app design
     */
    public static function getOptions(): array
    {
        return [
            [
                'key'             => 'dry',
                'label'           => 'Dry',
                'description'     => 'No moisture',
                'fertility_level' => 'Low fertility',
                'fertility_score' => 10,
                'color_theme'     => 'danger',
            ],
            [
                'key'             => 'sticky',
                'label'           => 'Sticky',
                'description'     => 'Thick, crumbly',
                'fertility_level' => 'Low fertility',
                'fertility_score' => 25,
                'color_theme'     => 'danger',
            ],
            [
                'key'             => 'creamy',
                'label'           => 'Creamy',
                'description'     => 'Lotion-like',
                'fertility_level' => 'Moderate',
                'fertility_score' => 50,
                'color_theme'     => 'warning',
            ],
            [
                'key'             => 'watery',
                'label'           => 'Watery',
                'description'     => 'Clear, thin',
                'fertility_level' => 'High fertility',
                'fertility_score' => 75,
                'color_theme'     => 'purple',
            ],
            [
                'key'             => 'egg_white',
                'label'           => 'Egg white',
                'description'     => 'Clear, stretchy',
                'fertility_level' => 'Peak fertility',
                'fertility_score' => 100,
                'color_theme'     => 'success',
            ],
        ];
    }
}
