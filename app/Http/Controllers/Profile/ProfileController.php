<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\PostpartumRecovery;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserPregnancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function getProfile()
    {
        try {
            $user = User::where('id', auth()->id())
                ->select('id', 'full_name', 'email')
                ->with(
                    'profile:id,user_id,life_stage_id,bio,profile_img,age,height,weight',
                    'profile.lifeStage',
                    'profile.connectDevices',
                    'profile.lifeJourneys',
                    'latestSubscription.subscriptionPlan',
                    'userLimits'
                )
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            // Check Pregnancy & Postpartum sub-stage
            [$subStage, $stageDetails] = $this->resolvePregnancySubStage($user);

            $userData = $user->toArray();
            $userData['active_sub_stage'] = $subStage;
            $userData['maternal_status'] = $stageDetails;

            return response()->json([
                'success' => true,
                'message' => 'Profile fetched successfully.',
                'data' => [
                    'user' => $userData,
                ],
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Error fetching profile: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create or update the authenticated user's profile.
     */
    public function saveProfile(Request $request)
    {
        $user_id = auth()->id();

        // Support life_journey_id / life_journey_ids (array or comma-separated or json or single id)
        $journeyIds = $request->input('life_journey_id') ?? $request->input('life_journey_ids');
        if (is_null($journeyIds) && is_array($request->input('life_stage_id'))) {
            // In case client sent multiple IDs under life_stage_id key
            $journeyIds = $request->input('life_stage_id');
        }

        if (is_string($journeyIds)) {
            $decoded = json_decode($journeyIds, true);
            $journeyIds = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $journeyIds)));
        } elseif (!is_null($journeyIds) && !is_array($journeyIds)) {
            $journeyIds = [$journeyIds];
        }

        $lifeStageId = $request->input('life_stage_id');
        if (is_array($lifeStageId)) {
            $lifeStageId = $lifeStageId[0] ?? null;
        }

        $request->validate([
            'full_name'      => 'required|string|max:255',
            'age'            => 'nullable|integer|min:1',
            'height'         => 'nullable|numeric|min:0',
            'weight'         => 'nullable|numeric|min:0',
            'activity_id'    => 'nullable|integer|exists:activities,id',
            'life_stage_id'  => 'nullable',
            'bio'            => 'nullable|string',
            'profile_img'    => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
        ]);

        if ($lifeStageId !== null) {
            $request->merge(['_clean_life_stage_id' => $lifeStageId]);
            $request->validate([
                '_clean_life_stage_id' => 'integer|exists:life_stages,id',
            ], [
                '_clean_life_stage_id.exists' => 'The selected life stage is invalid.',
            ]);
        }

        if ($journeyIds !== null) {
            $request->merge(['_clean_journey_ids' => $journeyIds]);
            $request->validate([
                '_clean_journey_ids'   => 'array',
                '_clean_journey_ids.*' => 'integer|exists:life_journeys,id',
            ], [
                '_clean_journey_ids.*.exists' => 'One or more selected life journeys are invalid.',
            ]);
        }

        try {
            DB::beginTransaction();

            // Update user basic info
            User::where('id', $user_id)->update([
                'full_name' => $request->full_name,
            ]);

            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('profile_img')) {
                $storedPath = $request->file('profile_img')->store('profiles', 'public');
                $imagePath  = asset('storage/' . $storedPath);
            }

            // Create or update profile
            $profileData = [
                'age'         => $request->age,
                'height'      => $request->height,
                'weight'      => $request->weight,
                'activity_id' => $request->activity_id,
                'bio'         => $request->bio,
            ];

            if ($lifeStageId !== null) {
                $profileData['life_stage_id'] = $lifeStageId;
            }

            if ($imagePath !== null) {
                $profileData['profile_img'] = $imagePath;
            }

            $profile = Profile::updateOrCreate(
                ['user_id' => $user_id],
                $profileData
            );

            // Sync life journeys if provided
            if ($journeyIds !== null) {
                $profile->lifeJourneys()->sync($journeyIds);
            }

            DB::commit();

            // Reload user with updated profile and journeys
            $user = User::where('id', $user_id)
                ->select('id', 'full_name', 'email')
                ->with([
                    'profile:id,user_id,life_stage_id,activity_id,bio,profile_img,age,height,weight',
                    'profile.lifeStage',
                    'profile.lifeJourneys',
                ])
                ->first();

            $userData = $user->toArray();
            [$subStage, $stageDetails] = $this->resolvePregnancySubStage($user);
            $userData['active_sub_stage'] = $subStage;
            $userData['maternal_status'] = $stageDetails;

            return response()->json([
                'success' => true,
                'message' => 'Profile saved successfully.',
                'data'    => $userData,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Profile save failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save profile.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve active pregnancy/postpartum sub-stage for a user.
     */
    private function resolvePregnancySubStage(User $user): array
    {
        $isPregnancyJourney = true;
        if ($user->profile && $user->profile->lifeJourneys && $user->profile->lifeJourneys->isNotEmpty()) {
            $isPregnancyJourney = $user->profile->lifeJourneys->contains(function ($journey) {
                return str_contains(strtolower($journey->title), 'pregnancy');
            }) || str_contains(strtolower($user->profile->lifeStage?->title ?? ''), 'pregnancy')
               || str_contains(strtolower($user->profile->lifeStage?->title ?? ''), 'postpartum');
        }

        $subStage = null;
        $stageDetails = null;

        if ($isPregnancyJourney) {
            $latestPregnancy = UserPregnancy::where('user_id', $user->id)
                ->latest()
                ->first();

            $postpartum = PostpartumRecovery::where('user_id', $user->id)
                ->latest()
                ->first();

            if ($latestPregnancy && $latestPregnancy->status === 'active') {
                $subStage = 'pregnancy';
                $stageDetails = [
                    'stage'           => 'pregnancy',
                    'current_week'    => $latestPregnancy->current_week,
                    'total_weeks'     => 40,
                    'due_date'        => $latestPregnancy->due_date?->toDateString(),
                    'days_remaining'  => $latestPregnancy->days_to_due_date,
                    'trimester'       => $latestPregnancy->trimester,
                ];
            } elseif ($latestPregnancy && $latestPregnancy->status === 'miscarriage') {
                $subStage = 'miscarriage';
                $stageDetails = [
                    'stage'               => 'miscarriage',
                    'status'              => 'miscarriage',
                    'healing_mode_active' => true,
                    'loss_date'           => $latestPregnancy->ended_at?->toDateString() ?? now()->toDateString(),
                    'active_tab'          => 'support',
                ];
            } elseif ($postpartum && (!$latestPregnancy || $latestPregnancy->status === 'completed')) {
                $subStage = 'postpartum';
                $stageDetails = [
                    'stage'           => 'postpartum',
                    'current_week'    => $postpartum->weeks_since_delivery,
                    'delivery_date'   => $postpartum->delivery_date?->toDateString(),
                ];
            }
        }

        return [$subStage, $stageDetails];
    }



    public function userConnectedDevice()
    {
        $user_id = auth()->id();


    }
}
