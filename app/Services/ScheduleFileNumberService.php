<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Schedule-driven file numbers.
 *
 * A schedule is an office that issues file numbers; it owns one or more formats
 * (schedule_file_formats.pattern, e.g. "ABC/{serial}/XY"). Generated numbers
 * live in `grouping`, keyed by (file_format, serial_no), with awaiting_fileno +
 * tracking_id populated so the existing grouping lookups (MLS generation,
 * linkAwaitingToMls) keep working.
 *
 * On KLAES both schedule tables start EMPTY - ALAES seeds Abia's Aba, Umuahia
 * and Ohafia schedules, Kano's are created in System Admin -> Configurable
 * Entries -> FileNo Prefix & SerialNo. Nothing here runs until one exists.
 *
 * generate() writes NEW rows into dbo.grouping, which holds 7.4 million live
 * Kano file numbers. It is only ever reached from the Generate button, by an
 * administrator, for a format they created.
 */
class ScheduleFileNumberService
{
    public const SERIAL_TOKEN = '{serial}';

    private const INSERT_CHUNK = 100; // 14 columns × 100 rows stays under SQL Server's 2100-parameter limit

    public function db(): ConnectionInterface
    {
        return DB::connection('sqlsrv');
    }

    /** Active schedules with their active formats, ordered for display. */
    public function schedules(): Collection
    {
        $formats = $this->db()->table('schedule_file_formats')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('schedule_id');

        return $this->db()->table('file_schedules')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($schedule) use ($formats) {
                $schedule->formats = ($formats[$schedule->id] ?? collect())->values();
                return $schedule;
            });
    }

    public function format(int $formatId): ?object
    {
        return $this->db()->table('schedule_file_formats as f')
            ->join('file_schedules as s', 's.id', '=', 'f.schedule_id')
            ->where('f.id', $formatId)
            ->select('f.*', 's.name as schedule_name', 's.code as schedule_code')
            ->first();
    }

    public function buildFileNumber(string $pattern, int $serial): string
    {
        return str_replace(self::SERIAL_TOKEN, (string) $serial, $pattern);
    }

    /**
     * Insert serials [$start, $start + $count) for one format into grouping.
     * Serials that already exist for the format are skipped, so re-runs are safe.
     *
     * @return array{inserted:int, skipped:int}
     */
    public function generate(int $formatId, int $start, int $count, string $createdBy): array
    {
        if ($start < 1 || $count < 1) {
            throw new InvalidArgumentException('Start and count must be positive.');
        }

        $format = $this->format($formatId);
        if (!$format) {
            throw new InvalidArgumentException("Format {$formatId} not found.");
        }

        $end = $start + $count - 1;
        $db = $this->db();

        $existing = $db->table('grouping')
            ->where('file_format', $format->pattern)
            ->whereBetween('serial_no', [$start, $end])
            ->pluck('serial_no')
            ->map(fn ($s) => (int) $s)
            ->flip();

        $now = now();
        $year = (int) $now->format('Y');
        $rows = [];

        for ($serial = $start; $serial <= $end; $serial++) {
            if (isset($existing[$serial])) {
                continue;
            }
            $rows[] = [
                'awaiting_fileno' => $this->buildFileNumber($format->pattern, $serial),
                'number' => (string) $serial,
                'registry' => 'Lands Registry',
                'mapping' => '0',
                'year' => $year,
                'tracking_id' => $this->trackingId(),
                'schedule' => $format->schedule_name,
                'file_prefix' => $format->file_prefix,
                'file_format' => $format->pattern,
                'serial_no' => $serial,
                'created_by' => $createdBy,
            ];
        }

        $db->transaction(function () use ($db, $rows, $format, $end, $now) {
            foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
                $db->table('grouping')->insert(array_map(
                    fn ($row) => $row + ['date' => $now, 'created_at' => $now, 'updated_at' => $now],
                    $chunk
                ));
            }

            $db->table('schedule_file_formats')
                ->where('id', $format->id)
                ->where('last_serial', '<', $end)
                ->update(['last_serial' => $end, 'updated_at' => $now]);
        });

        return ['inserted' => count($rows), 'skipped' => $count - count($rows)];
    }

    /**
     * Paged search over generated numbers for one format. $search matches the
     * serial ("12") or the full file number ("ABC/12").
     */
    public function search(int $formatId, string $search, int $page, int $perPage): array
    {
        $format = $this->format($formatId);
        if (!$format) {
            return ['files' => [], 'more' => false];
        }

        $query = $this->db()->table('grouping')
            ->where('file_format', $format->pattern)
            ->whereNull('deleted_at');

        $search = trim($search);
        if ($search !== '') {
            if (ctype_digit($search)) {
                $query->where('serial_no', (int) $search);
            } else {
                $query->where('awaiting_fileno', 'like', '%' . str_replace(['%', '_', '['], ['[%]', '[_]', '[[]'], $search) . '%');
            }
        }

        $rows = $query->orderBy('serial_no')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get(['id', 'awaiting_fileno', 'mls_fileno', 'tracking_id', 'schedule', 'file_prefix', 'file_format', 'serial_no']);

        $more = $rows->count() > $perPage;

        return [
            'files' => $rows->take($perPage)->map(fn ($r) => $this->present($r, $format))->values()->all(),
            'more' => $more,
        ];
    }

    /**
     * Lowest serial for a format whose file number has not been indexed yet
     * (auto-assign on the Indexing Interface). Null when every serial is used.
     */
    public function nextAvailable(int $formatId): ?array
    {
        $format = $this->format($formatId);
        if (!$format) {
            return null;
        }

        $row = $this->db()->table('grouping as g')
            ->where('g.file_format', $format->pattern)
            ->whereNull('g.deleted_at')
            ->whereNotExists(fn ($q) => $this->inMainIndexSubquery($q))
            ->whereNotExists(fn ($q) => $this->inFileIndexingsSubquery($q))
            ->orderBy('g.serial_no')
            ->first(['g.id', 'g.awaiting_fileno', 'g.mls_fileno', 'g.tracking_id', 'g.schedule', 'g.file_prefix', 'g.file_format', 'g.serial_no']);

        return $row ? $this->present($row, $format) + ['indexed' => false, 'indexed_record_id' => null] : null;
    }

    /**
     * File Commissioning: the next serial for a new file. Commissioning starts at 1
     * (commissioning_start_after + 1, set in Configurable Entries → FileNo Prefix &
     * SerialNo) and continues after the highest serial already commissioned under the
     * format, skipping any number that is already indexed.
     *
     * There is no counter to move: the position is read from the commissioned files
     * themselves, so a save that fails leaves no gap. The grouping row may not exist
     * yet for a serial past last_serial; it is generated on save (ensureCommissioningSerial).
     */
    public function nextCommissioningSerial(int $formatId): ?array
    {
        $format = $this->format($formatId);
        if (!$format) {
            return null;
        }

        $serial = max((int) ($format->commissioning_start_after ?? 0), $this->lastCommissionedSerial($format)) + 1;
        for ($tries = 0; $tries < 1000; $tries++, $serial++) {
            $fileNumber = $this->buildFileNumber($format->pattern, $serial);
            if ($this->indexedRecordId($fileNumber) !== null) {
                continue;
            }

            $row = $this->db()->table('grouping')
                ->where('file_format', $format->pattern)
                ->where('serial_no', $serial)
                ->whereNull('deleted_at')
                ->first(['id', 'awaiting_fileno', 'mls_fileno', 'tracking_id', 'schedule', 'file_prefix', 'file_format', 'serial_no']);

            $record = $row
                ? $this->present($row, $format)
                : [
                    'id' => null,
                    'file_number' => $fileNumber,
                    'fileno' => $fileNumber,
                    'serial_no' => $serial,
                    'schedule' => $format->schedule_name,
                    'file_prefix' => $format->file_prefix,
                    'file_suffix' => $format->suffix,
                    'file_format' => $format->pattern,
                    'format_id' => (int) $format->id,
                    'schedule_id' => (int) $format->schedule_id,
                    'tracking_id' => null,
                    'mls_fileno' => null,
                    'is_linked' => false,
                ];

            return $record + ['indexed' => false, 'indexed_record_id' => null, 'last_commissioned' => $this->lastCommissionedSerial($format), 'is_new' => !$row];
        }

        return null;
    }

    /** The highest serial a file has been commissioned with under this format (0 when none). */
    public function lastCommissionedSerial(object $format): int
    {
        $schedule = $format->schedule_name ?? $this->db()->table('file_schedules')->where('id', $format->schedule_id)->value('name');

        return (int) $this->db()->table('File_Indexing')
            ->whereNull('deleted_at')
            ->where('indexing_mode', 'commissioning')
            ->where('schedule', $schedule)
            ->where('file_prefix', $format->file_prefix)
            ->where(fn ($q) => $format->suffix ? $q->where('file_suffix', $format->suffix) : $q->whereNull('file_suffix')->orWhere('file_suffix', ''))
            ->max('schedule_serial_no');
    }

    /**
     * File Commissioning save: make sure the commissioned serial exists in grouping.
     * A serial past the format's last serial is generated (moving last_serial up);
     * a missing serial inside the pre-generated range is refused.
     */
    public function ensureCommissioningSerial(int $formatId, int $serial, string $createdBy): bool
    {
        if ($this->resolveSerial($formatId, $serial)['exists']) {
            return true;
        }

        $format = $this->format($formatId);
        if (!$format || $serial <= (int) $format->last_serial) {
            return false;
        }

        $this->generate($formatId, $serial, 1, $createdBy);

        return $this->resolveSerial($formatId, $serial)['exists'];
    }

    /**
     * Look up a typed serial for a format: whether it was generated, and whether
     * its file number is already indexed.
     *
     * @return array{exists:bool, indexed:bool, record:?array, indexed_record_id:?int}
     */
    public function resolveSerial(int $formatId, int $serial): array
    {
        $format = $this->format($formatId);
        $none = ['exists' => false, 'indexed' => false, 'record' => null, 'indexed_record_id' => null];
        if (!$format || $serial < 1) {
            return $none;
        }

        $row = $this->db()->table('grouping')
            ->where('file_format', $format->pattern)
            ->where('serial_no', $serial)
            ->whereNull('deleted_at')
            ->first(['id', 'awaiting_fileno', 'mls_fileno', 'tracking_id', 'schedule', 'file_prefix', 'file_format', 'serial_no']);

        if (!$row) {
            return $none;
        }

        $indexedId = $this->indexedRecordId($row->awaiting_fileno);

        return [
            'exists' => true,
            'indexed' => $indexedId !== null,
            'record' => $this->present($row, $format),
            'indexed_record_id' => $indexedId !== null ? (int) $indexedId : null,
        ];
    }

    /**
     * A serial counts as taken when its file number is already in the file index.
     *
     * On KLAES, File_Indexing is a VIEW over dbo.file_indexings (the
     * 2026_09_18_100000 compatibility layer), so both halves of this check read
     * the same 166k Kano rows. On ALAES they are two different tables.
     */
    private function inMainIndexSubquery($query): void
    {
        $query->select(DB::raw(1))
            ->from('File_Indexing as mfi')
            ->whereColumn('mfi.file_number', 'g.awaiting_fileno')
            ->whereNull('mfi.deleted_at');
    }

    /** … or in the older file_indexings table. */
    private function inFileIndexingsSubquery($query): void
    {
        $query->select(DB::raw(1))
            ->from('file_indexings as fi')
            ->whereColumn('fi.file_number', 'g.awaiting_fileno')
            ->where(fn ($q) => $q->whereNull('fi.is_deleted')->orWhere('fi.is_deleted', 0));
    }

    /** id of the File_Indexing / file_indexings row holding this file number, if any. */
    public function indexedRecordId(string $fileNumber): ?int
    {
        $mainId = $this->db()->table('File_Indexing')
            ->where('file_number', $fileNumber)
            ->whereNull('deleted_at')
            ->value('id');
        if ($mainId !== null) {
            return (int) $mainId;
        }

        $legacyId = $this->db()->table('file_indexings')
            ->where('file_number', $fileNumber)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->value('id');

        return $legacyId !== null ? (int) $legacyId : null;
    }

    /** Resolve a full file number (e.g. "ABC/12/XY") to its grouping row. */
    public function lookup(string $fileNumber): ?array
    {
        $row = $this->db()->table('grouping')
            ->where('awaiting_fileno', trim($fileNumber))
            ->whereNotNull('file_format')
            ->whereNull('deleted_at')
            ->first(['id', 'awaiting_fileno', 'mls_fileno', 'tracking_id', 'schedule', 'file_prefix', 'file_format', 'serial_no']);

        if (!$row) {
            return null;
        }

        $format = $this->db()->table('schedule_file_formats')->where('pattern', $row->file_format)->first();

        return $this->present($row, $format);
    }

    private function present(object $row, ?object $format): array
    {
        return [
            'id' => (int) $row->id,
            'file_number' => $row->awaiting_fileno,
            'fileno' => $row->awaiting_fileno,
            'serial_no' => (int) $row->serial_no,
            'schedule' => $row->schedule,
            'file_prefix' => $row->file_prefix,
            'file_suffix' => $format->suffix ?? null,
            'file_format' => $row->file_format,
            'format_id' => isset($format->id) ? (int) $format->id : null,
            'schedule_id' => isset($format->schedule_id) ? (int) $format->schedule_id : null,
            'tracking_id' => $row->tracking_id,
            'mls_fileno' => $row->mls_fileno,
            'is_linked' => !empty($row->mls_fileno),
        ];
    }

    private function trackingId(): string
    {
        return 'TRK-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    }
}

