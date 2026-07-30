<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogComment extends Model
{
    protected $guarded = [];

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }
}
