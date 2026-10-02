<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Step 1 of the PropID remediation — see docs/plans/PROP_ID_REMEDIATION_TRACKER.md
 *
 * Clears the prop_id of rows that are "squatting": holding an id that provably belongs
 * to a DIFFERENT parcel, with no PropID_Master row to repair toward.
 *
 * How they arise: propid:reconcile-indexing moved a file onto its canonical prop_id,
 * but a second file was already sitting on that id with a stale ordinal. That second
 * file has no PropID_Master row, so it fell into the reconciler's "skip no-master"
 * bucket and was never examined. The result is a corrected row colliding with an
 * uncorrectable one.
 *
 *     prop_id 11977
 *       CON-RES-1983-423   plot 419, Kawaji   repaired to 11977    master: 11977
 *       CON-AG-1984-184    FARMLAND, Yelwa    squatting on 11977   master: no row
 *
 * Why NULL rather than a new id: a NULL prop_id is INERT — LegalSearchService skips
 * prop_id expansion entirely for that file. A wrong one actively contaminates
 * timelines. NULL turns "wrong and potentially contaminating" into "absent and
 * harmless". Minting a fresh id instead would be an allocation decision, and could
 * create a second identity for a parcel that already exists under another spelling.
 *
 * Safety, mirroring propid:reconcile-indexing:
 *   - DRY RUN by default. Writes only with --apply.
 *   - --apply backs up every old value first; the run is reversible with --rollback.
 *   - Only touches a row that is ALL of: sharing a prop_id, on a different plot from
 *     the others, absent from PropID_Master, and not referenced as anyone's parent.
 *   - Never touches OP/ToT pairs or ST mothers, which share a prop_id by design.
 *   - Never touches a row the reconciler repaired — those are now correct.
 */
class ClearSquatterPropIds extends Command
{
    protected $signature = 'propid:clear-squatters
        {--apply : Actually write. Omit for a read-only dry-run report.}
        {--rollback : Restore prop_ids from the backup table written by the last --apply run.}
        {--limit=0 : Cap rows examined (0 = all).}';

    protected $description = 'Clear prop_ids held by a file that provably belongs to another parcel (dry-run by default).';

    private const CONNECTION = 'sqlsrv';
    private const BACKUP_TABLE = 'file_indexings_squatter_backup';

    /** Tables whose parent_prop_id makes a value load-bearing. */
    private const PARENT_PROP_TABLES = ['pra', 'file_indexings', 'fileNumber', 'deprecated_records', 'CofO_staging'];

