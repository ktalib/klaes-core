<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LPPL and RDKN application file numbers (user, 2026-10-30): numbered like GKN / LPKN,
 * "LPPL-1" and "RDKN-1", from 1. The GKN generator commissions a number by claiming its
 * pre-made row in a grouping table, so each prefix gets a table shaped like lpkn_grouping
 * holding 100,000 numbers with their tracking IDs, as LPKN and MISC KN have.
 */
return new class extends Migration
{
    private const PREFIXES = ['lppl' => 'LPPL-', 'rdkn' => 'RDKN-'];
    private const COUNT = 100000;

    public function up(): void
    {
        $db = DB::connection('sqlsrv');
        foreach (self::PREFIXES as $key => $prefix) {
            $table = "{$key}_grouping";
            if (Schema::connection('sqlsrv')->hasTable($table)) continue;

            // Same columns and types as lpkn_grouping (SELECT INTO keeps the identity id), with its own names.
            $db->statement("SELECT TOP 0 * INTO {$table} FROM lpkn_grouping");
            $db->statement("EXEC sp_rename '{$table}.lpkn_awaiting_fileno', '{$key}_awaiting_fileno', 'COLUMN'");
            $db->statement("EXEC sp_rename '{$table}.lpkn_fileno', '{$key}_fileno', 'COLUMN'");
            $db->statement("ALTER TABLE {$table} ADD CONSTRAINT PK_{$table} PRIMARY KEY (id)");

            // Rows as lpkn_grouping holds them: created_by "Generated", registry Survey, 100 numbers per group,
            // tracking ID TRK-<8 hex>-<serial padded to 5>.
            $db->statement("
                WITH n AS (SELECT TOP (" . self::COUNT . ") ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n
                           FROM sys.all_objects a CROSS JOIN sys.all_objects b)
                INSERT INTO {$table} ({$key}_awaiting_fileno, created_by, [year], created_at, updated_at, number, registry,
                                      [group], sys_batch_no, registry_batch_no, tracking_id)
                SELECT '{$prefix}' + CAST(n AS varchar(10)), 'Generated', YEAR(SYSDATETIME()), SYSDATETIME(), SYSDATETIME(),
                       CAST(n AS nvarchar(20)), 'Survey',
                       CAST((n - 1) / 100 + 1 AS nvarchar(20)), CAST((n - 1) / 100 + 1 AS nvarchar(20)), CAST((n - 1) / 100 + 1 AS nvarchar(20)),
                       'TRK-' + LEFT(REPLACE(CONVERT(varchar(36), NEWID()), '-', ''), 8) + '-'
                           + CASE WHEN n < 100000 THEN RIGHT('00000' + CAST(n AS varchar(10)), 5) ELSE CAST(n AS varchar(10)) END
                FROM n ORDER BY n");
            $db->statement("CREATE INDEX IX_{$table}_awaiting ON {$table} ({$key}_awaiting_fileno)");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::PREFIXES) as $key) {
            Schema::connection('sqlsrv')->dropIfExists("{$key}_grouping");
        }
    }
};
