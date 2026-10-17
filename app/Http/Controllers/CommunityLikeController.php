<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CommunityLike;
use App\Models\CommunityPost;
use Illuminate\Support\Facades\Auth;

class CommunityLikeController extends Controller
{
    /**
     * POST /api/community/posts/{post}/like
     * Toggles the like: if already liked -> removes it, otherwise creates it.
     */
    public function toggle(CommunityPost $post)
    {
        $userId = Auth::id();

        $deleted = CommunityLike::where('post_id', $post->id)
            ->where('user_id', $userId)
            ->delete();

        if ($deleted) {
            $liked = false;
        } else {
            CommunityLike::create([
                'post_id'  => $post->id,
                'user_id'  => $userId,
                'liked_at' => now(),
            ]);
            $liked = true;
        }

        return response()->json([
            'liked'       => $liked,
            'likes_count' => CommunityLike::where('post_id', $post->id)->count(),
        ]);
    }
}