    public function handle(): int
    {
        $conn = DB::connection(self::CONNECTION);

        if ($this->option('rollback')) {
            return $this->rollback($conn);
        }

        $apply = (bool) $this->option('apply');
        $this->info(($apply ? '[APPLY]' : '[DRY-RUN]') . ' Clearing squatter prop_ids…');

        if (!Schema::connection(self::CONNECTION)->hasTable('file_indexings_propid_backup')) {
            $this->error('file_indexings_propid_backup is missing — run propid:reconcile-indexing --apply first.');

            return self::FAILURE;
        }

        $this->line('Loading guard sets…');
        $repaired = $conn->table('file_indexings_propid_backup')->pluck('old_prop_id', 'file_indexing_id');
        $parentPropIds = $this->loadParentPropIds($conn);
        $opTot = $this->loadOpTotFileNumbers($conn);
        $this->line(sprintf('  %d repaired rows, %d parent ids, %d OP/TOT file numbers.',
            $repaired->count(), count($parentPropIds), count($opTot)));

        $clusters = $conn->table('file_indexings')
            ->select('prop_id', DB::raw('count(*) as total'))
            ->whereNotNull('prop_id')->where('prop_id', '<>', '')
            ->groupBy('prop_id')->havingRaw('count(*) > 1')
            ->get();

        if (($limit = (int) $this->option('limit')) > 0) {
            $clusters = $clusters->take($limit);
        }

        $counts = ['clusters' => 0, 'same_parcel' => 0, 'no_repaired' => 0, 'clear' => 0,
                   'skip_parentref' => 0, 'skip_optot' => 0, 'skip_has_master' => 0];
        $log = [];
        $pending = [];

        $bar = $this->output->createProgressBar($clusters->count());
        $bar->start();

        foreach ($clusters as $cluster) {
            $bar->advance();
            $counts['clusters']++;

            $members = $conn->table('file_indexings')
                ->where('prop_id', $cluster->prop_id)
                ->get(['id', 'file_number', 'prop_id', 'plot_number', 'district', 'file_title']);

            // Same parcel across several files (the KANGIS three-file model) is correct.
            $plots = array_values(array_diff(array_unique(array_map(
                fn ($m) => strtoupper(trim((string) $m->plot_number)), $members->all()
            )), ['PIECE OF LAND', '']));

            if (count($plots) <= 1) {
                $counts['same_parcel']++;
                continue;
            }

            // Only clusters this remediation assembled. A cluster nobody repaired is a
            // pre-existing bulk-allocation problem — step 4, and a policy decision.
            $repairedHere = $members->filter(fn ($m) => $repaired->has($m->id));

            if ($repairedHere->isEmpty()) {
                $counts['no_repaired']++;
                continue;
            }

            foreach ($members as $m) {
                if ($repaired->has($m->id)) {
                    continue;   // repaired => now canonical => leave alone
                }

                $fn = strtoupper(trim((string) $m->file_number));

                // Has its own authority: not a squatter, needs the reconciler instead.
                $master = $conn->table('PropID_Master')
                    ->where('primary_file_number_norm', $fn)->value('prop_id');

                if ($master !== null) {
                    $counts['skip_has_master']++;
                    continue;
                }

                if (isset($parentPropIds[trim((string) $m->prop_id)])) {
                    $counts['skip_parentref']++;
                    $log[] = [$m->id, $m->file_number, $m->prop_id, 'SKIP: referenced as parent_prop_id'];
                    continue;
                }

                if ($fn !== '' && isset($opTot[$fn])) {
                    $counts['skip_optot']++;
                    $log[] = [$m->id, $m->file_number, $m->prop_id, 'SKIP: OP/TOT relationship'];
                    continue;
                }

                if (str_starts_with($fn, 'ST-')) {
                    $counts['skip_optot']++;
                    $log[] = [$m->id, $m->file_number, $m->prop_id, 'SKIP: ST mother adopts a land file id'];
                    continue;
                }

                $counts['clear']++;
                $log[] = [$m->id, $m->file_number, $m->prop_id, 'CLEAR'];

                if ($apply) {
                    $pending[] = ['id' => $m->id, 'old' => $m->prop_id, 'file_number' => $m->file_number];
                }
            }
        }

        $bar->finish();
        $this->newLine(2);

        if ($apply && !empty($pending)) {
            $runId = date('Ymd-His');
            $this->ensureBackupTable($conn);

            foreach (array_chunk($pending, 500) as $batch) {
                $conn->transaction(function () use ($conn, $batch, $runId) {
                    foreach ($batch as $row) {
                        $conn->table(self::BACKUP_TABLE)->insert([
                            'run_id' => $runId,
                            'file_indexing_id' => $row['id'],
                            'file_number' => $row['file_number'],
                            'old_prop_id' => $row['old'],
                            'created_at' => now(),
                        ]);
                        // Only when the row still holds what we read, so a concurrent
                        // edit is left alone rather than silently overwritten.
                        $conn->table('file_indexings')
                            ->where('id', $row['id'])
                            ->where('prop_id', $row['old'])
                            ->update(['prop_id' => null]);
                    }
                });
            }

            $this->info("Backup written to " . self::BACKUP_TABLE . " (run_id {$runId}). Undo with --rollback.");
        }

        $this->table(
            ['clusters', 'same parcel', 'no repaired row', 'to clear', 'skip parent-ref', 'skip OP/TOT-ST', 'skip has-master'],
            [[$counts['clusters'], $counts['same_parcel'], $counts['no_repaired'], $counts['clear'],
              $counts['skip_parentref'], $counts['skip_optot'], $counts['skip_has_master']]]
        );

        $path = storage_path('app/propid-remediation/squatters-' . ($apply ? 'applied' : 'dryrun') . '-' . date('Ymd-His') . '.csv');
        @mkdir(dirname($path), 0775, true);

        if ($h = @fopen($path, 'w')) {
            fputcsv($h, ['file_indexing_id', 'file_number', 'old_prop_id', 'action']);
            foreach ($log as $row) {
                fputcsv($h, $row);
            }
            fclose($h);
            $this->info(($apply ? 'Applied.' : 'Dry-run only (no changes written).') . " Report: {$path}");
        }

        if (!$apply) {
            $this->comment('Review the report, then re-run with --apply to write changes.');
        }

        return self::SUCCESS;
    }

