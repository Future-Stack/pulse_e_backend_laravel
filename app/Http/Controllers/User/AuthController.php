<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\SendOtpEmail;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{

public function register(Request $request)
{
    try {

        $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'is_privacy_accepted' => 'required|accepted',
        ], [
            'full_name.required' => 'Full name field is required.',

            'email.required' => 'Email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'Email is already registered.',

            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',

            'is_privacy_accepted.required' => 'You must accept the terms and conditions.',
            'is_privacy_accepted.accepted' => 'You must accept the terms and conditions.',
        ]);

        // Generate OTP
        $otp = random_int(1000, 9999);

        // Create User
        $user = User::create([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),

            'user_type' => 'user',
            'status' => 'active',

            'otp' => $otp,
            'otp_expire_at' => Carbon::now()->addMinutes(5),

            'is_privacy_accepted' => true,
            'onboardingCompleted' => false,
        ]);

        // Send OTP Email
        SendOtpEmail::dispatch($user->id, 'verify', $otp);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. OTP sent to your email.',
            'data' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'user_type' => $user->user_type,
                'status' => $user->status,
                'is_privacy_accepted' => $user->is_privacy_accepted,
                'onboardingCompleted' => $user->onboardingCompleted, // false
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
            ]
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {

        return response()->json([
            'success' => false,
            'message' => collect($e->errors())->flatten()->first(),
        ], 422);

    } catch (\Exception $e) {

        Log::error('Registration Error: '.$e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Something went wrong. Please try again.',
        ], 500);
    }
}

public function login(Request $request)
{
    try {

        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ], [
            'email.required' => 'Email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password field is required.',
            'password.min' => 'Password must be at least 8 characters.',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        // Email verification
        if (is_null($user->email_verified_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email before logging in.',
            ], 403);
        }

        // Suspended account
        if ($user->status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => $user->suspend_reason
                    ? 'Your account has been suspended. Reason: ' . $user->suspend_reason
                    : 'Your account has been suspended.',
            ], 403);
        }

        // Update last login time
        $user->update([
            'last_login_at' => now(),
        ]);

        // Remove old tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'user_type' => $user->user_type,
                    'status' => $user->status,
                    'onboardingCompleted' => $user->onboardingCompleted,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {

        return response()->json([
            'success' => false,
            'message' => collect($e->errors())->flatten()->first(),
        ], 422);

    } catch (\Exception $e) {

        Log::error('Login Error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Something went wrong. Please try again later.',
        ], 500);
    }
}
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user'    => $request->user(),
        ], 200);
    }


    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ], 200);
    }


    public function forgotPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ], [
                'email.required' => 'Email field is required.',
                'email.email'    => 'Please enter a valid email address.',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not found.',
                ], 404);
            }

            $otp = random_int(1000, 9999);

            $user->update([
                'otp'           => $otp,
                'otp_expire_at' => now()->addMinutes(5),
            ]);

            SendOtpEmail::dispatch($user->id, 'forgot', $otp);

            return response()->json([
                'success' => true,
                'message' => 'OTP sent to your email for password reset.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Forgot Password Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again.',
            ], 500);
        }
    }


    public function verifyOtp(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'otp'   => 'required|digits:4',
            ], [
                'email.required' => 'Email field is required.',
                'email.email'    => 'Please enter a valid email address.',
                'otp.required'   => 'OTP field is required.',
                'otp.digits'     => 'OTP must be 4 digits.',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not found.',
                ], 404);
            }

            if (!$user->otp || !$user->otp_expire_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active OTP found. Please request a new OTP.',
                ], 400);
            }

            if (Carbon::parse($user->otp_expire_at)->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP has expired. Please request a new one.',
                ], 400);
            }

            if ((string) $user->otp !== (string) $request->otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP. Please try again.',
                ], 400);
            }

            $isRegisterFlow = is_null($user->email_verified_at);

            $user->update([
                'otp'               => null,
                'otp_expire_at'     => null,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            if ($isRegisterFlow) {
                $user = $user->fresh();

                if ($user->user_type === 'inspector') {
                    return response()->json([
                        'success' => true,
                        'message' => 'Email verified successfully. Your account is under admin review. Please wait for approval.',
                    ], 200);
                }

                $token = $user->createToken(config('auth.token_full_name', 'auth_token'))->plainTextToken;

                return response()->json([
                    'success'      => true,
                    'message'      => 'Email verified and logged in successfully.',
                    'access_token' => $token,
                    'token_type'   => 'Bearer',
                    'user'         => $user,
                ], 200);
            }

            Cache::put('password_reset_approved_' . $user->email, true, now()->addMinutes(15));

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully. You can now reset your password.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Verify OTP Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'OTP verification failed. Please try again.',
            ], 500);
        }
    }


    public function resendOtp(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ], [
                'email.required' => 'Email field is required.',
                'email.email'    => 'Please enter a valid email address.',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not found.',
                ], 404);
            }

            $otp = random_int(1000, 9999);

            $user->update([
                'otp'           => $otp,
                'otp_expire_at' => Carbon::now()->addMinutes(5),0


            ]);

            $mailType = is_null($user->email_verified_at) ? 'register' : 'forgot';
            SendOtpEmail::dispatch($user->id, $mailType, $otp);

            return response()->json([
                'success' => true,
                'message' => 'A new OTP has been sent to your email.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Resend OTP Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend OTP. Please try again.',
            ], 500);
        }
    }


    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'email'        => 'required|email|exists:users,email',
                'new_password' => [
                    'required',
                    'confirmed',
                    Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
                ],
            ], [
                'email.required'         => 'Email field is required.',
                'email.exists'           => 'Email not found.',
                'new_password.required'  => 'New password is required.',
                'new_password.confirmed' => 'New password confirmation does not match.',
            ]);

            $cacheKey = 'password_reset_approved_' . $request->email;

            if (!Cache::get($cacheKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired session. Please verify OTP again.',
                ], 400);
            }

            $user = User::where('email', $request->email)->first();

            $user->update([
                'password' => Hash::make($request->new_password),
            ]);

            Cache::forget($cacheKey);

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Reset Password Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password. Please try again.',
            ], 500);
        }
    }


    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required',
                'new_password'     => ['required', 'confirmed', Password::min(8)],
            ]);

            $user = $request->user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect.',
                ], 400);
            }

            $user->update([
                'password' => Hash::make($request->new_password),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Change Password Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password. Please try again.',
            ], 500);
        }
    }




