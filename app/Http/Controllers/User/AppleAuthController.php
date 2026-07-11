<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppleAuthController extends Controller
{
    public function login(Request $request)
    {
        // Validate request
        $request->validate([
            'fcm_token' => 'required|string',
            'full_name'        => 'nullable|string',
            'email'       => 'nullable|email',
        ]);

        try {
            // 🔹 Dummy token for local testing
            if ($request->apple_token === 'dummy_apple_token_9876543210_test_value') {
                $appleUserId = 'dummy_apple_user_12345';
                $email       = $request->email ?? 'dummy@example.com';
                $full_name        = $request->full_name ?? 'Dummy User';
            } else {
                // 🔹 Real Apple token verify
                $appleUser   = $this->verifyAppleToken($request->apple_token);
                $appleUserId = $appleUser->sub;
                $email       = $request->email ?? null;
                $full_name   = $request->full_name ?? 'Apple User';
            }

            // 🔹 Fallback email if null (Apple ID based)
            if (!$email) {
                $email = "apple_{$appleUserId}@temp.local";
            }

            // 🔹 Find or create user by apple_id
            $user = User::firstOrCreate(
                ['apple_id' => $appleUserId],
                [
                    'password' => bcrypt(Str::random(32)),
                    'full_name'     => $full_name,
                    'email'    => $email,
                ]
            );

            // 🔹 Issue Sanctum token
            $token = $user->createToken('flutter-app')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'user'   => $user,
                'token'  => $token,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid Apple identity token: ' . $e->getMessage()
            ], 401);
        }
    }

    /**
     * Verify Apple identity token using Apple's public keys
     */
    private function verifyAppleToken(string $token)
    {
        $appleKeys = json_decode(file_get_contents('https://appleid.apple.com/auth/keys'), true);

        try {
            $keys = JWK::parseKeySet($appleKeys);
            return JWT::decode($token, $keys);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
