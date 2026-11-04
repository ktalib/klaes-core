<?php

namespace App\Console\Commands;

use App\Services\PropertyIdAllocationService;
use App\Services\PropIdLineageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Step 8 follow-up of the PropID remediation — docs/plans/PROP_ID_REMEDIATION_TRACKER.md
 *
 * The "ID taken" files held for review on 2026-10-19: PropID_Master and the file's own
 * PRA rows give it an id, but its old KANGIS file is sitting on that id, so the file
 * was left NULL.
 *
 *     CON-COM-2010-47   prop_id NULL   parent_prop_id 9279   master: 9279
 *     MLKN 3074         prop_id 9279   (its old KANGIS file)
 *
 * THE SWAP: the file takes the id; the KANGIS file gets a NEW id from the canonical
 * allocator; its own transactions follow it; every parent_prop_id link that meant "the
 * KANGIS file" is re-pointed to the new id, and ancestral_prop_id is recomputed.
 *
 * Input is a reviewed preview CSV (verdict READY). Every row is re-checked live and
 * skipped, never guessed at, when anything has changed or looks off:
 *   - the file already has a prop_id, or master does not give it exactly the id
 *   - not exactly one live holder, or the holder is not the reviewed one
 *   - the holder has a parent of its own, or is ST
 *   - the holder's own master id (copied instead of minting) is held by another file
 *   - the holder's own rows name the id as their parent (self-parent)
 *   - neither a parent link nor a master alias connects the two files
 *   - a transaction on the id belongs to neither file
 *
 * Safety: DRY RUN by default · --apply backs up every changed value (and the inserted
 * master row) per file, in one short transaction per file · --rollback restores a run.
 */
class SwapTakenPropIds extends Command
{
    protected $signature = 'propid:swap-taken
        {--from= : Preview CSV (verdict column). Default: newest propid-swap-preview-*.csv.}
        {--only= : Restrict to one target file number.}
        {--apply : Actually write. Omit for a read-only dry-run report.}
        {--rollback= : Undo a run by run_id ("last" for the newest).}';

    protected $description = 'Give "ID taken" files their master prop_id and the old KANGIS holder a new one (dry-run by default).';

    private const CONNECTION = 'sqlsrv';
    private const BACKUP_TABLE = 'propid_swap_backup';
    private const DIR = 'app/propid-remediation';

    /** table => columns that may carry a file number (same rule as propid:sync-downstream). */
    private const DOWNSTREAM = [
        'pra' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno', 'temp_fileno'],
        'CofO_staging' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno', 'temp_fileno'],
        'file_history_staging' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno', 'temp_fileno'],
        'pic' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno', 'temp_fileno'],
        'instrument_capture' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'],
        'deed_registrations' => ['fileno'],
    ];

    /** Tables carrying parent_prop_id / ancestral_prop_id, with the column naming the row's file. */
    private const LINEAGE = [
        'file_indexings' => ['file_number'],
        'fileNumber' => ['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo'],
        'pra' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno'],
        'CofO_staging' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno'],
    ];

    private const MASTER_COLS = ['primary_file_number_norm', 'mlsFNo_norm', 'kangisFileNo_norm', 'NewKANGISFileno_norm', 'temp_fileno_norm'];

    private array $columns = [];

