<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Step 4c of the PropID remediation — docs/plans/PROP_ID_REMEDIATION_TRACKER.md
 *
 * Carries the prop_id re-assignments made by propid:mint through to the TRANSACTION
 * tables, which still hold the old shared id.
 *
 * THE GAP THIS CLOSES:
 * propid:mint corrected file_indexings only. Legal Search searches pra / CofO_staging /
 * file_history_staging / pic by prop_id, so a file whose identity moved still has all
 * its transactions filed under the OLD id — and that old id still belongs to a
 * neighbour. The contamination therefore survived the repair:
 *
 *     file_indexings   RES-2005-446   prop_id = 7358     corrected, keeper
 *     file_indexings   RES-2005-447   prop_id = 180721   corrected, new id
 *     pra              RES-2005-447   prop_id = 7358     <-- still points at the keeper
 *
 * ~9,000 rows across 3,308 re-assigned ids.
 *
 * HOW A ROW IS MATCHED — this is the part that has to be exactly right.
 * Only a row that is BOTH (a) still on the old prop_id AND (b) identifiably that file's
 * own row is touched. Identity is proved by the file number appearing in one of the
 * table's own file-number columns. Matching on prop_id alone would drag the keeper's
 * transactions along with it, which is the very bug being fixed.
 *
 * Everything needed is derivable from file_indexings_mint_backup (old id, new id, file
 * number, per row), so nothing here is guesswork.
 *
 * Safety: DRY RUN by default · --snapshot writes a before-image of every affected row
 * · --apply backs up each change · --rollback restores it.
 */
class SyncDownstreamPropIds extends Command
{
    protected $signature = 'propid:sync-downstream
        {--apply : Actually write. Omit for a read-only dry-run report.}
        {--snapshot : Write a before-image of every row that could be touched, then exit.}
        {--rollback : Undo the last --apply run.}
        {--table= : Restrict to one table.}
        {--batch=0 : Maximum rows to change (0 = all).}';

    protected $description = 'Carry propid:mint re-assignments into pra/CofO_staging/etc (dry-run by default).';

    private const CONNECTION = 'sqlsrv';
    private const BACKUP_TABLE = 'downstream_propid_backup';
    private const SNAPSHOT_TABLE = 'downstream_propid_snapshot';

    /**
     * table => the columns that may carry this file's number.
     *
     * A row qualifies if ANY of them matches, because the same transaction is filed
     * under different numbering conventions depending on which registry captured it.
     */
    private const TABLES = [
        'pra' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno', 'temp_fileno'],
        'CofO_staging' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno', 'temp_fileno'],
        'file_history_staging' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno', 'temp_fileno'],
        'pic' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno', 'temp_fileno'],
        'instrument_capture' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'],
        'deed_registrations' => ['fileno'],
    ];

