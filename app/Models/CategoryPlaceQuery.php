<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryPlaceQuery extends Model
{
    protected $fillable = ['category_id', 'places_type', 'keyword'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProviderCategory::class, 'category_id');
    }
}
