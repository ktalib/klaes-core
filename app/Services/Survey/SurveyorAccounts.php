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
                // A company may have several surveyors (user, 2026-11-10); the 2026-11-06 one-account-per-company rule is lifted.
                $account = $this->db()->transaction(function () use ($data) {
                    return SurveyorPortalAccount::create($data + [
                        'registration_id' => SurveyorPortalAccount::nextId('registration_id', 'MLPPSG-REG', 5),
                        'status' => SurveyorPortalAccount::PENDING,
                    ]);
                });
                break;
            } catch (QueryException $e) {
                // Two sign-ups choosing the same username at the same moment: the index refuses the second.
                if (str_contains($e->getMessage(), 'ux_surveyor_portal_username')) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['username' => 'That username is taken. Choose another.']);
                }
                $again = str_contains($e->getMessage(), 'ux_surveyor_portal_registration') || str_contains(strtolower($e->getMessage()), 'deadlock');
                if ($try >= 3 || ! $again) throw $e;
            }
        }
        $this->notify->registered($account);

        return $account;
    }

    /**
     * A surveyor added by the SG on Registrations (user, 2026-11-10: the Cadastral "Add a Surveyor" form, with the
     * company picked from the approved list). Approved at once, as the SG is the approver: a Surveyor ID, the SURCON
     * directory row (found by SURCON number or made, as approve() does), and that row's licence status and Active as
     * entered. Email is optional; without one the login goes by SMS only. The password is temporary and returned for SurveyCompanyLogins::send(); the caller sends it.
     *
     * @param array $data account values (surveyor_type, company_*, name, phone, email, surcon_number, username, addr_*)
     * @param array{licence_status:string, is_active:bool} $licence (Licence Expires is not asked, 2026-11-10: the row keeps its own)
     * @return array{account: SurveyorPortalAccount, password: string}
     */
    public function createByAdmin(array $data, array $licence): array
    {
        $password = app(SurveyCompanyLogins::class)->temporaryPassword();

        try {
            $account = $this->db()->transaction(function () use ($data, $licence, $password) {
                $a = SurveyorPortalAccount::create($data + [
                    'registration_id' => SurveyorPortalAccount::nextId('registration_id', 'MLPPSG-REG', 5),
                    'status' => SurveyorPortalAccount::PENDING,
                    'password' => Hash::make($password),
                    'must_change_password' => true,
                ]);
                $a->update(array_merge($this->directoryLink($a), [
                    'status' => SurveyorPortalAccount::ACTIVE,
                    'surveyor_id_no' => SurveyorPortalAccount::nextId('surveyor_id_no', 'MLPPSG-SV', 4),
                    'reviewed_by' => Auth::id(), 'reviewed_at' => now(),
                ]));
                if ($a->cadastral_surveyor_id) {
                    $q = $this->db()->table('cadastral_surveyors')->where('id', $a->cadastral_surveyor_id);
                    $before = (array) (clone $q)->first(['licence_status', 'licence_expires_on', 'is_active']);
                    $q->update($licence + ['updated_by' => Auth::id(), 'updated_at' => now()]);
                    Log::info('Surveyor directory licence set from MLPP-SG Add Surveyor', ['cadastral_surveyor' => $a->cadastral_surveyor_id, 'before' => $before, 'after' => $licence]);
                }

                return $a;
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ux_surveyor_portal_username')) {
                throw ValidationException::withMessages(['username' => 'That username is taken. Choose another.']);
            }
            throw $e;
        }
        Log::info('Surveyor portal account added by the SG', ['account' => $account->id, 'surveyor_id' => $account->surveyor_id_no, 'by' => Auth::id()]);

        return ['account' => $account, 'password' => $password];
    }

    /**
     * Edit a surveyor's portal account from MLPP-SG Portal Admin (user, 2026-11-12). The Surveyor ID, status and
     * password stay; no login is sent. A linked directory row (cadastral_surveyors) takes the licence status and
     * Active tick, as on Add. Before/after go to the log.
     */
    public function updateByAdmin(SurveyorPortalAccount $account, array $data, array $licence): SurveyorPortalAccount
    {
        try {
            $account = $this->db()->transaction(function () use ($account, $data, $licence) {
                $a = SurveyorPortalAccount::query()->lockForUpdate()->findOrFail($account->id);
                $before = $a->only(array_keys($data));
                $a->update($data);
                if ($a->cadastral_surveyor_id) {
                    $this->db()->table('cadastral_surveyors')->where('id', $a->cadastral_surveyor_id)
                        ->update($licence + ['updated_by' => Auth::id(), 'updated_at' => now()]);
                }
                Log::info('MLPP-SG surveyor account edited', ['account' => $a->id, 'by' => Auth::id(), 'before' => $before, 'after' => $data, 'licence' => $licence]);

                return $a;
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ux_surveyor_portal_username')) {
                throw ValidationException::withMessages(['username' => 'That username is taken. Choose another.']);
            }
            throw $e;
        }

        return $account;
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
                'surveyor_id_no' => SurveyorPortalAccount::nextId('surveyor_id_no', 'MLPPSG-SV', 4),
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
            "Your MLPP-SG password reset code is {$code}. It expires in {$minutes} minutes.",
            "MLPP-SG: use {$code} to reset your password. Valid for {$minutes} minutes.",
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
        $account->update(['password' => Hash::make($password), 'reset_code' => null, 'reset_expires_at' => null, 'reset_attempts' => 0, 'must_change_password' => false]);
    }
}
