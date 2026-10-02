<?php

namespace App\Http\Controllers\Laas;

use App\Http\Controllers\Controller;
use App\Models\Laas\LaasApplicant;
use App\Models\Laas\LaasApplication;
use App\Services\BulkSmsNgService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The applicant's own account settings.
 *
 * Three things happen here, and only one of them is complicated.
 *
 * Name, email, NIN and address are ordinary edits. They deliberately do NOT
 * propagate to applications already submitted: an application is a record of
 * what was claimed at the time, and rewriting it later would falsify it.
 *
 * The phone number is the exception, because it is not a claim — it is a
 * delivery address. LaasNotificationService texts
 * `laas_applications.applicant_phone`, the snapshot frozen at submission, not
 * the account. So changing the account phone WITHOUT carrying it across would
 * leave an applicant signed in on a new number while every workflow SMS kept
 * going to the old one. It is carried across, but only to applications still in
 * flight (see propagatePhone).
 *
 * And because it is the delivery address, a change is proved before it is
 * applied: a code goes to the NEW number, which is the only thing that
 * demonstrates the applicant can actually receive messages there.
 */
class LaasProfileController extends Controller
{
    /** Gateway status from the last code we tried to send, for the error text. */
    private ?string $lastSmsCode = null;

    public function __construct(private BulkSmsNgService $sms)
    {
    }

    public function show()
    {
        $applicant = Auth::guard('laas')->user();

        return view('laas.profile', [
            'applicant'     => $applicant,
            'unreadUpdates' => 0,
        ]);
    }

