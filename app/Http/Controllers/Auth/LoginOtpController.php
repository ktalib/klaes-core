<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\LoginOtpService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The sign-in code screen: steps 2 and 3 of signing in (see LoginOtpService).
 *
 * Every action works on the PENDING sign-in held in this browser's session, never on a
 * user id from the request, so the screen cannot be pointed at somebody else's account.
 */
class LoginOtpController extends Controller
{
    public function __construct(private LoginOtpService $otp)
    {
    }

    public function show(Request $request)
    {
        $user = $this->otp->pendingUser($request);
        if (!$user) {
            return redirect()->route('login')->with('error', __('Your sign-in has expired. Enter your username and password again.'));
        }

        // The account's code (shared by every session of the account).
        $last = $this->otp->lastSend($user);

        // Seconds the current code has left, for the countdown under the boxes.
        $expiresIn = empty($last['hash'])
            ? 0
            : max(0, $this->otp->ttlMinutes() * 60 - (now()->timestamp - (int) $last['sent_at']));

        return view('auth.login-otp', [
            'expiresIn' => $expiresIn,
            // The networks hold SMS 19:45–08:00 Lagos time; only then is the email route worth pointing at.
            'quietHours' => $this->otp->inQuietHours(),
            'maskedPhone' => app(\App\Services\PhoneOtpService::class)->mask($this->otp->phoneFor($user)),
            'maskedEmail' => ($email = $this->otp->emailFor($user))
                ? app(\App\Services\PhoneOtpService::class)->maskEmail($email)
                : null,
            'channel' => $last['channel'] ?? LoginOtpService::CHANNEL_SMS,
            'retryAfter' => $this->otp->cooldownRemaining($request),
            'ttlMinutes' => $this->otp->ttlMinutes(),
            'codeLength' => max(4, min(10, (int) config('phone_verification.code_length', 6))),
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate(
            ['code' => ['required', 'string', 'max:10']],
            ['code.required' => __('Enter the code that was sent to you.')]
        );

        $result = $this->otp->verify($request, (string) $request->input('code'));

        if (!$result['verified']) {
            // A lapsed sign-in has nothing left to type a code into.
            if (!$this->otp->pendingUser($request)) {
                return redirect()->route('login')->with('error', $result['message']);
            }

            return redirect()->route('login.otp')->with('error', $result['message']);
        }

        return app(AuthenticatedSessionController::class)
            ->completeLogin($request, $result['user'], $result['remember']);
    }

    public function resend(Request $request)
    {
        $validated = $request->validate([
            'channel' => ['required', Rule::in([LoginOtpService::CHANNEL_SMS, LoginOtpService::CHANNEL_EMAIL])],
        ]);

        if (!$this->otp->pendingUser($request)) {
            return redirect()->route('login')->with('error', __('Your sign-in has expired. Enter your username and password again.'));
        }

        $result = $this->otp->send($request, $validated['channel']);

        return redirect()->route('login.otp')
            ->with($result['sent'] ? 'success' : 'error', $result['message']);
    }

    public function cancel(Request $request)
    {
        $this->otp->clear($request);

        return redirect()->route('login');
    }
}