    private function rollback($conn): int
    {
        if (!Schema::connection(self::CONNECTION)->hasTable(self::BACKUP_TABLE)) {
            $this->error('No backup table — nothing to roll back.');

            return self::FAILURE;
        }

        $runId = $conn->table(self::BACKUP_TABLE)->max('run_id');
        $rows = $conn->table(self::BACKUP_TABLE)->where('run_id', $runId)->get();
        $restored = 0;

        foreach ($rows as $row) {
            // Only restore a row still NULL, so a later deliberate edit is preserved.
            $restored += $conn->table('file_indexings')
                ->where('id', $row->file_indexing_id)
                ->whereNull('prop_id')
                ->update(['prop_id' => $row->old_prop_id]);
        }

        $this->info("Rolled back run {$runId}: {$restored} of {$rows->count()} rows restored.");

        if ($restored < $rows->count()) {
            $this->comment('Rows changed since the run were left untouched on purpose.');
        }

        return self::SUCCESS;
    }

    private function ensureBackupTable($conn): void
    {
        if (Schema::connection(self::CONNECTION)->hasTable(self::BACKUP_TABLE)) {
            return;
        }

        Schema::connection(self::CONNECTION)->create(self::BACKUP_TABLE, function ($t) {
            $t->bigIncrements('id');
            $t->string('run_id', 32)->index();
            $t->unsignedBigInteger('file_indexing_id')->index();
            $t->string('file_number', 100)->nullable();
            $t->string('old_prop_id', 100)->nullable();
            $t->dateTime('created_at')->nullable();
        });

        $this->line('Created backup table ' . self::BACKUP_TABLE . '.');
    }

    /** Every value used as a parent_prop_id anywhere; lists are split. */
    private function loadParentPropIds($conn): array
    {
        $set = [];

        foreach (self::PARENT_PROP_TABLES as $t) {
            try {
                $conn->table($t)->whereNotNull('parent_prop_id')
                    ->select('parent_prop_id')->orderBy('parent_prop_id')
                    ->chunk(20000, function ($rows) use (&$set) {
                        foreach ($rows as $r) {
                            foreach (explode(',', (string) $r->parent_prop_id) as $p) {
                                if (($p = trim($p)) !== '') {
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

    /** Mirrors ReconcileIndexingPropIds — same rule, same source. */
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
                ->select('fileno', 'mlsFNo')->orderBy('id')
                ->chunk(20000, function ($rows) use (&$set) {
                    foreach ($rows as $r) {
                        foreach ([$r->fileno, $r->mlsFNo] as $v) {
                            if (($v = strtoupper(trim((string) $v))) !== '') {
                                $set[$v] = true;
                            }
                        }
                    }
                });
        } catch (Throwable $e) {
            // Table absent — the whitelist simply does not apply.
        }

        return $set;
    }
}
