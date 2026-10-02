<?php

namespace App\Console\Commands;

use App\Services\GenderNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill file_indexings.gender and mls_file_no.gender.
 *
 * The gender columns landed on 2026-07-28 and are nullable, so they only carry
 * values for rows created after that date: 6 of 133,779 on file_indexings and
 * 2 of 6,069 on mls_file_no when this command was written. Every PRS gender
 * chart is empty until that changes, and 11 of the report's 14 sections need it.
 *
 * Four passes, highest confidence first. A row is written once and never
 * revisited, so a captured value can never be overwritten by an inferred one.
 *
 *   1 captured      stamp provenance on values that were already there
 *   2 oss_sex       copy oss_applications.sex — captured on the OSS form
 *   3 pair          copy across the mls_file_no <-> file_indexings pair
 *   4 inference     GenderNormalizer over the holder name
 *   5 pair (again)  sync whatever pass 4 resolved on one side only
 *
 * Every write records gender_source, so the whole backfill reverses with --reset.
 *
 * See docs/prs-2025/20-live-data-implementation.md for why this exists.
 */
class BackfillGender extends Command
{
    protected $signature = 'gender:backfill
                            {--connection=sqlsrv : Database connection to run against}
                            {--dry-run : Classify and report, write nothing}
                            {--reset : Undo the backfill (clears every row whose gender_source is not "captured")}
                            {--chunk=2000 : Rows read per batch}';

    protected $description = 'Backfill gender on file_indexings and mls_file_no from OSS records, the commissioning/indexing pair, and holder-name inference. Records provenance in gender_source.';

    /** table => name column carrying the holder, and whether the enum allows Government. */
    private const TARGETS = [
        'file_indexings' => ['name' => 'file_title', 'key' => 'file_number',       'government' => false],
        'mls_file_no'    => ['name' => 'file_name',  'key' => 'full_file_number',  'government' => true],
    ];

    public function handle(GenderNormalizer $normalizer): int
    {
        $connName = $this->option('connection');
        $conn     = DB::connection($connName);
        $dryRun   = (bool) $this->option('dry-run');

        foreach (array_keys(self::TARGETS) as $table) {
            if (!Schema::connection($connName)->hasColumn($table, 'gender_source')) {
                $this->error("$table.gender_source is missing. Run the migration first:");
                $this->line('  php artisan migrate --database=sqlsrv');

                return self::FAILURE;
            }
        }

        if ($this->option('reset')) {
            return $this->reset($conn, $dryRun);
        }

        $this->preflight($conn);

        if ($dryRun) {
            $this->warn('--dry-run set: nothing will be written.');
        }

        $this->stampCaptured($conn, $dryRun);
        $this->fromOssApplications($conn, $dryRun);
        $this->fromPair($conn, $dryRun, 'pass 3');
        $this->fromNames($conn, $normalizer, $dryRun);
        $this->fromPair($conn, $dryRun, 'pass 5');

        $this->newLine();
        $this->info('Final coverage:');
        $this->report($conn);

        return self::SUCCESS;
    }

    // ── Passes ──────────────────────────────────────────────────────────────

    /** Pass 1 — provenance for values that were captured on a form. */
    private function stampCaptured($conn, bool $dryRun): void
    {
        $this->newLine();
        $this->info('Pass 1 — stamp captured values');

        foreach (self::TARGETS as $table => $meta) {
            $n = (int) $conn->table($table)->whereNotNull('gender')->whereNull('gender_source')->count();

            if (!$dryRun && $n > 0) {
                $conn->table($table)
                    ->whereNotNull('gender')
                    ->whereNull('gender_source')
                    ->update(['gender_source' => GenderNormalizer::SOURCE_CAPTURED]);
            }

            $this->line(sprintf('  %-16s %6d marked captured', $table, $n));
        }
    }

