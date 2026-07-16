<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserManagementController extends Controller
{
    /**
     * Display a paginated list of users.
     */
    public function index(): JsonResponse
    {
        $users = User::with([
                'profile.lifeJourneys',
                'latestSubscription.subscriptionPlan',
            ])
            ->where('id', '!=', 1) // Exclude Super Admin
            ->latest()
            ->paginate(10);

        $data = $users->getCollection()->map(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'plan' => $user->latestSubscription?->subscriptionPlan?->name,
                'journey' => $user->profile?->lifeJourneys->first()?->title,
                'last_login' => $user->last_login_at?->diffForHumans(),
                'status' => ucfirst($user->status),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'User list retrieved successfully.',
            'data' => $data,
            'pagination' => [
                'current_page' => $users->currentPage(),
                'next_page' => $users->hasMorePages()
                    ? $users->currentPage() + 1
                    : null,
                'prev_page' => $users->currentPage() > 1
                    ? $users->currentPage() - 1
                    : null,
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ], 200);
    }



    public function show($id): JsonResponse
{
    $user = User::with([
        'profile.lifeStage',
        'profile.activity',
        'profile.healthGoals',
        'profile.lifeJourneys',
        'latestSubscription.subscriptionPlan',
    ])
    ->where('id', '!=', 1)
    ->findOrFail($id);

    return response()->json([
        'success' => true,
        'message' => 'User details retrieved successfully.',
        'data' => [
            'account_information' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'age' => $user->profile?->age,
                'joined' => optional($user->created_at)->format('F d, Y'),
                'status' => ucfirst($user->status),
                'last_login' => $user->last_login_at?->diffForHumans(),
                'subscription_plan' => $user->latestSubscription?->subscriptionPlan?->name,
            ],

            'health_profile' => [
                'life_stage' => $user->profile?->lifeStage?->title,
                'health_goal' => $user->profile?->healthGoals?->pluck('title')->implode(', '),
                'activity' => $user->profile?->activity?->title,
                'life_journey' => $user->profile?->lifeJourneys?->pluck('title')->implode(', '),
                'health_condition' => $user->profile?->bio,
            ],
        ],
    ], 200);
}
}