<?php

namespace App\Console\Commands;

use App\Services\PlotWorkflowService;
use App\Services\TemporaryFileNumberResolver;
use App\Support\DecommissionScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decommission the temporary "(T)" records belonging to files that are already on the
 * Decommissioned Files list.
 *
 * A temporary number `X(T)` and its base `X` are the SAME physical file, but they are
 * stored in different columns, and PlotWorkflowService::decommissionFiles() matches
 * `file_indexings.file_number` / `fileNumber.mlsfNo` literally. A temp record whose
 * `file_number` is EMPTY — the "(T)" in `temp_file_no` is its whole identity — is
 * therefore never reached: the archive row is written, the live temp row stays
 * unflagged, and the retired file keeps surfacing as active. This command closes that
 * gap for the files already decommissioned, and is safe to re-run.
 *
 * Scope: every row of the Decommissioned Files list — the archive table AND the
 * `false_decommissioning = 1` rows the main list hides — plus files flagged on
 * fileNumber. Pass --real-only to leave the false-decommissioning rows alone.
 *
 * NOTE on false decommissioning: a `false_decommissioning = 1` row is a Title Status
 * flag raised from File Indexing — the BASE file is live and not decommissioned. Its
 * temp record is still a spent placeholder, so retiring the temp is coherent, but only
 * where the temp has a row of its OWN. Where the "(T)" hangs off the row that is also
 * filed under the live base number, flagging it would decommission the live file, so
 * those records are reported and SKIPPED unless --include-shared is passed.
 *
 * Nothing is deleted and no new decommissioned_files row is written: the temp is the
 * same file as its base, which the archive already records. The live rows are flagged
 * in place through PlotWorkflowService::flagDecommissioned(), so they carry exactly the
 * column set every other decommissioning writes, with successor_file_no pointing at the
 * base file.
 */
class DecommissionTempFiles extends Command
{
    protected $signature = 'decommissioning:temp-files
        {--apply : Actually write changes. Omit for a read-only dry-run report.}
        {--file= : Restrict to a single decommissioned file number (base or "(T)" form).}
        {--real-only : Skip the false_decommissioning = 1 rows (Title Status flags).}
        {--include-shared : Also flag a "(T)" that shares its row with a live base file. DANGEROUS — this decommissions the live file.}
        {--limit=0 : Cap the number of decommissioned files processed (0 = all).}';

    protected $description = 'Decommission the temporary "(T)" records of files already on the Decommissioned Files list.';

