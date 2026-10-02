<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Step 6a of the PropID remediation — see docs/plans/PROP_ID_REMEDIATION_TRACKER.md
 *
 * Gives a prop_id to KANGIS files that have none, by LOOKUP ONLY.
 *
 * Why it matters: the KANGIS model makes the Old KANGIS file the parent, and the Land
 * and New KANGIS files point up at its prop_id. KangisParentLinkService cannot write
 * that link when the parent has no prop_id — it returns null and gives up silently.
 * 4,794 KANGIS files are in that state, and ~2/3 of them are already named as another
 * file's related file, so children really are waiting on them.
 *
 * SCOPE — deliberately only half the problem:
 *   2,350 can be RESOLVED from PropID_Master (this command)
 *   2,442 would need a NEW id MINTED (step 6b, NOT done here)
 *
 * Minting is a different risk class: it creates a new identity, writes to
 * PropID_Master, and can give a parcel a second id if it already exists under another
 * spelling. This command never mints. It only ever copies an id PropID_Master already
 * holds, and only when exactly one matches.
 *
 * THE COLLISION GUARD, and why it exists:
 * Step 0 repaired 10,216 rows toward PropID_Master and accidentally created 878 new
 * cross-parcel collisions, because a second file was already squatting on the target
 * id. That cost a whole extra remediation pass. So before writing, this checks whether
 * the target prop_id is already held by a file on a DIFFERENT parcel, and skips if so.
 *
 * BEHAVIOUR CHANGE — read this before --apply:
 * A NULL prop_id makes LegalSearchService skip prop_id expansion for that file. Filling
 * it switches expansion ON. That is the same concern that makes the 116,820-row
 * backfill (step 8) the last item on the list. It is accepted here because the scale is
 * small, the values are authoritative rather than invented, and the collision guard
 * below refuses any row that would land on a contested id.
 *
 * Safety: DRY RUN by default · --apply backs up first · --rollback restores.
 */
class BackfillKangisPropIds extends Command
{
    protected $signature = 'propid:backfill-kangis
        {--apply : Actually write. Omit for a read-only dry-run report.}
        {--rollback : Restore from the backup table written by the last --apply run.}
        {--limit=0 : Cap rows examined (0 = all).}';

    protected $description = 'Give KANGIS files a prop_id by lookup from PropID_Master (never mints; dry-run by default).';

    private const CONNECTION = 'sqlsrv';
    private const BACKUP_TABLE = 'file_indexings_kangis_propid_backup';

    private const MASTER_ALIAS_COLUMNS = [
        'primary_file_number_norm',
        'mlsFNo_norm',
        'kangisFileNo_norm',
        'NewKANGISFileno_norm',
        'temp_fileno_norm',
    ];

    /** Values in plot_number that identify nothing — see AuditIndexingPropIds. */
    private const PLOT_PLACEHOLDERS = ['PIECE OF LAND', 'PLOT', 'N/A', 'NIL', '-', ''];

