<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityPostReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'user_id',
        'comment',
        'is_active',
        'report_cause',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the post that was reported.
     */
    public function post()
    {
        return $this->belongsTo(CommunityPost::class, 'post_id');
    }

    /**
     * Get the user who submitted the report.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}