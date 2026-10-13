<?php

namespace App\Services\FileNumber;

use App\Models\AuditLog;
use App\Models\MlsFileNo;
use App\Models\MlsSerialControl;
use App\Services\MlsSerialAllocationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * "Move Commissioned File" on the MLPP File Number Generator.
 *
 * A commissioned file turned out to share its number with another PHYSICAL folder
 * (not a duplicate row in the system). The commissioned record is moved to the next
 * available serial of its OWN prefix — CON-RES stays CON-RES, -RC stays -RC — and
 * only the file number changes. Every place the number is stored is rewritten:
 * registers, indexing, customer, entity, OSS, LAAS, tracking, commissioning sheet,
 * PRA / CofO / PropID_Master, history and logs, and related-file lists of other
 * files.
 *
 * TRACKING ID. In the grouping register every awaiting file number owns its own
 * tracking ID. So the file takes the tracking ID of the NEW number's grouping row
 * (and that row is mapped, as commissioning maps it), and the old tracking ID is
 * replaced by it everywhere it is stored. The OLD number's grouping row is left as
 * it is (number, tracking ID and mapping) because that number and its label are
 * still on the other physical folder. grouping.tracking_id is never rewritten.
 *
 * The old number is archived to deprecated_records ("Physical Duplicate → NEW"),
 * and MlsSerialAllocationService treats an archived number as occupied, so it is
 * never offered again as a reclaimable serial while a folder still carries it.
 *
 * The footprint is found with read-only NOLOCK probes BEFORE the transaction, so
 * the write transaction is short (klas has no snapshot isolation; a long write
 * transaction blocks live reads).
 */
class CommissionedFileMoveService
{
    public const AUDIT_ACTION = 'COMMISSIONED FILE MOVED';

    /** Plain MLPP numbers only: PREFIX-YYYY-SERIAL. No (T), no AND EXTENSION. */
    private const NUMBER_PATTERN = '/^(.+)-(\d{4})-(\d+)$/';

    /**
     * Columns that hold a file number as one value among several (JSON array or
     * comma list). Matched by name; rewritten token by token.
     */
    private const LIST_COLUMNS = ['related_fileno', 'successor_file_no', 'old_file_numbers'];

    /**
     * Never rewritten:
     *  - deprecated_records: the archive this move writes to; the old number belongs there.
     *  - grouping: the register. Every column of the old number's row stays as it is
     *    (it also holds suffixed copies such as cadastral_tracking_id = TRK-...-0001);
     *    the new number's row is mapped by mapNewGroupingRow().
     */
    private const EXCLUDED_TABLES = ['deprecated_records', 'grouping'];
    private const EXCLUDED_COLUMNS = [];

    public function __construct(private MlsSerialAllocationService $allocator)
    {
    }

    /**
     * What a move would do, without changing anything.
     *
     * @return array{old_file_number:string,new_file_number:string,prefix:string,year:int,file_title:?string,tracking_id:?string,footprint:array<int,array>}
     */
    public function preview(string $oldFileNo): array
    {
        $oldFileNo = trim($oldFileNo);
        $record = $this->liveRecord($oldFileNo);
        [$prefix] = $this->parse($oldFileNo);
        $year = (int) date('Y');

        return [
            'old_file_number' => $oldFileNo,
            'new_file_number' => $newFileNo = $this->peekNextFree($prefix, $year),
            'prefix' => $prefix,
            'year' => $year,
            'file_title' => $record->file_name ?? null,
            'tracking_id' => $record->tracking_id ?? null,
            // The tracking ID the file will take: the new number's own grouping row.
            'new_tracking_id' => $this->groupingRow($newFileNo)->tracking_id ?? null,
            'has_grouping_row' => $this->groupingRow($newFileNo) !== null,
            // Why the move would be refused at the grouping step, shown before confirming.
            'grouping_problem' => $this->groupingProblem($newFileNo),
            'footprint' => $this->footprint($oldFileNo),
        ];
    }

