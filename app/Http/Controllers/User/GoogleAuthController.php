<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class GoogleAuthController extends Controller
{
    public function tokenLogin(Request $request)
    {
        // 1. Validate request
        $request->validate([
            'id_token' => 'required|string',
        ]);

        $idToken = $request->id_token;

        // 2. Verify token with Google
        $response = Http::get("https://oauth2.googleapis.com/tokeninfo?id_token={$idToken}");

        if ($response->failed()) {
            return response()->json(['error' => 'Invalid Google token'], 401);
        }

        $googleUser = $response->json();

        // 3. Create or update user
        $user = User::updateOrCreate(
            ['email' => $googleUser['email']],
            [
                'full_name' => $googleUser['full_name'] ?? 'No Name',
                'email_verified_at' => now(),
                'password' => bcrypt(Str::random(16)),
                'profile_photo_path' => $googleUser['picture'] ?? null,
            ]
        );

        // 4. Create Sanctum token
        $token = $user->createToken('api-token')->plainTextToken;

        // 5. Return response
        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }
}
