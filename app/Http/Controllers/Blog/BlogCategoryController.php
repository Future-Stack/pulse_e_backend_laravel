<?php

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogCategoryController extends Controller
{
    // ✅ Fetch all categories
    public function index()
    {
        try {
            $categories = BlogCategory::orderBy('sort_order')->get();

            return response()->json([
                'success' => true,
                'data' => $categories,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Fetch categories failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch categories.',
            ], 500);
        }
    }

    // ✅ Create category
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'sort_order' => 'nullable|integer',
                'is_active' => 'boolean',
            ]);

            $category = BlogCategory::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'description' => $validated['description'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully.',
                'data' => $category,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Create category failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to create category.',
            ], 500);
        }
    }

    // ✅ Show single category
    public function show(string $slug)
    {
        try {
            $category = BlogCategory::where('slug', $slug)->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $category,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Show category failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Category not found.',
            ], 404);
        }
    }

    // ✅ Update category
    public function update(Request $request, string $slug)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'sort_order' => 'nullable|integer',
                'is_active' => 'boolean',
            ]);

            $category = BlogCategory::where('slug', $slug)->firstOrFail();

            $category->update([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'description' => $validated['description'] ?? $category->description,
                'sort_order' => $validated['sort_order'] ?? $category->sort_order,
                'is_active' => $validated['is_active'] ?? $category->is_active,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully.',
                'data' => $category,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Update category failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to update category.',
            ], 500);
        }
    }

    // ✅ Delete category
    public function destroy(string $slug)
    {
        try {
            $category = BlogCategory::where('slug', $slug)->firstOrFail();
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully.',
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Delete category failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to delete category.',
            ], 500);
        }
    }
}
