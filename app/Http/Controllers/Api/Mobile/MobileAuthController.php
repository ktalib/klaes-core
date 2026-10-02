<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MobileAuthController extends Controller
{
    /**
     * Handle a login request from the React Native client.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => 'required|string', // username, email or phone
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = $this->findUserByIdentifier($data['identifier']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return $this->invalidCredentialsResponse();
        }

        if ((string) $user->is_active === '0') {
            return response()->json([
                'status' => 'error',
                'message' => 'Account is disabled. Contact support.',
            ], 423);
        }

        $deviceName = $data['device_name'] ?? 'react-mobile-app';

        // Keep one token per device name to make logout predictable
        $user->tokens()->where('name', $deviceName)->delete();

        $plainTextToken = $user->createToken($deviceName, ['mobile-api'])->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token_type' => 'Bearer',
            'token' => $plainTextToken,
            'expires_in_minutes' => config('sanctum.expiration'),
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'profile' => $user->profile,
            ],
        ]);
    }

    /**
     * Invalidate the current Sanctum token.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully.',
        ]);
    }

    private function findUserByIdentifier(string $identifier): ?User
    {
        $query = User::query();

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $query->where('email', $identifier)->first();
        }

        return $query
            ->where('username', $identifier)
            ->orWhere('phone_number', $identifier)
            ->first();
    }

    private function invalidCredentialsResponse(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid credentials supplied.',
        ], 401);
    }
}
