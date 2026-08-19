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
            $blogs = Blog::with('blogCategory')->orderByDesc('published_at');

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
    public function show(string $slug)
    {
        try {
            $blog = Blog::with('blogCategory')->where('slug', $slug)->firstOrFail();

            // Increment views_count each time this API is called
            $blog->increment('views_count');

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
    public function update(Request $request, string $slug)
    {
        try {
            $validated = $request->validate([
                'blog_category_id' => 'required|exists:blog_categories,id',
                'title' => 'required|string|max:255',
                'short_desc' => 'nullable|string',
                'content' => 'required|string',
                'cover_image' => 'nullable|image',
                'author_name' => 'nullable|string|max:255',
                'reading_time' => 'nullable|integer',
                'word_count' => 'nullable|integer',
                'tags' => 'nullable|array',
                'is_featured' => 'boolean',
                'is_published' => 'boolean',
            ]);

            $blog = Blog::where('slug', $slug)->firstOrFail();

            $blog->update([
                'blog_category_id' => $validated['blog_category_id'],
                'title' => $validated['title'],
                'short_desc' => $validated['short_desc'] ?? $blog->short_desc,
                'content' => $validated['content'],
                'author_name' => $validated['author_name'] ?? $blog->author_name,
                'reading_time' => $validated['reading_time'] ?? $blog->reading_time,
                'word_count' => $validated['word_count'] ?? $blog->word_count,
                'tags' => $validated['tags'] ?? $blog->tags,
                'is_featured' => $validated['is_featured'] ?? $blog->is_featured,
                'is_published' => $validated['is_published'] ?? $blog->is_published,
            ]);

            $imagePath = '';
            if ($request->hasFile('cover_image')) {
                $storedPath = $request->file('cover_image')->store('blogs', 'public');
                $imagePath  = asset('storage/' . $storedPath); // ✅ full URL
            }

            $blog->update([
                'cover_image' => $imagePath,
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
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ✅ Delete blog
    public function destroy(string $slug)
    {
        try {
            $blog = Blog::where('slug', $slug)->firstOrFail();
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