//save device token
    public function saveFcmToken(Request $request)
{
    try {

        $request->validate([
            'fcm_token' => [
                'required',
                'string',

            ],
        ], [
            'fcm_token.required' => 'FCM token is required.',
            'fcm_token.string'   => 'Invalid FCM token.',

        ]);

        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $user->update([
            'fcm_token' => $request->fcm_token,
        ]);

        Log::info("Device token updated for user {$user->id}");

        return response()->json([
            'success' => true,
            'message' => 'Device token saved successfully.',
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {

        return response()->json([
            'success' => false,
            'message' => collect($e->errors())->flatten()->first(),
        ], 422);

    } catch (\Exception $e) {

        Log::error('Save Fcm Token Error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to save fcm token.',
        ], 500);
    }
}



//suspend reason
public function suspendUser(Request $request)
{
    $request->validate([
        'user_id' => 'required|exists:users,id',
        'suspend_reason' => 'required|string|max:1000',
    ]);

    $user = User::findOrFail($request->user_id);

    $user->update([
        'status' => 'suspended',
        'suspend_reason' => $request->suspend_reason,
    ]);

    Mail::raw(
        "Dear {$user->full_name},\n\n" .
        "Your account has been suspended.\n\n" .
        "Reason:\n{$request->suspend_reason}\n\n" .
        "If you believe this is a mistake, please contact our support team.",
        function ($message) use ($user) {
            $message->to($user->email)
                    ->subject('Account Suspended');
        }
    );

    return response()->json([
        'success' => true,
        'message' => 'User suspended successfully and email sent.',
    ]);
}



public function updateStatus(Request $request)
{
    $request->validate([
        'user_id' => 'required|exists:users,id',
        'status' => 'required|in:active,suspended',
    ]);

    $user = User::findOrFail($request->user_id);

    $user->update([
        'status' => $request->status,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'User status updated successfully.',
        'data' => $user,
    ]);
}
}
