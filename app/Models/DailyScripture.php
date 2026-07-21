<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyScripture extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'scripture_date',
        'badge',
        'verse_text',
        'reference',
        'reason',
        'status',
    ];

    protected $casts = [
        'scripture_date' => 'date',
    ];

    /**
     * Daily Scripture belongs to a user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}