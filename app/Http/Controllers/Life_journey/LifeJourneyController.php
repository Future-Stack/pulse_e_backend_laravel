<?php

namespace App\Http\Controllers\Life_journey;

use App\Http\Controllers\Controller;
use App\Models\LifeJourney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LifeJourneyController extends Controller
{
    
    public function index(): JsonResponse
    {
        $journeys = LifeJourney::where('status', 1)
            ->with(['features' => function($query) {
                $query->select('id', 'life_journey_id', 'feature_name');
            }])
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Life journeys retrieved successfully.',
            'data'    => $journeys
        ], 200);
    }

    
    public function show($id): JsonResponse
    {
        $journey = LifeJourney::where('status', 1)
            ->with('features')
            ->find($id);

        if (!$journey) {
            return response()->json([
                'success' => false,
                'message' => 'Life journey not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Life journey details retrieved successfully.',
            'data'    => $journey
        ], 200);
    }
}