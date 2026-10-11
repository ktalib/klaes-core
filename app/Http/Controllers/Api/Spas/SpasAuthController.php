<?php

namespace App\Http\Controllers\Api\Spas;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Token auth for the SPAS offline mobile app.
 *
 * The existing SPAS Mobile web page authenticates with a Laravel session
 * cookie, which the offline app cannot use: it must make authenticated calls
 * long after the browser session would have expired, and it must survive being
 * offline for hours or days without being logged out.
 *
 * Mirrors the Sanctum pattern already proven in
 * Api\Mobile\MobileAuthController, differing only in the token ability
 * ('spas-mobile') so a SPAS device token cannot be replayed against the
 * React Native app's endpoints.
 *
 * @see docs/plans/SPAS_MOBILE_OFFLINE_CAPACITOR_SYNC_PLAN.md §7
 */
class SpasAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier'  => 'required|string', // username, email or phone
            'password'    => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = $this->findUserByIdentifier($data['identifier']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid credentials supplied.',
            ], 401);
        }

        if ((string) $user->is_active === '0') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Account is disabled. Contact support.',
            ], 423);
        }

        $deviceName = $data['device_name'] ?? 'spas-mobile';

        // One token per device name keeps logout predictable and stops a
        // surveyor's old handset from holding a live token after a swap.
        $user->tokens()->where('name', $deviceName)->delete();

        return response()->json([
            'status'     => 'success',
            'token_type' => 'Bearer',
            'token'      => $user->createToken($deviceName, ['spas-mobile'])->plainTextToken,
            'user'       => $this->profile($user),
            // The device stamps rows with its own user, but the server remains
            // the authority on surveyor_id/created_by. Returned so the app can
            // show "created by me" locally before a push confirms it.
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** The signed-in user, for the app's sidebar. Refreshed on every sync. */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'user' => $this->profile($request->user())]);
    }

    private function profile(User $user): array
    {
        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values()->all() : [];

        return [
            'id'       => $user->id,
            'name'     => $user->name ?? trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'username' => $user->username,
            'email'    => $user->email,
            'role'     => (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) ? 'Super Admin' : ($roles[0] ?? null),
            // Sent as data, not a link, so the phone keeps it offline.
            'photo'    => $this->photoThumb($user),
        ];
    }

    /** The profile photo as a small square JPEG data URL, or null. Never throws. */
    private function photoThumb(User $user): ?string
    {
        try {
            $path = \App\Support\UserPhoto::existingPath($user->profile, $user->passport_photo_path);
            if (! $path) {
                return null;
            }
            $img = @imagecreatefromstring(\Illuminate\Support\Facades\Storage::disk('public')->get($path));
            if (! $img) {
                return null;
            }
            $w = imagesx($img);
            $h = imagesy($img);
            $side = min($w, $h);                       // square crop from the centre
            $out = imagecreatetruecolor(256, 256);
            imagecopyresampled($out, $img, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), 256, 256, $side, $side);
            ob_start();
            imagejpeg($out, null, 82);
            $jpeg = ob_get_clean();
            imagedestroy($img);
            imagedestroy($out);

            return 'data:image/jpeg;base64,'.base64_encode($jpeg);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['status' => 'success', 'message' => 'Logged out successfully.']);
    }

    private function findUserByIdentifier(string $identifier): ?User
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return User::where('email', $identifier)->first();
        }

        return User::where('username', $identifier)
            ->orWhere('phone_number', $identifier)
            ->first();
    }
}
