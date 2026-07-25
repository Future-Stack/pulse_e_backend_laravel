<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class Provider extends Model
{
    use HasFactory, HasSpatial;

    protected $fillable = [
        'npi', 'google_place_id', 'display_name', 'org_name', 'phone_e164', 'website',
        'addr_line1', 'addr_line2', 'city', 'state', 'zip',
        'location', 'metro_id', 'source_nppes', 'source_places', 'match_confidence', 'status',
    ];

    protected $casts = [
        'location' => Point::class,
        'source_nppes' => 'boolean',
        'source_places' => 'boolean',
        'match_confidence' => 'decimal:2',
    ];

    public function metro(): BelongsTo
    {
        return $this->belongsTo(Metro::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProviderCategory::class, 'provider_category', 'provider_id', 'category_id')
            ->withPivot('source');
    }

    public function placeDetailsCache(): HasOne
    {
        return $this->hasOne(PlaceDetailsCache::class);
    }

    public function vettingRecords(): HasMany
    {
        return $this->hasMany(VettingRecord::class);
    }

    public function sponsoredSlots(): HasMany
    {
        return $this->hasMany(SponsoredSlot::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** True once every mandatory vetting check for this provider's categories has passed and is not expired. */
    public function isFullyVetted(): bool
    {
        $requiredTypes = $this->categories
            ->flatMap(fn (ProviderCategory $category) => $category->requiredCheckTypes())
            ->unique();

        foreach ($requiredTypes as $type) {
            $passed = $this->vettingRecords()
                ->where('check_type', $type)
                ->where('status', 'pass')
                ->where(function ($q) {
                    $q->whereNull('next_due_at')->orWhere('next_due_at', '>', now());
                })
                ->exists();

            if (! $passed) {
                return false;
            }
        }

        return true;
    }
}
