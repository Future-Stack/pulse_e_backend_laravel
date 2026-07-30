<?php

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Models\BlogComment;
use Illuminate\Http\Request;

class BlogCommentController extends Controller
{
    // ✅ List comments for a blog
    public function index($blogId)
    {
        try {
            $comments = BlogComment::where('blog_id', $blogId)
                ->where('status', 1) // only approved/active
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'data'    => $comments,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Fetch comments failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch comments.',
            ], 500);
        }
    }

    // ✅ Store new comment
    public function store(Request $request, $blogId)
    {
        try {
            $validated = $request->validate([
                'author_name' => 'required|string|max:255',
                'comment'     => 'required|string|max:5000',
            ]);

            $comment = BlogComment::create([
                'blog_id'     => $blogId,
                'author_name' => $validated['author_name'],
                'comment'     => $validated['comment'],
                'status'      => 1, // default active
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comment posted successfully.',
                'data'    => $comment,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Create comment failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to post comment.',
            ], 500);
        }
    }
}