    /**
     * Move the record to the next free serial of its prefix.
     *
     * $expectedNewFileNo is the number the officer was shown. If another commissioning
     * took it meanwhile, nothing is changed and the caller is told the new next number.
     *
     * @return array{old_file_number:string,new_file_number:string,updated:array<int,array>,rows:int}
     */
    public function move(string $oldFileNo, string $expectedNewFileNo, ?int $userId, string $movedBy): array
    {
        $oldFileNo = trim($oldFileNo);
        $expectedNewFileNo = trim($expectedNewFileNo);
        $record = $this->liveRecord($oldFileNo);
        [$prefix] = $this->parse($oldFileNo);
        $year = (int) date('Y');

        $oldTracking = trim((string) ($record->tracking_id ?? ''));

        // Read-only discovery, outside the transaction.
        $footprint = $this->footprint($oldFileNo);
        $trackingFootprint = $oldTracking !== '' ? $this->trackingFootprint($oldTracking) : [];

        $db = DB::connection('sqlsrv');
        $db->beginTransaction();

        try {
            $newFileNo = $this->allocateNextFree($prefix, $year);
            if (strcasecmp($newFileNo, $expectedNewFileNo) !== 0) {
                throw new StaleNextNumberException($newFileNo);
            }

            // The new number's own grouping row supplies the tracking ID.
            $newTracking = $this->mapNewGroupingRow($newFileNo);

            // The file's tracker rows as they were, for the tracking log below.
            $trackersBefore = $db->table('file_tracker')
                ->where('file_number', $oldFileNo)
                ->get(['id', 'file_number', 'tracking_id', 'movement_log', 'notes', 'current_office_code']);

            // Snapshot the indexing row before its number changes.
            $indexing = $db->table('file_indexings')
                ->where('file_number', $oldFileNo)
                ->orderByDesc('id')
                ->first();

            $updated = [];
            $total = 0;
            foreach ($footprint as $hit) {
                $n = $hit['kind'] === 'list'
                    ? $this->rewriteListColumn($hit['table'], $hit['column'], $oldFileNo, $newFileNo)
                    : $db->update(
                        "update [{$hit['table']}] set [{$hit['column']}] = ? where [{$hit['column']}] = ?",
                        [$newFileNo, $oldFileNo]
                    );
                if ($n > 0) {
                    $updated[] = ['table' => $hit['table'], 'column' => $hit['column'], 'rows' => $n];
                    $total += $n;
                }
            }

            // The tracking ID follows the new number. The file tracker stores it with a
            // sequence suffix (TRK-XXXXXXXX-XXXXX-0003), so the suffix is kept and only
            // the tracking ID in front of it is replaced.
            foreach ($trackingFootprint as $hit) {
                $n = $db->update(
                    "update [{$hit['table']}] set [{$hit['column']}] = ? + substring([{$hit['column']}], ?, 4000)
                     where [{$hit['column']}] = ? or [{$hit['column']}] like ?",
                    [$newTracking, mb_strlen($oldTracking) + 1, $oldTracking, $this->escapeLike($oldTracking) . '-%']
                );
                if ($n > 0) {
                    $updated[] = ['table' => $hit['table'], 'column' => $hit['column'], 'rows' => $n];
                    $total += $n;
                }
            }
            // A record that never had a tracking ID still gets the new one on its registers.
            if ($oldTracking === '') {
                foreach (['mls_file_no' => 'full_file_number', 'fileNumber' => 'mlsfNo', 'file_indexings' => 'file_number'] as $table => $column) {
                    $db->table($table)->where($column, $newFileNo)->update(['tracking_id' => $newTracking]);
                }
            }

            // The old number becomes the file's related file number, which is what the
            // commissioning sheet prints under "Related FileNo/Old FileNo".
            $this->addRelatedFileNumber($newFileNo, $oldFileNo);

            // Record the change on the file's tracking.
            $this->logOnTrackers($trackersBefore, $oldFileNo, $newFileNo, $oldTracking, $newTracking, $userId, $movedBy);

            // The register row must have moved; anything less is not a move.
            if (!$db->table('mls_file_no')->where('full_file_number', $newFileNo)->where('is_deleted', 0)->exists()) {
                throw new RuntimeException("The register row for {$oldFileNo} did not move. Nothing was changed.");
            }

            $this->archiveOldNumber($oldFileNo, $newFileNo, $indexing, $record, $movedBy);

            AuditLog::create([
                'user_id' => $userId,
                'action' => self::AUDIT_ACTION,
                'resource_type' => 'mls_file_no',
                'resource_id' => $record->id,
                'old_values' => ['file_number' => $oldFileNo, 'tracking_id' => $oldTracking ?: null],
                'new_values' => [
                    'file_number' => $newFileNo,
                    'tracking_id' => $newTracking,
                    'reason' => 'Physical duplicate',
                    'updated' => $updated,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        Cache::forever('file_numbers_datatable_version', (string) microtime(true));

        Log::channel('fileno_duplicates')->info('Move Commissioned File', [
            'old_file_number' => $oldFileNo,
            'new_file_number' => $newFileNo,
            'old_tracking_id' => $oldTracking ?: null,
            'new_tracking_id' => $newTracking,
            'rows' => $total,
            'user_id' => $userId,
        ]);

        return [
            'old_file_number' => $oldFileNo,
            'new_file_number' => $newFileNo,
            'old_tracking_id' => $oldTracking ?: null,
            'new_tracking_id' => $newTracking,
            'updated' => $updated,
            'rows' => $total,
        ];
    }

    // ---- eligibility ----------------------------------------------------------

    private function liveRecord(string $fileNo): object
    {
        if ($fileNo === '') {
            throw new InvalidArgumentException('No file number was given.');
        }

        $this->parse($fileNo);

        $record = DB::connection('sqlsrv')->table('mls_file_no')
            ->where('full_file_number', $fileNo)
            ->where(fn ($q) => $q->where('is_deleted', 0)->orWhereNull('is_deleted'))
            ->orderByDesc('id')
            ->first(['id', 'full_file_number', 'file_name', 'tracking_id', 'file_option']);

        if (!$record) {
            throw new InvalidArgumentException("{$fileNo} is not a live commissioned MLPP file.");
        }

        return $record;
    }

    /** @return array{0:string,1:int,2:int} prefix, year, serial */
    private function parse(string $fileNo): array
    {
        if (!preg_match(self::NUMBER_PATTERN, $fileNo, $m)) {
            throw new InvalidArgumentException("{$fileNo} is not a plain PREFIX-YEAR-SERIAL file number, so it cannot be moved here.");
        }

        return [$m[1], (int) $m[2], (int) $m[3]];
    }

    // ---- next number ----------------------------------------------------------

    /** Read-only: the number allocateNextFree() would hand out now. */
    private function peekNextFree(string $prefix, int $year): string
    {
        $serial = (int) MlsSerialControl::getCurrentSerial($prefix, $year);
        for ($i = 0; $i < 200; $i++) {
            $serial++;
            $candidate = MlsFileNo::generateFileNumber($prefix, $year, $serial);
            if (!$this->isTaken($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException("Could not find a free {$prefix}-{$year} number: too many consecutive serials are in use.");
    }

    /** Consumes the counter (row-locked by MlsSerialControl). Inside the transaction. */
    private function allocateNextFree(string $prefix, int $year): string
    {
        for ($i = 0; $i < 200; $i++) {
            $next = $this->allocator->allocateNextFreeSerial($prefix, $year);
            if (!$this->isTaken($next['file_number'])) {
                return $next['file_number'];
            }
        }

        throw new RuntimeException("Could not find a free {$prefix}-{$year} number: too many consecutive serials are in use.");
    }

    private function isTaken(string $fileNo): bool
    {
        $db = DB::connection('sqlsrv');

        return $db->table('mls_file_no')->where('full_file_number', $fileNo)->exists()
            || $db->table('fileNumber')->where('mlsfNo', $fileNo)->exists()
            || count($this->allocator->takenInFileIndexings([$fileNo])) > 0
            || $db->table('pra')->where('mlsFNo', $fileNo)->exists()
            || $db->table('PropID_Master')->where('mlsFNo', $fileNo)->exists()
            || $db->table('deprecated_records')->where('file_number', $fileNo)->exists();
    }

    // ---- related file number / tracking log -----------------------------------

    /**
     * Add $oldFileNo to the related_fileno list on fileNumber and file_indexings for
     * $newFileNo, keeping whatever related numbers the file already had. Stored as a
     * JSON array, the form the other workflows write.
     */
    public function addRelatedFileNumber(string $newFileNo, string $oldFileNo): void
    {
        $db = DB::connection('sqlsrv');

        foreach (['fileNumber' => 'mlsfNo', 'file_indexings' => 'file_number'] as $table => $column) {
            foreach ($db->table($table)->where($column, $newFileNo)->get(['id', 'related_fileno']) as $row) {
                $list = $this->parseList($row->related_fileno);
                if (in_array(strtoupper($oldFileNo), array_map('strtoupper', $list), true)) {
                    continue;
                }
                $list[] = $oldFileNo;
                $db->table($table)->where('id', $row->id)->update(['related_fileno' => json_encode(array_values($list))]);
            }
        }
    }

    /** A related_fileno value as a list: JSON array, or a comma-separated string. */
    private function parseList($value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        $items = is_array($decoded) ? $decoded : explode(',', $value);

        return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $items), fn ($v) => $v !== ''));
    }

    /**
     * Note the move on each of the file's trackers: a line on the tracker's notes, and
     * a row in file_movement_log_edits (the tracker change log) with the file number
     * and tracking ID before and after. The movement log itself is not touched — its
     * entries are office movements, and this is not one.
     */
    public function logOnTrackers($trackersBefore, string $oldFileNo, string $newFileNo, string $oldTracking, string $newTracking, ?int $userId, string $movedBy): void
    {
        $db = DB::connection('sqlsrv');
        $stamp = now();

        foreach ($trackersBefore as $t) {
            $after = $db->table('file_tracker')->where('id', $t->id)->first(['file_number', 'tracking_id', 'notes']);
            if (!$after) {
                continue;
            }

            $line = "[{$stamp->format('Y-m-d H:i')}] File number moved from {$oldFileNo} to {$newFileNo} (physical duplicate); "
                . 'tracking ID ' . ($oldTracking ?: '-') . " → {$newTracking} by {$movedBy}.";
            $notes = trim((string) $after->notes);
            $db->table('file_tracker')->where('id', $t->id)->update([
                'notes' => $notes === '' ? $line : $notes . "\n" . $line,
                'updated_at' => $stamp,
            ]);

            $log = json_decode((string) $t->movement_log, true);
            $log = is_array($log) ? array_values($log) : [];
            $index = max(count($log) - 1, 0);
            $entry = $log[$index] ?? [];

            $db->table('file_movement_log_edits')->insert([
                'file_tracker_id' => $t->id,
                'tracking_id' => $after->tracking_id,
                'file_number' => $newFileNo,
                'entry_index' => $index,
                'log_id' => $entry['log_id'] ?? null,
                'office_code' => $entry['office_code'] ?? ($t->current_office_code ?? null),
                'changed_fields' => json_encode(['file_number', 'tracking_id']),
                'before_entry' => json_encode($entry),
                'after_entry' => json_encode($entry),
                'tracker_before' => json_encode(['file_number' => $oldFileNo, 'tracking_id' => $t->tracking_id]),
                'tracker_after' => json_encode(['file_number' => $newFileNo, 'tracking_id' => $after->tracking_id]),
                'reason' => "Move Commissioned File: {$oldFileNo} → {$newFileNo} (physical duplicate)",
                'edited_by' => $userId ?? 0,
                'edited_by_name' => mb_substr($movedBy, 0, 150),
                'ip_address' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
                'created_at' => $stamp,
            ]);
        }
    }

    // ---- grouping / tracking ID -----------------------------------------------

    private function groupingRow(string $fileNo, bool $lock = false): ?object
    {
        $q = DB::connection('sqlsrv')->table('grouping')->where('awaiting_fileno', $fileNo)->orderBy('id');
        if ($lock) {
            $q->lockForUpdate();
        }

        return $q->first(['id', 'awaiting_fileno', 'mls_fileno', 'mapping', 'tracking_id']);
    }

    /**
     * Map the new number's grouping row the way commissioning does (mls_fileno +
     * mapping = 1) and return its tracking ID. A row without one gets one in the
     * register's own format, as GroupingFileNumberService does.
     */
    /** Null when the new number's grouping row can be taken; otherwise why not. */
    private function groupingProblem(string $newFileNo, ?object $row = null, bool $looked = false): ?string
    {
        $row = $looked ? $row : $this->groupingRow($newFileNo);
        if (!$row) {
            return "{$newFileNo} has no row in the grouping register, so it has no tracking ID to take.";
        }

        $mappedTo = trim((string) ($row->mls_fileno ?? ''));
        if ((int) ($row->mapping ?? 0) === 1 && $mappedTo !== '' && strcasecmp($mappedTo, $newFileNo) !== 0) {
            return "The grouping row for {$newFileNo} is already marked as mapped (to \"{$mappedTo}\"). Have the register checked first.";
        }

        return null;
    }

    private function mapNewGroupingRow(string $newFileNo): string
    {
        $row = $this->groupingRow($newFileNo, true);
        if ($problem = $this->groupingProblem($newFileNo, $row, true)) {
            throw new InvalidArgumentException($problem . ' Nothing was changed.');
        }

        $tracking = trim((string) ($row->tracking_id ?? ''));
        if ($tracking === '') {
            $tracking = 'TRK-' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 8))
                . '-' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 5));
        }

        DB::connection('sqlsrv')->table('grouping')->where('id', $row->id)->update([
            'mls_fileno' => $newFileNo,
            'mapping' => 1,
            'tracking_id' => $tracking,
            'updated_at' => now(),
        ]);

        return $tracking;
    }

