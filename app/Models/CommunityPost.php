<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CommunityPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'content',
        'is_anonymous',
        'is_approved',
        'tags',
        'posted_at',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'is_approved'  => 'boolean',
        'tags'         => 'array',
        'posted_at'    => 'datetime',
    ];

    // ===== Relationships =====

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A post can belong to multiple life journeys via the pivot table
     * community_post_life_journey (proper foreign keys, cascade delete).
     */
    public function lifeJourneys()
    {
        return $this->belongsToMany(
            LifeJourney::class,
            'community_post_life_journey',
            'community_post_id',
            'life_journey_id'
        )->withTimestamps();
    }

    public function comments()
    {
        return $this->hasMany(CommunityComment::class, 'post_id');
    }

    public function likes()
    {
        return $this->hasMany(CommunityLike::class, 'post_id');
    }

    public function reports()
    {
        return $this->hasMany(CommunityPostReport::class, 'post_id');
    }

    // ===== Helpers =====

    public function isLikedBy(?int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        return $this->likes()->where('user_id', $userId)->exists();
    }

    public static function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $i = 1;

        while (self::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }
}