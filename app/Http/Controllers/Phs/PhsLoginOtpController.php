<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Services\Phs\PhsLoginOtpService;
use App\Services\PhoneOtpService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The PHS Portal sign-in code screen: steps 2 and 3 of signing in
 * (see PhsLoginOtpService).
 *
 * Every action works on the PENDING sign-in held in this browser's session,
 * never on a member id from the request, so the screen cannot be pointed at
 * somebody else's account.
 */
class PhsLoginOtpController extends Controller
{
    public function __construct(
        private PhsLoginOtpService $otp,
        private PhoneOtpService $phoneOtp,
    ) {
    }

    public function show(Request $request)
    {
        $member = $this->otp->pendingMember($request);
        if (!$member) {
            return $this->expired($request);
        }

        $last = $this->otp->lastSend($member);

        // Seconds the current code has left, for the countdown under the boxes.
        $expiresIn = empty($last['hash'])
            ? 0
            : max(0, $this->otp->ttlMinutes() * 60 - (now()->timestamp - (int) $last['sent_at']));

        return view('phs.auth.login-otp', [
            'expiresIn' => $expiresIn,
            'maskedEmail' => ($email = $this->otp->emailFor($member))
                ? $this->phoneOtp->maskEmail($email)
                : null,
            // Only ever a number the member confirmed by code; see phoneFor().
            'maskedPhone' => ($phone = $this->otp->phoneFor($member))
                ? $this->phoneOtp->mask($phone)
                : null,
            'channel' => $last['channel'] ?? PhsLoginOtpService::CHANNEL_EMAIL,
            // The networks hold SMS 19:45-08:00 Lagos time; only then is it
            // worth warning that a text asked for now arrives in the morning.
            'quietHours' => $this->otp->inQuietHours(),
            'organization' => $member->institution->name ?? null,
            'retryAfter' => $this->otp->cooldownRemaining($request),
            'ttlMinutes' => $this->otp->ttlMinutes(),
            'codeLength' => max(4, min(10, (int) config('phone_verification.code_length', 6))),
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate(
            ['code' => ['required', 'string', 'max:10']],
            ['code.required' => 'Enter the code that was sent to you.']
        );

        $result = $this->otp->verify($request, (string) $request->input('code'));

        if (!$result['verified']) {
            // A lapsed sign-in has nothing left to type a code into.
            if (!$this->otp->pendingMember($request)) {
                return $this->expired($request, $result['message']);
            }

            return redirect()->route('phs.login.otp')->with('error', $result['message']);
        }

        return app(PhsAuthController::class)
            ->completeLogin($request, $result['member'], $result['remember']);
    }

    public function resend(Request $request)
    {
        $validated = $request->validate([
            'channel' => ['nullable', Rule::in([PhsLoginOtpService::CHANNEL_EMAIL, PhsLoginOtpService::CHANNEL_SMS])],
        ]);

        if (!$this->otp->pendingMember($request)) {
            return $this->expired($request);
        }

        $result = $this->otp->send($request, $validated['channel'] ?? null);

        return redirect()->route('phs.login.otp')
            ->with($result['sent'] ? 'status' : 'error', $result['message']);
    }

    public function cancel(Request $request)
    {
        $this->otp->clear($request);

        return redirect()->route('phs.login');
    }

    private function expired(Request $request, ?string $message = null)
    {
        $this->otp->clear($request);

        return redirect()->route('phs.login')
            ->with('error', $message ?: 'Your sign-in has expired. Enter your username and password again.');
    }
}
