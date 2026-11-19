<?php

namespace App\Services;

use App\Services\CadastralDept\CadastralCaseSync;
use App\Services\RecordVersions\FcRecordVersions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A file title changed on one screen, copied to the file's other records (user, 2026-11-19): File Commissioning
 * (fileNumber.FileName), indexing (file_title, current_holder), customers, entities, land recommendations and
 * cadastral cases. The same set the Land FC edit already updates, for the screens that did not (OSS FC).
 *
 * A Land Block's indexing title (a JSON list of holders) is left alone.
 */
class FileTitleSync
{
    /** @param array<int, string|null> $fileNumbers */
    public function apply(array $fileNumbers, string $title): void
    {
        $title = trim($title);
        $numbers = array_values(array_unique(array_filter(array_map(fn ($n) => trim((string) $n), $fileNumbers), fn ($n) => $n !== '')));
        if ($title === '' || ! $numbers) {
            return;
        }
        $db = DB::connection('sqlsrv');

        // File Commissioning, with its Record State Version like any FC edit.
        try {
            $versions = app(FcRecordVersions::class);
            $ids = $db->table('fileNumber')
                ->where(fn ($q) => $q->whereIn('mlsfNo', $numbers)->orWhereIn('kangisFileNo', $numbers)->orWhereIn('NewKANGISFileNo', $numbers))
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->whereNull('deleted_numbers')
                ->whereRaw('ISNULL(FileName, \'\') <> ?', [$title])
                ->pluck('id');
            foreach ($ids as $id) {
                $before = $versions->capture(FcRecordVersions::FILE_NUMBER, (int) $id);
                $db->table('fileNumber')->where('id', $id)->update(['FileName' => mb_substr($title, 0, 255), 'updated_at' => now()]);
                $versions->record(FcRecordVersions::FILE_NUMBER, (int) $id, $before);
            }
        } catch (\Throwable $e) {
            Log::warning('File title sync: fileNumber', ['files' => $numbers, 'error' => $e->getMessage()]);
        }

        $copies = [
            ['file_indexings', 'file_number', ['file_title', 'current_holder']],
            ['customers_staging', 'file_number', ['customer_name']],
            ['entities_staging', 'file_number', ['entity_name']],
            ['land_recommendations', 'file_number', ['applicant_name']],
            // A file can hold more than one OSS application row; all of them carry its title.
            ['oss_applications', 'file_no', ['applicant_name']],
        ];
        foreach ($copies as [$table, $key, $columns]) {
            try {
                $q = $db->table($table)->whereIn($key, $numbers);
                if ($table === 'file_indexings') {
                    $q->whereRaw("LTRIM(ISNULL(file_title, '')) NOT LIKE '[[]%'");
                }
                $q->update(array_fill_keys($columns, $title) + ($table === 'entities_staging' || $table === 'customers_staging' ? [] : ['updated_at' => now()]));
            } catch (\Throwable $e) {
                Log::warning('File title sync: ' . $table, ['files' => $numbers, 'error' => $e->getMessage()]);
            }
        }

        app(CadastralCaseSync::class)->sync($numbers, ['file_title' => $title]);
    }
}
