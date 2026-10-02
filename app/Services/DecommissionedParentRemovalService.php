<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Physical retirement is restricted to these four live-register tables. */
class DecommissionedParentRemovalService
{
    public const TABLES = [
        'customers_staging' => ['file_number'],
        'entities_staging' => ['file_number'],
        'file_indexings' => ['file_number', 'kangis_file_no'],
        'fileNumber' => ['mlsfNo', 'kangisFileNo'],
    ];

    public function matchingRows(string $table, string $fileNo)
    {
        if (!isset(self::TABLES[$table]) || trim($fileNo) === '' || strtoupper(trim($fileNo)) === 'N/A') {
            throw new RuntimeException('Invalid parent removal target.');
        }

        return DB::connection('sqlsrv')->table($table)->where(function ($q) use ($table, $fileNo) {
            foreach (self::TABLES[$table] as $column) {
                $q->orWhere($column, trim($fileNo));
            }
        });
    }

    public function assertNoIndirectDeletes(): void
    {
        $db = DB::connection('sqlsrv');
        foreach (array_keys(self::TABLES) as $table) {
            // Never let a cascade or trigger mutate transaction/history tables.
            if ($db->selectOne('SELECT TOP 1 name FROM sys.foreign_keys WHERE referenced_object_id = OBJECT_ID(?) AND delete_referential_action <> 0 AND is_disabled = 0', ['dbo.' . $table])
                || $db->selectOne('SELECT TOP 1 name FROM sys.triggers WHERE parent_id = OBJECT_ID(?) AND is_disabled = 0', ['dbo.' . $table])) {
                throw new RuntimeException("Removal blocked: {$table} has cascading actions or triggers requiring review.");
            }
        }
    }

    /** No backup or archive is created here; genuine decommissioning must already exist. */
    public function remove(string $fileNo): array
    {
        $fileNo = trim($fileNo);
        $this->assertNoIndirectDeletes();

        return DB::connection('sqlsrv')->transaction(function () use ($fileNo) {
            $archive = DB::connection('sqlsrv')->table('decommissioned_files')
                ->where(fn ($q) => $q->where('file_no', $fileNo)->orWhere('mls_file_no', $fileNo))
                ->where(fn ($q) => $q->where('false_decommissioning', '<>', 1)->orWhereNull('false_decommissioning'))
                ->lockForUpdate()->first();
            if (!$archive) {
                throw new RuntimeException("Parent {$fileNo} has no genuine decommissioning archive.");
            }

            $removed = [];
            foreach (array_keys(self::TABLES) as $table) {
                // Match own file numbers only: never PropID, parent_prop_id, related
                // file numbers or new KANGIS successor pointers.
                $ids = $this->matchingRows($table, $fileNo)->lockForUpdate()->pluck('id')->all();
                $removed[$table] = 0;
                foreach (array_chunk($ids, 500) as $chunk) {
                    $removed[$table] += DB::connection('sqlsrv')->table($table)->whereIn('id', $chunk)->delete();
                }
            }

            return $removed;
        });
    }
}
