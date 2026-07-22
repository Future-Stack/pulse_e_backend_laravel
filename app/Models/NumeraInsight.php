<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumeraInsight extends Model
{

    protected $fillable = [
        'user_id',
        'title',
        'tag',
        'eyebrow',
        'headline',
        'description',
        'cycle_day',
        'theme',
        'priority',
        'status',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

}