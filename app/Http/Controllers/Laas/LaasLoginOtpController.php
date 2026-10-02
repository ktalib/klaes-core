<?php

namespace App\Http\Controllers\Laas;

use App\Http\Controllers\Controller;
use App\Services\Laas\LaasLoginOtpService;
use App\Services\PhoneOtpService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The LAAS Portal sign-in code screen: steps 2 and 3 of signing in
 * (see LaasLoginOtpService).
 *
 * Every action works on the PENDING sign-in held in this browser's session,
 * never on an applicant id from the request, so the screen cannot be pointed at
 * somebody else's account.
 */
class LaasLoginOtpController extends Controller
{
    public function __construct(
        private LaasLoginOtpService $otp,
        private PhoneOtpService $phoneOtp,
    ) {
    }

    public function show(Request $request)
    {
        // Read before anything can lapse the pending state: pendingApplicant()
        // clears it when the challenge has timed out, and with it the record of
        // which of the two front doors this sign-in started at.
        $portal = $this->otp->portal($request);

        $applicant = $this->otp->pendingApplicant($request);
        if (!$applicant) {
            return $this->expired($request, null, $portal);
        }

        // The account's code (shared by every session of the account).
        $last = $this->otp->lastSend($applicant);

        // Seconds the current code has left, for the countdown under the boxes.
        $expiresIn = empty($last['hash'])
            ? 0
            : max(0, $this->otp->ttlMinutes() * 60 - (now()->timestamp - (int) $last['sent_at']));

        return view('laas.auth.login-otp', [
            'expiresIn' => $expiresIn,
            // The networks hold SMS 19:45–08:00 Lagos time; only then is the
            // email route worth pointing at in the hint text.
            'quietHours' => $this->otp->inQuietHours(),
            'maskedPhone' => ($phone = $this->otp->phoneFor($applicant))
                ? $this->phoneOtp->mask($phone)
                : null,
            'maskedEmail' => ($email = $this->otp->emailFor($applicant))
                ? $this->phoneOtp->maskEmail($email)
                : null,
            'channel' => $last['channel'] ?? LaasLoginOtpService::CHANNEL_SMS,
            'retryAfter' => $this->otp->cooldownRemaining($request),
            'ttlMinutes' => $this->otp->ttlMinutes(),
            'codeLength' => max(4, min(10, (int) config('phone_verification.code_length', 6))),
            'portal' => $portal,
        ]);
    }

    public function verify(Request $request)
    {
        $portal = $this->otp->portal($request);

        $request->validate(
            ['code' => ['required', 'string', 'max:10']],
            ['code.required' => 'Enter the code that was sent to you.']
        );

        $result = $this->otp->verify($request, (string) $request->input('code'));

        if (!$result['verified']) {
            // A lapsed sign-in has nothing left to type a code into.
            if (!$this->otp->pendingApplicant($request)) {
                return $this->expired($request, $result['message'], $portal);
            }

            return redirect()->route('laas.login.otp')->with('error', $result['message']);
        }

        return app(LaasAuthController::class)
            ->completeLogin($request, $result['applicant'], $result['remember']);
    }

    public function resend(Request $request)
    {
        $portal = $this->otp->portal($request);

        $validated = $request->validate([
            'channel' => ['required', Rule::in([LaasLoginOtpService::CHANNEL_SMS, LaasLoginOtpService::CHANNEL_EMAIL])],
        ]);

        if (!$this->otp->pendingApplicant($request)) {
            return $this->expired($request, null, $portal);
        }

        $result = $this->otp->send($request, $validated['channel']);

        return redirect()->route('laas.login.otp')
            ->with($result['sent'] ? 'status' : 'error', $result['message']);
    }

    public function cancel(Request $request)
    {
        $portal = $this->otp->portal($request);
        $this->otp->clear($request);

        return redirect()->route($portal === LaasLoginOtpService::PORTAL_ALAES
            ? 'alaes_portal.login'
            : 'laas.login');
    }

    /**
     * Back to whichever sign-in screen this started at.
     *
     * $portal is passed in by every caller, read at the top of the action:
     * pendingApplicant() and verify() both CLEAR a lapsed sign-in, and once it
     * is gone there is nothing left to say which of the two front doors the
     * applicant came through — every expiry would land them at the LAAS one.
     */
    private function expired(Request $request, ?string $message = null, ?string $portal = null)
    {
        $portal ??= $this->otp->portal($request);
        $this->otp->clear($request);

        return redirect()
            ->route($portal === LaasLoginOtpService::PORTAL_ALAES ? 'alaes_portal.login' : 'laas.login')
            ->with('error', $message ?: 'Your sign-in has expired. Enter your phone number and password again.');
    }
}
