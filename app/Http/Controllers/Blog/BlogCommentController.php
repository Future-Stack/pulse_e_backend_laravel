<?php

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BlogCommentController extends Controller
{
    // ✅ List comments for a blog
    public function index($blogId)
    {
        try {
            $comments = BlogComment::where('blog_id', $blogId)
                ->where('is_approved', true)
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
                'name'    => 'nullable|string|max:255',
                'comment' => 'required|string|max:2000',
            ]);

            $comment = BlogComment::create([
                'blog_id' => $blogId,
                'name'    => $validated['name'] ?? null,
                'comment' => $validated['comment'],
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

    // ✅ Delete comment
    public function destroy($id)
    {
        try {
            $comment = BlogComment::findOrFail($id);
            $comment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully.',
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Delete comment failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to delete comment.',
            ], 500);
        }
    }
}