    public function handle(): int
    {
        $conn = DB::connection(self::CONNECTION);

        if ($this->option('rollback')) {
            return $this->rollback($conn);
        }

        $apply = (bool) $this->option('apply');

        $cfg = config('database.connections.' . self::CONNECTION);
        $this->info(($apply ? '[APPLY]' : '[DRY-RUN]') . " KANGIS prop_id backfill — {$cfg['host']}/{$cfg['database']}");
        $this->line('Lookup only. This command never mints a new prop_id.');
        $this->newLine();

        $query = $conn->table('file_indexings')
            ->select('id', 'file_number', 'file_title', 'registry', 'plot_number', 'district')
            ->where('registry', 'like', '%KANGIS%')
            ->where(function ($q) {
                $q->whereNull('prop_id')->orWhere('prop_id', '');
            });

        if (($limit = (int) $this->option('limit')) > 0) {
            $query->limit($limit);
        }

        $rows = $query->get();
        $this->line("Examining {$rows->count()} KANGIS files with no prop_id…");

        $counts = ['examined' => 0, 'fill' => 0, 'skip_no_master' => 0,
                   'skip_ambiguous' => 0, 'skip_collision' => 0, 'error' => 0];
        $log = [];
        $pending = [];

        $bar = $this->output->createProgressBar($rows->count());
        $bar->start();

        foreach ($rows as $r) {
            $bar->advance();
            $counts['examined']++;

            try {
                $fn = strtoupper(trim((string) $r->file_number));

                if ($fn === '') {
                    $counts['skip_no_master']++;
                    continue;
                }

                $ids = $conn->table('PropID_Master')
                    ->where(function ($q) use ($fn) {
                        foreach (self::MASTER_ALIAS_COLUMNS as $col) {
                            $q->orWhere($col, $fn);
                        }
                    })
                    ->distinct()
                    ->pluck('prop_id');

                if ($ids->isEmpty()) {
                    // Would need minting — that is step 6b, and a separate decision.
                    $counts['skip_no_master']++;
                    continue;
                }

                if ($ids->count() > 1) {
                    $counts['skip_ambiguous']++;
                    $log[] = [$r->id, $r->file_number, '', $ids->implode('|'), 'SKIP: several master ids'];
                    continue;
                }

                $target = (string) $ids->first();

                // THE GUARD. Is this id already held by a file on a different parcel?
                if ($holder = $this->conflictingHolder($conn, $target, $r)) {
                    $counts['skip_collision']++;
                    $log[] = [$r->id, $r->file_number, '', $target,
                              'SKIP: ' . $target . ' already held by ' . $holder];
                    continue;
                }

                $counts['fill']++;
                $log[] = [$r->id, $r->file_number, '', $target, 'FILL'];

                if ($apply) {
                    $pending[] = ['id' => $r->id, 'new' => $target, 'file_number' => $r->file_number];
                }
            } catch (Throwable $e) {
                $counts['error']++;
                $log[] = [$r->id, $r->file_number ?? '', '', '', 'ERROR: ' . $e->getMessage()];
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
                            'new_prop_id' => $row['new'],
                            'created_at' => now(),
                        ]);
                        // Still NULL, so a concurrent write is never clobbered.
                        $conn->table('file_indexings')
                            ->where('id', $row['id'])
                            ->where(function ($q) {
                                $q->whereNull('prop_id')->orWhere('prop_id', '');
                            })
                            ->update(['prop_id' => $row['new']]);
                    }
                });
            }

            $this->info('Backup written to ' . self::BACKUP_TABLE . " (run_id {$runId}). Undo with --rollback.");
        }

        $this->table(
            ['examined', 'to fill', 'skip no-master (step 6b)', 'skip ambiguous', 'skip collision', 'errors'],
            [[$counts['examined'], $counts['fill'], $counts['skip_no_master'],
              $counts['skip_ambiguous'], $counts['skip_collision'], $counts['error']]]
        );

        $path = storage_path('app/propid-remediation/kangis-backfill-'
            . ($apply ? 'applied' : 'dryrun') . '-' . date('Ymd-His') . '.csv');
        @mkdir(dirname($path), 0775, true);

        if ($h = @fopen($path, 'w')) {
            fputcsv($h, ['file_indexing_id', 'file_number', 'old_prop_id', 'new_prop_id', 'action']);
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

    /**
     * Returns the file number already holding $target on a DIFFERENT parcel, or null.
     *
     * "Different parcel" uses the same plot test as the audit: a real plot number that
     * disagrees. Placeholders prove nothing either way, so a cluster with no usable plot
     * is NOT treated as a conflict — this guard should block the provable cases, not
     * refuse everything ambiguous.
     */
    private function conflictingHolder($conn, string $target, $candidate): ?string
    {
        $holders = $conn->table('file_indexings')
            ->where('prop_id', $target)
            ->where('id', '<>', $candidate->id)
            ->get(['file_number', 'plot_number']);

        if ($holders->isEmpty()) {
            return null;
        }

        $mine = $this->normalisePlot($candidate->plot_number);

        if ($mine === null) {
            return null;   // cannot prove a conflict
        }

        foreach ($holders as $h) {
            $theirs = $this->normalisePlot($h->plot_number);

            if ($theirs !== null && $theirs !== $mine) {
                return (string) $h->file_number;
            }
        }

        return null;
    }

    /** A usable plot identifier, or null when the value identifies nothing. */
    private function normalisePlot($value): ?string
    {
        $plot = strtoupper(trim((string) $value));
        $plot = preg_replace('/^A\s+PIECE\s+OF\s+LAND$/', 'PIECE OF LAND', $plot);

        return in_array($plot, self::PLOT_PLACEHOLDERS, true) ? null : $plot;
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
            // Only clear a row still holding exactly what this run wrote.
            $restored += $conn->table('file_indexings')
                ->where('id', $row->file_indexing_id)
                ->where('prop_id', $row->new_prop_id)
                ->update(['prop_id' => null]);
        }

        $this->info("Rolled back run {$runId}: {$restored} of {$rows->count()} rows restored to NULL.");

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
            $t->string('new_prop_id', 100)->nullable();
            $t->dateTime('created_at')->nullable();
        });

        $this->line('Created backup table ' . self::BACKUP_TABLE . '.');
    }
}
