<?php

namespace App\Services\Survey;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SurveyCompanyRegistry
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    private function db()
    {
        return DB::connection('sqlsrv');
    }

    public static function nextBeaconLetters(array $used): string
    {
        $used = array_fill_keys(array_map(fn ($value) => mb_strtoupper(trim((string) $value)), $used), true);

        for ($serial = 1; $serial <= 18278; $serial++) {
            $candidate = self::lettersFor($serial);
            if (! isset($used[$candidate])) {
                return $candidate;
            }
        }

        throw new RuntimeException('No three-letter company beacon prefix remains.');
    }

    private static function lettersFor(int $serial): string
    {
        $letters = '';
        while ($serial > 0) {
            $serial--;
            $letters = chr(65 + ($serial % 26)) . $letters;
            $serial = intdiv($serial, 26);
        }

        return $letters;
    }

    public function register(array $data): object
    {
        $this->assertInstalled();

        return $this->db()->transaction(function () use ($data) {
            $this->rejectDuplicate($data);
            $now = now();
            $id = $this->db()->table('survey_companies')->insertGetId($data + [
                'beacon_letters' => null,
                'is_active' => 0,
                'status' => self::PENDING,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $this->db()->table('survey_companies')->where('id', $id)->first();
        });
    }

    public function approve(int $id): object
    {
        $this->assertInstalled();

        $company = $this->db()->transaction(function () use ($id) {
            $company = $this->db()->table('survey_companies')->where('id', $id)->lockForUpdate()->first();
            abort_unless($company, 404);
            if ($company->status !== self::PENDING) {
                throw ValidationException::withMessages(['company' => "{$company->name} is already {$company->status}."]);
            }

            $used = $this->db()->table('survey_companies')->whereNotNull('beacon_letters')->pluck('beacon_letters')->all();
            $letters = self::nextBeaconLetters($used);
            $this->db()->table('survey_companies')->where('id', $id)->update([
                'beacon_letters' => $letters,
                'is_active' => 1,
                'status' => self::APPROVED,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'review_note' => null,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);

            return $this->db()->table('survey_companies')->where('id', $id)->first();
        });

        Log::info('Survey company approved', ['company' => $company->id, 'beacon_letters' => $company->beacon_letters, 'by' => Auth::id()]);

        return $company;
    }

    public function reject(int $id, string $note): object
    {
        $this->assertInstalled();

        $company = $this->db()->transaction(function () use ($id, $note) {
            $company = $this->db()->table('survey_companies')->where('id', $id)->lockForUpdate()->first();
            abort_unless($company, 404);
            if ($company->status !== self::PENDING) {
                throw ValidationException::withMessages(['company' => "{$company->name} is already {$company->status}."]);
            }

            $this->db()->table('survey_companies')->where('id', $id)->update([
                'is_active' => 0,
                'status' => self::REJECTED,
                'review_note' => $note,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);

            return $this->db()->table('survey_companies')->where('id', $id)->first();
        });

        Log::info('Survey company registration rejected', ['company' => $company->id, 'by' => Auth::id()]);

        return $company;
    }

    private function rejectDuplicate(array $data): void
    {
        $nameKey = self::duplicateKey($data['name'] ?? '');
        $rcKey = self::duplicateKey($data['company_rc_no'] ?? '');
        $companies = $this->db()->table('survey_companies')->get(['id', 'name', 'company_rc_no', 'status', 'is_active']);

        foreach ($companies as $company) {
            $sameName = $nameKey !== '' && $nameKey === self::duplicateKey($company->name);
            $sameRc = $rcKey !== '' && $rcKey === self::duplicateKey($company->company_rc_no);
            if ($sameName || $sameRc) {
                $field = $sameRc && ! $sameName ? 'company_rc_no' : 'name';
                $message = match ($company->status ?? ($company->is_active ? self::APPROVED : self::REJECTED)) {
                    self::APPROVED => $sameRc && ! $sameName
                        ? 'This RC number already belongs to an approved company. Select that company from the Private company list.'
                        : 'This company already exists. Select it from the Private company list.',
                    self::PENDING => 'A registration for this company is already waiting for approval.',
                    default => 'A registration for this company already exists. Contact the Office of the Surveyor General.',
                };
                throw ValidationException::withMessages([
                    $field => $message,
                ]);
            }
        }

        $legacy = $this->db()->table('surveyor_portal_accounts')
            ->where('surveyor_type', 'Private')
            ->where('status', '!=', 'rejected')
            ->get(['company_name', 'company_rc_no']);
        foreach ($legacy as $account) {
            $sameName = $nameKey !== '' && $nameKey === self::duplicateKey($account->company_name);
            $sameRc = $rcKey !== '' && $rcKey === self::duplicateKey($account->company_rc_no);
            if ($sameName || $sameRc) {
                $field = $sameRc && ! $sameName ? 'company_rc_no' : 'name';
                throw ValidationException::withMessages([
                    $field => 'This company already has a surveyor registration. Select the existing company or contact the Office of the Surveyor General.',
                ]);
            }
        }
    }

    private static function duplicateKey(?string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $value));
    }

    private function assertInstalled(): void
    {
        $schema = Schema::connection('sqlsrv');
        foreach ([
            'company_rc_no', 'phone', 'email', 'status', 'addr_house', 'addr_plot', 'addr_street', 'addr_street_other',
            'addr_district', 'addr_district_other', 'addr_lga', 'addr_state',
        ] as $column) {
            if (! $schema->hasColumn('survey_companies', $column)) {
                abort(503, 'Company registration is temporarily unavailable while the company registry is being set up.');
            }
        }
    }
}
