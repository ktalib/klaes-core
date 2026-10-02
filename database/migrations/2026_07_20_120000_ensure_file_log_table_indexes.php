<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Guarantee the indexes the File Log Table (create-file-tracker "list" endpoint)
 * depends on. The endpoint was timing out because the per-row mother/temp
 * counterpart lookups and the "home location" created_at lookups scanned the
 * 130k-row file_indexings table. The application code was fixed to (a) batch
 * those lookups and (b) query file_number with plain equality so the index can
 * seek — but that only helps if the file_number indexes actually exist.
 *
 * Local/dev already has these indexes, so this migration is a safe no-op there.
 * Its real job is to make the requirement explicit and self-healing on
 * production, where the indexes may be missing. Each CREATE INDEX is guarded by
 * a sys.indexes check so re-running is harmless.
 */
return new class extends Migration
{
    /**
     * table => [indexName => "col list"] to ensure exist.
     */
    private function targets(): array
    {
        return [
            'file_indexings' => [
                'IX_file_indexings_file_number' => 'file_number',
            ],
            'file_tracker' => [
                'IX_file_tracker_file_number' => 'file_number',
            ],
        ];
    }

    public function up(): void
    {
        $conn = DB::connection('sqlsrv');

        foreach ($this->targets() as $table => $indexes) {
            // Skip silently if the table isn't present on this environment.
            $tableExists = (int) $conn->selectOne(
                'SELECT COUNT(*) AS c FROM sys.tables WHERE name = ?',
                [$table]
            )->c;
            if ($tableExists === 0) {
                continue;
            }

            foreach ($indexes as $indexName => $columns) {
                // Only create the index if NO index already covers this column as
                // its leading key (any equivalent index is good enough for a seek),
                // and this exact index name does not already exist.
                $named = (int) $conn->selectOne(
                    'SELECT COUNT(*) AS c FROM sys.indexes
                     WHERE object_id = OBJECT_ID(?) AND name = ?',
                    [$table, $indexName]
                )->c;

                if ($named > 0) {
                    continue;
                }

                $leading = (int) $conn->selectOne(
                    "SELECT COUNT(*) AS c
                       FROM sys.index_columns ic
                       JOIN sys.columns col
                         ON col.object_id = ic.object_id AND col.column_id = ic.column_id
                      WHERE ic.object_id = OBJECT_ID(?)
                        AND ic.key_ordinal = 1
                        AND col.name = ?",
                    [$table, $columns]
                )->c;

                if ($leading > 0) {
                    // An equivalent leading-column index already exists — nothing to do.
                    continue;
                }

                $conn->statement("CREATE INDEX [{$indexName}] ON [{$table}] ([{$columns}])");
            }
        }
    }

    public function down(): void
    {
        $conn = DB::connection('sqlsrv');

        // Only drop the indexes this migration itself would have created, and only
        // if present. We never drop pre-existing equivalent indexes.
        foreach ($this->targets() as $table => $indexes) {
            foreach ($indexes as $indexName => $columns) {
                $exists = (int) $conn->selectOne(
                    'SELECT COUNT(*) AS c FROM sys.indexes
                     WHERE object_id = OBJECT_ID(?) AND name = ?',
                    [$table, $indexName]
                )->c;

                if ($exists > 0) {
                    $conn->statement("DROP INDEX [{$indexName}] ON [{$table}]");
                }
            }
        }
    }
};
