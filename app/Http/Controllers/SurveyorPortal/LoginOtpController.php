<?php

namespace App\Http\Controllers\SurveyorPortal;

use App\Http\Controllers\Controller;
use App\Services\PhoneOtpService;
use App\Services\Survey\SurveyorLoginOtpService;
use App\Services\Survey\SurveyorSecurityQuestions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The MLPP-SG sign-in code screen (user, 2026-11-10): steps 2 and 3 of signing in, see
 * SurveyorLoginOtpService. Every action works on the PENDING sign-in in this browser's
 * session, never on an account id from the request.
 */
class LoginOtpController extends Controller
{
    public function __construct(
        private SurveyorLoginOtpService $otp,
        private PhoneOtpService $phoneOtp,
        private SurveyorSecurityQuestions $questions,
    ) {
    }

    public function show(Request $request)
    {
        $account = $this->otp->pendingAccount($request);
        if (!$account) {
            return $this->expired();
        }

        $last = $this->otp->lastSend($account);
        $expiresIn = empty($last['hash'])
            ? 0
            : max(0, $this->otp->ttlMinutes() * 60 - (now()->timestamp - (int) $last['sent_at']));

        return view('surveyor_portal.auth.login_otp', [
            'expiresIn' => $expiresIn,
            // Seconds until the security-question fallback opens; null when the account has none set.
            'questionsOpenIn' => $this->questions->enabled() && $this->questions->hasAnswers($account)
                ? $this->questions->fallbackOpensIn((int) ($this->otp->pending($request)['started_at'] ?? 0))
                : null,
            'quietHours' => $this->otp->inQuietHours(),
            'maskedPhone' => ($phone = $this->otp->phoneFor($account)) ? $this->phoneOtp->mask($phone) : null,
            'maskedEmail' => ($email = $this->otp->emailFor($account)) ? $this->phoneOtp->maskEmail($email) : null,
            'channel' => $last['channel'] ?? SurveyorLoginOtpService::CHANNEL_SMS,
            'retryAfter' => $this->otp->cooldownRemaining($request),
            'codeLength' => max(4, min(10, (int) config('phone_verification.code_length', 6))),
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:10']], ['code.required' => 'Enter the code that was sent to you.']);

        $result = $this->otp->verify($request, (string) $request->input('code'));
        if (!$result['verified']) {
            return $this->otp->pendingAccount($request)
                ? redirect()->route('surveyor-portal.login.otp')->with('error', $result['message'])
                : $this->expired($result['message']);
        }

        return app(AuthController::class)->completeLogin($request, $result['account'], $result['remember']);
    }

    public function resend(Request $request)
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in([SurveyorLoginOtpService::CHANNEL_SMS, SurveyorLoginOtpService::CHANNEL_EMAIL])],
        ]);

        if (!$this->otp->pendingAccount($request)) {
            return $this->expired();
        }

        $result = $this->otp->send($request, $data['channel']);

        return redirect()->route('surveyor-portal.login.otp')->with($result['sent'] ? 'status' : 'error', $result['message']);
    }

    public function cancel(Request $request)
    {
        $this->otp->clear($request);

        return redirect()->route('surveyor-portal.login');
    }

    private function expired(?string $message = null)
    {
        return redirect()->route('surveyor-portal.login')
            ->withErrors(['username' => $message ?: 'Your sign-in has expired. Enter your username and password again.']);
    }
}
