<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenopauseSymptomInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'link_percentage',
        'description',
        'category',
        'display_order',
    ];

    protected $casts = [
        'link_percentage' => 'integer',
        'display_order' => 'integer',
    ];
}
