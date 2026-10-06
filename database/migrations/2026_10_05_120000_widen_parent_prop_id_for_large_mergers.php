<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * Widen parent_prop_id on the live registry tables to NVARCHAR(MAX).
     *
     * A merged file's parent_prop_id is every source file's prop_id, comma-joined
     * (MlsFileNoController, merger linkage). At NVARCHAR(255) that holds about 28
     * sources (prop_ids run to 8 characters), so a bigger merger fails with "would be
     * truncated" and rolls back. The duplex now allows up to 2000 sources per stage
     * (DuplexParcelUpdate::MAX_PLOTS), which needs about 18,000 characters.
     *
     * No index covers the column on either table, only auto-created statistics, which
     * do not block widening a column of the same type. DuplexCommitService reads the
     * column width at commit time, so its merger guard lifts by itself once this runs.
     *
     * Not reversed in down(): narrowing back would truncate any long list written since.
     */
    private const TABLES = ['fileNumber', 'file_indexings'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $bytes = DB::connection('sqlsrv')->selectOne(
                'SELECT COL_LENGTH(?, ?) AS b', [$table, 'parent_prop_id']
            )->b ?? null;

            // Missing, or already MAX: nothing to do.
            if ($bytes === null || (int) $bytes === -1) {
                continue;
            }

            DB::connection('sqlsrv')->statement(
                "ALTER TABLE dbo.[{$table}] ALTER COLUMN parent_prop_id NVARCHAR(MAX) NULL"
            );
        }
    }

    public function down(): void
    {
        // Intentionally left wide; see the note above.
    }
};