    /**
     * Pass 2 — oss_applications.sex. Captured on the OSS application form and the
     * only gender population in KLAES with real coverage (95.4%).
     */
    private function fromOssApplications($conn, bool $dryRun): void
    {
        $this->newLine();
        $this->info('Pass 2 — oss_applications.sex');

        if (!Schema::connection($this->option('connection'))->hasTable('oss_applications')) {
            $this->line('  oss_applications not present, skipped');

            return;
        }

        foreach (self::TARGETS as $table => $meta) {
            $key = $meta['key'];

            $sql = "
                UPDATE t
                SET t.gender = CASE WHEN UPPER(LTRIM(RTRIM(o.sex))) = 'FEMALE' THEN 'Female' ELSE 'Male' END,
                    t.gender_source = ?
                FROM $table t
                INNER JOIN oss_applications o
                        ON LTRIM(RTRIM(o.file_no)) = LTRIM(RTRIM(t.$key))
                WHERE t.gender IS NULL
                  AND o.sex IS NOT NULL
                  AND UPPER(LTRIM(RTRIM(o.sex))) IN ('MALE', 'FEMALE')
                  AND ISNULL(o.is_deleted, 0) = 0
            ";

            $count = (int) $conn->selectOne("
                SELECT COUNT(*) n
                FROM $table t
                INNER JOIN oss_applications o
                        ON LTRIM(RTRIM(o.file_no)) = LTRIM(RTRIM(t.$key))
                WHERE t.gender IS NULL
                  AND o.sex IS NOT NULL
                  AND UPPER(LTRIM(RTRIM(o.sex))) IN ('MALE', 'FEMALE')
                  AND ISNULL(o.is_deleted, 0) = 0
            ")->n;

            if (!$dryRun && $count > 0) {
                $conn->statement($sql, [GenderNormalizer::SOURCE_OSS]);
            }

            $this->line(sprintf('  %-16s %6d filled from OSS', $table, $count));
        }
    }

    /**
     * Pass 3 / 5 — propagate across the commissioning/indexing pair.
     *
     * FileIndexingService::createFromMlsFileNumber() already copies gender at
     * creation, but it has only done so since 2026-07-28 and copies null when the
     * commissioning row is itself null. This closes both directions after the fact.
     */
    private function fromPair($conn, bool $dryRun, string $label): void
    {
        $this->newLine();
        $this->info("$label — propagate across the mls_file_no <-> file_indexings pair");

        $directions = [
            'mls_file_no -> file_indexings' => "
                UPDATE t SET t.gender = s.gender, t.gender_source = ?
                FROM file_indexings t
                INNER JOIN mls_file_no s
                        ON LTRIM(RTRIM(s.full_file_number)) = LTRIM(RTRIM(t.file_number))
                WHERE t.gender IS NULL AND s.gender IS NOT NULL AND s.gender <> 'Government'
            ",
            'file_indexings -> mls_file_no' => "
                UPDATE t SET t.gender = s.gender, t.gender_source = ?
                FROM mls_file_no t
                INNER JOIN file_indexings s
                        ON LTRIM(RTRIM(s.file_number)) = LTRIM(RTRIM(t.full_file_number))
                WHERE t.gender IS NULL AND s.gender IS NOT NULL
            ",
        ];

        foreach ($directions as $name => $sql) {
            if ($dryRun) {
                $this->line(sprintf('  %-32s (dry run, not counted — depends on prior passes)', $name));
                continue;
            }

            $affected = $conn->affectingStatement($sql, [GenderNormalizer::SOURCE_PAIR]);
            $this->line(sprintf('  %-32s %6d propagated', $name, $affected));
        }
    }

    /** Pass 4 — infer from the holder name. */
    private function fromNames($conn, GenderNormalizer $normalizer, bool $dryRun): void
    {
        $this->newLine();
        $this->info('Pass 4 — infer from holder name');

        foreach (self::TARGETS as $table => $meta) {
            $col     = $meta['name'];
            $tally   = [];
            $pending = [];
            $scanned = 0;
            $written = 0;

            $conn->table($table)
                ->select('id', $col)
                ->whereNull('gender')
                ->whereNotNull($col)
                ->orderBy('id')
                ->chunkById((int) $this->option('chunk'), function ($rows) use (
                    $normalizer, $meta, $col, &$tally, &$pending, &$scanned, &$written, $conn, $table, $dryRun
                ) {
                    foreach ($rows as $row) {
                        $scanned++;
                        $result = $normalizer->classify($row->{$col}, $meta['government']);

                        if ($result === null) {
                            continue;
                        }

                        [$gender, $source] = $result;
                        $bucket = $gender . '|' . $source;

                        $tally[$bucket] = ($tally[$bucket] ?? 0) + 1;
                        $pending[$bucket][] = $row->id;
                        $written++;

                        if (count($pending[$bucket]) >= 500) {
                            $this->flush($conn, $table, $bucket, $pending[$bucket], $dryRun);
                            $pending[$bucket] = [];
                        }
                    }
                });

            foreach ($pending as $bucket => $ids) {
                if ($ids) {
                    $this->flush($conn, $table, $bucket, $ids, $dryRun);
                }
            }

            $this->line(sprintf('  %s — scanned %s unset rows, classified %s (%s%%)',
                $table,
                number_format($scanned),
                number_format($written),
                $scanned > 0 ? number_format($written / $scanned * 100, 1) : '0'
            ));

            ksort($tally);

            foreach ($tally as $bucket => $n) {
                [$gender, $source] = explode('|', $bucket);
                $this->line(sprintf('      %-11s via %-13s %7s', $gender, $source, number_format($n)));
            }
        }
    }

    private function flush($conn, string $table, string $bucket, array $ids, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        [$gender, $source] = explode('|', $bucket);

        foreach (array_chunk($ids, 500) as $slice) {
            $conn->table($table)
                ->whereIn('id', $slice)
                ->whereNull('gender')
                ->update(['gender' => $gender, 'gender_source' => $source]);
        }
    }

    // ── Reporting and reversal ──────────────────────────────────────────────

    private function preflight($conn): void
    {
        $this->info('Pre-flight coverage:');
        $this->report($conn);
    }

    private function report($conn): void
    {
        foreach (self::TARGETS as $table => $meta) {
            $total = (int) $conn->table($table)->count();
            $set   = (int) $conn->table($table)->whereNotNull('gender')->count();

            $this->line(sprintf('  %-16s %s / %s populated (%s%%)',
                $table,
                number_format($set),
                number_format($total),
                $total > 0 ? number_format($set / $total * 100, 2) : '0'
            ));

            $rows = $conn->table($table)
                ->selectRaw('gender, gender_source, COUNT(*) n')
                ->whereNotNull('gender')
                ->groupBy('gender', 'gender_source')
                ->orderByRaw('COUNT(*) DESC')
                ->get();

            foreach ($rows as $r) {
                $this->line(sprintf('      %-11s %-14s %7s',
                    $r->gender, $r->gender_source ?? '(none)', number_format($r->n)));
            }
        }
    }

    /** Undo everything this command wrote, leaving captured values untouched. */
    private function reset($conn, bool $dryRun): int
    {
        $this->warn('Reset: clearing every gender value that was not captured on a form.');

        foreach (self::TARGETS as $table => $meta) {
            $n = (int) $conn->table($table)
                ->whereNotNull('gender_source')
                ->where('gender_source', '<>', GenderNormalizer::SOURCE_CAPTURED)
                ->count();

            if (!$dryRun && $n > 0) {
                $conn->table($table)
                    ->whereNotNull('gender_source')
                    ->where('gender_source', '<>', GenderNormalizer::SOURCE_CAPTURED)
                    ->update(['gender' => null, 'gender_source' => null]);
            }

            $this->line(sprintf('  %-16s %6d cleared', $table, $n));
        }

        $this->newLine();
        $this->report($conn);

        return self::SUCCESS;
    }
}
