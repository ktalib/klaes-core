<?php

namespace App\Services\Survey;

use App\Mail\SurveyCompanyLoginDetails;
use App\Models\Survey\SurveyorPortalAccount as Account;
use App\Services\BulkSmsNgService;
use App\Services\PhoneOtpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * A company's own MLPP-SG portal login (user, 2026-11-10: "company login detail will send once via sms and
 * email, once it has been approved"). Made when the SG approves the company: a surveyor_portal_accounts row
 * with surveyor_type 'Company', active at once, signing in like a surveyor (username + password, sign-in code,
 * security questions) to the company's own sidebar. The password is temporary and must be changed at the first
 * sign-in, as on LAAS commissioning accounts, because it has passed through the SMS gateway and a mailbox.
 *
 * Sent once: approval never sends the details again. Only reissue() (staff, on request: a new temporary password)
 * does. The password is only ever in the messages, never logged or stored readable.
 */
class SurveyCompanyLogins
{
    public function __construct(private BulkSmsNgService $gateway, private PhoneOtpService $phoneOtp)
    {
    }

    /** The company's login, if it has one. */
    public function for(int $companyId): ?Account
    {
        return Account::where('surveyor_type', Account::COMPANY)->where('company_id', $companyId)->first();
    }

    /**
     * Make the login for an approved company. Call inside the approval's transaction; send() goes after commit.
     * Null when the company already has one or has neither a phone nor an email to send it to.
     *
     * @return array{account:Account, password:string}|null
     */
    public function create(object $company): ?array
    {
        if ($this->for((int) $company->id)) return null;

        $phone = Account::normalizePhone((string) ($company->phone ?? '')) ?: (trim((string) ($company->phone ?? '')) ?: null);
        $email = mb_strtolower(trim((string) ($company->email ?? ''))) ?: null;
        if (! $phone && ! $email) return null;

        $password = $this->temporaryPassword();
        $address = [];
        foreach (Account::addressColumns('addr_') as $col) {
            $address[$col] = $company->{$col} ?? null;
        }

        $account = Account::create($address + [
            'registration_id' => Account::nextId('registration_id', 'MLPPSG-CO', 5),
            'surveyor_type' => Account::COMPANY,
            'company_id' => $company->id,
            'company_name' => $company->name,
            'company_rc_no' => $company->company_rc_no ?? null,
            'name' => $company->name,
            'phone' => (string) $phone,
            'email' => (string) $email,
            'username' => $this->uniqueUsername((string) $company->name),
            'password' => Hash::make($password),
            'must_change_password' => true,
            'status' => Account::ACTIVE,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);
        Log::info('MLPP-SG company login made', ['company' => $company->id, 'account' => $account->id, 'username' => $account->username, 'by' => Auth::id()]);

        return ['account' => $account, 'password' => $password];
    }

    /**
     * Text and email the login details, once each. Stamps what went.
     *
     * @return array{sms:bool, email:bool}
     */
    /**
     * A new temporary password for an existing login (user, 2026-11-10: the Demo login to a second person). The old
     * password stops working and the new one must be changed at the next sign-in. Returns it for send().
     */
    public function reissue(Account $account): string
    {
        $password = $this->temporaryPassword();
        $account->forceFill(['password' => Hash::make($password), 'must_change_password' => true])->save();
        Log::info('MLPP-SG company login password reissued', ['account' => $account->id, 'username' => $account->username, 'by' => Auth::id()]);

        return $password;
    }

    /**
     * @param string|null $toPhone send here instead of the login's own phone (its stamps are then left alone)
     * @param string|null $toEmail send here instead of the login's own email
     */
    public function send(Account $account, string $password, ?string $toPhone = null, ?string $toEmail = null): array
    {
        // Straight to the right sign-in page: companies and surveyors sign in apart (2026-11-11).
        $url = rtrim((string) config('app.url'), '/') . '/mlpp-sg/login/' . ($account->isCompany() ? 'company' : 'surveyor');
        $sms = false;
        $mail = false;

        if ($phone = BulkSmsNgService::normalizeNumber((string) ($toPhone ?? $account->phone))) {
            // A surveyor added by the SG (2026-11-10) gets the same once-only details, worded for a surveyor.
            $sms = $this->gateway->sendFirstAccepted($phone, $account->isCompany() ? [
                "MLPP-SG Portal: {$account->company_name} is approved. Sign in at {$url} Username: {$account->username} Password: {$password} You will choose a new password at first sign-in.",
                "MLPP-SG company sign-in. Username {$account->username} Password {$password} Site {$url}",
            ] : [
                "MLPP-SG Portal: your surveyor account is ready, Surveyor ID {$account->surveyor_id_no}. Sign in at {$url} Username: {$account->username} Password: {$password} You will choose a new password at first sign-in.",
                "MLPP-SG surveyor sign-in. Username {$account->username} Password {$password} Site {$url}",
            ], \App\Models\SmsSetting::senderFor(\App\Models\SmsSetting::KEY_PHONE_OTP)) !== null;
            if (! $sms) {
                Log::warning('MLPP-SG company login: SMS not delivered', ['account' => $account->id, 'reason' => $this->gateway->lastFailureReason()]);
            }
        }

        $email = $this->phoneOtp->normaliseEmail((string) ($toEmail ?? $account->email));
        if ($email && ! $this->phoneOtp->isPlaceholderEmail($email)) {
            try {
                // ->send(), never ->queue(): no queue worker is guaranteed, and the password must not sit in the jobs table.
                Mail::to($email)->send(new SurveyCompanyLoginDetails($account->isCompany() ? (string) $account->company_name : (string) $account->name, (string) $account->username, $password, $url,
                    $account->isCompany() ? null : (string) $account->surveyor_id_no));
                $mail = true;
            } catch (\Throwable $e) {
                Log::warning('MLPP-SG company login: email not delivered', ['account' => $account->id, 'message' => $e->getMessage()]);
            }
        }

        if ($toPhone === null && $toEmail === null) {
            $account->forceFill([
                'login_sms_sent_at' => $sms ? now() : null,
                'login_email_sent_at' => $mail ? now() : null,
            ])->save();
        }

        return ['sms' => $sms, 'email' => $mail];
    }

    /** demo.survey27: the first two words of the company name, then digits until free (LAAS commissioning pattern). */
    public function uniqueUsername(string $name): string
    {
        $words = preg_split('/[\s,&.\/-]+/', Str::ascii(mb_strtolower(trim($name)))) ?: [];
        $words = array_values(array_filter(array_map(fn ($w) => preg_replace('/[^a-z0-9]/', '', $w), $words), fn ($w) => $w !== ''));
        $base = substr(implode('.', array_slice($words, 0, 2)) ?: 'company', 0, 40);

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $candidate = $base . random_int($attempt < 20 ? 10 : 100, $attempt < 20 ? 99 : 9999);
            if (! Account::whereRaw('LOWER(username) = ?', [$candidate])->exists()) {
                return $candidate;
            }
        }

        return $base . Str::lower(Str::random(6));
    }

    /** Readable, no look-alike characters: Kx7m-4821. */
    public function temporaryPassword(): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
        $word = '';
        for ($i = 0; $i < 4; $i++) {
            $word .= $letters[random_int(0, strlen($letters) - 1)];
        }

        return $word . '-' . random_int(1000, 9999);
    }
}
