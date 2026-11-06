<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyorPortalAccount;
use App\Services\BulkSmsNgService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Surveyor portal accounts (CR 03112026 items 17-18). Anyone can reach the sign-up page, so an account
 * waits for the SG (user, 2026-11-05: login only after SG approval). Approving links the account to
 * the directory: a Private surveyor to the SURCON register, a Public one to the Survey staff list.
 */
class SurveyorAccounts
{
    public function __construct(private SurveyorPortalNotifier $notify, private BulkSmsNgService $gateway)
    {
    }

    private function db()
    {
        return DB::connection('sqlsrv');
    }

    /** @param array{surveyor_type:string, company_id:?int, company_name:?string, name:string, phone:string, email:string, surcon_number:?string, password:string} $data */
    public function register(array $data): SurveyorPortalAccount
    {
        $data['password'] = Hash::make($data['password']);

        for ($try = 1; ; $try++) {
            try {
                $account = $this->db()->transaction(function () use ($data) {
                    $this->refuseSecondCompanyAccount($data);

                    return SurveyorPortalAccount::create($data + [
                        'registration_id' => SurveyorPortalAccount::nextId('registration_id', 'KSSGOF-REG', 5),
                        'status' => SurveyorPortalAccount::PENDING,
                    ]);
                });
                break;
            } catch (QueryException $e) {
                $again = str_contains($e->getMessage(), 'ux_surveyor_portal_registration') || str_contains(strtolower($e->getMessage()), 'deadlock');
                if ($try >= 3 || ! $again) throw $e;
            }
        }
        $this->notify->registered($account);

        return $account;
    }

    /**
     * One account per company (user, 2026-11-06). Only a rejected registration frees the company. The rows stay
     * locked until the new account is saved, so two sign-ups at the same moment can't both pass.
     */
    private function refuseSecondCompanyAccount(array $data): void
    {
        if (($data['surveyor_type'] ?? null) !== 'Private') return;

        $taken = SurveyorPortalAccount::query()->where('surveyor_type', 'Private')
            ->where('status', '!=', SurveyorPortalAccount::REJECTED)
            ->lockForUpdate()->get(['id', 'company_id', 'company_name', 'company_rc_no']);

        foreach ($taken as $a) {
            $same = SurveyorPortalAccount::sameCompanyAs($a->only(['company_id', 'company_name', 'company_rc_no']), $data);
            if (! $same) continue;

            $field = 'company_choice';
            throw ValidationException::withMessages([$field => ($same === 'rc' ? 'This RC number is already registered' : "{$a->company_name} is already registered")
                . ' on the portal. Each company has one account. Contact the Office of the Surveyor General if this is wrong.']);
        }
    }

    public function approve(SurveyorPortalAccount $account): SurveyorPortalAccount
    {
        if ($account->status !== SurveyorPortalAccount::PENDING) {
            throw ValidationException::withMessages(['account' => "{$account->name} is already {$account->status}."]);
        }

        $account = $this->db()->transaction(function () use ($account) {
            $a = SurveyorPortalAccount::query()->lockForUpdate()->findOrFail($account->id);
            $a->update(array_merge($this->directoryLink($a), [
                'status' => SurveyorPortalAccount::ACTIVE,
                'surveyor_id_no' => SurveyorPortalAccount::nextId('surveyor_id_no', 'KSSGOF-SV', 4),
                'reviewed_by' => Auth::id(), 'reviewed_at' => now(),
            ]));

            return $a;
        });
        Log::info('Surveyor portal account approved', ['account' => $account->id, 'surveyor_id' => $account->surveyor_id_no, 'by' => Auth::id()]);
        $this->notify->accountApproved($account);

        return $account;
    }

    public function reject(SurveyorPortalAccount $account, string $note): SurveyorPortalAccount
    {
        if ($account->status !== SurveyorPortalAccount::PENDING) {
            throw ValidationException::withMessages(['account' => "{$account->name} is already {$account->status}."]);
        }
        $account->update(['status' => SurveyorPortalAccount::REJECTED, 'review_note' => $note, 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);
        $this->notify->accountRejected($account);

        return $account;
    }

    /** Private: the SURCON register row with this number, or a new one. Public: the staff list row with this phone. */
    private function directoryLink(SurveyorPortalAccount $a): array
    {
        if ($a->surveyor_type === 'Private') {
            $id = $a->surcon_number
                ? $this->db()->table('cadastral_surveyors')->whereNull('deleted_at')->whereRaw('UPPER(surcon_number) = ?', [mb_strtoupper($a->surcon_number)])->value('id')
                : null;
            // A new directory row takes the address given at registration. A row that already exists keeps its own.
            $id = $id ?: $this->db()->table('cadastral_surveyors')->insertGetId($a->only(SurveyorPortalAccount::addressColumns('addr_')) + [
                'surcon_number' => $a->surcon_number, 'full_name' => $a->name, 'firm_name' => $a->company_name, 'firm_rc_no' => $a->company_rc_no,
                'phone' => $a->phone, 'email' => $a->email, 'licence_status' => 'Active', 'is_active' => 1,
                'created_by' => Auth::id(), 'updated_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['cadastral_surveyor_id' => $id];
        }

        $staff = $this->db()->table('surveyors_list')->whereIn('phone', array_filter([$a->phone, '0' . ltrim((string) $a->phone, '0')]))->value('id');

        return ['surveyors_list_id' => $staff];
    }

    /** Text a reset code. Says nothing about whether the account exists: the caller's message is always the same. */
    public function sendResetCode(SurveyorPortalAccount $account): void
    {
        $code = (string) random_int(100000, 999999);
        $account->update(['reset_code' => Hash::make($code), 'reset_expires_at' => now()->addMinutes(SurveyorPortalAccount::RESET_TTL_MINUTES), 'reset_attempts' => 0]);

        $minutes = SurveyorPortalAccount::RESET_TTL_MINUTES;
        $sent = $this->gateway->sendFirstAccepted((string) $account->phone, [
            "Your KSSGOF password reset code is {$code}. It expires in {$minutes} minutes.",
            "KSSGOF: use {$code} to reset your password. Valid for {$minutes} minutes.",
        ], \App\Models\SmsSetting::senderFor(\App\Models\SmsSetting::KEY_PHONE_OTP));

        if ($sent === null) {
            Log::warning('Surveyor portal: reset code not delivered', ['account' => $account->id, 'reason' => $this->gateway->lastFailureReason()]);
        }
    }

    public function resetPassword(SurveyorPortalAccount $account, string $code, string $password): void
    {
        $bad = ValidationException::withMessages(['code' => 'That code is wrong or has expired. Ask for a new one.']);
        if (! $account->reset_code || ! $account->reset_expires_at || $account->reset_expires_at->isPast()
            || $account->reset_attempts >= SurveyorPortalAccount::RESET_MAX_ATTEMPTS) {
            throw $bad;
        }
        if (! Hash::check($code, $account->reset_code)) {
            $account->increment('reset_attempts');
            throw $bad;
        }
        $account->update(['password' => Hash::make($password), 'reset_code' => null, 'reset_expires_at' => null, 'reset_attempts' => 0]);
    }
}
