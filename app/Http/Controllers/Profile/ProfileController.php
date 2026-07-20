<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function getProfile()
    {
        try {
//            $user = auth()->user();
            $user = User::where('id',auth()->id())->select('id','full_name','email')->with('profile:id,user_id,life_stage_id,bio,profile_img,age,height,weight','profile.lifeStage','profile.connectDevices','profile.lifeJourneys')->first();

            return response()->json([
                'success' => true,
                'message' => 'Profile fetched successfully.',
                'data' => [
                    'user' => $user,
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
/**
 * Create or update the authenticated user's profile.
 */
public function saveProfile(Request $request)
{
    $user_id = auth()->id();

    $request->validate([
        'full_name'      => 'required|string|max:255',
        'age'            => 'nullable|integer|min:1',
        'height'         => 'nullable|numeric|min:0',
        'weight'         => 'nullable|numeric|min:0',
        'life_stage_id'  => 'nullable|integer|exists:life_stages,id',
        'bio'            => 'nullable|string',
        'profile_img'    => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp', // key ঠিক করা হলো
    ]);

    try {
        DB::beginTransaction();

        // Update user basic info
        User::where('id', $user_id)->update([
            'full_name' => $request->full_name,
        ]);

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('profile_img')) { // key এখন profile_img
            $storedPath = $request->file('profile_img')->store('profiles', 'public');
            $imagePath  = asset('storage/' . $storedPath);
        }

        // Create or update profile
        Profile::updateOrCreate(
            ['user_id' => $user_id],
            [
                'age'           => $request->age,
                'height'        => $request->height,
                'weight'        => $request->weight,
                'life_stage_id' => $request->life_stage_id,
                'activity_id'   => $request->activity_id,
                'bio'           => $request->bio,
                'profile_img'   => $imagePath,
            ]
        );

        DB::commit();

        // Reload user with updated profile
        $user = User::where('id', $user_id)
            ->select('id','full_name','email')
            ->with('profile:id,user_id,life_stage_id,bio,profile_img,age,height,weight','profile.lifeStage')
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Profile saved successfully.',
            'data'    => $user,
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



    public function userConnectedDevice()
    {
        $user_id = auth()->id();


    }
}
