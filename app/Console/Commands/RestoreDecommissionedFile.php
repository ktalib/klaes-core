<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Put a decommissioned file back into the live registers.
 *
 * Commissioning a parcel update used to physically delete the parent from
 * fileNumber and file_indexings (PlotWorkflowService's $removeIndexedParent arm).
 * That arm is now off everywhere — a retired parent is archived and FLAGGED, never
 * removed — but files retired before the change are gone from the live tables and
 * have to be put back from what was archived.
 *
 * The file returns flagged as decommissioned, carrying its reason and successors:
 * the state the corrected commissioning path now produces. It does not come back
 * as an active file, because it was genuinely retired.
 *
 * Sources, in order of fidelity:
 *   deprecated_records      a full snapshot of the file_indexings row (best)
 *   mls_file_no             the commissioning record, if the file was commissioned here
 *   decommissioned_files    the archive row: name, dates, reason, successors
 *   file_history_staging /  lineage rows, which decommissioning deliberately keeps
 *   pra                     and which carry land use, plot and parties
 *
 * Dry run by default; pass --commit to write.
 */
class RestoreDecommissionedFile extends Command
{
    protected $signature = 'klaes:restore-decommissioned
                            {file* : One or more file numbers to restore}
                            {--commit : Write the rows (otherwise this is a dry run)}';

    protected $description = 'Restore a decommissioned file to fileNumber / file_indexings from its archives';

    /**
     * Ids already claimed by an earlier file in this same run.
     *
     * A Change of Purpose renumbers the fileNumber row in place, so the parent and the
     * file it became share one id. Restoring both means the second needs a fresh id —
     * true in a --commit run because the first has been written by then, but the dry
     * run would otherwise promise the same id twice and read as a conflict.
     *
     * @var array<string,array<int,true>>
     */
    private array $claimed = ['file_indexings' => [], 'fileNumber' => []];

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');

        foreach ($this->argument('file') as $fileNo) {
            $this->restore(trim($fileNo), $commit);
        }

        if (! $commit) {
            $this->newLine();
            $this->warn('DRY RUN — nothing was written. Re-run with --commit to apply.');
        }

        return self::SUCCESS;
    }

    private function restore(string $fileNo, bool $commit): void
    {
        $db = DB::connection('sqlsrv');
        $this->newLine();
        $this->line("<options=bold>{$fileNo}</>");

        $archive = $db->table('decommissioned_files')
            ->where(fn ($q) => $q->where('file_no', $fileNo)->orWhere('mls_file_no', $fileNo))
            ->where(fn ($q) => $q->where('false_decommissioning', '<>', 1)->orWhereNull('false_decommissioning'))
            ->orderByDesc('id')
            ->first();

        if (! $archive) {
            $this->error('  no decommissioning archive — nothing to restore from');
            return;
        }

        $liveIndexing = $db->table('file_indexings')->where('file_number', $fileNo)->exists();
        $liveNumber   = $db->table('fileNumber')->where('mlsfNo', $fileNo)->exists();

        if ($liveIndexing && $liveNumber) {
            $this->info('  already present in both registers — skipped');
            return;
        }

        $snapshot = $db->table('deprecated_records')->where('file_number', $fileNo)->orderByDesc('id')->first();
        $mls      = $db->table('mls_file_no')->where('full_file_number', $fileNo)->orderByDesc('id')->first();
        $hist     = $db->table('file_history_staging')->where('fileno', $fileNo)->orderByDesc('id')->first();
        $pra      = $db->table('pra')->where('mlsFNo', $fileNo)->orderByDesc('id')->first();

        // Every screen reads the retirement off these, so they ride on both rows.
        $flags = [
            'is_decommissioned'      => 1,
            'decommissioned_by'      => $archive->decommissioned_by,
            'decommissioning_reason' => $archive->decommissioning_reason,
            'successor_file_no'      => $archive->successor_file_no,
        ];

        $indexing = $this->forTable('file_indexings', array_merge(
            $snapshot ? $this->drop((array) $snapshot, ['id', 'file_indexing_id', 'workflow_type', 'decommissioned_at']) : [],
            array_filter([
                'file_number'     => $fileNo,
                'file_title'      => $archive->file_name,
                // file_history_staging first — it is the richer row — then the deed.
                // A file retired before it ever reached Legal Search has only the deed.
                'land_use_type'   => $hist->land_use ?? ($pra->land_use ?? null),
                'plot_number'     => $hist->plot_no ?? ($pra->plot_no ?? null),
                'district'        => $hist->districtName ?? ($pra->districtName ?? null),
                'lga'             => $hist->lgsaOrCity ?? ($pra->lgsaOrCity ?? null),
                'location'        => $hist->location ?? ($pra->location ?? null),
                'plot_size'       => $pra->plot_size ?? null,
                'prop_id'         => $hist->prop_id ?? ($pra->prop_id ?? null),
                'original_holder' => $hist->party_1 ?? ($pra->party_1 ?? null),
                'current_holder'  => $hist->party_2 ?? ($pra->party_2 ?? null),
                'kangis_file_no'  => $archive->kangis_file_no,
            ], fn ($v) => $v !== null && $v !== ''),
            $flags,
            [
                'created_by'        => $snapshot->created_by ?? 'System (restore)',
                'decommissioned_at' => $archive->decommissioning_date,
                'created_at'        => $snapshot->created_at ?? ($mls->created_at ?? now()),
                'updated_at'        => now(),
            ]
        ));

        $number = $this->forTable('fileNumber', array_merge(
            array_filter([
                'mlsfNo'             => $fileNo,
                'kangisFileNo'       => $archive->kangis_file_no,
                'NewKANGISFileNo'    => $archive->new_kangis_file_no,
                'FileName'           => $archive->file_name,
                'land_use'           => $mls->land_use ?? ($hist->land_use ?? null),
                'plot_no'            => $mls->plot_no ?? ($hist->plot_no ?? null),
                'location'           => $mls->location ?? ($hist->location ?? null),
                'lga'                => $mls->lga ?? ($hist->lgsaOrCity ?? null),
                'district'           => $mls->district ?? ($hist->districtName ?? null),
                'tracking_id'        => $mls->tracking_id ?? null,
                'customer_type'      => $mls->customer_type ?? null,
                'commissioning_date' => $archive->commissioning_date ?? ($mls->commissioning_date ?? null),
                'related_fileno'     => $snapshot->related_fileno ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
            $flags,
            [
                'decommissioning_date' => $archive->decommissioning_date,
                'created_at'           => $mls->created_at ?? now(),
                'updated_at'           => now(),
            ]
        ));

        // The original ids are reused when still free, so anything that pointed at the
        // row by id — scanned documents above all — reattaches instead of dangling.
        $free = function (string $table, $id) use ($db): ?int {
            $id = (int) $id;
            if ($id <= 0 || isset($this->claimed[$table][$id])) {
                return null;
            }
            $key = $table === 'fileNumber' ? 'id' : 'id';
            if ($db->table($table)->where($key, $id)->exists()) {
                return null;
            }
            $this->claimed[$table][$id] = true;

            return $id;
        };

        $indexingId = $snapshot ? $free('file_indexings', $snapshot->file_indexing_id) : null;
        $numberId   = $free('fileNumber', $archive->file_number_id);

        // The commissioning record. A Change of Purpose RENUMBERS this row in place
        // rather than writing a new one, so a file retired that way has no mls_file_no
        // of its own any more — its row is now the successor's. That row is the file's
        // own history, so it is cloned back with the number parts re-stamped.
        [$mlsRow, $mlsNote] = $this->rebuildMlsRow($db, $fileNo, $archive, $mls, $pra);

        $this->line('  archive        : id ' . $archive->id . ' — ' . $archive->decommissioning_reason);
        $this->line('  snapshot       : ' . ($snapshot ? 'deprecated_records id ' . $snapshot->id : 'none (rebuilt from lineage)'));
        $this->line('  file_indexings : ' . ($liveIndexing ? 'already live' : ($indexingId ? "insert id {$indexingId}" : 'insert (new id)')) . ' — ' . count($indexing) . ' columns');
        $this->line('  fileNumber     : ' . ($liveNumber ? 'already live' : ($numberId ? "insert id {$numberId}" : 'insert (new id)')) . ' — ' . count($number) . ' columns');
        $this->line('  mls_file_no    : ' . $mlsNote);
        $this->line('  successors     : ' . \Illuminate\Support\Str::limit((string) $archive->successor_file_no, 70));

        if (! $commit) {
            return;
        }

        $db->transaction(function () use ($db, $indexing, $number, $mlsRow, $indexingId, $numberId, $liveIndexing, $liveNumber) {
            if (! $liveIndexing) {
                $this->insert($db, 'file_indexings', $indexing, $indexingId);
            }
            if (! $liveNumber) {
                $this->insert($db, 'fileNumber', $number, $numberId);
            }
            if ($mlsRow) {
                $db->table('mls_file_no')->insert($mlsRow);
            }
        });

        $this->info('  restored');
    }

    /**
     * The mls_file_no row, when the file no longer has one.
     *
     * Preferred source is the successor's row, because a Change of Purpose renumbers
     * this row in place: CON-RES-2026-3004's row became CON-COM-2026-636's, keeping
     * the subdivision that made it and its creation time. Cloning it back and
     * re-stamping only the number parts restores the file's own record exactly.
     *
     * Where the successor has no row either, the number itself gives land use, year
     * and serial, and the archive and deed give the rest.
     *
     * @return array{0: ?array, 1: string}  the row to insert (or null), and what to print
     */
    private function rebuildMlsRow($db, string $fileNo, $archive, $existing, $pra): array
    {
        if ($existing) {
            return [null, 'already present (id ' . $existing->id . ')'];
        }

        // "CON-RES-2026-3004" -> land use CON-RES, year 2026, serial 3004.
        if (! preg_match('/^(.*)-(\d{4})-(\d+)$/', $fileNo, $m)) {
            return [null, 'missing — file number does not parse, skipped'];
        }
        [, $landUse, $year, $serial] = $m;

        $stamp = [
            'full_file_number' => $fileNo,
            'land_use'         => $landUse,
            'year'             => $year,
            'serial_number'    => $serial,
        ];

        $successors = array_filter(array_map('trim', explode(',', (string) $archive->successor_file_no)));
        foreach ($successors as $successor) {
            $row = $db->table('mls_file_no')->where('full_file_number', $successor)->first();
            if (! $row) {
                continue;
            }

            $clone = $this->drop((array) $row, ['id']);

            return [
                $this->forTable('mls_file_no', array_merge($clone, $stamp)),
                'rebuild from successor ' . $successor . ' (row ' . $row->id . ' — renumbered in place)',
            ];
        }

        $row = $this->forTable('mls_file_no', array_merge($stamp, array_filter([
            'file_name'          => $archive->file_name,
            'plot_no'            => $pra->plot_no ?? null,
            'location'           => $pra->location ?? null,
            'commissioning_date' => $archive->commissioning_date,
            'created_at'         => now(),
            'updated_at'         => now(),
            'is_deleted'         => 0,
        ], fn ($v) => $v !== null && $v !== '')));

        return [$row, 'rebuild from the file number + archive (' . count($row) . ' columns)'];
    }

    /** Insert, reusing the original id where one was free. */
    private function insert($db, string $table, array $row, ?int $id): void
    {
        if ($id === null) {
            $db->table($table)->insert($row);
            return;
        }

        $row['id'] = $id;

        // PDO::exec, not Connection::statement(). The sqlsrv driver prepares statements
        // through sp_prepexec, which runs SET IDENTITY_INSERT in its own scope — the
        // setting is gone again before the insert lands, and SQL Server then rejects the
        // explicit id. exec() sends it as a plain batch on the same session, where it holds.
        $pdo = $db->getPdo();
        $pdo->exec("SET IDENTITY_INSERT {$table} ON");
        try {
            $db->table($table)->insert($row);
        } finally {
            $pdo->exec("SET IDENTITY_INSERT {$table} OFF");
        }
    }

    /** Keep only the keys the table actually has, with dates in the shape it accepts. */
    private function forTable(string $table, array $row): array
    {
        $row = array_intersect_key($row, array_flip(Schema::connection('sqlsrv')->getColumnListing($table)));

        return $this->normaliseDates($table, $row);
    }

    /**
     * Re-shape every date value to the precision its column actually holds.
     *
     * The archives are datetime2 (7 decimals) while these registers are plain datetime
     * (3), and SQL Server refuses "2026-09-23 18:02:07.7620000" outright rather than
     * rounding it — "Conversion failed when converting date and/or time from character
     * string". Parsing and re-formatting per column type is what makes an archived row
     * insertable again.
     */
    private function normaliseDates(string $table, array $row): array
    {
        static $types = [];

        if (! isset($types[$table])) {
            $types[$table] = [];
            foreach (DB::connection('sqlsrv')->select(
                'SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?',
                [$table]
            ) as $c) {
                if (str_contains(strtolower($c->DATA_TYPE), 'date')) {
                    $types[$table][$c->COLUMN_NAME] = strtolower($c->DATA_TYPE);
                }
            }
        }

        foreach ($row as $column => $value) {
            if (! isset($types[$table][$column]) || $value === null || $value === '') {
                continue;
            }

            try {
                $parsed = \Carbon\Carbon::parse($value);
                $row[$column] = $types[$table][$column] === 'date'
                    ? $parsed->format('Y-m-d')
                    : $parsed->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                // Unparseable: drop it rather than fail the whole restore over a date.
                $row[$column] = null;
            }
        }

        return $row;
    }

    private function drop(array $row, array $keys): array
    {
        foreach ($keys as $k) {
            unset($row[$k]);
        }

        return $row;
    }
}