    public function __construct(
        private TemporaryFileNumberResolver $resolver,
        private PlotWorkflowService $workflow
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply         = (bool) $this->option('apply');
        $realOnly      = (bool) $this->option('real-only');
        $includeShared = (bool) $this->option('include-shared');
        $only          = trim((string) $this->option('file'));
        $limit         = (int) $this->option('limit');

        $this->info($apply ? 'MODE: APPLY — changes will be written.' : 'MODE: DRY RUN — no changes will be written. Re-run with --apply.');
        $this->line('');

        $files = $this->decommissionedFiles($realOnly, $only);
        if ($files->isEmpty()) {
            $this->warn('No decommissioned files matched.');

            return self::SUCCESS;
        }

        // One sweep of every stored "(T)", then matched in memory. A per-file lookup
        // would be hundreds of round trips, and the wildcard scan is what guarantees an
        // unexpected stored shape is not missed.
        $this->line('Scanning for stored "(T)" numbers...');
        $index = $this->resolver->scanAll();
        $this->line('Found ' . count($index) . ' distinct temporary file number(s) across the registry.');
        $this->line('');

        $rows = [];
        $counts = ['flagged' => 0, 'already' => 0, 'skipped_shared' => 0, 'skipped_table' => 0, 'files' => 0];
        $processed = 0;

        foreach ($files as $file) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $matches = [];
            $seen = [];
            foreach ($file->numbers as $number) {
                $key = TemporaryFileNumberResolver::baseKey($number);
                if ($key === '' || isset($seen[$key]) || !isset($index[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $matches[] = ['base' => trim($number), 'temp' => $index[$key]];
            }

            if (empty($matches)) {
                continue;
            }

            $processed++;
            $counts['files']++;

            foreach ($matches as $match) {
                $tempNo = $match['temp']['temp_file_no'];
                $reason = 'Temporary File Retirement: temporary number ' . $tempNo
                    . ' retired with decommissioned file ' . $match['base'];

                foreach ($match['temp']['records'] as $record) {
                    $target = $record['table'] . '.' . $record['column'] . '#' . $record['id'];

                    if (!in_array($record['table'], TemporaryFileNumberResolver::FLAGGABLE_TABLES, true)) {
                        $counts['skipped_table']++;
                        $rows[] = [$match['base'], $tempNo, $target, 'skip: record table, not flagged'];
                        continue;
                    }

                    if ($record['shared'] && !$includeShared) {
                        $counts['skipped_shared']++;
                        $rows[] = [$match['base'], $tempNo, $target, 'SKIP: shared with live file ' . $record['identity']];
                        continue;
                    }

                    if ($this->alreadyFlagged($record['table'], $record['id'])) {
                        $counts['already']++;
                        $rows[] = [$match['base'], $tempNo, $target, 'already decommissioned'];
                        continue;
                    }

                    if (!$apply) {
                        $counts['flagged']++;
                        $rows[] = [$match['base'], $tempNo, $target, 'would flag' . ($record['shared'] ? ' (SHARED)' : '')];
                        continue;
                    }

                    // Stamp through the canonical writer so the temp row carries the same
                    // columns as every other decommissioning, and points back at its base.
                    $written = $this->workflow->flagDecommissioned(
                        $record['table'],
                        function ($query) use ($record) {
                            $query->where('id', $record['id']);
                        },
                        $reason,
                        'System (decommissioning:temp-files)',
                        $match['base']
                    );

                    if ($written > 0) {
                        $counts['flagged']++;
                        $rows[] = [$match['base'], $tempNo, $target, 'FLAGGED' . ($record['shared'] ? ' (SHARED)' : '')];
                    } else {
                        $rows[] = [$match['base'], $tempNo, $target, 'no rows written — check the log'];
                    }
                }
            }
        }

        if (empty($rows)) {
            $this->warn('No decommissioned file has a "(T)" counterpart. Nothing to do.');

            return self::SUCCESS;
        }

        $this->table(['Decommissioned File', 'Temporary File No', 'Record', 'Action'], $rows);
        $this->line('');
        $this->info(sprintf(
            'Files with a temporary counterpart: %d  |  %s: %d  |  already decommissioned: %d',
            $counts['files'],
            $apply ? 'flagged' : 'would flag',
            $counts['flagged'],
            $counts['already']
        ));

        if ($counts['skipped_shared'] > 0) {
            $this->warn(sprintf(
                'Skipped %d record(s) where the "(T)" shares its row with a LIVE base file — flagging those would decommission the live file. Review them, then pass --include-shared if that is intended.',
                $counts['skipped_shared']
            ));
        }

        if ($counts['skipped_table'] > 0) {
            $this->line(sprintf(
                'Ignored %d record-table mention(s) (pra / deed_registrations / mls_file_no): those hold registration history and are never flagged by decommissioning.',
                $counts['skipped_table']
            ));
        }

        if (!$apply) {
            $this->line('');
            $this->comment('Dry run only. Re-run with --apply to write.');
        }

        return self::SUCCESS;
    }

    /**
     * The Decommissioned Files list, as rows of {label, numbers[]}.
     *
     * Mirrors FileDecommissioningController's union: the archive table plus the files
     * flagged on fileNumber. The archive's false_decommissioning rows are INCLUDED by
     * default — they are hidden from the main list but still hold a real file whose
     * temporary placeholder is spent.
     */
    private function decommissionedFiles(bool $realOnly, string $only)
    {
        $conn = DB::connection('sqlsrv');
        $wanted = $only === '' ? null : TemporaryFileNumberResolver::baseKey($only);

        $archive = $conn->table('decommissioned_files')
            ->when($realOnly, function ($query) {
                DecommissionScope::real($query);
            })
            ->orderBy('id')
            ->get(['id', 'mls_file_no', 'kangis_file_no', 'new_kangis_file_no', 'file_name', 'false_decommissioning']);

        $files = $archive->map(function ($row) {
            return (object) [
                'source'  => $row->false_decommissioning == DecommissionScope::FALSE_DECOMMISSIONING ? 'archive(false)' : 'archive',
                'id'      => $row->id,
                'numbers' => [$row->mls_file_no, $row->kangis_file_no, $row->new_kangis_file_no],
            ];
        });

        if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'is_decommissioned')) {
            $flagged = $conn->table('fileNumber')
                ->where('is_decommissioned', 1)
                ->where(function ($query) {
                    $query->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->orderBy('id')
                ->get(['id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo']);

            $files = $files->concat($flagged->map(function ($row) {
                return (object) [
                    'source'  => 'fileNumber',
                    'id'      => $row->id,
                    'numbers' => [$row->mlsfNo, $row->kangisFileNo, $row->NewKANGISFileNo],
                ];
            }));
        }

        return $files
            ->map(function ($file) {
                $file->numbers = array_values(array_filter(array_map(function ($n) {
                    return trim((string) $n);
                }, $file->numbers), function ($n) {
                    return $n !== '';
                }));

                return $file;
            })
            ->filter(function ($file) use ($wanted) {
                if (empty($file->numbers)) {
                    return false;
                }

                if ($wanted === null) {
                    return true;
                }

                foreach ($file->numbers as $number) {
                    if (TemporaryFileNumberResolver::baseKey($number) === $wanted) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * Is this row already decommissioned? Re-running must not restamp a row with this
     * command's reason and overwrite the genuine one it was decommissioned under.
     */
    private function alreadyFlagged(string $table, $id): bool
    {
        if (!Schema::connection('sqlsrv')->hasColumn($table, 'is_decommissioned')) {
            return false;
        }

        return DB::connection('sqlsrv')->table($table)
            ->where('id', $id)
            ->where('is_decommissioned', 1)
            ->exists();
    }
}
