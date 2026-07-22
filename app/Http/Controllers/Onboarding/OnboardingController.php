<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ConnectDevice;
use App\Models\HealthGoal;
use App\Models\LifeJourney;
use App\Models\LifeStage;
use App\Models\Page;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnboardingController extends Controller
{
    public function getPrivacyPolicy()
    {
        try {
            $data = Page::where('slug', 'privacy-policy')->first();

            return response()->json([
                'success' => true,
                'message' => 'Privacy policy has been loaded.',
                'data' => $data,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error fetching privacy & policy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch life stages.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getLifeStages()
    {
        try {
            $data = LifeStage::where('status', 1)->get();

            return response()->json([
                'success' => true,
                'message' => 'Life stages fetched successfully.',
                'data' => $data,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error fetching life stages: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch life stages.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getHealthGoals()
    {
        try {
            $data = HealthGoal::where('status', 1)->get();

            return response()->json([
                'success' => true,
                'message' => 'Health goals fetched successfully.',
                'data' => $data,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error fetching health goals: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch health goals.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getActivities()
    {
        try {
            $data = Activity::where('status', 1)->get();

            return response()->json([
                'success' => true,
                'message' => 'Activities fetched successfully.',
                'data' => $data,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error fetching activities: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch activities.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getLifeJourneys()
    {
        try {
            $data = LifeJourney::where('status', 1)->get();

            return response()->json([
                'success' => true,
                'message' => 'Life journeys fetched successfully.',
                'data' => $data,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error fetching life journeys: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch life journeys.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getConnectDevices()
    {
        try {
            $data = ConnectDevice::where('status', 1)->get();

            return response()->json([
                'success' => true,
                'message' => 'Connect devices fetched successfully.',
                'data' => $data,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error fetching connect devices: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch connect devices.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function profileOnboarding(Request $request)
    {
        $user_id = auth()->id();

        $request->validate([
            'age' => 'required|integer',
            'life_stage_id' => 'required|integer|exists:life_stages,id',
            'activity_id' => 'required|integer|exists:activities,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'height' => 'required|numeric',
            'weight' => 'required|numeric',
            'health_goal_id' => 'required|array',
            'health_goal_id.*' => 'integer|exists:health_goals,id',
            'life_journey_id' => 'required|array',
            'life_journey_id.*' => 'integer|exists:life_journeys,id',
            'connect_device_id' => 'nullable|array',
            'connect_device_id.*' => 'integer|exists:connect_devices,id',
        ]);

        try {
            DB::beginTransaction();

            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('image')) {
                $storedPath = $request->file('image')->store('profiles', 'public');
                $imagePath = asset('storage/' . $storedPath);
            }

            // Create or update profile
            $profile = Profile::updateOrCreate(
                ['user_id' => $user_id],
                [
                    'age' => $request->age,
                    'life_stage_id' => $request->life_stage_id,
                    'activity_id' => $request->activity_id,
                    'profile_img' => $imagePath,
                    'height' => $request->height,
                    'weight' => $request->weight,
                ]
            );

            // Sync pivot tables
            $profile->healthGoals()->sync($request->health_goal_id);
            $profile->lifeJourneys()->sync($request->life_journey_id);

            if ($request->connect_device_id) {
                $profile->connectDevices()->sync($request->connect_device_id);
            }

            User::where('id', $user_id)->update(['onboardingCompleted' => true]);

            //Assign User to Free plan

            $freePlan = SubscriptionPlan::where('slug', 'free')->first();

            // Check if user already has a subscription payment
            $existingPayment = Payment::where('user_id', $user_id)
                ->where('type', 'subscription')
                ->first();

            if (!$existingPayment) {
                // Create a payment record for free plan
                $payment = Payment::create([
                    'user_id' => $user_id,
                    'subscription_plan_id' => $freePlan->id,
                    'type' => 'subscription',
                    'billing_cycle' => 'month',
                    'current_period_start' => now(),
                    'current_period_end' => now()->addDays(7),
                    'amount' => 0,
                    'status' => 'paid',
                ]);

                // Create or update user limits
                UserLimit::updateOrCreate(
                    [
                        'user_id' => $user_id,
                    ],
                    [
                        'payment_id' => $payment->id,
                        'type' => 'subscription',
                        'skin_scans_limit' => $freePlan->skin_scans_limit,
                        'ai_coaching_limit' => $freePlan->ai_coaching_limit,
                        'deep_reports_limit' => $freePlan->deep_reports_limit,
                        'subscription_expires_at' => $payment->current_period_end,
                    ]
                );
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profile onboarding completed successfully.',
                'data' => $profile->load(['user', 'lifeStage', 'activity', 'healthGoals', 'lifeJourneys', 'connectDevices']),
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Profile onboarding failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete profile onboarding.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
