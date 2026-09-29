<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PregnancyWeeklyGuide extends Model
{
    use HasFactory;

    protected $fillable = [
        'week_number',
        'trimester',
        'baby_size_comparison',
        'approx_size_text',
        'baby_development',
        'your_body',
        'nutrition_focus',
        'safe_exercise',
        'clinical_warning_signs',
    ];
}
