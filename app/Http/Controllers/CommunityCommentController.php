<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommunityCommentController extends Controller
{
    /**
     * GET /api/community/posts/{post}/comments
     */
    public function index(CommunityPost $post)
    {
        $comments = $post->comments()
            ->with('user:id,full_name')
            ->latest()
            ->paginate(20);

        return response()->json($comments);
    }

    /**
     * POST /api/community/posts/{post}/comments
     */
    public function store(Request $request, CommunityPost $post)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $comment = CommunityComment::create([
            'post_id' => $post->id,
            'user_id' => Auth::id(),
            'content' => $validated['content'],
        ]);

        $comment->load('user:id,full_name');

        return response()->json([
            'message' => 'Comment added successfully.',
            'comment' => $comment,
        ], 201);
    }

    /**
     * DELETE /api/community/comments/{comment}
     * Only the comment author (or admin) can delete.
     */
    public function destroy(CommunityComment $comment)
    {
        $user = Auth::user();

        if ($comment->user_id !== $user->id && !($user->hasRole('admin') ?? false)) {
            return response()->json(['message' => 'You are not allowed to delete this comment.'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully.']);
    }
}