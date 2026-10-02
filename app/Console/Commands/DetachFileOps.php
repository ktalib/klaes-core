<?php

namespace App\Console\Commands;

use App\Services\PropertyIdAllocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Remove a file's Occupancy Permits, and optionally register the file's Property ID.
 *
 * WHY A COMMAND
 * This is a bulk removal of real registry rows, and it has to be run on production as
 * well as here. A command is repeatable, prints exactly what it is about to do, and
 * leaves the ids in the log so the run can be reversed — none of which a hand-typed
 * UPDATE gives you.
 *
 * WHAT IT TOUCHES, AND WHAT IT REFUSES TO
 * Only Occupancy Permits, and only ones carrying the file's number (or an alias). A
 * parcel usually holds other instruments — KN81 also carries a Certificate of Occupancy
 * and a Right of Occupancy on the same prop_id — and those are somebody's title. They
 * are never in scope, which is why rows are selected by INSTRUMENT TYPE plus FILE
 * NUMBER, never by prop_id.
 *
 * SOFT BY DEFAULT
 * is_deleted = 1, which is what every reader in this application already filters on, and
 * what --restore puts back. --hard is available for a genuine purge but must be asked
 * for: a DELETE here cannot be undone and the rows carry registration particulars that
 * exist nowhere else.
 *
 * ALIASES
 * A land file's permits are often filed under its KANGIS number, held in
 * file_indexings.related_fileno as a JSON array. RES-2016-2818 is exactly that: every
 * alias column empty, related_fileno ["KN81"], and all 27 of its permits filed as KN81.
 * Those aliases are resolved automatically and listed before anything is done, because
 * an alias can be shared — KN81 is also related to KNML 6060, so removing "KN81's
 * permits" removes them from that file's view too.
 *
 * USAGE
 *   php artisan op:detach-file-ops RES-2016-2818 --dry-run
 *   php artisan op:detach-file-ops RES-2016-2818 --allocate
 *   php artisan op:detach-file-ops RES-2016-2818 --restore
 */
