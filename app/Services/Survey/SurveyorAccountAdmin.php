<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyorPortalAccount as Account;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The row menu on MLPP-SG Portal Admin > Registrations > Surveyors (user, 2026-11-16: "like" the LAAS Portal Accounts
 * menu, without Merge; "transfer to another company in case the user selected a wrong company"). View profile, Update
 * contact, Issue sign-in slip, Transfer to another company, Suspend / Restore, Delete. Every change is logged with its
 * before values.
 */
class SurveyorAccountAdmin
{
    /** Work a surveyor's account owns: while any is there the account is never deleted. */
    public const WORK = [
        'survey_beacon_requests' => 'Beacon requests',
        'survey_plan_uploads' => 'Survey plans',
        'sdi_map_requests' => 'Map requests',
        'survey_change_reports' => "Surveyor's reports",
    ];

    private function db()
    {
        return DB::connection('sqlsrv');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function surveyor(int $id): Account
    {
        return Account::query()->surveyors()->findOrFail($id);
    }

    /** @return array<int, array<string, int>> account id => [table => count], for the ids given */
    public function work(array $ids): array
    {
        $out = [];
        if (! $ids) return $out;
        foreach (array_keys(self::WORK) as $table) {
            $rows = $this->db()->table($table)->whereIn('surveyor_portal_account_id', $ids)
                ->selectRaw('surveyor_portal_account_id AS a, COUNT(*) AS n')->groupBy('surveyor_portal_account_id')->get();
            foreach ($rows as $r) $out[(int) $r->a][$table] = (int) $r->n;
        }

        return $out;
    }

    public function profile(int $id): array
    {
        $a = $this->surveyor($id);
        $company = $a->company_id ? $this->db()->table('survey_companies')->where('id', $a->company_id)->first(['name', 'beacon_letters', 'company_rc_no']) : null;
        $directory = $a->cadastral_surveyor_id ? $this->db()->table('cadastral_surveyors')->where('id', $a->cadastral_surveyor_id)->first(['surcon_number', 'licence_status', 'is_active']) : null;

        return ['a' => $a, 'company' => $company, 'directory' => $directory, 'work' => $this->work([$a->id])[$a->id] ?? []];
    }

    /** Phone and email only. The same uniqueness as Add / Edit Surveyor. */
    public function updateContact(int $id, string $phoneIn, ?string $emailIn): Account
    {
        $a = $this->surveyor($id);
        $phone = Account::normalizePhone($phoneIn) ?? $this->fail('phone', 'Enter a valid Nigerian phone number, e.g. 08031234567.');
        $email = mb_strtolower(trim((string) $emailIn)) ?: null;
        $others = fn () => Account::query()->surveyors()->where('id', '<>', $a->id);
        if ($others()->where('phone', $phone)->exists()) $this->fail('phone', 'Another surveyor account already uses this phone number.');
        if ($email && $others()->where('email', $email)->exists()) $this->fail('email', 'Another surveyor account already uses this email address.');

        $before = $a->only(['phone', 'email']);
        $a->update(['phone' => $phone, 'email' => $email]);
        Log::info('MLPP-SG surveyor contact updated', ['account' => $a->id, 'by' => Auth::id(), 'before' => $before, 'after' => ['phone' => $phone, 'email' => $email]]);

        return $a;
    }

    /** A new temporary password for the printed slip; the old one stops working and the new one is changed at sign-in. */
    public function issueSlip(int $id): array
    {
        $a = $this->surveyor($id);
        if (! $a->isActive()) $this->fail('account', "{$a->name}'s account is not active, so no sign-in slip can be issued.");
        if (! $a->username) $this->fail('account', "{$a->name} has no username yet. Set one with Edit account first.");
        $password = app(SurveyCompanyLogins::class)->reissue($a);

        return ['a' => $a->fresh(), 'password' => $password, 'url' => rtrim((string) config('app.url'), '/') . '/kn-mlpp-sg/login/surveyor'];
    }

    /**
     * Move the surveyor to the right company (a wrong one picked at registration). The account and its SURCON register
     * row follow when that row still names the old company. Beacon requests already made keep their company: issued
     * numbers carry that company's prefix.
     */
    public function transfer(int $id, int $companyId, string $reason): Account
    {
        $a = $this->surveyor($id);
        $to = SurveySettings::companies()->firstWhere('id', $companyId) ?? $this->fail('company_id', 'Choose an approved company from the list.');
        if ((int) $a->company_id === (int) $to->id) $this->fail('company_id', "{$a->name} is already under {$to->name}.");

        return $this->db()->transaction(function () use ($a, $to, $reason) {
            $a = Account::query()->lockForUpdate()->findOrFail($a->id);
            $before = $a->only(['company_id', 'company_name', 'company_rc_no']);
            $after = ['company_id' => $to->id, 'company_name' => $to->name, 'company_rc_no' => $to->company_rc_no ?? null];
            $a->update($after);
            if ($a->cadastral_surveyor_id) {
                $this->db()->table('cadastral_surveyors')->where('id', $a->cadastral_surveyor_id)
                    ->where(fn ($w) => $w->whereNull('firm_name')->orWhere('firm_name', $before['company_name']))
                    ->update(['firm_name' => $to->name, 'firm_rc_no' => $to->company_rc_no ?? null, 'updated_by' => Auth::id(), 'updated_at' => now()]);
            }
            Log::info('MLPP-SG surveyor transferred to another company', ['account' => $a->id, 'by' => Auth::id(), 'reason' => $reason, 'before' => $before, 'after' => $after]);

            return $a;
        });
    }

    /** Active <-> suspended. A suspended surveyor is signed out at the next page (RequireSurveyorLoginOtp). */
    public function toggle(int $id): Account
    {
        $a = $this->surveyor($id);
        $next = match ($a->status) {
            Account::ACTIVE => Account::SUSPENDED,
            Account::SUSPENDED => Account::ACTIVE,
            default => $this->fail('account', "{$a->name}'s registration is {$a->status}: approve or refuse it first."),
        };
        $a->update(['status' => $next]);
        Log::info('MLPP-SG surveyor ' . ($next === Account::SUSPENDED ? 'suspended' : 'restored'), ['account' => $a->id, 'by' => Auth::id()]);

        return $a;
    }

    /**
     * Only an account with no work. Its notifications and security answers go with it; the SURCON register row stays.
     * The whole row is logged first, so it can be put back.
     */
    public function delete(int $id): string
    {
        $a = $this->surveyor($id);

        return $this->db()->transaction(function () use ($a) {
            $a = Account::query()->lockForUpdate()->findOrFail($a->id);
            $work = $this->work([$a->id])[$a->id] ?? [];
            if ($work) {
                $what = implode(', ', array_map(fn ($t, $n) => self::WORK[$t] . ': ' . $n, array_keys($work), $work));
                $this->fail('account', "{$a->name} cannot be deleted because the account has work ({$what}). Suspend it instead.");
            }
            $row = (array) $this->db()->table('surveyor_portal_accounts')->where('id', $a->id)->first();
            unset($row['password'], $row['reset_code'], $row['remember_token']);
            $notes = $this->db()->table('surveyor_portal_notifications')->where('surveyor_portal_account_id', $a->id)->delete();
            $answers = $this->db()->table('surveyor_portal_security_answers')->where('surveyor_portal_account_id', $a->id)->delete();
            $this->db()->table('surveyor_portal_accounts')->where('id', $a->id)->delete();
            Log::warning('MLPP-SG surveyor account deleted', ['by' => Auth::id(), 'row' => $row, 'notifications' => $notes, 'security_answers' => $answers]);

            return (string) $a->name;
        });
    }
}