    public function handle(PropertyIdAllocationService $allocator, PropIdLineageService $lineage): int
    {
        $conn = DB::connection(self::CONNECTION);

        if ($this->option('rollback') !== null) {
            return $this->rollback($conn, (string) $this->option('rollback'));
        }

        $from = $this->option('from') ?: $this->newestPreview();
        if (!$from || !is_file($from)) {
            $this->error('No preview CSV found. Pass --from=<path>.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $runId = date('Ymd-His');
        $this->info(($apply ? '[APPLY] ' : '[DRY-RUN] ') . 'run ' . $runId . ' · from ' . basename($from));

        $rows = $this->readPreview($from);
        if ($only = $this->norm($this->option('only'))) {
            $rows = array_values(array_filter($rows, fn ($r) => $this->norm($r['target_file']) === $only));
        }
        $this->line(count($rows) . ' READY rows in the preview.');

        if ($apply) {
            $this->ensureBackupTable($conn);
        }

        $log = [];
        $done = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($rows as $r) {
            $targetNo = $this->norm($r['target_file']);
            $holderNo = $this->norm($r['holder_file']);
            $id = (string) $r['takes_prop_id'];

            try {
                $plan = $this->plan($conn, (int) $r['target_id'], $targetNo, $holderNo, $id);
            } catch (Throwable $e) {
                $errors++;
                $log[] = [$targetNo, $id, $holderNo, '', 'ERROR (plan): ' . $e->getMessage(), '', '', ''];
                continue;
            }

            if ($plan['skip']) {
                $skipped++;
                $log[] = [$targetNo, $id, $holderNo, '', 'SKIPPED: ' . $plan['skip'], '', '', ''];
                continue;
            }

            $counts = [count($plan['downstream']), count($plan['parents']), count($plan['ancestral_only'])];

            if (!$apply) {
                $done++;
                $log[] = [$targetNo, $id, $holderNo, $plan['holder_new'] ? $plan['holder_new'] . ' (own master id)' : '(new)', 'WOULD SWAP', ...$counts];
                continue;
            }

            try {
                $new = $conn->transaction(fn () => $this->swap($conn, $allocator, $lineage, $runId, $plan));
                $done++;
                $log[] = [$targetNo, $id, $holderNo, $new, 'SWAPPED', ...$counts];
            } catch (Throwable $e) {
                $errors++;
                $log[] = [$targetNo, $id, $holderNo, '', 'ERROR: ' . $e->getMessage(), '', '', ''];
            }
        }

        $header = ['target_file', 'prop_id', 'holder_file', 'holder_new_prop_id', 'result', 'holder_tx_rows', 'parent_links', 'ancestral_only'];
        $this->table($header, $log);
        $this->table(['run', $apply ? 'swapped' : 'would swap', 'skipped', 'errors'], [[$runId, $done, $skipped, $errors]]);

        $path = storage_path(self::DIR . '/swap-' . ($apply ? 'applied' : 'dryrun') . '-' . $runId . '.csv');
        $fh = fopen($path, 'w');
        fputcsv($fh, $header);
        foreach ($log as $l) {
            fputcsv($fh, $l);
        }
        fclose($fh);
        $this->line('Log: ' . $path);
        if ($apply && $done > 0) {
            $this->line("Undo with: php artisan propid:swap-taken --rollback={$runId}");
        }

        return $errors ? self::FAILURE : self::SUCCESS;
    }

    /** Live re-check of one preview row; returns the exact rows the swap will touch. */
    private function plan($conn, int $targetId, string $targetNo, string $holderNo, string $id): array
    {
        $plan = ['skip' => null, 'target_id' => $targetId, 'target' => $targetNo, 'holder' => $holderNo, 'id' => $id];

        $target = $conn->table('file_indexings')->where('id', $targetId)->first(['id', 'file_number', 'prop_id', 'parent_prop_id', 'is_deleted']);
        if (!$target || $this->norm($target->file_number) !== $targetNo || (int) $target->is_deleted === 1) {
            return ['skip' => 'target row missing, renamed or deleted'] + $plan;
        }
        if (trim((string) $target->prop_id) !== '') {
            return ['skip' => 'target already has prop_id ' . $target->prop_id] + $plan;
        }

        $tm = $this->masterIds($conn, $targetNo);
        if ($tm['primary'] !== [$id]) {
            return ['skip' => 'master primary ids for target are [' . implode(',', $tm['primary']) . ']'] + $plan;
        }

        $holders = $conn->table('file_indexings')->where('prop_id', $id)->where('id', '<>', $targetId)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', '<>', 1))
            ->get(['id', 'file_number', 'parent_prop_id']);
        if ($holders->count() !== 1 || $this->norm($holders[0]->file_number) !== $holderNo) {
            return ['skip' => 'live holders now: ' . $holders->pluck('file_number')->implode(' | ')] + $plan;
        }
        $holder = $holders[0];
        if (trim((string) $holder->parent_prop_id) !== '') {
            return ['skip' => 'holder has a parent of its own'] + $plan;
        }
        if (str_starts_with($holderNo, 'ST-')) {
            return ['skip' => 'holder is ST'] + $plan;
        }
        // A holder PropID_Master already knows has its own id: copy it, never mint a second.
        $hm = $this->masterIds($conn, $holderNo);
        $plan['holder_new'] = null;
        if (in_array($id, $hm['primary'], true) || count($hm['primary']) > 1) {
            return ['skip' => 'holder is primary of master id(s) ' . implode(',', $hm['primary'])] + $plan;
        }
        if (count($hm['primary']) === 1) {
            $own = $hm['primary'][0];
            if ($conn->table('file_indexings')->where('prop_id', $own)->exists()) {
                return ['skip' => "holder's own master id {$own} is held by another file"] + $plan;
            }
            $plan['holder_new'] = $own;
        }

        $linked = in_array($id, $this->tokens($target->parent_prop_id), true) || in_array($id, $hm['alias'], true);
        if (!$linked) {
            return ['skip' => 'no parent link or master alias connects the files'] + $plan;
        }

        // downstream rows on the id: the holder's move with it, the target's stay
        $plan['downstream'] = [];
        foreach (self::DOWNSTREAM as $table => $cols) {
            $cols = $this->existing($table, $cols);
            if (!$cols || !$this->has($table, 'prop_id')) {
                continue;
            }
            foreach ($conn->table($table)->where('prop_id', $id)->get(array_merge(['id'], $cols)) as $row) {
                $nums = array_map(fn ($c) => $this->norm($row->$c), $cols);
                if (in_array($targetNo, $nums, true)) {
                    continue;
                }
                if (!in_array($holderNo, $nums, true)) {
                    return ['skip' => "transaction {$table}#{$row->id} on {$id} belongs to neither file"] + $plan;
                }
                $plan['downstream'][] = [$table, (string) $row->id];
            }
        }

        // lineage rows naming the id as parent (re-point) or only as ancestral (re-root)
        $plan['parents'] = [];
        $plan['ancestral_only'] = [];
        foreach (self::LINEAGE as $table => $numCols) {
            $numCols = $this->existing($table, $numCols);
            $hasAnc = $this->has($table, 'ancestral_prop_id');
            $sel = array_merge(['id', 'parent_prop_id'], $hasAnc ? ['ancestral_prop_id'] : [], $this->has($table, 'prop_id') ? ['prop_id'] : [], $numCols);
            $rows = $conn->table($table)
                ->where(function ($q) use ($id, $hasAnc) {
                    $q->where('parent_prop_id', 'like', '%' . $id . '%');
                    if ($hasAnc) {
                        $q->orWhere('ancestral_prop_id', 'like', '%' . $id . '%');
                    }
                })->get($sel);

            foreach ($rows as $row) {
                $nums = array_map(fn ($c) => $this->norm($row->$c), $numCols);
                $inParent = in_array($id, $this->tokens($row->parent_prop_id), true);
                // The holder's own row calling the id its parent is a self-link: ambiguous.
                if ($inParent && in_array($holderNo, $nums, true) && !in_array($targetNo, $nums, true)) {
                    return ['skip' => "holder's own {$table}#{$row->id} names {$id} as its parent"] + $plan;
                }
                if ($inParent) {
                    $plan['parents'][] = [$table, (string) $row->id];
                } elseif ($hasAnc && in_array($id, $this->tokens($row->ancestral_prop_id), true)) {
                    $plan['ancestral_only'][] = [$table, (string) $row->id];
                }
            }
        }

        $plan['holder_id'] = (string) $holder->id;

        return $plan;
    }

    /** One file's swap. Runs inside a transaction; returns the holder's new id. */
    private function swap($conn, PropertyIdAllocationService $allocator, PropIdLineageService $lineage, string $runId, array $plan): string
    {
        $id = $plan['id'];
        $ctx = [$runId, $plan['target'], $plan['holder']];

        if ($plan['holder_new'] !== null) {
            $new = $plan['holder_new'];
        } else {
            // skip_lookup: without it the allocator hands back the id we are moving the holder off.
            $new = (string) $allocator->allocateOrRetrievePropId($plan['holder'], null, null, null, ['skip_lookup' => true]);
            foreach ($conn->table('PropID_Master')->where('prop_id', $new)->pluck('id') as $mid) {
                $this->backup($conn, $ctx, 'PropID_Master', (string) $mid, '__inserted__', null, $new);
            }
        }

        $this->set($conn, $ctx, 'file_indexings', (string) $plan['target_id'], 'prop_id', null, $id);
        $this->set($conn, $ctx, 'file_indexings', $plan['holder_id'], 'prop_id', $id, $new);

        foreach ($plan['downstream'] as [$table, $rowId]) {
            $this->set($conn, $ctx, $table, $rowId, 'prop_id', $id, $new);
        }

        $touched = [];
        foreach ($plan['parents'] as [$table, $rowId]) {
            $old = $conn->table($table)->where('id', $rowId)->value('parent_prop_id');
            $this->set($conn, $ctx, $table, $rowId, 'parent_prop_id', $old, $this->replaceToken($old, $id, $new));
            $touched[] = [$table, $rowId];
        }

        // The holder is a root (checked), so whatever climbed to the id now climbs to the new one.
        $lineage->flushCache(true);
        foreach ($touched as [$table, $rowId]) {
            if (!$this->has($table, 'ancestral_prop_id')) {
                continue;
            }
            $row = $conn->table($table)->where('id', $rowId)->first(array_merge(['parent_prop_id', 'ancestral_prop_id'], $this->has($table, 'prop_id') ? ['prop_id'] : []));
            $anc = $lineage->resolveAncestralForRow($row->prop_id ?? null, $row->parent_prop_id);
            if ((string) $anc !== (string) $row->ancestral_prop_id) {
                $this->set($conn, $ctx, $table, $rowId, 'ancestral_prop_id', $row->ancestral_prop_id, $anc);
            }
        }
        foreach ($plan['ancestral_only'] as [$table, $rowId]) {
            $old = $conn->table($table)->where('id', $rowId)->value('ancestral_prop_id');
            $this->set($conn, $ctx, $table, $rowId, 'ancestral_prop_id', $old, $this->replaceToken($old, $id, $new));
        }

        return $new;
    }

    /** Conditional update (row must still hold $old) plus its backup line. */
    private function set($conn, array $ctx, string $table, string $rowId, string $col, $old, $new): void
    {
        $q = $conn->table($table)->where('id', $rowId);
        ($old === null || $old === '')
            ? $q->where(fn ($w) => $w->whereNull($col)->orWhere($col, ''))
            : $q->where($col, $old);

        if ($q->update([$col => $new]) !== 1) {
            throw new \RuntimeException("{$table}#{$rowId}.{$col} changed under us");
        }

        $this->backup($conn, $ctx, $table, $rowId, $col, $old, $new);
    }

    private function backup($conn, array $ctx, string $table, string $rowId, string $col, $old, $new): void
    {
        [$runId, $target, $holder] = $ctx;
        $conn->table(self::BACKUP_TABLE)->insert([
            'run_id' => $runId,
            'target_file' => $target,
            'holder_file' => $holder,
            'table_name' => $table,
            'row_id' => $rowId,
            'column_name' => $col,
            'old_value' => $old === null ? null : (string) $old,
            'new_value' => $new === null ? null : (string) $new,
            'created_at' => now(),
        ]);
    }

    private function rollback($conn, string $runId): int
    {
        if (!Schema::connection(self::CONNECTION)->hasTable(self::BACKUP_TABLE)) {
            $this->error('Nothing to roll back: ' . self::BACKUP_TABLE . ' does not exist.');

            return self::FAILURE;
        }
        if ($runId === '' || $runId === 'last') {
            $runId = (string) $conn->table(self::BACKUP_TABLE)->whereNull('rolled_back_at')->max('run_id');
        }
        $groups = $conn->table(self::BACKUP_TABLE)->where('run_id', $runId)->whereNull('rolled_back_at')
            ->orderByDesc('id')->get()->groupBy('target_file');
        if ($groups->isEmpty()) {
            $this->error("No un-rolled-back rows for run {$runId}.");

            return self::FAILURE;
        }

        $ok = 0;
        $failed = 0;
        foreach ($groups as $target => $lines) {
            try {
                $conn->transaction(function () use ($conn, $lines) {
                    foreach ($lines as $b) { // newest first: exact reverse of the swap
                        if ($b->column_name === '__inserted__') {
                            $conn->table('PropID_Master')->where('id', $b->row_id)->where('prop_id', $b->new_value)->delete();
                        } else {
                            $q = $conn->table($b->table_name)->where('id', $b->row_id);
                            $b->new_value === null ? $q->whereNull($b->column_name) : $q->where($b->column_name, $b->new_value);
                            if ($q->update([$b->column_name => $b->old_value]) !== 1) {
                                throw new \RuntimeException("{$b->table_name}#{$b->row_id}.{$b->column_name} changed since the swap");
                            }
                        }
                        $conn->table(self::BACKUP_TABLE)->where('id', $b->id)->update(['rolled_back_at' => now()]);
                    }
                });
                $ok++;
            } catch (Throwable $e) {
                $failed++;
                $this->warn("{$target}: not rolled back — " . $e->getMessage());
            }
        }

        $this->info("Run {$runId}: {$ok} file(s) rolled back, {$failed} failed.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function ensureBackupTable($conn): void
    {
        if (Schema::connection(self::CONNECTION)->hasTable(self::BACKUP_TABLE)) {
            return;
        }
        Schema::connection(self::CONNECTION)->create(self::BACKUP_TABLE, function ($t) {
            $t->bigIncrements('id');
            $t->string('run_id', 20)->index();
            $t->string('target_file', 100)->nullable();
            $t->string('holder_file', 100)->nullable();
            $t->string('table_name', 64);
            $t->string('row_id', 32);
            $t->string('column_name', 64);
            $t->string('old_value', 255)->nullable();
            $t->string('new_value', 255)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->timestamp('rolled_back_at')->nullable();
        });
        $this->line('Created backup table ' . self::BACKUP_TABLE . '.');
    }

    private function readPreview(string $path): array
    {
        $fh = fopen($path, 'r');
        $head = fgetcsv($fh);
        $rows = [];
        while (($r = fgetcsv($fh)) !== false) {
            $r = array_combine($head, $r);
            if (($r['verdict'] ?? '') === 'READY') {
                $rows[] = $r;
            }
        }
        fclose($fh);

        return $rows;
    }

    private function newestPreview(): ?string
    {
        $files = glob(storage_path(self::DIR . '/propid-swap-preview-2*.csv')) ?: [];
        rsort($files);

        return $files[0] ?? null;
    }

    private function masterIds($conn, string $n): array
    {
        $out = ['primary' => [], 'alias' => []];
        foreach ($conn->table('PropID_Master')->where(function ($q) use ($n) {
            foreach (self::MASTER_COLS as $c) {
                $q->orWhere($c, $n);
            }
        })->get(['prop_id', 'primary_file_number_norm']) as $m) {
            $out[$this->norm($m->primary_file_number_norm) === $n ? 'primary' : 'alias'][] = (string) $m->prop_id;
        }

        return ['primary' => array_values(array_unique($out['primary'])), 'alias' => array_values(array_unique($out['alias']))];
    }

    private function replaceToken($list, string $from, string $to): string
    {
        $out = [];
        foreach ($this->tokens($list) as $t) {
            $t = $t === $from ? $to : $t;
            if (!in_array($t, $out, true)) {
                $out[] = $t;
            }
        }

        return implode(',', $out);
    }

    private function tokens($list): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $list)), fn ($t) => $t !== ''));
    }

    private function norm($v): string
    {
        return strtoupper(trim((string) $v));
    }

    private function existing(string $table, array $cols): array
    {
        return array_values(array_filter($cols, fn ($c) => $this->has($table, $c)));
    }

    private function has(string $table, string $col): bool
    {
        if (!isset($this->columns[$table])) {
            $s = Schema::connection(self::CONNECTION);
            $this->columns[$table] = $s->hasTable($table) ? array_flip($s->getColumnListing($table)) : [];
        }

        return isset($this->columns[$table][$col]);
    }
}