class DetachFileOps extends Command
{
    protected $signature = 'op:detach-file-ops
        {file : the file number whose Occupancy Permits are to be removed}
        {--alias=* : extra file numbers to treat as this file (added to the ones resolved automatically)}
        {--no-aliases : use ONLY the file number given, resolving no aliases}
        {--allocate : register a Property ID for the file in PropID_Master if it has none}
        {--hard : DELETE the rows instead of flagging them. Cannot be undone}
        {--restore : put a previous run back — clears is_deleted on this file\'s permits}
        {--dry-run : show what would happen and change nothing}
        {--force : skip the confirmation prompt (for unattended production runs)}';

    protected $description = "Remove a file's Occupancy Permits (soft by default) and optionally register its Property ID";

    private const CONNECTION = 'sqlsrv';

    public function handle(): int
    {
        $fileNo = trim((string) $this->argument('file'));

        if ($fileNo === '') {
            $this->error('A file number is required.');

            return self::FAILURE;
        }

        $conn = DB::connection(self::CONNECTION);
        $numbers = $this->resolveNumbers($conn, $fileNo);

        $this->line('');
        $this->info('File           : ' . $fileNo);
        $this->info('Numbers in use : ' . implode(', ', $numbers));

        $this->warnAboutSharedAliases($conn, $fileNo, $numbers);

        if ($this->option('restore')) {
            return $this->restore($conn, $numbers);
        }

        $rows = $this->findOps($conn, $numbers, false);

        if ($rows->isEmpty()) {
            $this->line('');
            $this->warn('No live Occupancy Permits are filed under those numbers. Nothing to do.');

            return $this->maybeAllocate($conn, $fileNo);
        }

        $this->render($rows);
        $this->showSpared($conn, $rows);

        if ($this->option('dry-run')) {
            $this->line('');
            $this->warn('Dry run — nothing was changed.');

            return self::SUCCESS;
        }

        $hard = (bool) $this->option('hard');

        if (! $this->option('force')) {
            $question = $hard
                ? 'PERMANENTLY DELETE these ' . $rows->count() . ' permit(s)? This cannot be undone.'
                : 'Flag these ' . $rows->count() . ' permit(s) as deleted? (reversible with --restore)';

            if (! $this->confirm($question, false)) {
                $this->line('Cancelled. Nothing was changed.');

                return self::SUCCESS;
            }
        }

        $affected = $this->remove($conn, $rows, $hard);

        $this->line('');
        $this->info($affected . ' permit(s) ' . ($hard ? 'deleted permanently.' : 'flagged as deleted.'));

        // The ids go to the log as well as the screen: a production run scrolls away, and
        // without them a --restore has nothing to aim at if the file numbers are edited
        // afterwards.
        Log::channel('op_batch')->warning('op:detach-file-ops removed permits', [
            'file' => $fileNo,
            'numbers' => $numbers,
            'hard' => $hard,
            'count' => $affected,
            'pra_ids' => $rows->where('source_table', 'pra')->pluck('id')->all(),
            'instrument_capture_ids' => $rows->where('source_table', 'instrument_capture')->pluck('id')->all(),
        ]);

        if (! $hard) {
            $this->line('Undo with: php artisan op:detach-file-ops ' . escapeshellarg($fileNo) . ' --restore');
        }

        return $this->maybeAllocate($conn, $fileNo);
    }

    /**
     * The file's own number, plus every number its permits might be filed under.
     *
     * related_fileno is the one that matters and the one no column exposes: a JSON array
     * on newer rows, a comma list on older ones. Split on comma only — KANGIS numbers
     * contain spaces ("KNML 1") and splitting on those tears one number into two.
     *
     * @return array<int,string>
     */
    private function resolveNumbers($conn, string $fileNo): array
    {
        $numbers = [$fileNo];

        foreach ((array) $this->option('alias') as $alias) {
            $alias = trim((string) $alias);
            if ($alias !== '') {
                $numbers[] = $alias;
            }
        }

        if (! $this->option('no-aliases')) {
            $indexing = $conn->table('file_indexings')
                ->where(function ($q) use ($fileNo) {
                    foreach (['file_number', 'mls_file_no', 'kangis_file_no', 'new_kangis_file_no', 'temp_file_no'] as $column) {
                        $q->orWhereRaw("LTRIM(RTRIM($column)) = ?", [$fileNo]);
                    }
                })
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->orderByDesc('id')
                ->first();

            if ($indexing) {
                foreach (['file_number', 'mls_file_no', 'kangis_file_no', 'new_kangis_file_no', 'temp_file_no', 'old_fileno'] as $column) {
                    $value = trim((string) ($indexing->{$column} ?? ''));
                    if ($value !== '' && $value !== '-') {
                        $numbers[] = $value;
                    }
                }

                foreach ($this->parseRelatedFileno($indexing->related_fileno ?? null) as $value) {
                    $numbers[] = $value;
                }
            }
        }

        $seen = [];
        $out = [];
        foreach ($numbers as $number) {
            $upper = mb_strtoupper($number);
            if ($number === '' || isset($seen[$upper])) {
                continue;
            }
            $seen[$upper] = true;
            $out[] = $number;
        }

        return $out;
    }

    /** @return array<int,string> */
    private function parseRelatedFileno($raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [];
        }

        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter(
                    array_map(fn ($v) => trim((string) $v), $decoded),
                    fn ($v) => $v !== '' && $v !== '-'
                ));
            }
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn ($v) => $v !== '' && $v !== '-'
        ));
    }

    /**
     * An alias can belong to more than one file. Say so before anything is removed —
     * the permits about to go will disappear from those files' views too.
     */
    private function warnAboutSharedAliases($conn, string $fileNo, array $numbers): void
    {
        foreach ($numbers as $number) {
            if (mb_strtoupper($number) === mb_strtoupper($fileNo)) {
                continue;
            }

            try {
                $others = $conn->table('file_indexings')
                    ->where('related_fileno', 'LIKE', '%"' . $number . '"%')
                    ->whereRaw('LTRIM(RTRIM(file_number)) <> ?', [$fileNo])
                    ->pluck('file_number')
                    ->all();
            } catch (\Throwable $e) {
                continue;
            }

            if (! empty($others)) {
                $this->line('');
                $this->warn('NOTE: "' . $number . '" is also a related number of: ' . implode(', ', $others));
                $this->warn('      Permits filed under it will leave those files too.');
            }
        }
    }

    /**
     * The Occupancy Permits filed under these numbers.
     *
     * Selected by instrument type AND file number — never by prop_id. A parcel carries
     * other instruments (KN81's own Certificate of Occupancy and Right of Occupancy sit
     * on the same prop_id) and those are title records, not permits.
     */
    private function findOps($conn, array $numbers, bool $deletedOnly)
    {
        $fileMatch = function ($query, array $columns) use ($numbers) {
            $query->where(function ($outer) use ($columns, $numbers) {
                foreach ($columns as $column) {
                    foreach ($numbers as $number) {
                        $outer->orWhereRaw("LTRIM(RTRIM($column)) = ?", [$number]);
                    }
                }
            });
        };

        $praQuery = $conn->table('pra')
            ->where('instrument_type', 'LIKE', '%Occupancy Permit%');
        $fileMatch($praQuery, ['mlsFNo', 'fileno', 'temp_fileno', 'kangisFileNo', 'NewKANGISFileno']);
        $praQuery->where(fn ($q) => $deletedOnly ? $q->where('is_deleted', 1) : $q->whereNull('is_deleted')->orWhere('is_deleted', 0));

        $pra = $praQuery
            ->select('id', 'op_serial_number', 'prop_id', 'mlsFNo', 'fileno', 'temp_fileno', 'party_2', 'transaction_date')
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => (object) [
                'source_table' => 'pra',
                'id' => (int) $r->id,
                'serial' => trim((string) $r->op_serial_number),
                'prop_id' => trim((string) $r->prop_id),
                'file_no' => trim((string) ($r->mlsFNo ?: $r->fileno ?: $r->temp_fileno)),
                'holder' => trim((string) $r->party_2),
                'date' => $r->transaction_date,
            ]);

        $icQuery = $conn->table('instrument_capture')
            ->where('instrument_type', 'Occupancy Permit (OP)');
        $fileMatch($icQuery, ['mlsFNo', 'temp_fileno', 'kangisFileNo', 'NewKANGISFileno']);
        $icQuery->where(fn ($q) => $deletedOnly ? $q->where('is_deleted', 1) : $q->whereNull('is_deleted')->orWhere('is_deleted', 0));

        $ic = $icQuery
            ->select('id', 'op_serial_number', 'prop_id', 'mlsFNo', 'temp_fileno', 'party_2_name', 'instrument_date')
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => (object) [
                'source_table' => 'instrument_capture',
                'id' => (int) $r->id,
                'serial' => trim((string) $r->op_serial_number),
                'prop_id' => trim((string) $r->prop_id),
                'file_no' => trim((string) ($r->mlsFNo ?: $r->temp_fileno)),
                'holder' => trim((string) $r->party_2_name),
                'date' => $r->instrument_date,
            ]);

        return $pra->concat($ic)->values();
    }

    private function render($rows): void
    {
        $this->line('');
        $this->line('Occupancy Permits in scope: ' . $rows->count());
        $this->table(
            ['table', 'id', 'serial', 'prop_id', 'file no', 'holder'],
            $rows->map(fn ($r) => [
                $r->source_table,
                $r->id,
                $r->serial !== '' ? $r->serial : '—',
                $r->prop_id !== '' ? $r->prop_id : '—',
                $r->file_no !== '' ? $r->file_no : '—',
                mb_substr($r->holder, 0, 30),
            ])->all()
        );
    }

    /**
     * What sits on the same parcels and is deliberately being left alone. Printed so the
     * operator can see the command is not clearing the parcel out.
     */
    private function showSpared($conn, $rows): void
    {
        $propIds = $rows->pluck('prop_id')->filter(fn ($v) => $v !== '')->unique()->values()->all();

        if (empty($propIds)) {
            return;
        }

        $ids = $rows->where('source_table', 'pra')->pluck('id')->all();

        $spared = $conn->table('pra')
            ->whereIn('prop_id', $propIds)
            ->whereNotIn('id', $ids ?: [0])
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->select('id', 'instrument_type', 'mlsFNo')
            ->orderBy('id')
            ->get();

        if ($spared->isEmpty()) {
            return;
        }

        $this->line('');
        $this->info('NOT touched — other instruments on the same parcel(s):');
        foreach ($spared as $row) {
            $this->line('   pra#' . $row->id . '  ' . $row->instrument_type . '  ' . $row->mlsFNo);
        }
    }

    private function remove($conn, $rows, bool $hard): int
    {
        $now = now();
        $affected = 0;

        $conn->transaction(function () use ($conn, $rows, $hard, $now, &$affected) {
            foreach (['pra', 'instrument_capture'] as $table) {
                $ids = $rows->where('source_table', $table)->pluck('id')->all();

                if (empty($ids)) {
                    continue;
                }

                if ($hard) {
                    $affected += $conn->table($table)->whereIn('id', $ids)->delete();

                    continue;
                }

                $update = ['is_deleted' => 1, 'updated_at' => $now];

                if ($conn->getSchemaBuilder()->hasColumn($table, 'deleted_at')) {
                    $update['deleted_at'] = $now;
                }

                $affected += $conn->table($table)->whereIn('id', $ids)->update($update);
            }
        });

        return $affected;
    }

    private function restore($conn, array $numbers): int
    {
        $rows = $this->findOps($conn, $numbers, true);

        if ($rows->isEmpty()) {
            $this->line('');
            $this->warn('No deleted Occupancy Permits are filed under those numbers. Nothing to restore.');

            return self::SUCCESS;
        }

        $this->render($rows);

        if ($this->option('dry-run')) {
            $this->line('');
            $this->warn('Dry run — nothing was changed.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Restore these ' . $rows->count() . ' permit(s)?', false)) {
            $this->line('Cancelled. Nothing was changed.');

            return self::SUCCESS;
        }

        $now = now();
        $affected = 0;

        $conn->transaction(function () use ($conn, $rows, $now, &$affected) {
            foreach (['pra', 'instrument_capture'] as $table) {
                $ids = $rows->where('source_table', $table)->pluck('id')->all();

                if (empty($ids)) {
                    continue;
                }

                $update = ['is_deleted' => 0, 'updated_at' => $now];

                if ($conn->getSchemaBuilder()->hasColumn($table, 'deleted_at')) {
                    $update['deleted_at'] = null;
                }

                $affected += $conn->table($table)->whereIn('id', $ids)->update($update);
            }
        });

        $this->line('');
        $this->info($affected . ' permit(s) restored.');

        Log::channel('op_batch')->warning('op:detach-file-ops restored permits', [
            'numbers' => $numbers,
            'count' => $affected,
        ]);

        return self::SUCCESS;
    }

    /**
     * Register a Property ID for the file, if asked and if it has none.
     *
     * Through PropertyIdAllocationService, the same allocator every capture screen uses,
     * so the id is registered in PropID_Master rather than merely written somewhere. It
     * is idempotent per file number: running the command twice does not mint two ids.
     */
    private function maybeAllocate($conn, string $fileNo): int
    {
        if (! $this->option('allocate')) {
            return self::SUCCESS;
        }

        $existing = $conn->table('PropID_Master')
            ->where(function ($q) use ($fileNo) {
                foreach (['primary_file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'] as $column) {
                    $q->orWhere($column, $fileNo);
                }
            })
            ->value('prop_id');

        if ($existing) {
            $this->line('');
            $this->info($fileNo . ' already has Property ID ' . $existing . ' — left as it is.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line('');
            $this->warn('Dry run — a Property ID would have been allocated for ' . $fileNo . '.');

            return self::SUCCESS;
        }

        try {
            $propId = (int) app(PropertyIdAllocationService::class)->allocateOrRetrievePropId($fileNo, $fileNo);
        } catch (\Throwable $e) {
            $this->error('Could not allocate a Property ID: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->line('');
        $this->info($fileNo . ' registered in PropID_Master with Property ID ' . $propId . '.');

        Log::channel('op_batch')->warning('op:detach-file-ops allocated a Property ID', [
            'file' => $fileNo,
            'prop_id' => $propId,
        ]);

        return self::SUCCESS;
    }
}
