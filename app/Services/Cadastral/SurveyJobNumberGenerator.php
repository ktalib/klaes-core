<?php

namespace App\Services\Cadastral;

use App\Models\Cadastral\CadastralSurveyJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Survey job numbers and Instruction-to-Surveyor numbers.
 *
 * Job numbers are SUV-JOB-<year>-<serial> (CR 11112026-2; SRV-JOB- before): one series shared
 * by Cadastral survey jobs and Survey's public Computation Files (both are the
 * "Job No" a surveyor quotes on a beacon request), so the next serial is the
 * highest across both tables.
 *
 * Uniqueness is belt and braces: the serial is read under UPDLOCK inside the
 * caller's transaction, and the column carries a unique index that would reject
 * a collision anyway. Neither alone is enough — the lock without the index loses
 * to a request outside a transaction, the index without the lock turns a race
 * into an exception on a screen the user was told had succeeded.
 */
class SurveyJobNumberGenerator
{
    private const CONN = 'sqlsrv';

    /** Next survey job number, e.g. "SUV-JOB-2026-001". */
    public function nextJobNumber(): string
    {
        // Configurable Entries → Cadastral, falling back to the config file.
        $cfg = app(CadastralSettings::class)->jobNumberFormat();

        return $this->next(
            'job_number',
            (string) $cfg['format'],
            (int) $cfg['serial_pad'],
            Schema::connection(self::CONN)->hasColumn('survey_computation_files', 'job_no')
                ? ['survey_computation_files' => 'job_no']
                : []
        );
    }

    /**
     * The number the next registered job should get, for display on the form.
     * Not reserved: the real one is allocated on save, so it can differ if
     * someone else registers a job first.
     */
    public function previewJobNumber(): string
    {
        return $this->nextJobNumber();
    }

    /** Next Instruction to Surveyor number, e.g. "ITS/2026/0001". */
    public function nextItsNumber(): string
    {
        $cfg = app(CadastralSettings::class)->itsNumberFormat();

        return $this->next(
            'its_number',
            (string) $cfg['format'],
            (int) $cfg['serial_pad']
        );
    }

    /**
     * Highest serial for this year in $column, plus one, formatted.
     *
     * Reads with UPDLOCK/HOLDLOCK so two officers clicking Generate at the same
     * moment serialise rather than both seeing the same maximum. That only holds
     * inside a transaction, which is why every caller opens one.
     */
    private function next(string $column, string $format, int $pad, array $alsoIn = []): string
    {
        $year = date('Y');

        // The stem is everything before the serial, so "SUV-JOB-2026-" — matching
        // on it keeps last year's numbers out of this year's pool.
        $stem = str_replace(['{year}', '{serial}'], [$year, ''], $format);

        // $alsoIn: other [table => column] pools sharing this series.
        $rows = [];

        foreach (['cadastral_survey_jobs' => $column] + $alsoIn as $table => $col) {
            $rows = array_merge($rows, DB::connection(self::CONN)
                ->select(
                    "SELECT TOP 500 [{$col}] AS value
                       FROM [{$table}] WITH (UPDLOCK, HOLDLOCK)
                      WHERE [{$col}] LIKE ?
                   ORDER BY LEN([{$col}]) DESC, [{$col}] DESC",
                    [$stem . '%']
                ));
        }

        $highest = 0;

        foreach ($rows as $row) {
            if (preg_match('/(\d+)\s*$/', (string) $row->value, $m)) {
                $highest = max($highest, (int) $m[1]);
            }
        }

        $serial = str_pad((string) ($highest + 1), $pad, '0', STR_PAD_LEFT);

        return str_replace(['{year}', '{serial}'], [$year, $serial], $format);
    }

    /**
     * Whether the format is still the shipped placeholder. The screens surface
     * this so nobody issues a hundred numbers in a format that has to be redone.
     */
    public function formatIsPlaceholder(): bool
    {
        return app(CadastralSettings::class)->jobNumberFormat()['is_placeholder'];
    }

    /** Guard against a number that somehow already exists. */
    public function isTaken(string $jobNumber): bool
    {
        return CadastralSurveyJob::withTrashed()->where('job_number', $jobNumber)->exists();
    }
}
