<?php

namespace App\Services\CadastralDept;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Writes a file's correspondence and shadow numbers onto its fileNumber rows
 * and OSS applications (migration 2026_10_10_120000). Called wherever a
 * correspondence file is created or matched: the Create Correspondence File
 * screen, the old Cadastral Module's registration, and the new workflow.
 *
 * Matches every number column of fileNumber (one file has several rows), and
 * never overwrites a value already set. Does nothing until the columns exist.
 * Must run inside the caller's sqlsrv transaction.
 */
class CorrespondenceColumns
{
    private const NUMBER_COLUMNS = ['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no'];

    public static function available(): bool
    {
        static $has = null;

        try {
            return $has ??= Schema::connection('sqlsrv')->hasColumn('fileNumber', 'shadow_fileno');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @param string[] $numbers every spelling of the file's number */
    public static function stamp(array $numbers, ?string $correspondingFileno, ?string $shadowRef): void
    {
        $numbers = array_values(array_unique(array_filter(array_map(fn ($n) => trim((string) $n), $numbers))));
        if (!$numbers || !self::available() || (!$correspondingFileno && !$shadowRef)) {
            return;
        }
        $db = DB::connection('sqlsrv');

        foreach (['corresponding_fileno' => $correspondingFileno, 'shadow_fileno' => $shadowRef] as $col => $value) {
            if (!$value) {
                continue;
            }
            $db->table('fileNumber')
                ->where(function ($w) use ($numbers) {
                    foreach (self::NUMBER_COLUMNS as $c) {
                        $w->orWhereIn($c, $numbers);
                    }
                })
                ->where(fn ($w) => $w->whereNull($col)->orWhere($col, ''))
                ->update([$col => $value]);

            if (Schema::connection('sqlsrv')->hasColumn('oss_applications', $col)) {
                $db->table('oss_applications')
                    ->whereIn('file_no', $numbers)
                    ->where(fn ($w) => $w->whereNull($col)->orWhere($col, ''))
                    ->update([$col => $value]);
            }
        }
    }
}
