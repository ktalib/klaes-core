<?php

namespace App\Services\Cadastral;

use App\Models\Cadastral\CadastralSurveyJob;
use Illuminate\Support\Facades\DB;

/**
 * Survey job numbers and Instruction-to-Surveyor numbers.
 *
 * THE FORMAT IS UNCONFIRMED. The concept note calls for a SURCON-compliant
 * number and nobody in this codebase knows the pattern SURCON mandates.
 * config('cadastral_module.job_number.format') is a KLAES-local placeholder and
 * says so; cadastral-module:install warns while it is still the default. Confirm
 * with the Surveyor-General's office before go-live, or numbers issued now will
 * need reissuing.
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

    /** Next survey job number, e.g. "KN/CAD/2026/0001". */
    public function nextJobNumber(): string
    {
        // Configurable Entries → Cadastral, falling back to the config file.
        $cfg = app(CadastralSettings::class)->jobNumberFormat();

        return $this->next(
            'job_number',
            (string) $cfg['format'],
            (int) $cfg['serial_pad']
        );
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
    private function next(string $column, string $format, int $pad): string
    {
        $year = date('Y');

        // The stem is everything before the serial, so "KN/CAD/2026/" — matching
        // on it keeps last year's numbers out of this year's pool.
        $stem = str_replace(['{year}', '{serial}'], [$year, ''], $format);

        $rows = DB::connection(self::CONN)
            ->select(
                "SELECT TOP 500 [{$column}] AS value
                   FROM [cadastral_survey_jobs] WITH (UPDLOCK, HOLDLOCK)
                  WHERE [{$column}] LIKE ?
               ORDER BY LEN([{$column}]) DESC, [{$column}] DESC",
                [$stem . '%']
            );

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
