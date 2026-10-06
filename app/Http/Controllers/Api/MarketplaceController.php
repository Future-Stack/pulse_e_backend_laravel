<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SlateRequest;
use App\Http\Resources\ProviderSlateResource;
use App\Models\LifeJourney;
use App\Models\MarketplaceLifeStage;
use App\Models\Metro;
use App\Models\ProviderCategory;
use App\Services\MarketplaceSlateService;
use App\Services\ZipGeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * GET /v1/marketplace/slate
 * GET /v1/marketplace/categories
 *
 * Privacy boundary (spec 2.1): this endpoint accepts category, zip/metro_id/coords,
 * and an optional life_stage / life_journey_id. No insight ID or private health signal
 * is ever logged or persisted here.
 */
class MarketplaceController extends Controller
{
    public function __construct(
        private readonly MarketplaceSlateService $slateService,
        private readonly ZipGeocodingService $zipGeocodingService,
    ) {
    }

    /**
     * Get available provider categories for a given life journey / life stage,
     * or for the authenticated user's current journeys.
     */
    public function categories(Request $request): JsonResponse
    {
        $journeyInput = $request->input('life_journey_id')
            ?? $request->input('journey_id')
            ?? $request->input('life_stage');

        if ($journeyInput) {
            $stage = $this->resolveSingleLifeStage($journeyInput);
            if ($stage) {
                $categories = $stage->categories()
                    ->where('active', true)
                    ->orderBy('marketplace_life_stage_category.display_priority', 'asc')
                    ->get();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'life_stage' => [
                            'id' => $stage->id,
                            'slug' => $stage->slug,
                            'name' => $stage->name,
                        ],
                        'categories' => $categories->map(fn ($cat) => [
                            'id' => $cat->id,
                            'slug' => $cat->slug,
                            'display_name' => $cat->display_name,
                            'vetting_tier' => $cat->vetting_tier,
                            'display_priority' => $cat->pivot->display_priority ?? 0,
                        ]),
                    ],
                ]);
            }
        }

        // Check if user is authenticated and has multiple journeys
        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();
        if ($user && $user->profile && $user->profile->lifeJourneys()->exists()) {
            $userStages = $this->resolveLifeStages($request);
            $userStages->load(['categories' => function ($q) {
                $q->where('active', true)->orderBy('marketplace_life_stage_category.display_priority', 'asc');
            }]);

            $data = $userStages->map(function ($stage) {
                return [
                    'life_stage' => [
                        'id' => $stage->id,
                        'slug' => $stage->slug,
                        'name' => $stage->name,
                    ],
                    'categories' => $stage->categories->map(fn ($cat) => [
                        'id' => $cat->id,
                        'slug' => $cat->slug,
                        'display_name' => $cat->display_name,
                        'vetting_tier' => $cat->vetting_tier,
                        'display_priority' => $cat->pivot->display_priority ?? 0,
                    ]),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        }

        // If no specific journey requested, return all stages with their active categories
        $stages = MarketplaceLifeStage::with(['categories' => function ($q) {
            $q->where('active', true)->orderBy('marketplace_life_stage_category.display_priority', 'asc');
        }])->get();

        $data = $stages->map(function ($stage) {
            return [
                'life_stage' => [
                    'id' => $stage->id,
                    'slug' => $stage->slug,
                    'name' => $stage->name,
                ],
                'categories' => $stage->categories->map(fn ($cat) => [
                    'id' => $cat->id,
                    'slug' => $cat->slug,
                    'display_name' => $cat->display_name,
                    'vetting_tier' => $cat->vetting_tier,
                    'display_priority' => $cat->pivot->display_priority ?? 0,
                ]),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function slate(SlateRequest $request): JsonResponse
    {
        $data = $request->validated();

        $lifeStages = $this->resolveLifeStages($request);

        $category = null;
        $matchedLifeStage = null;

        if (! empty($data['category'])) {
            $category = ProviderCategory::where('slug', $data['category'])->where('active', true)->first();
        } elseif ($lifeStages->isNotEmpty()) {
            // Automatically select the highest priority category for the first active life stage
            $matchedLifeStage = $lifeStages->first();
            $category = $matchedLifeStage->categories()
                ->where('active', true)
                ->orderBy('marketplace_life_stage_category.display_priority', 'asc')
                ->first();
        }

        if (! $category) {
            return response()->json(['message' => 'Category not found or required.'], 404);
        }

        $userCoords = null;
        $metro = null;

        if (! empty($data['metro_id'])) {
            $metro = Metro::find($data['metro_id']);
        } elseif (! empty($data['lat']) && ! empty($data['lng'])) {
            $userCoords = [
                'lat' => (float) $data['lat'],
                'lng' => (float) $data['lng'],
            ];
            $metro = $this->zipGeocodingService->resolveMetroFromCoords($userCoords['lat'], $userCoords['lng']);
        } elseif (! empty($data['zip'])) {
            $resolution = $this->zipGeocodingService->resolveMetroWithCoords($data['zip']);
            $metro = $resolution['metro'];
            $userCoords = $resolution['coords'];
        }

        if (! $metro) {
            return response()->json([
                'data' => [],
                'message_key' => 'marketplace.no_metro_match',
            ]);
        }

        if ($lifeStages->isNotEmpty()) {
            $matchedLifeStage = $lifeStages->first(function ($stage) use ($category) {
                return $stage->categories()->where('provider_categories.id', $category->id)->exists();
            });

            $hasExplicitJourney = $request->filled('life_journey_id')
                || $request->filled('journey_id')
                || $request->filled('life_stage');

            if (! $matchedLifeStage && $hasExplicitJourney) {
                return response()->json(['message' => 'Category not eligible for the given life stage.'], 404);
            }
        }

        $limit = $data['limit'] ?? 3;

        $slate = $this->slateService->buildSlate($category, $metro, $limit, $userCoords);

        if ($slate->isEmpty()) {
            return response()->json([
                'data' => [],
                'message_key' => 'marketplace.no_providers_in_area',
            ]);
        }

        return response()->json([
            'data' => ProviderSlateResource::collection($slate),
            'meta' => [
                'category' => $category->slug,
                'category_name' => $category->display_name,
                'life_stage' => $matchedLifeStage?->slug,
                'life_stage_name' => $matchedLifeStage?->name,
                'user_life_stages' => $lifeStages->map(fn ($s) => [
                    'id' => $s->id,
                    'slug' => $s->slug,
                    'name' => $s->name,
                ])->values(),
            ],
        ]);
    }

    /**
     * Resolves all matching MarketplaceLifeStage(s) from input or authenticated user.
     * @return \Illuminate\Support\Collection<int, MarketplaceLifeStage>
     */
    private function resolveLifeStages(Request $request): \Illuminate\Support\Collection
    {
        $journeyInput = $request->input('life_journey_id')
            ?? $request->input('journey_id')
            ?? $request->input('life_stage');

        if (! empty($journeyInput)) {
            $single = $this->resolveSingleLifeStage($journeyInput);
            return $single ? collect([$single]) : collect();
        }

        // Fallback to authenticated user's active life journeys (supports multiple!)
        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();
        if ($user && $user->profile) {
            $userJourneys = $user->profile->lifeJourneys()->get();
            if ($userJourneys->isNotEmpty()) {
                return $userJourneys->map(function ($journey) {
                    return MarketplaceLifeStage::find($journey->id)
                        ?? MarketplaceLifeStage::where('slug', Str::slug($journey->title))->first();
                })->filter()->values();
            }
        }

        return collect();
    }

    /**
     * Resolves a single MarketplaceLifeStage from id, slug, or title.
     */
    private function resolveSingleLifeStage(mixed $input): ?MarketplaceLifeStage
    {
        if (is_numeric($input)) {
            $id = (int) $input;
            $stage = MarketplaceLifeStage::find($id);
            if ($stage) {
                return $stage;
            }

            $journey = LifeJourney::find($id);
            if ($journey) {
                return MarketplaceLifeStage::where('slug', Str::slug($journey->title))->first();
            }
        }

        $slug = Str::slug((string) $input);
        $stage = MarketplaceLifeStage::where('slug', $input)
            ->orWhere('slug', $slug)
            ->orWhere('name', $input)
            ->first();

        if ($stage) {
            return $stage;
        }

        $journey = LifeJourney::where('title', $input)->first();
        if ($journey) {
            return MarketplaceLifeStage::where('slug', Str::slug($journey->title))->first();
        }

        return null;
    }
}
