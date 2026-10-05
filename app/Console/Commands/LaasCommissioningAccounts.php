<?php

namespace App\Console\Commands;

use App\Services\Laas\LaasCommissioningAccountService;
use Illuminate\Console\Command;

/**
 * Open LAAS Portal accounts for files that were commissioned before the
 * commissioning hook existed — the same code path the hook runs, one file list
 * at a time. Files given together are treated as one commissioning (one batch):
 * the same holder lands on one account.
 *
 *   php artisan laas:commissioning-accounts RES-2026-3439 RES-2026-3441 --no-sms --allow-no-phone
 *
 * --no-sms prints the temporary passwords instead of texting them, for the
 * office to hand over. Without it the applicant is texted exactly as at
 * commissioning.
 */
class LaasCommissioningAccounts extends Command
{
    protected $signature = 'laas:commissioning-accounts
                            {files* : Commissioned file numbers, treated as one commissioning}
                            {--no-sms : Do not text the applicant; print the sign-in details instead}
                            {--allow-no-phone : Open accounts for files with no usable phone number}';

    protected $description = 'Open LAAS Portal accounts for already-commissioned files';

    public function handle(LaasCommissioningAccountService $service): int
    {
        $report = $service->provisionForFiles($this->argument('files'), [
            'send_sms'      => !$this->option('no-sms'),
            'require_phone' => !$this->option('allow-no-phone'),
        ]);

        if (!$report) {
            $this->warn('Nothing was provisioned (unknown file numbers, no phone, or the hook is switched off).');

            return self::FAILURE;
        }

        $this->table(
            ['Status', 'Username', 'Temp password', 'Files', 'New applications', 'SMS'],
            array_map(fn ($r) => [
                $r['status'],
                $r['username'] ?? '',
                $r['temp_password'] ?? '(existing account)',
                implode(', ', $r['files'] ?? []),
                implode(', ', $r['new_applications'] ?? []),
                $r['sms'] ?? '',
            ], $report)
        );

        return self::SUCCESS;
    }
}
