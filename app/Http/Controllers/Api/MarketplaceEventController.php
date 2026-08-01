<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSlateEventsRequest;
use App\Models\Metro;
use App\Models\ProviderCategory;
use App\Models\SlateEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * POST /v1/marketplace/events
 *
 * Batched client analytics events. StoreSlateEventsRequest already rejects any
 * field beyond the allow-listed set (deny-by-default guard, spec 2.5), so by the
 * time we get here the payload is safe to persist as-is.
 */
class MarketplaceEventController extends Controller
{
    public function store(StoreSlateEventsRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $sessionToken = $payload['session_token'];

        // Bulk query optimization
        $categorySlugs = collect($payload['events'])->pluck('category')->filter()->unique();
        $categories = ProviderCategory::whereIn('slug', $categorySlugs)->pluck('id', 'slug');

        $lifeStageSlugs = collect($payload['events'])->pluck('life_stage')->filter()->unique();
        $lifeStages = \App\Models\MarketplaceLifeStage::whereIn('slug', $lifeStageSlugs)->pluck('id', 'slug');

        $rows = collect($payload['events'])->map(function (array $event) use ($sessionToken, $categories, $lifeStages) {
            $lifeStageId = $event['marketplace_life_stage_id'] ?? null;
            if (! $lifeStageId && ! empty($event['life_stage'])) {
                $lifeStageId = $lifeStages[$event['life_stage']] ?? null;
            }

            return [
                'occurred_at'                 => $event['occurred_at'] ?? now(),
                'metro_id'                    => $event['metro_id'] ?? null,
                'category_id'                 => $categories[$event['category']] ?? null,
                'marketplace_life_stage_id'   => $lifeStageId,
                'provider_id'                 => $event['provider_id'] ?? null,
                'slot_position'               => $event['slot_position'] ?? null,
                'sponsored'                   => $event['sponsored'] ?? false,
                'event_type'                  => $event['event_type'],
                'session_token'               => $sessionToken,
            ];
        });

        DB::table('slate_events')->insert($rows->all());

        return response()->json(['status' => 'accepted', 'count' => $rows->count()], 202);
    }
}
