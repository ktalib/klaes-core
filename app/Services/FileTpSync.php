<?php

namespace App\Services;

use App\Services\RecordVersions\FcRecordVersions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A TP No. corrected on one screen, copied to the file's other records (user, 2026-11-19): File Commissioning
 * (fileNumber.tp_no), the MLS record, indexing, the file's OP and Transfer of Title rows (what the OP card shows)
 * and the OSS application's Plan No. Land FC, the OP edit and OSS FC each used to reach only some of these.
 */
class FileTpSync
{
    /** @param array<int, string|null> $fileNumbers */
    public function apply(array $fileNumbers, ?string $tp): void
    {
        $tp = trim((string) $tp);
        $numbers = array_values(array_unique(array_filter(array_map(fn ($n) => trim((string) $n), $fileNumbers), fn ($n) => $n !== '')));
        if ($tp === '' || ! $numbers) {
            return;
        }
        $db = DB::connection('sqlsrv');
        $differs = fn ($q, string $col) => $q->whereRaw("ISNULL(LTRIM(RTRIM({$col})), '') <> ?", [$tp]);

        // File Commissioning, with its Record State Version like any FC edit.
        try {
            $versions = app(FcRecordVersions::class);
            $ids = $differs($db->table('fileNumber')
                ->where(fn ($q) => $q->whereIn('mlsfNo', $numbers)->orWhereIn('kangisFileNo', $numbers)->orWhereIn('NewKANGISFileNo', $numbers))
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->whereNull('deleted_numbers'), 'tp_no')
                ->pluck('id');
            foreach ($ids as $id) {
                $before = $versions->capture(FcRecordVersions::FILE_NUMBER, (int) $id);
                $db->table('fileNumber')->where('id', $id)->update(['tp_no' => mb_substr($tp, 0, 100), 'updated_at' => now()]);
                $versions->record(FcRecordVersions::FILE_NUMBER, (int) $id, $before);
            }
        } catch (\Throwable $e) {
            Log::warning('TP sync: fileNumber', ['files' => $numbers, 'error' => $e->getMessage()]);
        }

        $copies = [
            ['mls_file_no', 'full_file_number', 'tp_no'],
            ['file_indexings', 'file_number', 'tp_no'],
            ['oss_applications', 'file_no', 'plan_no'],
        ];
        foreach ($copies as [$table, $key, $col]) {
            try {
                $differs($db->table($table)->whereIn($key, $numbers), $col)->update([$col => $tp, 'updated_at' => now()]);
            } catch (\Throwable $e) {
                Log::warning('TP sync: ' . $table, ['files' => $numbers, 'error' => $e->getMessage()]);
            }
        }

        // The file's own OP and Transfer of Title rows (the OP card reads these).
        try {
            $differs($db->table('pra')
                ->where(fn ($q) => $q->whereIn('mlsFNo', $numbers)->orWhereIn('fileno', $numbers))
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->where(fn ($q) => $q->where('instrument_type', 'like', 'Occupancy Permit%')->orWhere('instrument_type', 'like', 'Transfer of Title%')), 'tp_no')
                ->update(['tp_no' => $tp, 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('TP sync: pra', ['files' => $numbers, 'error' => $e->getMessage()]);
        }
    }
}
