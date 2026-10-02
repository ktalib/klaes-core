<?php

namespace App\Console\Commands;

use App\Services\PropertyIdAllocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Steps 4a / 6b / 8 of the PropID remediation — docs/plans/PROP_ID_REMEDIATION_TRACKER.md
 *
 * THE RULE (confirmed with the product owner, 2026-09-27):
 * every distinct file number gets its OWN prop_id. Sharing is a violation. Files with
 * no prop_id get one minted.
 *
 * Two exemptions, never touched by this command:
 *   - OP/ToT pairs        (ReconcileIndexingPropIds: "shared prop_id is legit")
 *   - ST mothers          (BackfillStPropIds: they adopt the old land file's id)
 *
 * TWO POPULATIONS, deliberately separate:
 *   --population=duplicates   ~4,661 excess holders on a shared id. ACTIVELY WRONG:
 *                             a join on that id returns a stranger's file today.
 *   --population=nulls        ~115,525 files with no prop_id. DORMANT: a NULL makes
 *                             LegalSearchService skip prop_id expansion entirely, so
 *                             these harm nobody until they are filled.
 *
 * Duplicates run first. That is the ordering principle this whole remediation has used:
 * fix what is actively wrong before activating what is dormant.
 *
 * WHY BATCHES: filling a prop_id switches Legal Search expansion ON for that file.
 * Doing 115,525 at once would flip the behaviour of the whole registry in one step, on
 * a database with no backup infrastructure. --batch caps each run so a mistake is found
 * while it is still small. Verify between batches; do not loop this unattended.
 *
 * MINTING goes through PropertyIdAllocationService, the canonical allocator, so
 * PropID_Master gets a proper row. This command never invents an id itself.
 *
 * Safety: DRY RUN by default · --snapshot writes a full before-image · --apply backs up
 * each run · --rollback restores it.
 */
class MintMissingPropIds extends Command
{
    protected $signature = 'propid:mint
        {--population=duplicates : duplicates|nulls|child-adopted}
        {--batch=500 : Maximum rows to change in this run.}
        {--registry= : Restrict to one registry, e.g. KANGIS. Scopes the NULL population.}
        {--with-pages : Only files that have scanned pages — the ones staff actually see.}
        {--apply : Actually write. Omit for a read-only dry-run report.}
        {--snapshot : Write a full before-image of every non-null prop_id, then exit.}
        {--rollback : Undo the last --apply run of this command.}';

    protected $description = 'Give every file number its own prop_id, in batches (dry-run by default).';

    private const CONNECTION = 'sqlsrv';
    private const BACKUP_TABLE = 'file_indexings_mint_backup';
    private const SNAPSHOT_TABLE = 'file_indexings_propid_snapshot';

    private const MASTER_ALIAS_COLUMNS = [
        'primary_file_number_norm', 'mlsFNo_norm', 'kangisFileNo_norm',
        'NewKANGISFileno_norm', 'temp_fileno_norm',
    ];

    private const PLOT_PLACEHOLDERS = ['PIECE OF LAND', 'PLOT', 'N/A', 'NIL', '-', ''];

    public function handle(PropertyIdAllocationService $allocator): int
    {
        $conn = DB::connection(self::CONNECTION);
        $cfg = config('database.connections.' . self::CONNECTION);

        if ($this->option('snapshot')) {
            return $this->snapshot($conn);
        }

        if ($this->option('rollback')) {
            return $this->rollback($conn);
        }

        $apply = (bool) $this->option('apply');
        $population = $this->option('population');
        $batch = max(1, (int) $this->option('batch'));

        if (!in_array($population, ['duplicates', 'nulls', 'child-adopted'], true)) {
            $this->error('--population must be duplicates, nulls or child-adopted.');

            return self::FAILURE;
        }

        $this->info(($apply ? '[APPLY]' : '[DRY-RUN]') . " propid:mint — {$cfg['host']}/{$cfg['database']}");
        $registry = trim((string) $this->option('registry'));
        $this->line("Population: {$population} · batch cap: {$batch}"
            . ($registry !== '' ? " · registry: {$registry}" : ''));

        if ($apply && !Schema::connection(self::CONNECTION)->hasTable(self::SNAPSHOT_TABLE)) {
            $this->error('No before-image found. Run --snapshot first; this database has no other backup.');

            return self::FAILURE;
        }

        $this->newLine();

        $targets = match ($population) {
            'duplicates' => $this->duplicateTargets($conn, $batch),
            'child-adopted' => $this->childAdoptedTargets($conn, $batch),
            default => $this->nullTargets($conn, $batch, $registry, (bool) $this->option('with-pages')),
        };

        $this->line('Candidates selected: ' . count($targets['rows']));

        foreach ($targets['skipped'] as $reason => $n) {
            $this->line(sprintf('  skipped %-32s %5d', $reason, $n));
        }

        $log = [];
        $minted = 0;
        $errors = 0;
        $runId = date('Ymd-His');

        if ($apply && !empty($targets['rows'])) {
            $this->ensureBackupTable($conn);
        }

        foreach ($targets['rows'] as $row) {
            try {
                if (!$apply) {
                    $log[] = [$row->id, $row->file_number ?: $row->temp_file_no, $row->prop_id ?? '', '(new)', 'WOULD MINT'];
                    $minted++;
                    continue;
                }

                // skip_lookup forces a brand-new id. Without it the allocator would hand
                // back the very id we are trying to move this file off.
                //
                // 167 rows carry no file_number at all — their number lives only in
                // temp_file_no, because the main file was never found. The allocator
                // rejects those unless the temp number is passed explicitly with
                // allow_temp_only, which is what the first batch's 6 errors were.
                $new = $allocator->allocateOrRetrievePropId(
                    $row->file_number ?: null,
                    null,
                    null,
                    null,
                    [
                        'skip_lookup' => true,
                        'temp_fileno' => $row->temp_file_no ?: null,
                        'allow_temp_only' => true,
                    ]
                );

                $conn->transaction(function () use ($conn, $row, $new, $runId) {
                    $conn->table(self::BACKUP_TABLE)->insert([
                        'run_id' => $runId,
                        'population' => $this->option('population'),
                        'file_indexing_id' => $row->id,
                        'file_number' => $row->file_number ?: $row->temp_file_no,
                        'old_prop_id' => $row->prop_id,
                        'new_prop_id' => $new,
                        'created_at' => now(),
                    ]);

                    // Conditional on the row still holding what we read.
                    $q = $conn->table('file_indexings')->where('id', $row->id);
                    $row->prop_id === null || $row->prop_id === ''
                        ? $q->where(fn ($w) => $w->whereNull('prop_id')->orWhere('prop_id', ''))
                        : $q->where('prop_id', $row->prop_id);
                    $q->update(['prop_id' => $new]);
                });

                $log[] = [$row->id, $row->file_number ?: $row->temp_file_no, $row->prop_id ?? '', $new, 'MINTED'];
                $minted++;
            } catch (Throwable $e) {
                $errors++;
                $log[] = [$row->id, $row->file_number ?: $row->temp_file_no, $row->prop_id ?? '', '', 'ERROR: ' . $e->getMessage()];
            }
        }

        $this->newLine();
        $this->table(['population', $apply ? 'minted' : 'would mint', 'errors'],
                     [[$population, $minted, $errors]]);

        $path = storage_path('app/propid-remediation/mint-' . $population . '-'
            . ($apply ? 'applied' : 'dryrun') . '-' . date('Ymd-His') . '.csv');
        @mkdir(dirname($path), 0775, true);

        if ($h = @fopen($path, 'w')) {
            fputcsv($h, ['file_indexing_id', 'file_number', 'old_prop_id', 'new_prop_id', 'action']);
            foreach ($log as $r) {
                fputcsv($h, $r);
            }
            fclose($h);
            $this->info(($apply ? "Applied (run_id {$runId})." : 'Dry-run only.') . " Report: {$path}");
        }

        if ($apply) {
            $this->comment('Undo this run with: php artisan propid:mint --rollback');
            $this->comment('VERIFY before the next batch: re-run propid:audit-indexing and spot-check Legal Search.');
        } else {
            $this->comment('Review the report, then re-run with --apply.');
        }

        return self::SUCCESS;
    }

    /**
     * Excess holders on a shared prop_id — the ones actively causing wrong joins.
     *
     * Who KEEPS the existing id, and who gets a new one:
     *   - exactly one member is named by PropID_Master  -> that one keeps it
     *   - nobody is named                               -> the oldest row keeps it
     *   - several are named                             -> SKIP the whole cluster
     *
     * The last case is skipped on purpose: PropID_Master itself has awarded one id to
     * several file numbers, so minting here would paper over a registry fault rather
     * than fix it. Those 23 clusters need the registry corrected first.
     */
    private function duplicateTargets($conn, int $batch): array
    {
        $opTot = $this->loadOpTotFileNumbers($conn);
        $rows = [];
        $skipped = ['exempt (OP/ToT or ST)' => 0, 'several master owners' => 0, 'batch cap reached' => 0];

        $clusters = $conn->table('file_indexings')
            ->select('prop_id', DB::raw('count(*) as total'))
            ->whereNotNull('prop_id')->where('prop_id', '<>', '')
            ->groupBy('prop_id')->havingRaw('count(*) > 1')
            ->orderByDesc(DB::raw('count(*)'))
            ->get();

        foreach ($clusters as $cluster) {
            if (count($rows) >= $batch) {
                $skipped['batch cap reached']++;
                continue;
            }

            $members = $conn->table('file_indexings')
                ->where('prop_id', $cluster->prop_id)
                ->orderBy('id')
                ->get(['id', 'file_number', 'temp_file_no', 'prop_id', 'plot_number']);

            $exempt = $members->contains(function ($m) use ($opTot) {
                $fn = strtoupper(trim((string) $m->file_number));

                return isset($opTot[$fn]) || str_starts_with($fn, 'ST-');
            });

            if ($exempt) {
                $skipped['exempt (OP/ToT or ST)']++;
                continue;
            }

            $authoritative = $members->filter(function ($m) use ($conn, $cluster) {
                $fn = strtoupper(trim((string) $m->file_number));

                return $fn !== '' && $conn->table('PropID_Master')
                    ->where(function ($q) use ($fn) {
                        foreach (self::MASTER_ALIAS_COLUMNS as $col) {
                            $q->orWhere($col, $fn);
                        }
                    })
                    ->where('prop_id', $cluster->prop_id)
                    ->exists();
            });

            if ($authoritative->count() > 1) {
                $skipped['several master owners']++;
                continue;
            }

            $keeper = $authoritative->count() === 1 ? $authoritative->first() : $members->first();

            foreach ($members as $m) {
                if ($m->id === $keeper->id || count($rows) >= $batch) {
                    continue;
                }

                $rows[] = $m;
            }
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    /**
     * Children holding their PARENT's prop_id.
     *
     * KangisParentLinkService documents the model plainly: "Each file carries its OWN
     * prop_id. The two children point up at the Old KANGIS file's prop_id via
     * parent_prop_id." Some rows instead have prop_id EQUAL to their own
     * parent_prop_id, so parent and child share one identity — the violation the rule
     * forbids, and a cause of cross-file contamination in Legal Search.
     *
     *     child RES-1992-873   prop=40864   parent KNML 1041   prop=40864
     *
     * The fix is clean: mint the CHILD its own id and leave parent_prop_id untouched.
     * The relationship survives, expressed the way the model intends; only the shared
     * identity goes away. The parent is never touched — it is the rightful holder.
     */
    private function childAdoptedTargets($conn, int $batch): array
    {
        $opTot = $this->loadOpTotFileNumbers($conn);
        $skipped = ['exempt (OP/ToT or ST)' => 0, 'no other holder — nothing shared' => 0];

        $rows = $conn->table('file_indexings')
            ->whereNotNull('prop_id')->where('prop_id', '<>', '')
            ->whereNotNull('parent_prop_id')->where('parent_prop_id', '<>', '')
            ->whereColumn('prop_id', 'parent_prop_id')
            ->orderBy('id')
            ->limit($batch * 3)
            ->get(['id', 'file_number', 'temp_file_no', 'prop_id', 'parent_prop_id', 'plot_number']);

        $kept = [];

        foreach ($rows as $row) {
            if (count($kept) >= $batch) {
                break;
            }

            $fn = strtoupper(trim((string) $row->file_number));

            if (($fn !== '' && isset($opTot[$fn])) || str_starts_with($fn, 'ST-')) {
                $skipped['exempt (OP/ToT or ST)']++;
                continue;
            }

            // Only act where a DIFFERENT file genuinely holds that id — otherwise there
            // is no sharing to undo and nothing to fix.
            $otherHolder = $conn->table('file_indexings')
                ->where('prop_id', $row->prop_id)
                ->where('id', '<>', $row->id)
                ->exists();

            if (!$otherHolder) {
                $skipped['no other holder — nothing shared']++;
                continue;
            }

            $kept[] = $row;
        }

        return ['rows' => $kept, 'skipped' => $skipped];
    }

    /**
     * Files with no prop_id at all. Dormant, so these come after the duplicates.
     *
     * --registry scopes this. It exists because the KANGIS slice (2,529 files) has a
     * concrete justification the rest of the NULL population does not: a KANGIS file
     * with no prop_id cannot be the parent its own children point at, so
     * KangisParentLinkService gives up silently and the link is never written. Doing
     * that slice on its own is a targeted fix with a measurable outcome — the dangling
     * pointer count — rather than a bulk activation.
     */
    private function nullTargets($conn, int $batch, string $registry = '', bool $withPages = false): array
    {
        $opTot = $this->loadOpTotFileNumbers($conn);
        $skipped = ['exempt (OP/ToT or ST) — must adopt, not mint' => 0];

        // A row with no file_number is still mintable when it has a temp number, so
        // the filter requires ONE of the two rather than file_number specifically.
        $rows = $conn->table('file_indexings')
            ->where(function ($q) {
                $q->where(function ($w) {
                    $w->whereNotNull('file_number')->where('file_number', '<>', '');
                })->orWhere(function ($w) {
                    $w->whereNotNull('temp_file_no')->where('temp_file_no', '<>', '');
                });
            })
            ->where(function ($q) {
                $q->whereNull('prop_id')->orWhere('prop_id', '');
            })
            ->when($registry !== '', fn ($q) => $q->where('registry', 'like', '%' . $registry . '%'))
            // Only files staff can actually open. Useful as a first slice: if switching
            // expansion on causes trouble, it shows up on files people look at.
            ->when($withPages, fn ($q) => $q->whereExists(fn ($e) => $e->selectRaw(1)
                ->from('pagetypings')->whereColumn('pagetypings.file_indexing_id', 'file_indexings.id')))
            ->orderBy('id')
            ->limit($batch * 2)
            ->get(['id', 'file_number', 'temp_file_no', 'prop_id', 'plot_number']);

        // THE EXEMPTIONS APPLY HERE TOO.
        // An OP/ToT partner and an ST mother are SUPPOSED to share an existing prop_id,
        // so minting them a fresh one would break the very pairing the rule protects.
        // They are skipped rather than guessed at: adopting the partner's id is a
        // different operation and deserves its own deliberate pass.
        $kept = [];

        foreach ($rows as $row) {
            if (count($kept) >= $batch) {
                break;
            }

            $fn = strtoupper(trim((string) $row->file_number));

            if (($fn !== '' && isset($opTot[$fn])) || str_starts_with($fn, 'ST-')) {
                $skipped['exempt (OP/ToT or ST) — must adopt, not mint']++;
                continue;
            }

            $kept[] = $row;
        }

        return ['rows' => $kept, 'skipped' => $skipped];
    }

    /** A full before-image of every non-null prop_id. The only safety net here. */
    private function snapshot($conn): int
    {
        if (!Schema::connection(self::CONNECTION)->hasTable(self::SNAPSHOT_TABLE)) {
            Schema::connection(self::CONNECTION)->create(self::SNAPSHOT_TABLE, function ($t) {
                $t->unsignedBigInteger('file_indexing_id')->primary();
                $t->string('file_number', 100)->nullable();
                $t->string('prop_id', 100)->nullable();
                $t->string('parent_prop_id', 255)->nullable();
                $t->dateTime('taken_at')->nullable();
            });
            $this->line('Created ' . self::SNAPSHOT_TABLE . '.');
        }

        $total = $conn->table('file_indexings')->count();
        $have = $conn->table(self::SNAPSHOT_TABLE)->count();

        if ($have >= $total) {
            $this->warn("Snapshot already complete: {$have} rows. Drop the table to retake.");

            return self::SUCCESS;
        }

        if ($have > 0) {
            // Resume rather than restart: an earlier run died part-way.
            $this->line("Resuming — {$have} of {$total} rows already captured.");
        }

        $now = now();
        $copied = 0;
        $bar = $this->output->createProgressBar($total - $have);
        $bar->start();

        // SQL Server caps a statement at 2,100 parameters. Five columns per row means
        // 400 rows is the largest safe insert; 2,000 blew the limit on the first run.
        $conn->table('file_indexings')
            ->select('id', 'file_number', 'prop_id', 'parent_prop_id')
            ->orderBy('id')
            ->chunk(400, function ($chunk) use ($conn, $now, &$copied, $bar) {
                $existing = $conn->table(self::SNAPSHOT_TABLE)
                    ->whereIn('file_indexing_id', $chunk->pluck('id')->all())
                    ->pluck('file_indexing_id')
                    ->flip();

                $insert = $chunk->reject(fn ($r) => $existing->has($r->id))
                    ->map(fn ($r) => [
                        'file_indexing_id' => $r->id,
                        'file_number' => $r->file_number,
                        'prop_id' => $r->prop_id,
                        'parent_prop_id' => $r->parent_prop_id,
                        'taken_at' => $now,
                    ])->values()->all();

                if (!empty($insert)) {
                    $conn->table(self::SNAPSHOT_TABLE)->insert($insert);
                    $copied += count($insert);
                }

                $bar->advance($chunk->count());
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Before-image written to " . self::SNAPSHOT_TABLE . ": {$copied} new rows, "
            . $conn->table(self::SNAPSHOT_TABLE)->count() . " total.");
        $this->comment('Keep this table until the whole remediation is signed off.');

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
            // Only where the row still holds exactly what this run wrote.
            $restored += $conn->table('file_indexings')
                ->where('id', $row->file_indexing_id)
                ->where('prop_id', $row->new_prop_id)
                ->update(['prop_id' => $row->old_prop_id]);
        }

        $this->info("Rolled back run {$runId}: {$restored} of {$rows->count()} rows restored.");
        $this->comment('PropID_Master rows minted by that run are left in place; they are harmless and reusable.');

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
            $t->string('population', 20)->nullable();
            $t->unsignedBigInteger('file_indexing_id')->index();
            $t->string('file_number', 100)->nullable();
            $t->string('old_prop_id', 100)->nullable();
            $t->string('new_prop_id', 100)->nullable();
            $t->dateTime('created_at')->nullable();
        });

        $this->line('Created backup table ' . self::BACKUP_TABLE . '.');
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
            // Table absent — the exemption simply does not apply.
        }

        return $set;
    }
}
