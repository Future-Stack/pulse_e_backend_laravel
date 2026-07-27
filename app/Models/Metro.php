<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

/**
 * NOTE: Spatial point casting uses matanyadaev/laravel-eloquent-spatial.
 * composer require matanyadaev/laravel-eloquent-spatial
 * If you prefer not to add the package, cast 'centroid' as a raw string
 * and read/write lat/lng via DB::raw(ST_X/ST_Y) instead.
 */
class Metro extends Model
{
    use HasFactory, HasSpatial;

    protected $fillable = ['name', 'state', 'centroid', 'radius_km', 'density_tier', 'active'];

    protected $casts = [
        'centroid' => Point::class,
        'active' => 'boolean',
    ];

    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class);
    }

    public function sponsoredSlots(): HasMany
    {
        return $this->hasMany(SponsoredSlot::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