    /** Name, email, NIN, address. Applied immediately. */
    public function updateDetails(Request $request)
    {
        $applicant = Auth::guard('laas')->user();

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:200'],
            'email'   => ['required', 'email', 'max:150'],
            'nin'     => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        // Checked by hand rather than with the `unique` rule: the table is on
        // the sqlsrv connection, and the applicant's own row must be excluded.
        $taken = LaasApplicant::where('email', $data['email'])
            ->where('id', '!=', $applicant->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'email' => 'Another account already uses this email address.',
            ]);
        }

        $applicant->fill($data)->save();

        return back()->with('status', 'Your details have been saved.');
    }

    /**
     * Stage a phone change and text a code to the new number.
     *
     * Nothing about the account changes here. The old number keeps receiving
     * everything until the code is confirmed, so an applicant who mistypes is
     * never cut off — they simply never confirm, and the change lapses.
     */
    public function requestPhoneChange(Request $request)
    {
        $applicant = Auth::guard('laas')->user();

        $request->validate([
            'phone'    => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
        ]);

        if (!Hash::check($request->input('password'), $applicant->password)) {
            throw ValidationException::withMessages([
                'password' => 'That password is not correct.',
            ]);
        }

        $phone = LaasApplicant::normalizePhone($request->input('phone'));

        if (!$phone) {
            throw ValidationException::withMessages([
                'phone' => 'Enter a valid Nigerian phone number, e.g. 08031234567.',
            ]);
        }

        if ($phone === $applicant->phone) {
            throw ValidationException::withMessages([
                'phone' => 'That is already the number on your account.',
            ]);
        }

        if (LaasApplicant::where('phone', $phone)->where('id', '!=', $applicant->id)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'Another account already uses this phone number.',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        $applicant->forceFill([
            'pending_phone'                => $phone,
            'verification_code'            => $code,
            'verification_code_expires_at' => now()->addMinutes(LaasApplicant::OTP_TTL_MINUTES),
            'verification_attempts'        => 0,
        ])->save();

        $sent = $this->sendCode($phone, $code, $applicant);

        if (!$sent) {
            // Leave the change staged — the applicant can ask for another code
            // — but say plainly that nothing arrived, rather than leaving them
            // waiting for a message that was never delivered. The wording comes
            // from the gateway's own status: telling someone to "check the
            // number" when the provider refused our TEXT sends them hunting for
            // a fault that is ours.
            return back()->with('error', $this->sendFailureMessage())->withInput();
        }

        return back()->with('status', 'We sent a 6-digit code to ' . $phone . '. Enter it below to confirm the change.');
    }

    /** Confirm the staged number and carry it into applications still in flight. */
    public function confirmPhoneChange(Request $request)
    {
        $applicant = Auth::guard('laas')->user();

        $request->validate([
            'code' => ['required', 'string', 'max:10'],
        ]);

        if (empty($applicant->pending_phone) || empty($applicant->verification_code)) {
            return back()->with('error', 'There is no phone change waiting to be confirmed.');
        }

        if ($applicant->verification_code_expires_at === null || $applicant->verification_code_expires_at->isPast()) {
            $applicant->clearPhoneChange();

            return back()->with('error', 'That code has expired. Please request a new one.');
        }

        if ($applicant->verification_attempts >= LaasApplicant::OTP_MAX_ATTEMPTS) {
            $applicant->clearPhoneChange();

            return back()->with('error', 'Too many incorrect codes. Please request a new one.');
        }

        if (!hash_equals((string) $applicant->verification_code, trim($request->input('code')))) {
            $applicant->increment('verification_attempts');
            $left = max(0, LaasApplicant::OTP_MAX_ATTEMPTS - $applicant->verification_attempts);

            throw ValidationException::withMessages([
                'code' => "That code is not correct. {$left} attempt(s) left.",
            ]);
        }

        $oldPhone = $applicant->phone;
        $newPhone = $applicant->pending_phone;

        // Re-check uniqueness at the moment of commit: the code is valid for ten
        // minutes, and another account could have claimed the number in between.
        if (LaasApplicant::where('phone', $newPhone)->where('id', '!=', $applicant->id)->exists()) {
            $applicant->clearPhoneChange();

            return back()->with('error', 'Another account claimed that phone number. Please try a different one.');
        }

        $moved = DB::connection('sqlsrv')->transaction(function () use ($applicant, $newPhone) {
            $applicant->forceFill([
                'phone'                        => $newPhone,
                'phone_verified_at'            => now(),
                'pending_phone'                => null,
                'verification_code'            => null,
                'verification_code_expires_at' => null,
                'verification_attempts'        => 0,
            ])->save();

            return $this->propagatePhone($applicant, $newPhone);
        });

        Log::info('LAAS: applicant phone changed', [
            'applicant_id'         => $applicant->id,
            'from'                 => LaasApplicant::maskPhone($oldPhone),
            'to'                   => LaasApplicant::maskPhone($newPhone),
            'applications_updated' => $moved,
        ]);

        $note = $moved > 0
            ? " Updates for {$moved} application(s) still in progress will now go to this number."
            : '';

        return back()->with('status', 'Your phone number has been updated.' . $note);
    }

    public function cancelPhoneChange()
    {
        Auth::guard('laas')->user()->clearPhoneChange();

        return back()->with('status', 'Phone change cancelled. Your number is unchanged.');
    }

    public function updatePassword(Request $request)
    {
        $applicant = Auth::guard('laas')->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($request->input('current_password'), $applicant->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That password is not correct.',
            ]);
        }

        $applicant->forceFill([
            'password' => Hash::make($request->input('password')),
        ])->save();

        return back()->with('status', 'Your password has been changed.');
    }

    /**
     * Carry a new phone onto applications that have not finished.
     *
     * Scoped to in-flight work on purpose. A signed RoFO or a declined
     * application will never generate another message, and its stored phone is
     * part of the record of how the applicant was contacted at the time —
     * rewriting it would destroy that history for no benefit.
     *
     * @return int number of applications updated
     */
    private function propagatePhone(LaasApplicant $applicant, string $phone): int
    {
        return LaasApplication::where('laas_applicant_id', $applicant->id)
            ->whereNotIn('stage', [
                LaasApplication::STAGE_ROFO_SIGNED,
                LaasApplication::STAGE_REJECTED,
            ])
            ->update(['applicant_phone' => $phone, 'updated_at' => now()]);
    }

    /**
     * Text the confirmation code, working down from the friendliest wording to
     * the barest one the gateway will accept.
     *
     * Sent through Bulk-SMS.ng, whose promotional route is the only one enabled
     * on this account. Promotional routes are exactly where gateways filter on
     * wording, and the phrasings a one-time passcode normally uses — "code",
     * "do not share", "if you did not request this" — are what such filters are
     * tuned to catch. BetaSMS refused this very message before the switch.
     *
     * So: a natural sentence first, then something too plain to trip anything.
     * A rejected message reached nobody, so the retry cannot double-send. Any
     * failure that is not about the message stops the walk — retrying a bad
     * number or an empty account would only burn attempts.
     *
     * Use artisan laas:sms-probe to check which wordings this account currently
     * tolerates; gateways change their rules without notice.
     */
    private function sendCode(string $phone, string $code, LaasApplicant $applicant): bool
    {
        $minutes = LaasApplicant::OTP_TTL_MINUTES;

        $templates = [
            "KLAES LAAS: {$code} is your confirmation number for the phone change on your land application account. It is valid for {$minutes} minutes.",
            "KLAES LAAS confirmation: {$code}",
        ];

        try {
            $delivered = $this->sms->sendFirstAccepted($phone, $templates);
        } catch (\Throwable $e) {
            Log::error('LAAS: phone-change code send threw', [
                'applicant_id' => $applicant->id,
                'error'        => $e->getMessage(),
            ]);

            return false;
        }

        $this->lastSmsCode = $this->sms->lastStatusCode();

        return $delivered !== null;
    }

    /** Say what actually went wrong, rather than blaming the applicant's number. */
    private function sendFailureMessage(): string
    {
        // Codes are Bulk-SMS.ng's; see the status table in BulkSmsNgService.
        switch ($this->lastSmsCode) {
            case BulkSmsNgService::CODE_REWORDABLE:   // 602 — malformed request
                return 'We could not send the confirmation message — our SMS provider rejected it. '
                     . 'This is a problem on our side, not with your number. Please try again shortly, '
                     . 'or contact the Lands office if it keeps happening.';

            case '601':   // bad credentials
            case '604':   // no balance
            case '608':   // route not enabled for this account
                return 'We could not send the confirmation message because of a problem with our SMS account. '
                     . 'Please contact the Lands office.';

            case '606':   // gateway internal error
                return 'Our SMS provider is having trouble right now. Please try again in a few minutes.';

            default:
                return 'We could not send the confirmation message just now. Please try again shortly.';
        }
    }
}
