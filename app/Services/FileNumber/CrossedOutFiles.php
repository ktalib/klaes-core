<?php

namespace App\Services\FileNumber;

use Illuminate\Support\Facades\DB;

/**
 * A deleted file stays on its table, crossed out and read-only (user, 2026-11-06).
 *
 * The row gets is_deleted = 1 and its numbers move into deleted_numbers. Most code reads
 * and joins these tables by file number without looking at is_deleted, so leaving the
 * number in place would keep the file live on those screens and stop the number being
 * used again. With the number moved, every such read behaves as it did when the row was
 * removed, and only the tables that ask for crossed-out rows still show it.
 *
 * Callers own the transaction.
 */
class CrossedOutFiles
{
    private const FILE_NUMBER_COLUMNS = [
        'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'temp_fileno', 'temp_file_no',
        'gkn_fileno', 'kangis_fileno_placeholder', 'kangis_fileno_resolved', 'corresponding_fileno', 'shadow_fileno',
    ];

    private const INDEXING_COLUMNS = [
        'file_number', 'mls_file_no', 'kangis_file_no', 'new_kangis_file_no', 'temp_file_no',
        'kangis_fileno_placeholder', 'kangis_fileno_resolved', 'corresponding_fileno',
    ];

    /** Keys in deleted_numbers that are not columns of the row. */
    private const NOTE_KEYS = ['mls_tombstone', 'list'];

    /** Cross out the rows holding these numbers, for a delete that starts from the number. */
    public function crossOutNumbers(array $numbers): array
    {
        $numbers = array_values(array_filter($numbers, fn ($n) => trim((string) $n) !== ''));
        if (! $numbers) {
            return ['fileNumber' => 0, 'file_indexings' => 0, 'mls_file_no' => 0];
        }

        $db = DB::connection('sqlsrv');
        $fileNumberIds = $db->table('fileNumber')->whereIn('mlsfNo', $numbers)->pluck('id')->all();
        $indexingIds = $db->table('file_indexings')->whereIn('file_number', $numbers)->pluck('id')->all();
        $mls = $this->crossOutMlsRows($db->table('mls_file_no')->whereIn('full_file_number', $numbers)->pluck('id')->all());

        return [
            'fileNumber' => $this->crossOutFileNumberRows($fileNumberIds, $mls),
            'file_indexings' => $this->crossOutIndexings($indexingIds),
            'mls_file_no' => $mls['count'],
        ];
    }

    public function crossOutIndexings(array $ids): int
    {
        $count = 0;
        foreach ($this->notYet(DB::connection('sqlsrv')->table('file_indexings'), $ids) as $row) {
            $count += $this->moveNumbers('file_indexings', $row, self::INDEXING_COLUMNS, ['deleted_at' => now()]);
        }

        return $count;
    }

    /** @param array $mls the result of crossOutMlsRows() when those rows went first */
    public function crossOutFileNumberRows(array $ids, array $mls = []): int
    {
        $db = DB::connection('sqlsrv');
        $count = 0;

        foreach ($this->notYet($db->table('fileNumber'), $ids) as $row) {
            $number = trim((string) $row->mlsfNo);
            $register = $mls['rows'][$number] ?? ($number === '' ? null
                : $db->table('mls_file_no')->where('full_file_number', $number)->orderByDesc('id')->first());

            $notes = [];
            if (! empty($mls['rows'][$number])) {
                $notes['mls_tombstone'] = self::mlsTombstone((int) $register->id);
            }
            // Which Land FC tab the row belongs on. Worked out now: once the number has
            // moved, the tests that tab uses cannot find the file.
            if ($this->wasOnNewTab($row, $register)) {
                $notes['list'] = 'New';
            }

            $count += $this->moveNumbers('fileNumber', $row, self::FILE_NUMBER_COLUMNS, [], $notes);
        }

        return $count;
    }

    /**
     * full_file_number is NOT NULL and unique, so it takes a placeholder rather than a blank.
     *
     * @return array{count: int, rows: array<string, object>}
     */
    public function crossOutMlsRows(array $ids): array
    {
        $db = DB::connection('sqlsrv');
        $rows = [];

        foreach ($this->notYet($db->table('mls_file_no'), $ids) as $row) {
            $db->table('mls_file_no')->where('id', $row->id)->update([
                'is_deleted' => 1,
                'deleted_numbers' => json_encode(['full_file_number' => $row->full_file_number]),
                'full_file_number' => self::mlsTombstone((int) $row->id),
            ]);
            $rows[trim((string) $row->full_file_number)] = $row;
        }

        return ['count' => count($rows), 'rows' => $rows];
    }

    /** Puts the numbers back on a row read for a table, for display only. */
    public static function restoreNumbers(object $row): object
    {
        $numbers = json_decode((string) ($row->deleted_numbers ?? ''), true);
        foreach (is_array($numbers) ? $numbers : [] as $column => $value) {
            if (! in_array($column, self::NOTE_KEYS, true)) {
                $row->{$column} = $value;
            }
        }

        return $row;
    }

    /** Only rows with moved numbers show crossed out; older is_deleted rows stay hidden. */
    public static function isCrossedOut(object $row): bool
    {
        return trim((string) ($row->deleted_numbers ?? '')) !== '';
    }

    /** Rows not already crossed out, so a second delete cannot wipe the numbers they hold. */
    private function notYet($table, array $ids)
    {
        return $table->whereIn('id', $ids)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->get();
    }

    private static function mlsTombstone(int $id): string
    {
        return 'DELETED-' . $id;
    }

    private function wasOnNewTab(object $row, ?object $register): bool
    {
        if ($register && ($register->system_sub_type ?? null) === \App\Support\OssOpCommissionFilter::OSS) {
            return false;
        }

        $viaRegister = $register && strtolower(trim((string) ($register->file_option ?? ''))) !== 'temporary';
        $viaSource = in_array($row->SOURCE ?? null, ['MLS_Commissioned', 'MLS_Commissioned_Batch'], true)
            && ($row->type ?? null) === 'MlsFileNO';

        return $viaRegister || $viaSource;
    }

    private function moveNumbers(string $table, object $row, array $columns, array $update, array $notes = []): int
    {
        $held = [];
        foreach ($columns as $column) {
            $value = $row->{$column} ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                $held[$column] = $value;
            }
        }

        return DB::connection('sqlsrv')->table($table)->where('id', $row->id)->update($update + [
            'is_deleted' => 1,
            // Never empty, so a file with no number on it still shows as crossed out.
            'deleted_numbers' => json_encode(($held + $notes) ?: new \stdClass()),
        ] + array_fill_keys($columns, null));
    }
}