    public function handle(): int
    {
        $conn = DB::connection(self::CONNECTION);
        $cfg = config('database.connections.' . self::CONNECTION);

        if (!Schema::connection(self::CONNECTION)->hasTable('file_indexings_mint_backup')) {
            $this->error('file_indexings_mint_backup is missing — run propid:mint --apply first.');

            return self::FAILURE;
        }

        if ($this->option('snapshot')) {
            return $this->snapshot($conn);
        }

        if ($this->option('rollback')) {
            return $this->rollback($conn);
        }

        $apply = (bool) $this->option('apply');
        $batch = (int) $this->option('batch');
        $only = $this->option('table');

        $this->info(($apply ? '[APPLY]' : '[DRY-RUN]') . " propid:sync-downstream — {$cfg['host']}/{$cfg['database']}");

        if ($apply && !Schema::connection(self::CONNECTION)->hasTable(self::SNAPSHOT_TABLE)) {
            $this->error('No before-image. Run --snapshot first; these tables have no other backup.');

            return self::FAILURE;
        }

        $moves = $conn->table('file_indexings_mint_backup')
            ->whereNotNull('old_prop_id')->where('old_prop_id', '<>', '')
            ->whereNotNull('new_prop_id')
            ->get(['file_number', 'old_prop_id', 'new_prop_id']);

        $this->line('Re-assignments to carry through: ' . $moves->count());
        $this->newLine();

        $runId = date('Ymd-His');
        $totals = [];
        $log = [];
        $changed = 0;

        if ($apply) {
            $this->ensureBackupTable($conn);
        }

        foreach (self::TABLES as $table => $numberColumns) {
            if ($only && $only !== $table) {
                continue;
            }

            if (!Schema::connection(self::CONNECTION)->hasTable($table)) {
                continue;
            }

            $usable = array_values(array_filter(
                $numberColumns,
                fn ($col) => Schema::connection(self::CONNECTION)->hasColumn($table, $col)
            ));

            if (empty($usable)) {
                continue;
            }

            $n = 0;
            $bar = $this->output->createProgressBar($moves->count());
            $this->line("  {$table}…");
            $bar->start();

            foreach ($moves as $move) {
                $bar->advance();

                if ($batch > 0 && $changed >= $batch) {
                    continue;
                }

                $fileNumber = trim((string) $move->file_number);

                if ($fileNumber === '') {
                    continue;
                }

                try {
                    // (a) still on the old id AND (b) demonstrably this file's own row.
                    $rows = $conn->table($table)
                        ->where('prop_id', $move->old_prop_id)
                        ->where(function ($q) use ($usable, $fileNumber) {
                            foreach ($usable as $col) {
                                $q->orWhereRaw("UPPER(LTRIM(RTRIM({$col}))) = ?", [strtoupper($fileNumber)]);
                            }
                        })
                        ->get(['id']);

                    if ($rows->isEmpty()) {
                        continue;
                    }

                    foreach ($rows as $r) {
                        if ($batch > 0 && $changed >= $batch) {
                            break;
                        }

                        $log[] = [$table, $r->id, $fileNumber, $move->old_prop_id, $move->new_prop_id,
                                  $apply ? 'SYNCED' : 'WOULD SYNC'];
                        $n++;
                        $changed++;

                        if (!$apply) {
                            continue;
                        }

                        $conn->transaction(function () use ($conn, $table, $r, $move, $fileNumber, $runId) {
                            $conn->table(self::BACKUP_TABLE)->insert([
                                'run_id' => $runId,
                                'source_table' => $table,
                                'row_id' => $r->id,
                                'file_number' => $fileNumber,
                                'old_prop_id' => $move->old_prop_id,
                                'new_prop_id' => $move->new_prop_id,
                                'created_at' => now(),
                            ]);

                            // Conditional: only while it still holds the old value.
                            $conn->table($table)
                                ->where('id', $r->id)
                                ->where('prop_id', $move->old_prop_id)
                                ->update(['prop_id' => $move->new_prop_id]);
                        });
                    }
                } catch (Throwable $e) {
                    $log[] = [$table, '', $fileNumber, $move->old_prop_id, '', 'ERROR: ' . $e->getMessage()];
                }
            }

            $bar->finish();
            $this->newLine();
            $totals[$table] = $n;
        }

        $this->newLine();
        $this->table(['table', $apply ? 'synced' : 'would sync'],
            collect($totals)->map(fn ($n, $t) => [$t, $n])->values()->all());

        $path = storage_path('app/propid-remediation/downstream-'
            . ($apply ? 'applied' : 'dryrun') . '-' . date('Ymd-His') . '.csv');
        @mkdir(dirname($path), 0775, true);

        if ($h = @fopen($path, 'w')) {
            fputcsv($h, ['table', 'row_id', 'file_number', 'old_prop_id', 'new_prop_id', 'action']);
            foreach ($log as $r) {
                fputcsv($h, $r);
            }
            fclose($h);
            $this->info(($apply ? "Applied (run_id {$runId})." : 'Dry-run only.') . " Report: {$path}");
        }

        $this->comment($apply
            ? 'Undo with: php artisan propid:sync-downstream --rollback'
            : 'Review the report, then re-run with --apply.');

        return self::SUCCESS;
    }

    /** Before-image of every row that could be touched. These tables have no backup. */
    private function snapshot($conn): int
    {
        if (!Schema::connection(self::CONNECTION)->hasTable(self::SNAPSHOT_TABLE)) {
            Schema::connection(self::CONNECTION)->create(self::SNAPSHOT_TABLE, function ($t) {
                $t->bigIncrements('id');
                $t->string('source_table', 64)->index();
                $t->unsignedBigInteger('row_id');
                $t->string('prop_id', 100)->nullable();
                $t->dateTime('taken_at')->nullable();
            });
            $this->line('Created ' . self::SNAPSHOT_TABLE . '.');
        }

        $old = $conn->table('file_indexings_mint_backup')
            ->whereNotNull('old_prop_id')->where('old_prop_id', '<>', '')
            ->pluck('old_prop_id')->unique()->values();

        $now = now();
        $total = 0;

        foreach (self::TABLES as $table => $_) {
            if (!Schema::connection(self::CONNECTION)->hasTable($table)) {
                continue;
            }

            $already = $conn->table(self::SNAPSHOT_TABLE)->where('source_table', $table)->count();

            if ($already > 0) {
                $this->line("  {$table}: {$already} rows already captured, skipping.");
                $total += $already;
                continue;
            }

            $n = 0;

            // 400-row chunks: SQL Server caps a statement at 2,100 parameters.
            foreach ($old->chunk(400) as $batch) {
                $conn->table($table)->whereIn('prop_id', $batch->all())
                    ->select('id', 'prop_id')->orderBy('id')
                    ->chunk(400, function ($rows) use ($conn, $table, $now, &$n) {
                        $conn->table(self::SNAPSHOT_TABLE)->insert(
                            $rows->map(fn ($r) => [
                                'source_table' => $table,
                                'row_id' => $r->id,
                                'prop_id' => $r->prop_id,
                                'taken_at' => $now,
                            ])->all()
                        );
                        $n += $rows->count();
                    });
            }

            $this->line(sprintf('  %-24s %6d rows captured', $table, $n));
            $total += $n;
        }

        $this->info("Before-image complete: {$total} rows in " . self::SNAPSHOT_TABLE . '.');
        $this->comment('Keep this table until the remediation is signed off.');

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
            $restored += $conn->table($row->source_table)
                ->where('id', $row->row_id)
                ->where('prop_id', $row->new_prop_id)
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
            $t->string('source_table', 64);
            $t->unsignedBigInteger('row_id');
            $t->string('file_number', 100)->nullable();
            $t->string('old_prop_id', 100)->nullable();
            $t->string('new_prop_id', 100)->nullable();
            $t->dateTime('created_at')->nullable();
        });

        $this->line('Created backup table ' . self::BACKUP_TABLE . '.');
    }
}
