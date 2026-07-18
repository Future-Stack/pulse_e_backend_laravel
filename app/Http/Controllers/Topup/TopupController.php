<?php

namespace App\Http\Controllers\Topup;

use App\Http\Controllers\Controller;
use App\Models\TopupProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TopupController extends Controller
{
    public function createOrUpdate(Request $request, string $slug)
    {
        try {
            $validated = $request->validate([
                'name'         => 'required|string|max:255',
                'description'  => 'nullable|string',
                'topup_kind'   => 'nullable|in:coaching_sessions,skin_scans',
                'limit'        => 'nullable|integer|min:1',
                'price'        => 'required|numeric|min:0',
                'status'       => 'boolean',
            ]);

            $validated['slug'] = $slug;

            $product = TopupProduct::updateOrCreate(
                ['slug' => $validated['slug']],
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Top-up product saved successfully.',
                'data'    => $product,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Top-up product create/update failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save top-up product.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getAll()
    {
        try {
            $products = TopupProduct::where('status', 1)
                ->orderBy('price', 'asc')
                ->get()
                ->map(function ($product) {
                    return [
                        'id'           => $product->id,
                        'slug'         => $product->slug,
                        'name'         => $product->name,
                        'description'  => $product->description,
                        'topup_kind'   => $product->topup_kind,
                        'limit'        => $product->limit,
                        'price'        => $product->price,
                        'status'       => $product->status,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Top-up products fetched successfully.',
                'data'    => $products,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Fetch top-up products failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch top-up products.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch a single top-up product by slug.
     */
    public function getBySlug($slug)
    {
        try {
            $product = TopupProduct::where('slug', $slug)
                ->where('status', 1)
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Top-up product not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Top-up product fetched successfully.',
                'data'    => [
                    'id'           => $product->id,
                    'slug'         => $product->slug,
                    'name'         => $product->name,
                    'description'  => $product->description,
                    'topup_kind'   => $product->topup_kind,
                    'limit'        => $product->limit,
                    'price'        => $product->price,
                    'status'       => $product->status,
                ],
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Fetch top-up product failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch top-up product.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function topUpPayment()
    {

    }
}
