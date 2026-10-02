<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reconcile file_indexings.prop_id against PropID_Master, the canonical registry.
 *
 * Background: a bulk import on 2025-11-20 stamped ~10.9k file_indexings rows with a
 * sequential ROW ORDINAL instead of the file's real prop_id (fi_id - prop_id clusters
 * tightly around ~13,700 across 118 offsets). Because PropID_Master maps those stray
 * ordinals to real — but unrelated — files, Legal Search's contamination guard treats
 * the unrelated file as a legitimate "alias of the searched parcel" and pulls its
 * transactions onto the timeline (e.g. RES-1992-2918 showing COM-RC-1982-70's mortgage).
 *
 * PropID_Master was validated as the repair source: on a random sample of 300 affected
 * files, its prop_id agreed with the file's own pra/CofO_staging rows 296/296 times
 * (4 had no transactions), while the stored file_indexings.prop_id agreed just 1/300.
 *
 * Safe by design (mirrors propid:remediate-deed):
 *   - Default is a DRY-RUN (read-only). Writes only with --apply.
 *   - --apply first copies every affected row's old value into a backup table, so the
 *     whole run is reversible via --rollback.
 *   - Skips rows whose prop_id is a comma-separated list (lineage — needs a human).
 *   - Skips rows whose current prop_id is referenced as a parent_prop_id anywhere.
 *   - Skips files participating in an OP/TOT relationship (shared prop_id is legit).
 *   - Skips files absent from PropID_Master (nothing authoritative to repair toward).
 *   - Never invents a prop_id — it only ever copies one PropID_Master already holds.
 */
class ReconcileIndexingPropIds extends Command
{
    protected $signature = 'propid:reconcile-indexing
        {--apply : Actually write changes. Omit for a read-only dry-run report.}
        {--rollback : Restore prop_ids from the backup table written by the last --apply run.}
        {--limit=0 : Optionally cap the number of rows examined (0 = all).}
        {--chunk=1000 : Rows per batch.}';

    protected $description = 'Reconcile file_indexings.prop_id against PropID_Master (dry-run by default); repairs ordinal/mis-assigned ids.';

    private const BACKUP_TABLE = 'file_indexings_propid_backup';

    private const MASTER_ALIAS_COLUMNS = [
        'primary_file_number_norm',
        'mlsFNo_norm',
        'kangisFileNo_norm',
        'NewKANGISFileno_norm',
        'temp_fileno_norm',
    ];

    private const PARENT_PROP_TABLES = ['pra', 'file_indexings', 'fileNumber', 'deprecated_records'];

    public function handle(): int
    {
        $conn = DB::connection('sqlsrv');

        if ($this->option('rollback')) {
            return $this->rollback($conn);
        }

        $apply = (bool) $this->option('apply');
        $limit = (int) $this->option('limit');
        $chunk = max(100, (int) $this->option('chunk'));

        $this->info(($apply ? '[APPLY] ' : '[DRY-RUN] ') . 'Reconciling file_indexings.prop_id against PropID_Master…');

        if ($apply) {
            $this->ensureBackupTable($conn);
        }

        $this->line('Loading guard sets…');
        $parentPropIds = $this->loadParentPropIds($conn);
        $opTotFiles = $this->loadOpTotFileNumbers($conn);
        $this->line(sprintf(
            '  %d parent_prop_id values, %d OP/TOT file numbers.',
            count($parentPropIds),
            count($opTotFiles)
        ));

        $log = [];
        $counts = [
            'ok' => 0,
            'repair' => 0,
            'skip_list' => 0,
            'skip_parentref' => 0,
            'skip_optot' => 0,
            'skip_no_master' => 0,
            'error' => 0,
        ];
        $examined = 0;
        $runId = now()->format('Ymd-His');

        $query = $conn->table('file_indexings')
            ->whereNull('deleted_at')
            ->whereNotNull('prop_id')
            ->whereRaw("LTRIM(RTRIM(CAST(prop_id AS NVARCHAR(200)))) <> ''")
            ->select('id', 'file_number', 'temp_file_no', 'prop_id', 'parent_prop_id', 'registry')
            ->orderBy('id');

        $query->chunk($chunk, function ($rows) use (
            $conn, $apply, $limit, $runId, $parentPropIds, $opTotFiles, &$log, &$counts, &$examined
        ) {
            // One PropID_Master lookup for the whole batch, hitting the indexed *_norm columns.
            $keys = [];
            foreach ($rows as $r) {
                foreach ([$r->file_number, $r->temp_file_no] as $v) {
                    $v = strtoupper(trim((string) $v));
                    if ($v !== '') {
                        $keys[$v] = true;
                    }
                }
            }
            $aliasToPids = $this->loadMasterAliases($conn, array_keys($keys));

            $pending = [];

            foreach ($rows as $r) {
                if ($limit > 0 && $examined >= $limit) {
                    break;
                }
                $examined++;

                try {
                    $raw = trim((string) $r->prop_id);
                    $pids = array_values(array_filter(array_map('trim', explode(',', $raw))));

                    $fn = strtoupper(trim((string) $r->file_number));
                    $tn = strtoupper(trim((string) $r->temp_file_no));

                    $expected = [];
                    foreach ([$fn, $tn] as $k) {
                        if ($k !== '' && isset($aliasToPids[$k])) {
                            $expected += $aliasToPids[$k];
                        }
                    }

                    // Nothing authoritative to repair toward.
                    if (empty($expected)) {
                        $counts['skip_no_master']++;
                        continue;
                    }

                    // Already correct: at least one stored id genuinely belongs to this file.
                    foreach ($pids as $p) {
                        if (isset($expected[$p])) {
                            $counts['ok']++;
                            continue 2;
                        }
                    }

                    // Multi-valued prop_id encodes lineage — never rewrite automatically.
                    if (count($pids) > 1) {
                        $counts['skip_list']++;
                        $log[] = [$r->id, $r->file_number, $raw, '', 'SKIP: multi-valued prop_id'];
                        continue;
                    }

                    // The stray id may be load-bearing as someone's parent.
                    $isParent = false;
                    foreach ($pids as $p) {
                        if (isset($parentPropIds[$p])) {
                            $isParent = true;
                            break;
                        }
                    }
                    if ($isParent) {
                        $counts['skip_parentref']++;
                        $log[] = [$r->id, $r->file_number, $raw, '', 'SKIP: referenced as parent_prop_id'];
                        continue;
                    }

                    // OP/TOT parcels legitimately share one prop_id across two files.
                    if ($fn !== '' && isset($opTotFiles[$fn])) {
                        $counts['skip_optot']++;
                        $log[] = [$r->id, $r->file_number, $raw, '', 'SKIP: OP/TOT relationship'];
                        continue;
                    }

                    // Exactly one canonical id for this file, or we do not guess.
                    $expectedIds = array_keys($expected);
                    if (count($expectedIds) !== 1) {
                        $counts['skip_no_master']++;
                        $log[] = [$r->id, $r->file_number, $raw, implode('|', $expectedIds), 'SKIP: ambiguous master mapping'];
                        continue;
                    }

                    $new = $expectedIds[0];
                    $counts['repair']++;
                    $log[] = [$r->id, $r->file_number, $raw, $new, 'REPAIR'];

                    if ($apply) {
                        $pending[] = ['id' => $r->id, 'old' => $raw, 'new' => $new, 'file_number' => $r->file_number];
                    }
                } catch (Throwable $e) {
                    $counts['error']++;
                    $log[] = [$r->id, $r->file_number ?? '', $r->prop_id ?? '', '', 'ERROR: ' . $e->getMessage()];
                }
            }

            if ($apply && $pending) {
                $this->writeBatch($conn, $pending, $runId);
            }

            return !($limit > 0 && $examined >= $limit);
        });

        $csv = $this->writeReport($log, $apply, $runId);

        $this->newLine();
        $this->table(
            ['examined', 'already ok', 'to repair', 'skip list', 'skip parent-ref', 'skip OP/TOT', 'skip no-master', 'errors'],
            [[
                $examined,
                $counts['ok'],
                $counts['repair'],
                $counts['skip_list'],
                $counts['skip_parentref'],
                $counts['skip_optot'],
                $counts['skip_no_master'],
                $counts['error'],
            ]]
        );
        $this->info(($apply ? 'Applied. ' : 'Dry-run only (no changes written). ') . 'Report: ' . $csv);

        if (!$apply) {
            $this->comment('Review the report, then re-run with --apply to write changes.');
        } else {
            $this->comment('Backup written to ' . self::BACKUP_TABLE . ' (run_id ' . $runId . '). Undo with --rollback.');
        }

        return self::SUCCESS;
    }

    /**
     * Map UPPER(trimmed) file number => [prop_id => true] for the batch, using the
     * indexed *_norm columns so this stays a seek rather than a scan.
     */
    private function loadMasterAliases($conn, array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        // SQL Server caps a statement at 2100 parameters, and each key is bound once per
        // alias column — so the slice must stay under 2100 / count(MASTER_ALIAS_COLUMNS).
        $perSlice = (int) floor(2000 / count(self::MASTER_ALIAS_COLUMNS));

        $map = [];
        foreach (array_chunk($keys, $perSlice) as $slice) {
            $rows = $conn->table('PropID_Master')
                ->where(function ($q) use ($slice) {
                    foreach (self::MASTER_ALIAS_COLUMNS as $col) {
                        $q->orWhereIn($col, $slice);
                    }
                })
                ->get(array_merge(['prop_id'], self::MASTER_ALIAS_COLUMNS));

            foreach ($rows as $r) {
                $pid = trim((string) $r->prop_id);
                if ($pid === '') {
                    continue;
                }
                foreach (self::MASTER_ALIAS_COLUMNS as $col) {
                    $v = strtoupper(trim((string) ($r->{$col} ?? '')));
                    if ($v !== '') {
                        $map[$v][$pid] = true;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Every value used as a parent_prop_id anywhere, loaded once. Per-row EXISTS queries
     * cost ~5 round-trips per candidate (~55k for a full run); this is two scans total.
     */
    private function loadParentPropIds($conn): array
    {
        $set = [];
        foreach (self::PARENT_PROP_TABLES as $t) {
            try {
                $conn->table($t)
                    ->whereNotNull('parent_prop_id')
                    ->select('parent_prop_id')
                    ->orderBy('parent_prop_id')
                    ->chunk(20000, function ($rows) use (&$set) {
                        foreach ($rows as $r) {
                            // parent_prop_id may itself be a comma-separated lineage list.
                            foreach (explode(',', (string) $r->parent_prop_id) as $p) {
                                $p = trim($p);
                                if ($p !== '') {
                                    $set[$p] = true;
                                }
                            }
                        }
                    });
            } catch (Throwable $e) {
                // Table/column absent — ignore.
            }
        }

        return $set;
    }

    /**
     * File numbers participating in an OP/TOT relationship, loaded once. Such files
     * legitimately share a single prop_id across two file numbers.
     */
    private function loadOpTotFileNumbers($conn): array
    {
        $set = [];
        try {
            $conn->table('pra')
                ->where(function ($q) {
                    $q->where('instrument_type', 'like', '%Occupancy Permit%')
                        ->orWhere('instrument_type', 'like', '%Transfer of Title%')
                        ->orWhere('instrument_type', 'like', '%(OP)%');
                })
                ->select('fileno', 'mlsFNo')
                ->orderBy('id')
                ->chunk(20000, function ($rows) use (&$set) {
                    foreach ($rows as $r) {
                        foreach ([$r->fileno, $r->mlsFNo] as $v) {
                            $v = strtoupper(trim((string) $v));
                            if ($v !== '') {
                                $set[$v] = true;
                            }
                        }
                    }
                });
        } catch (Throwable $e) {
            // Table absent — ignore.
        }

        return $set;
    }

    private function ensureBackupTable($conn): void
    {
        if (Schema::connection('sqlsrv')->hasTable(self::BACKUP_TABLE)) {
            return;
        }

        $conn->statement('
            CREATE TABLE ' . self::BACKUP_TABLE . ' (
                id BIGINT IDENTITY(1,1) PRIMARY KEY,
                run_id VARCHAR(32) NOT NULL,
                file_indexing_id BIGINT NOT NULL,
                file_number NVARCHAR(255) NULL,
                old_prop_id NVARCHAR(200) NULL,
                new_prop_id NVARCHAR(200) NULL,
                created_at DATETIME NOT NULL DEFAULT GETDATE()
            )
        ');
        $conn->statement('CREATE INDEX IX_' . self::BACKUP_TABLE . '_run ON ' . self::BACKUP_TABLE . ' (run_id)');

        $this->line('Created backup table ' . self::BACKUP_TABLE . '.');
    }

    /** Back up then update one batch, inside a transaction. */
    private function writeBatch($conn, array $pending, string $runId): void
    {
        $conn->transaction(function () use ($conn, $pending, $runId) {
            $backup = [];
            foreach ($pending as $p) {
                $backup[] = [
                    'run_id' => $runId,
                    'file_indexing_id' => $p['id'],
                    'file_number' => $p['file_number'],
                    'old_prop_id' => $p['old'],
                    'new_prop_id' => $p['new'],
                ];
            }
            foreach (array_chunk($backup, 200) as $slice) {
                $conn->table(self::BACKUP_TABLE)->insert($slice);
            }

            foreach ($pending as $p) {
                $conn->table('file_indexings')
                    ->where('id', $p['id'])
                    ->update(['prop_id' => $p['new']]);
            }
        });
    }

    private function rollback($conn): int
    {
        if (!Schema::connection('sqlsrv')->hasTable(self::BACKUP_TABLE)) {
            $this->error('No backup table found — nothing to roll back.');
            return self::FAILURE;
        }

        $runId = $conn->table(self::BACKUP_TABLE)->max('run_id');
        if ($runId === null) {
            $this->error('Backup table is empty — nothing to roll back.');
            return self::FAILURE;
        }

        $rows = $conn->table(self::BACKUP_TABLE)->where('run_id', $runId)->get();
        $this->warn('Rolling back run ' . $runId . ' (' . $rows->count() . ' rows)…');

        $restored = 0;
        $conn->transaction(function () use ($conn, $rows, &$restored) {
            foreach ($rows as $r) {
                // Only restore rows still holding the value this run wrote, so a later
                // manual correction is never clobbered.
                $affected = $conn->table('file_indexings')
                    ->where('id', $r->file_indexing_id)
                    ->where('prop_id', $r->new_prop_id)
                    ->update(['prop_id' => $r->old_prop_id]);
                $restored += $affected;
            }
        });

        $conn->table(self::BACKUP_TABLE)->where('run_id', $runId)->delete();

        $this->info('Restored ' . $restored . ' of ' . $rows->count() . ' rows.');
        if ($restored < $rows->count()) {
            $this->comment('Rows skipped were changed after the run — left untouched on purpose.');
        }

        return self::SUCCESS;
    }

    private function writeReport(array $log, bool $apply, string $runId): string
    {
        $dir = storage_path('app/propid-remediation');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $csv = $dir . '/indexing-reconcile-' . ($apply ? 'applied' : 'dryrun') . '-' . $runId . '.csv';
        $fh = fopen($csv, 'w');
        fputcsv($fh, ['file_indexing_id', 'file_number', 'old_prop_id', 'new_prop_id', 'action']);
        foreach ($log as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);

        return $csv;
    }
}
