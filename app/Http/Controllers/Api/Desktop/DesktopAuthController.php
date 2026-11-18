<?php

namespace App\Http\Controllers\Api\Desktop;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Desktop\DesktopAccess;
use App\Services\Desktop\DesktopSignIn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Sign-in for KLAES-CORE Survey Desktop (/api/desktop/v1/auth/*), the same checks as the web sign-in
 * (AuthenticatedSessionController::store): username + password, account active, email verified, then the sign-in code
 * when the account needs one (LoginOtpService). The result is a Sanctum token for that PC, ability desktop-api.
 */
class DesktopAuthController extends Controller
{
    public function __construct(private DesktopSignIn $signIn)
    {
    }

    public function login(Request $r): JsonResponse
    {
        $d = $r->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'device'   => 'nullable|string|max:200',
        ]);
        $provider = Auth::guard('web')->getProvider();
        $creds = ['username' => trim($d['username']), 'password' => $d['password']];
        $user = $provider->retrieveByCredentials($creds);
        if (! $user || ! $provider->validateCredentials($user, $creds)) {
            Log::info('Desktop sign-in refused: wrong username or password', ['username' => $creds['username'], 'ip' => $r->ip()]);

            return response()->json(['message' => 'The username or password is not correct.'], 422);
        }
        if ($refusal = $this->refusal($user)) {
            return response()->json(['message' => $refusal], 403);
        }

        $otp = $this->signIn->otp();
        if (! $otp->requiredFor($user)) {
            // As on the web: an account without a confirmed mobile number signs in with the password alone.
            return response()->json($this->issue($user, $d['device'] ?? null, $r));
        }
        $challenge = $this->signIn->attach($r);
        $res = $otp->begin($r, $user, true);
        $this->signIn->save($r, $challenge);

        // The challenge goes back even when the SMS did not go: the app then offers "Send by email instead" (resend).
        return response()->json(['challenge' => $challenge, 'message' => $res['message'], 'sent' => $res['sent']]);
    }

    /** Send the code again, by SMS or to the backup email (the web's "send by email instead"). */
    public function resend(Request $r): JsonResponse
    {
        $d = $r->validate(['challenge' => 'required|string|max:100', 'channel' => 'nullable|in:sms,email']);
        if (! $this->signIn->attach($r, $d['challenge'])) {
            return response()->json(['message' => 'Your sign-in has expired. Enter your username and password again.'], 410);
        }
        $res = $this->signIn->otp()->send($r, $d['channel'] ?? 'sms');
        $this->signIn->save($r, $d['challenge']);

        return response()->json(['message' => $res['message'], 'sent' => $res['sent']], $res['sent'] ? 200 : 429);
    }

    public function verify(Request $r): JsonResponse
    {
        $d = $r->validate(['challenge' => 'required|string|max:100', 'code' => 'required|string|max:20', 'device' => 'nullable|string|max:200']);
        if (! $this->signIn->attach($r, $d['challenge'])) {
            return response()->json(['message' => 'Your sign-in has expired. Enter your username and password again.'], 410);
        }
        $res = $this->signIn->otp()->verify($r, $d['code']);
        if (! $res['verified']) {
            $this->signIn->save($r, $d['challenge']);

            return response()->json(['message' => $res['message']], 422);
        }
        $this->signIn->forget($d['challenge']);
        $user = $res['user'];
        if ($refusal = $this->refusal($user)) {
            return response()->json(['message' => $refusal], 403);
        }

        return response()->json($this->issue($user, $d['device'] ?? null, $r));
    }

    public function logout(Request $r): JsonResponse
    {
        $r->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out on this PC.']);
    }

    public function me(Request $r): JsonResponse
    {
        return response()->json(['data' => $this->userData($r->user())]);
    }

    private function refusal(User $user): ?string
    {
        if ((string) $user->is_active === '0') {
            return 'Your account is temporarily inactive. Please contact your administrator to reactivate your account.';
        }
        if (empty($user->email_verified_at)) {
            return 'Verification required: please verify your account by email on the web before using the desktop app.';
        }
        if (! DesktopAccess::allowed($user)) {
            return 'Your account does not have Survey Plan Management, which the desktop app needs. Ask your administrator.';
        }

        return null;
    }

    /** One token per PC: signing in again on the same PC replaces its old token. */
    private function issue(User $user, ?string $device, Request $r): array
    {
        $name = 'desktop: ' . mb_substr(trim((string) $device) ?: 'KLAES-CORE Survey Desktop', 0, 180);
        $user->tokens()->where('name', $name)->delete();
        $token = $user->createToken($name, [DesktopAccess::ABILITY])->plainTextToken;
        Log::info('Desktop sign-in', ['user' => $user->id, 'device' => $name, 'ip' => $r->ip(), 'version' => $r->header('X-Client-Version')]);

        return ['token' => $token, 'token_type' => 'Bearer', 'expires_in_days' => DesktopAccess::TOKEN_DAYS, 'user' => $this->userData($user)];
    }

    private function userData(User $user): array
    {
        $name = trim(implode(' ', array_filter([$user->first_name ?? null, $user->last_name ?? null]))) ?: ($user->name ?? $user->username);

        return ['id' => $user->id, 'name' => $name, 'username' => $user->username, 'roles' => DesktopAccess::roles($user)];
    }
}
