<?php

namespace App\Http\Controllers;

use App\Services\PhoneOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * The two endpoints behind the account-confirmation card: ask for a code, hand one back.
 *
 * Both stay reachable while RequirePhoneVerification has the rest of the system locked —
 * they are the only way out of it — so both are deliberately narrow: they act on the
 * SIGNED-IN user and nobody else. There is no user_id parameter to be tampered with,
 * because a request that could verify another account would be a way of switching the
 * gate off for somebody else's login.
 *
 * The code travels by email (the default) or by SMS. Which one is a parameter rather
 * than a second pair of endpoints: the two differ only in where the code is posted, and
 * a second /send would be a second place for the "who is this acting on?" rule to be
 * got wrong.
 */
class PhoneVerificationController extends Controller
{
    public function __construct(private PhoneOtpService $otp)
    {
    }

    /**
     * Send a code to the address the user confirmed or corrected.
     *
     * The address is accepted from the request rather than read off the row on purpose:
     * the card exists precisely because the stored value may be wrong — a placeholder
     * email at example.org, a number that belongs to somebody who left — and a user who
     * cannot correct it there has no way through at all.
     *
     * `target` carries whichever the chosen channel needs. `email` and `phone` are still
     * accepted under their own names so a cached copy of the old card keeps working
     * during a deployment. The combined card submits both contacts with channel=both.
     */
    public function send(Request $request): JsonResponse
    {
        // Spaces, dashes and brackets are how people write a number down, not part
        // of it. Stripping them BEFORE the rule runs means "0703 958 6723" is
        // accepted and "234..." is still refused, which is the point of the rule.
        if ($request->has('phone')) {
            $request->merge(['phone' => preg_replace('/\D+/', '', (string) $request->input('phone'))]);
        }

        $validated = $request->validate(
            [
                'channel' => ['nullable', 'string', Rule::in([PhoneOtpService::CHANNEL_EMAIL, PhoneOtpService::CHANNEL_SMS, PhoneOtpService::CHANNEL_BOTH])],
                'target' => ['nullable', 'string', 'max:191'],
                // The card's own two fields. A local mobile number is eleven digits
                // beginning with a zero and nothing else: the ten-digit and 234-
                // prefixed shapes the SMS normaliser also accepts are not what a
                // member of staff reads off their handset, and letting them through
                // here only produces numbers nobody can check by eye.
                'email' => ['required_if:channel,both', 'nullable', 'string', 'max:191', 'email:filter'],
                'phone' => ['required_if:channel,both', 'nullable', 'string', 'regex:/^0\d{10}$/'],
            ],
            [
                'channel.in' => __('That is not a way of sending a code.'),
                'phone.required_if' => __('Enter your mobile number.'),
                'phone.regex' => __('Enter an 11-digit mobile number starting with 0, such as 08012345678.'),
                'target.max' => __('That is too long to be an email address or a phone number.'),
                'email.required_if' => __('Enter your email address.'),
                'email.email' => __('Enter a complete email address, such as musa@gmail.com.'),
                'email.max' => __('That is too long to be an email address.'),
            ]
        );

        $user = Auth::user();
        $channel = $validated['channel'] ?? null;

        // Whichever field the card used. The service does the real validation — it is
        // the only place that knows what a usable Nigerian number or a live mailbox is.
        $target = $validated['target']
            ?? ($channel === PhoneOtpService::CHANNEL_SMS ? ($validated['phone'] ?? null) : ($validated['email'] ?? null))
            ?? $validated['phone']
            ?? $validated['email']
            ?? null;

        $result = $channel === PhoneOtpService::CHANNEL_BOTH
            ? $this->otp->sendToBoth($user, $validated['email'], $validated['phone'])
            : $this->otp->sendCode($user, $target, $channel);

        return response()->json([
            'success' => $result['sent'],
            'message' => $result['message'],
            'data' => [
                'channel' => $result['channel'],
                'masked' => $result['masked'],
                // Seconds the button stays disabled for. On a failure this is the
                // cooldown that now applies anyway — the code was written to the row
                // and the wallet may have been charged, whatever the gateway said.
                'retryAfter' => $result['retry_after'],
                'expiresIn' => $result['expires_in'],
            ],
        ], $result['sent'] ? 200 : 422);
    }

    /**
     * Check the code and, if it matches, let the user into the system.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate(
            ['code' => ['required', 'string', 'max:10']],
            ['code.required' => __('Enter the code that was sent to you.')]
        );

        $user = Auth::user();
        $result = $this->otp->verifyCode($user, $validated['code']);

        return response()->json([
            'success' => $result['verified'],
            'message' => $result['message'],
            'data' => [
                'attemptsLeft' => $result['attempts_left'],
                // The client reloads on true, so the card, the sidebar lock and the
                // banner all clear at once instead of at the next navigation.
                'verified' => $result['verified'],
            ],
        ], $result['verified'] ? 200 : 422);
    }
}
