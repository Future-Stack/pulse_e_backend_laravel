<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogComment extends Model
{
    protected $fillable = [
        'blog_id', 'name', 'comment', 'is_approved'
    ];

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }
}
