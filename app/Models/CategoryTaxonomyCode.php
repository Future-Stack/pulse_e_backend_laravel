<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryTaxonomyCode extends Model
{
    protected $fillable = ['category_id', 'nucc_code', 'nucc_prefix', 'label'];

    protected $casts = [
        'nucc_prefix' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProviderCategory::class, 'category_id');
    }
}
