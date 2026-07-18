<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabReport extends Model
{
    protected $fillable = [

        'user_id',

        'lab_report',

        'panel',

        'biomarkers',

        'ai_insights',

        'next_steps',

        'analysis_status',

    ];

    protected $casts = [

        'biomarkers' => 'array',

        'ai_insights' => 'array',

        'next_steps' => 'array',

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}