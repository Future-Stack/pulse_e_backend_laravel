<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SlateRequest;
use App\Http\Resources\ProviderSlateResource;
use App\Models\MarketplaceLifeStage;
use App\Models\Metro;
use App\Models\ProviderCategory;
use App\Services\MarketplaceSlateService;
use App\Services\ZipGeocodingService;
use Illuminate\Http\JsonResponse;

/**
 * GET /v1/marketplace/slate
 *
 * Privacy boundary (spec 2.1): this endpoint accepts exactly category, zip/metro_id,
 * and an optional life_stage slug. No user ID, insight ID, or health signal is ever
 * read, logged, or persisted here — the health domain resolves that upstream and only
 * the category slug crosses the boundary.
 */
class MarketplaceController extends Controller
{
    public function __construct(
        private readonly MarketplaceSlateService $slateService,
        private readonly ZipGeocodingService $zipGeocodingService,
    ) {
    }

    public function slate(SlateRequest $request): JsonResponse
    {
        $data = $request->validated();

        $category = ProviderCategory::where('slug', $data['category'])->where('active', true)->first();

        if (! $category) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        $metro = $this->resolveMetro($data);

        if (! $metro) {
            return response()->json([
                'data' => [],
                'message_key' => 'marketplace.no_metro_match',
            ]);
        }

        if (isset($data['life_stage'])) {
            $lifeStage = MarketplaceLifeStage::where('slug', $data['life_stage'])->first();

            $eligible = $lifeStage && $lifeStage->categories()->where('provider_categories.id', $category->id)->exists();

            if (! $eligible) {
                return response()->json(['message' => 'Category not eligible for the given life stage.'], 404);
            }
        }

        $limit = $data['limit'] ?? 3;

        $slate = $this->slateService->buildSlate($category, $metro, $limit);

        if ($slate->isEmpty()) {
            return response()->json([
                'data' => [],
                'message_key' => 'marketplace.no_providers_in_area',
            ]);
        }

        return response()->json([
            'data' => ProviderSlateResource::collection($slate),
        ]);
    }

    private function resolveMetro(array $data): ?Metro
    {
        if (! empty($data['metro_id'])) {
            return Metro::find($data['metro_id']);
        }

        // ZIP -> metro resolution via Google Geocoding, capped to each metro's own
        // radius_km and cached for 30 days (ZIP centroids don't move). Only a bare
        // 5-digit ZIP is ever sent to Google — no member identifier, no precise
        // address — preserving the spec 2.1 privacy boundary.
        return $this->zipGeocodingService->resolveMetro($data['zip']);
    }
}