    /**
     * Every column holding this tracking ID, except the grouping register's own.
     * Read-only NOLOCK probes.
     *
     * @return array<int,array{table:string,column:string,rows:int}>
     */
    public function trackingFootprint(string $trackingId): array
    {
        $db = DB::connection('sqlsrv');
        $cols = $db->select("
            select c.TABLE_NAME t, c.COLUMN_NAME c
            from INFORMATION_SCHEMA.COLUMNS c
            join INFORMATION_SCHEMA.TABLES tb on tb.TABLE_NAME = c.TABLE_NAME and tb.TABLE_TYPE = 'BASE TABLE'
            where c.DATA_TYPE in ('varchar','nvarchar','char','nchar')
              and COLUMNPROPERTY(OBJECT_ID(QUOTENAME(c.TABLE_SCHEMA) + '.' + QUOTENAME(c.TABLE_NAME)), c.COLUMN_NAME, 'IsComputed') = 0
              and c.COLUMN_NAME like '%tracking%id%'
              and c.TABLE_NAME not like '%bak%' and c.TABLE_NAME not like '%backup%' and c.TABLE_NAME not like '[_]%'
        ");

        $hits = [];
        foreach ($cols as $col) {
            if (in_array($col->t, self::EXCLUDED_TABLES, true) || in_array($col->t . '.' . $col->c, self::EXCLUDED_COLUMNS, true)) {
                continue;
            }
            try {
                // Exact, or with the tracker's sequence suffix (TRK-...-0003).
                $n = (int) $db->selectOne(
                    "select count(*) n from [{$col->t}] with (nolock) where [{$col->c}] = ? or [{$col->c}] like ?",
                    [$trackingId, $this->escapeLike($trackingId) . '-%']
                )->n;
            } catch (\Throwable $e) {
                Log::warning('Move Commissioned File: tracking probe failed', ['column' => "{$col->t}.{$col->c}", 'error' => $e->getMessage()]);
                continue;
            }
            if ($n > 0) {
                $hits[] = ['table' => $col->t, 'column' => $col->c, 'rows' => $n];
            }
        }

        return $hits;
    }

    // ---- footprint ------------------------------------------------------------

    /**
     * Every column holding this number. Read-only NOLOCK probes.
     *
     * @return array<int,array{table:string,column:string,kind:string,rows:int}>
     */
    public function footprint(string $fileNo): array
    {
        $db = DB::connection('sqlsrv');
        $hits = [];

        foreach ($this->candidateColumns() as $col) {
            $isList = in_array(strtolower($col->c), self::LIST_COLUMNS, true);
            try {
                $n = $isList
                    ? $this->countListMatches($col->t, $col->c, $fileNo)
                    : (int) $db->selectOne("select count(*) n from [{$col->t}] with (nolock) where [{$col->c}] = ?", [$fileNo])->n;
            } catch (\Throwable $e) {
                Log::warning('Move Commissioned File: probe failed', ['column' => "{$col->t}.{$col->c}", 'error' => $e->getMessage()]);
                continue;
            }

            if ($n > 0) {
                $hits[] = ['table' => $col->t, 'column' => $col->c, 'kind' => $isList ? 'list' : 'exact', 'rows' => $n];
            }
        }

        return $hits;
    }

    /** Text columns whose name says they hold a file number. Cached for the request. */
    private function candidateColumns(): array
    {
        static $cols = null;
        if ($cols !== null) {
            return $cols;
        }

        $names = "'" . implode("','", self::LIST_COLUMNS) . "'";
        $rows = DB::connection('sqlsrv')->select("
            select c.TABLE_NAME t, c.COLUMN_NAME c
            from INFORMATION_SCHEMA.COLUMNS c
            join INFORMATION_SCHEMA.TABLES tb on tb.TABLE_NAME = c.TABLE_NAME and tb.TABLE_TYPE = 'BASE TABLE'
            where c.DATA_TYPE in ('varchar','nvarchar','char','nchar')
              and COLUMNPROPERTY(OBJECT_ID(QUOTENAME(c.TABLE_SCHEMA) + '.' + QUOTENAME(c.TABLE_NAME)), c.COLUMN_NAME, 'IsComputed') = 0
              and (c.COLUMN_NAME like '%file%no%' or c.COLUMN_NAME like '%fileno%' or c.COLUMN_NAME like '%file_number%'
                   or c.COLUMN_NAME like '%filenumber%' or c.COLUMN_NAME like 'mlsf%' or c.COLUMN_NAME in ({$names}))
              and c.TABLE_NAME not like '%bak%' and c.TABLE_NAME not like '%backup%' and c.TABLE_NAME not like '[_]%'
        ");

        return $cols = array_values(array_filter($rows, function ($r) {
            return !in_array($r->t, self::EXCLUDED_TABLES, true)
                && !in_array($r->t . '.' . $r->c, self::EXCLUDED_COLUMNS, true);
        }));
    }

    /**
     * The number as a whole token: not preceded by a letter, digit or hyphen (so
     * RES-2026-39 is not found inside CON-RES-2026-39) and not followed by a digit
     * (so it is not found inside RES-2026-397).
     */
    private function tokenPattern(string $fileNo): string
    {
        return '/(?<![A-Za-z0-9-])' . preg_quote($fileNo, '/') . '(?![0-9])/';
    }

    private function countListMatches(string $table, string $column, string $fileNo): int
    {
        $values = DB::connection('sqlsrv')->select(
            "select [{$column}] v from [{$table}] with (nolock) where [{$column}] like ?",
            ['%' . $this->escapeLike($fileNo) . '%']
        );

        $pattern = $this->tokenPattern($fileNo);
        return count(array_filter($values, fn ($r) => preg_match($pattern, (string) $r->v)));
    }

    private function rewriteListColumn(string $table, string $column, string $old, string $new): int
    {
        $db = DB::connection('sqlsrv');
        $pattern = $this->tokenPattern($old);
        $values = $db->table($table)
            ->where($column, 'like', '%' . $this->escapeLike($old) . '%')
            ->distinct()
            ->pluck($column);

        $rows = 0;
        foreach ($values as $value) {
            $value = (string) $value;
            if (!preg_match($pattern, $value)) {
                continue;
            }
            $rows += $db->update(
                "update [{$table}] set [{$column}] = ? where [{$column}] = ?",
                [preg_replace($pattern, $new, $value), $value]
            );
        }

        return $rows;
    }

    /** SQL Server LIKE: [ ] % _ are wildcards. */
    private function escapeLike(string $s): string
    {
        return str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $s);
    }

    // ---- archive --------------------------------------------------------------

    private function archiveOldNumber(string $old, string $new, ?object $indexing, object $record, string $movedBy): void
    {
        $cut = fn ($v, int $len) => ($v === null || $v === '') ? null : mb_substr((string) $v, 0, $len);

        DB::connection('sqlsrv')->table('deprecated_records')->insert([
            'file_indexing_id' => $indexing->id ?? null,
            'file_number' => $old,
            'file_title' => $cut($indexing->file_title ?? $record->file_name ?? null, 255),
            'land_use_type' => $cut($indexing->land_use_type ?? null, 100),
            'plot_number' => $cut($indexing->plot_number ?? null, 255),
            'district' => $cut($indexing->district ?? null, 150),
            'lga' => $cut($indexing->lga ?? null, 150),
            'location' => $cut($indexing->location ?? null, 255),
            'plot_size' => $cut($indexing->plot_size ?? null, 100),
            'tp_no' => $cut($indexing->tp_no ?? null, 100),
            'tracking_id' => $cut($record->tracking_id ?? ($indexing->tracking_id ?? null), 150),
            'related_fileno' => json_encode([$new]),
            'has_transaction' => 0,
            'workflow_type' => "Physical Duplicate → {$new}",
            'decommissioned_by' => $cut($movedBy, 150),
            'decommissioned_at' => now(),
            'created_by' => $cut($movedBy, 150),
            'registry' => $cut($indexing->registry ?? null, 100),
            'general_registry' => $cut($indexing->general_registry ?? null, 100),
            // prop_id deliberately left empty: the property now lives under the new number.
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
