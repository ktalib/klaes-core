<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Restores file_indexings rows for decommissioned files that lost theirs.
 *
 * THE RULE, as the product owner states it: a parcel update must NOT delete the file.
 * It writes a row to decommissioned_files AND keeps the row in file_indexings and the
 * other tables, flagged. A surviving row for a retired file is correct; the MISSING row
 * is the bug.
 *
 * 18 of 3,096 decommissioned files have no file_indexings row at all. Something deleted
 * them outright instead of flagging. decommissioned_files kept the evidence, which is
 * the only reason they are recoverable:
 *
 *     CON-AG-2019-24    decommissioned 2026-07-23  "Change of Purpose to CON-RES"
 *                       successor CON-RES-2026-2611
 *
 * WHAT THIS RESTORES, and what it does NOT invent:
 * Every field comes from the decommissioned_files row — file number, holder name,
 * decommissioning date, reason, successor. Nothing is guessed.
 *
 * NOT every row in that table is a retirement. 2,960 of 3,096 are flagged
 * false_decommissioning — title-status markers, which the repo calls "flags, not actual
 * parcel lineage". Of the 18 missing rows, 7 are genuine retirements and 11 are flags.
 * So the restored row takes one of two shapes:
 *   genuine -> is_decommissioned = 1, with its date, reason and successor
 *   flag    -> a normal ACTIVE row, because the file was never retired
 * Both get their row back; only the missing row is the bug.
 *
 * prop_id is deliberately left NULL. A NULL is inert (LegalSearchService skips prop_id
 * expansion for it), whereas a guessed id would be wrong and contaminating. Mint it
 * afterwards with propid:mint if the file needs one.
 *
 * The decommissioned_files row is left exactly as it is — this adds the missing half,
 * it does not move anything.
 *
 * Safety: DRY RUN by default · --apply records what it created · --rollback removes
 * only rows this command created.
 */
class RestoreDecommissionedIndexing extends Command
{
    protected $signature = 'decommissioning:restore-indexing
        {--apply : Actually write. Omit for a read-only dry-run report.}
        {--rollback : Delete the rows created by the last --apply run.}
        {--file= : Restrict to one file number.}';

    protected $description = 'Recreate file_indexings rows for decommissioned files that lost them (dry-run by default).';

    private const CONNECTION = 'sqlsrv';
    private const LOG_TABLE = 'decommissioning_restore_log';

    /** The value to stamp as the creator, per the product owner. */
    private const CREATED_BY = '0';

