<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Blog extends Model
{
    protected $guarded = [];
    public function blogCategory() : BelongsTo
    {
        return $this->belongsTo(BlogCategory::class);
    }
}
