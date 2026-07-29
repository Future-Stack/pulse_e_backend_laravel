<?php

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    // ✅ List all blogs
    public function index(Request $request)
    {
        try {
            $blogs = Blog::with('category')->orderByDesc('published_at');

            $category_id = $request->query('category_id');
            if ($category_id) {
                $blogs = $blogs->where('blog_category_id', $category_id)->get();
            }
            else{
                $blogs = $blogs->get();
            }

            return response()->json([
                'success' => true,
                'data' => $blogs,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Fetch blogs failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ✅ Create a new blog
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'blog_category_id' => 'required|exists:blog_categories,id',
                'title' => 'required|string|max:255',
                'short_desc' => 'nullable|string',
                'content' => 'required|string',
                'cover_image' => 'nullable|image',
                'author_name' => 'nullable|string|max:255',
                'author_avatar' => 'nullable|string',
                'reading_time' => 'nullable|integer',
                'word_count' => 'nullable|integer',
                'tags' => 'nullable|array',
                'is_featured' => 'boolean',
                'is_published' => 'boolean',
            ]);


            $imagePath = '';
            if ($request->hasFile('cover_image')) {
                $storedPath = $request->file('cover_image')->store('blogs', 'public');
                $imagePath  = asset('storage/' . $storedPath); // ✅ full URL
            }

            $blog = Blog::create([
                'blog_category_id' => $validated['blog_category_id'],
                'title' => $validated['title'],
                'slug' => Str::slug($validated['title']),
                'cover_image' => $imagePath,
                'short_desc' => $validated['short_desc'] ?? null,
                'content' => asset($validated['content']),
                'author_name' => $validated['author_name'] ?? null,
                'author_avatar' => $validated['author_avatar'] ?? null,
                'reading_time' => $validated['reading_time'] ?? null,
                'word_count' => $validated['word_count'] ?? null,
                'tags' => isset($validated['tags']) ? json_encode($validated['tags']) : null,
                'is_featured' => $validated['is_featured'] ?? false,
                'is_published' => $validated['is_published'] ?? false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Blog created successfully.',
                'data' => $blog,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Create blog failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ✅ Show single blog
    public function show($id)
    {
        try {
            $blog = Blog::with('category')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $blog,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Show blog failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Blog not found.',
            ], 404);
        }
    }

    // ✅ Update blog
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'blog_category_id' => 'required|exists:blog_categories,id',
                'title' => 'required|string|max:255',
                'short_desc' => 'nullable|string',
                'content' => 'required|string',
                'cover_image' => 'nullable|string',
                'author_name' => 'nullable|string|max:255',
                'author_avatar' => 'nullable|string',
                'reading_time' => 'nullable|integer',
                'word_count' => 'nullable|integer',
                'tags' => 'nullable|array',
                'is_featured' => 'boolean',
                'is_published' => 'boolean',
                'published_at' => 'nullable|date',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string|max:255',
            ]);

            $blog = Blog::findOrFail($id);

            $blog->update([
                'blog_category_id' => $validated['blog_category_id'],
                'title' => $validated['title'],
                'slug' => Str::slug($validated['title']),
                'short_desc' => $validated['short_desc'] ?? $blog->short_desc,
                'content' => $validated['content'],
                'cover_image' => $validated['cover_image'] ?? $blog->cover_image,
                'author_name' => $validated['author_name'] ?? $blog->author_name,
                'author_avatar' => $validated['author_avatar'] ?? $blog->author_avatar,
                'reading_time' => $validated['reading_time'] ?? $blog->reading_time,
                'word_count' => $validated['word_count'] ?? $blog->word_count,
                'tags' => $validated['tags'] ?? $blog->tags,
                'is_featured' => $validated['is_featured'] ?? $blog->is_featured,
                'is_published' => $validated['is_published'] ?? $blog->is_published,
                'published_at' => $validated['published_at'] ?? $blog->published_at,
                'meta_title' => $validated['meta_title'] ?? $blog->meta_title,
                'meta_description' => $validated['meta_description'] ?? $blog->meta_description,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Blog updated successfully.',
                'data' => $blog,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Update blog failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to update blog.',
            ], 500);
        }
    }

    // ✅ Delete blog
    public function destroy($id)
    {
        try {
            $blog = Blog::findOrFail($id);
            $blog->delete();

            return response()->json([
                'success' => true,
                'message' => 'Blog deleted successfully.',
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Delete blog failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to delete blog.',
            ], 500);
        }
    }
}