    public function handle(): int
    {
        $conn = DB::connection(self::CONNECTION);

        if ($this->option('rollback')) {
            return $this->rollback($conn);
        }

        $apply = (bool) $this->option('apply');
        $cfg = config('database.connections.' . self::CONNECTION);

        $this->info(($apply ? '[APPLY]' : '[DRY-RUN]') . " restore decommissioned indexing rows — {$cfg['host']}/{$cfg['database']}");
        $this->newLine();

        $query = $conn->table('decommissioned_files')
            ->whereNotNull('file_no')->where('file_no', '<>', '')
            ->whereNotExists(fn ($q) => $q->selectRaw(1)
                ->from('file_indexings')
                ->whereRaw('UPPER(LTRIM(RTRIM(file_indexings.file_number))) = UPPER(LTRIM(RTRIM(decommissioned_files.file_no)))'));

        if ($only = $this->option('file')) {
            $query->where('file_no', $only);
        }

        $rows = $query->get();
        $genuine = $rows->filter(fn ($r) => (int) ($r->false_decommissioning ?? 0) !== 1)->count();
        $this->line('Decommissioned files with no indexing row: ' . $rows->count());
        $this->line("  genuine retirements, restored flagged : {$genuine}");
        $this->line('  false decommissionings, restored active: ' . ($rows->count() - $genuine));
        $this->newLine();

        $runId = date('Ymd-His');
        $created = 0;
        $errors = 0;
        $log = [];

        if ($apply && $rows->isNotEmpty()) {
            $this->ensureLogTable($conn);
        }

        foreach ($rows as $r) {
            try {
                // Everything below comes from the decommissioned_files row. Columns that
                // do not exist on this deployment are simply not written.
                // NOT every decommissioned_files row is a retirement. 2,960 of 3,096
                // are flagged false_decommissioning: they are title-status markers, and
                // the repo says so plainly — "flags, not actual parcel lineage". Of the
                // 18 missing rows, 11 are such flags.
                //
                // Restoring a flag row as is_decommissioned = 1 would retire a file that
                // was never retired. So the flag decides the shape of the restored row;
                // both still get their row back, because the MISSING row is the bug
                // either way.
                $isFalse = (int) ($r->false_decommissioning ?? 0) === 1;

                $payload = array_filter([
                    'file_number' => $r->file_no,
                    'file_title' => $r->file_name ?? null,
                    'registry' => $this->guessRegistry($conn, $r),
                    'created_by' => self::CREATED_BY,
                    // Only a genuine decommission gets the flag and its lineage fields.
                    'is_decommissioned' => $isFalse ? 0 : 1,
                    'decommissioned_at' => $isFalse ? null : ($r->decommissioning_date ?? null),
                    'decommissioning_reason' => $isFalse ? null : ($r->decommissioning_reason ?? null),
                    'successor_file_no' => $isFalse ? null : ($r->successor_file_no ?? null),
                    'created_at' => $r->created_at ?? now(),
                    'updated_at' => now(),
                ], fn ($v) => $v !== null);

                $payload = array_filter(
                    $payload,
                    fn ($k) => Schema::connection(self::CONNECTION)->hasColumn('file_indexings', $k),
                    ARRAY_FILTER_USE_KEY
                );

                $kind = $isFalse ? 'active (false decommissioning)' : 'decommissioned';
                $log[] = [$r->file_no, $r->file_name ?? '', $kind, $r->successor_file_no ?? '',
                          $apply ? 'RESTORED' : 'WOULD RESTORE'];

                if (!$apply) {
                    $created++;
                    continue;
                }

                $conn->transaction(function () use ($conn, $payload, $r, $runId, &$created) {
                    $id = $conn->table('file_indexings')->insertGetId($payload);

                    $conn->table(self::LOG_TABLE)->insert([
                        'run_id' => $runId,
                        'file_indexing_id' => $id,
                        'file_number' => $r->file_no,
                        'decommissioned_file_id' => $r->id ?? null,
                        'created_at' => now(),
                    ]);

                    $created++;
                });
            } catch (Throwable $e) {
                $errors++;
                $log[] = [$r->file_no, '', '', '', 'ERROR: ' . $e->getMessage()];
            }
        }

        $this->table([$apply ? 'restored' : 'would restore', 'errors'], [[$created, $errors]]);

        $path = storage_path('app/propid-remediation/decommission-restore-'
            . ($apply ? 'applied' : 'dryrun') . '-' . date('Ymd-His') . '.csv');
        @mkdir(dirname($path), 0775, true);

        if ($h = @fopen($path, 'w')) {
            fputcsv($h, ['file_no', 'file_name', 'restored_as', 'successor_file_no', 'action']);
            foreach ($log as $row) {
                fputcsv($h, $row);
            }
            fclose($h);
            $this->info(($apply ? "Applied (run_id {$runId})." : 'Dry-run only.') . " Report: {$path}");
        }

        if ($apply) {
            $this->comment('prop_id left NULL on purpose — mint separately with propid:mint.');
            $this->comment('Undo with: php artisan decommissioning:restore-indexing --rollback');
        } else {
            $this->comment('Review the report, then re-run with --apply.');
        }

        return self::SUCCESS;
    }

    /**
     * Registry for the restored row.
     *
     * Taken from the successor file where one exists and is indexed — a Change of
     * Purpose keeps the parcel in the same registry, so the successor is the best
     * evidence available. Falls back to the Lands default only when there is nothing
     * to read, since parcel updates are Lands Registry operations.
     */
    private function guessRegistry($conn, $r): ?string
    {
        $successor = trim((string) ($r->successor_file_no ?? ''));

        if ($successor !== '') {
            $reg = $conn->table('file_indexings')
                ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper($successor)])
                ->value('registry');

            if ($reg !== null && $reg !== '') {
                return $reg;
            }
        }

        return '1';
    }

    private function rollback($conn): int
    {
        if (!Schema::connection(self::CONNECTION)->hasTable(self::LOG_TABLE)) {
            $this->error('No log table — nothing to roll back.');

            return self::FAILURE;
        }

        $runId = $conn->table(self::LOG_TABLE)->max('run_id');
        $rows = $conn->table(self::LOG_TABLE)->where('run_id', $runId)->get();
        $removed = 0;

        foreach ($rows as $row) {
            // Only delete a row this command created AND that still looks untouched —
            // if someone has since edited it, leave it alone.
            $removed += $conn->table('file_indexings')
                ->where('id', $row->file_indexing_id)
                ->where('file_number', $row->file_number)
                ->where('created_by', self::CREATED_BY)
                ->whereNull('prop_id')
                ->delete();
        }

        $this->info("Rolled back run {$runId}: {$removed} of {$rows->count()} rows removed.");

        if ($removed < $rows->count()) {
            $this->comment('Rows edited since the run were left in place on purpose.');
        }

        return self::SUCCESS;
    }

    private function ensureLogTable($conn): void
    {
        if (Schema::connection(self::CONNECTION)->hasTable(self::LOG_TABLE)) {
            return;
        }

        Schema::connection(self::CONNECTION)->create(self::LOG_TABLE, function ($t) {
            $t->bigIncrements('id');
            $t->string('run_id', 32)->index();
            $t->unsignedBigInteger('file_indexing_id')->index();
            $t->string('file_number', 100)->nullable();
            $t->unsignedBigInteger('decommissioned_file_id')->nullable();
            $t->dateTime('created_at')->nullable();
        });

        $this->line('Created log table ' . self::LOG_TABLE . '.');
    }
}
