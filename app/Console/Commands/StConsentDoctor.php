<?php

namespace App\Console\Commands;

use App\Services\StAssignmentConsentResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Explains why an ST file's memo consent shows as free or spent on the capture
 * form. Run it on the machine whose database is in question — the ST registration
 * is written to `registered_instruments` but read from `deed_registrations`, and
 * the two do not always agree between environments.
 *
 *   php artisan st:consent-doctor ST-RES-2025-2-008
 */
class StConsentDoctor extends Command
{
    protected $signature = 'st:consent-doctor {file_number : A unit ST file number or the mother file number}';

    protected $description = 'Show how an ST file resolves to a memo consent, and what (if anything) already spent it';

    public function handle(): int
    {
        $fileNo = trim((string) $this->argument('file_number'));
        $this->line("File number: <info>{$fileNo}</info>");
        $this->newLine();

        $consents = (new StAssignmentConsentResolver())->forFileNumber($fileNo);

        if ($consents->isEmpty()) {
            $this->warn('No ST Assignment consent resolves for this file.');
            $this->line('Either the file is not sectional, or its mother application is not Approved,');
            $this->line('or it has no GENERATED primary / physical_planning memo.');
            $this->newLine();
            $this->probeRegistrationTables([$fileNo]);

            return self::SUCCESS;
        }

        foreach ($consents as $consent) {
            $this->line("Consent  : <info>{$consent->id}</info> ({$consent->application_tracking_no})");
            $this->line("Mother   : {$consent->file_number}");
            $this->line('Unit     : ' . ($consent->unit_file_number ?: '(none — mother file given)'));
            $this->line("Parties  : {$consent->applicant_name} -> {$consent->party_name}");

            if ($consent->is_used) {
                $used = $consent->used_by;
                $this->line('Status   : <comment>SPENT</comment> — greyed out on the capture form');
                $this->line("Spent by : {$used['instrument_type']} #{$used['registration_number']}"
                    . " on file {$used['file_number']} (reg date: " . ($used['reg_date'] ?: 'n/a') . ')');
            } else {
                $this->line('Status   : <info>FREE</info> — selectable on the capture form');
            }

            $this->newLine();
        }

        // Show the raw rows so a mismatch (wrong table, wrong status, wrong
        // instrument_type spelling) is visible rather than guessed at.
        $candidates = $consents
            ->map(fn ($c) => $c->unit_file_number ?: $c->file_number)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->probeRegistrationTables(array_unique(array_merge([$fileNo], $candidates)));

        return self::SUCCESS;
    }

    /**
     * A deliberately wide net: every row in every table that could represent this
     * file's first registration, matched by LIKE so a stray space, a different
     * dash or a parent-file key still shows up. Filters (instrument_type, status)
     * are printed rather than applied, so a near-miss is visible instead of silent.
     */
    private function probeRegistrationTables(array $filenos): void
    {
        $needles = [];
        foreach ($filenos as $fileno) {
            $fileno = trim((string) $fileno);
            if ($fileno === '') {
                continue;
            }
            // Whole file number only. A bare tail ("...-2-008") matched unrelated
            // files (IND-2002-001, RES-1992-001) and buried the real row in noise.
            $needles[$fileno] = $fileno;
        }

        $like = function ($query, array $columns) use ($needles) {
            $query->where(function ($q) use ($columns, $needles) {
                foreach ($columns as $column) {
                    foreach ($needles as $needle) {
                        $q->orWhere($column, 'like', '%' . $needle . '%');
                    }
                }
            });
        };

        $this->line('Searching for: ' . implode(', ', $needles));

        $probes = [
            'deed_registrations' => [
                'columns' => ['fileno', 'parent_fileno'],
                'select'  => ['id', 'fileno', 'parent_fileno', 'instrument_type', 'status', 'registration_number', 'is_deleted', 'created_at'],
            ],
            'registered_instruments' => [
                'columns' => ['StFileNo', 'fileno', 'MLSFileNo', 'parent_fileNo'],
                'select'  => ['id', 'StFileNo', 'fileno', 'MLSFileNo', 'parent_fileNo', 'instrument_type', 'status', 'particularsRegistrationNumber', 'created_at'],
            ],
            'instrument_capture' => [
                'columns' => ['mlsFNo', 'temp_fileno', 'kangisFileNo', 'NewKANGISFileno'],
                'select'  => ['id', 'mlsFNo', 'temp_fileno', 'instrument_type', 'is_deleted', 'created_at'],
            ],
            // pra carries transaction history and is known to hold rows typed
            // "ST Assignment" / "ST Fragmentation" — a candidate home for the
            // first registration when neither registration table has it.
            'pra' => [
                'columns' => ['fileno', 'mlsFNo', 'temp_fileno'],
                'select'  => ['id', 'fileno', 'mlsFNo', 'instrument_type', 'party_1', 'party_2', 'regNo', 'reg_date', 'is_deleted', 'created_at'],
            ],
        ];

        foreach ($probes as $table => $probe) {
            $this->newLine();
            $this->line("  <comment>{$table}</comment> (LIKE on " . implode(' / ', $probe['columns']) . ')');

            try {
                $rows = DB::connection('sqlsrv')->table($table)
                    ->where(fn ($q) => $like($q, $probe['columns']))
                    ->orderBy('created_at', 'desc')
                    ->limit(25)
                    ->get($probe['select']);
            } catch (\Throwable $e) {
                $this->line('    query failed: ' . $e->getMessage());
                continue;
            }

            if ($rows->isEmpty()) {
                $this->line('    none');
                continue;
            }

            foreach ($rows as $row) {
                $parts = [];
                foreach ((array) $row as $key => $value) {
                    if ($value !== null && $value !== '') {
                        $parts[] = "{$key}={$value}";
                    }
                }
                $this->line('    ' . implode(' | ', $parts));
            }
        }

        $this->newLine();
        $this->line('  The memo is spent only by a row whose instrument_type is exactly');
        $this->line('  "ST Assignment (Transfer of Title)" or "ST Fragmentation", with status = "registered",');
        $this->line('  in deed_registrations or registered_instruments, under one of the file numbers above.');
        $this->line('  If a row is listed but the verdict is FREE, the mismatch is in one of those three fields.');
    }
}
