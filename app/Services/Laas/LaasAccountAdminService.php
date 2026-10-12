<?php

namespace App\Services\Laas;

use App\Models\Laas\LaasApplicant;
use App\Models\Laas\LaasApplication;
use App\Models\Laas\LaasApplicationEvent;
use App\Services\BulkSmsNgService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Staff actions on LAAS Portal accounts (plan §3.5, §3.4):
 *
 *  updateContact  enter the holder's real phone/email; settles the
 *                 contact-pending flag and, optionally, texts fresh sign-in details.
 *  issueSlip      reset the temporary password for a counter hand-over.
 *  merge          move every file of one account onto another (grouping fix).
 *
 * Passwords are returned to the caller for the printed slip and never written
 * to a timeline (the timeline is visible to whoever later signs in).
 */
class LaasAccountAdminService
{
    public function __construct(
        private LaasCommissioningAccountService $accounts,
        private BulkSmsNgService $sms,
    ) {
    }

    /**
     * @return array{temp_password:?string, sms:string}
     *         sms: not_requested | sent | failed | no_phone
     */
    public function updateContact(LaasApplicant $applicant, ?string $phone, ?string $email, bool $textSignIn, int $staffId): array
    {
        $phone = $phone !== null && trim($phone) !== '' ? LaasApplicant::normalizePhone($phone) : null;
        $email = $email !== null && trim($email) !== '' ? mb_strtolower(trim($email)) : null;

        $changes = [];
        if ($phone) {
            if (LaasApplicant::where('phone', $phone)->where('id', '!=', $applicant->id)->exists()) {
                throw new \InvalidArgumentException('Another portal account already uses that phone number.');
            }
            $changes['phone'] = $phone;
            $changes += LaasApplicant::contactSettledAttributes($applicant->contact_flag_reason);
        }
        if ($email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('That email address is not valid.');
            }
            if (LaasApplicant::where('email', $email)->where('id', '!=', $applicant->id)->exists()) {
                throw new \InvalidArgumentException('Another portal account already uses that email address.');
            }
            $changes['email'] = $email;
            $reasons = array_diff(array_filter(explode(',', (string) ($changes['contact_flag_reason'] ?? $applicant->contact_flag_reason))), ['no_email']);
            $changes['contact_flag_reason'] = $reasons ? implode(',', $reasons) : null;
        }
        if (!$changes) {
            throw new \InvalidArgumentException('Enter a phone number or an email address.');
        }

        $tempPassword = $textSignIn ? $this->accounts->temporaryPassword() : null;
        if ($tempPassword) {
            $changes['password'] = Hash::make($tempPassword);
            $changes['must_change_password'] = true;
        }

        DB::connection('sqlsrv')->transaction(function () use ($applicant, $changes, $phone, $staffId) {
            $applicant->forceFill($changes)->save();

            if ($phone) {
                LaasApplication::where('laas_applicant_id', $applicant->id)->current()
                    ->update(['applicant_phone' => $phone, 'updated_at' => now()]);
            }
            $this->stamp($applicant, 'Contact details updated', 'The office updated the contact details on your account.', $staffId);
        });

        $sms = 'not_requested';
        if ($tempPassword) {
            $sms = $this->textSignIn($applicant->fresh(), $tempPassword) ? 'sent' : ($applicant->fresh()->textablePhone() ? 'failed' : 'no_phone');
        }

        return ['temp_password' => $tempPassword, 'sms' => $sms];
    }

    /** New temporary password for a printed counter slip. */
    public function issueSlip(LaasApplicant $applicant, int $staffId): string
    {
        $tempPassword = $this->accounts->temporaryPassword();

        DB::connection('sqlsrv')->transaction(function () use ($applicant, $tempPassword, $staffId) {
            $applicant->forceFill([
                'password'             => Hash::make($tempPassword),
                'must_change_password' => true,
            ])->save();
            $this->stamp($applicant, 'Sign-in details issued', 'The office issued you a sign-in slip.', $staffId);
        });

        return $tempPassword;
    }

    /**
     * Move every application of $source onto $target and suspend $source.
     * For two accounts that are one person (e.g. the same holder with and
     * without a phone). Returns the number of applications moved.
     */
    public function merge(LaasApplicant $source, LaasApplicant $target, int $staffId): int
    {
        if ((int) $source->id === (int) $target->id) {
            throw new \InvalidArgumentException('Choose a different account to merge into.');
        }

        return DB::connection('sqlsrv')->transaction(function () use ($source, $target, $staffId) {
            $moved = LaasApplication::where('laas_applicant_id', $source->id)
                ->update(['laas_applicant_id' => $target->id, 'updated_at' => now()]);

            $source->forceFill(['status' => 'suspended'])->save();

            $this->stamp($target, 'Files added to your account', "The office moved {$moved} file(s) from another account onto yours.", $staffId);

            Log::info('LAAS: accounts merged', ['from' => $source->id, 'into' => $target->id, 'applications' => $moved, 'staff' => $staffId]);

            return $moved;
        });
    }

    /** The printable slip's contents. */
    public function slipData(LaasApplicant $applicant, string $tempPassword): array
    {
        return [
            'applicant'     => $applicant,
            'temp_password' => $tempPassword,
            'url'           => rtrim((string) config('app.url'), '/') . '/laas/login',
            'files'         => LaasApplication::where('laas_applicant_id', $applicant->id)->current()
                ->whereNotNull('file_number')->orderBy('id')->pluck('file_number')->all(),
        ];
    }

    private function textSignIn(LaasApplicant $applicant, string $tempPassword): bool
    {
        $phone = $applicant->textablePhone();
        if (!$phone) {
            return false;
        }

        $files = LaasApplication::where('laas_applicant_id', $applicant->id)->current()
            ->whereNotNull('file_number')->pluck('file_number')->all();

        try {
            return (bool) $this->sms->sendFirstAccepted($phone, $this->accounts->loginMessages($applicant, $tempPassword, $files ?: ['on file'], true));
        } catch (\Throwable $e) {
            Log::warning('LAAS: sign-in SMS failed', ['applicant_id' => $applicant->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** One timeline line on the account's newest current application. */
    private function stamp(LaasApplicant $applicant, string $title, string $body, int $staffId): void
    {
        $application = LaasApplication::where('laas_applicant_id', $applicant->id)->current()->orderByDesc('id')->first();
        if (!$application) {
            return;
        }

        LaasApplicationEvent::create([
            'laas_application_id'  => $application->id,
            'stage'                => $application->stage,
            'title'                => $title,
            'body'                 => $body,
            'actor_type'           => 'staff',
            'actor_id'             => $staffId,
            'visible_to_applicant' => true,
        ]);
    }
}
