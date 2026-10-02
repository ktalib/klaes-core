<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * Make decommissioning a flag the live tables carry, instead of a delete.
     *
     * Until now PlotWorkflowService archived a file into decommissioned_files and
     * then HARD DELETED its rows from fileNumber, file_indexings, customers_staging,
     * entities_staging and kangis_grouping. The archive was the only surviving copy,
     * so a decommissioned file lost its indexing detail, its customer/entity parties
     * and its grouping placeholder permanently, and every screen "knew" it was
     * decommissioned only because the row had vanished.
     *
     * Nothing is deleted any more. The rows stay, flagged, and each table becomes
     * self-describing: you can read the decommission state without joining back to
     * decommissioned_files (which stays the registry and is still written).
     *
     * Canonical column set, applied to every table below:
     *   is_decommissioned      0/1 — the flag every read path filters or badges on
     *   decommissioned_at      when it happened
     *   decommissioned_by      who did it (display name, matching decommissioned_files)
     *   decommissioning_reason the workflow that caused it (Merger / Subdivision / ...)
     *   successor_file_no      the file that replaced it, for lineage without a join
     *
     * fileNumber already carries is_decommissioned + decommissioning_date +
     * decommissioning_reason from an earlier change; only its missing columns are
     * added, and the service keeps decommissioning_date in step with
     * decommissioned_at so the existing File Decommissioning screen still works.
     *
     * Every column is added only when absent, so this is safe to re-run and safe on
     * a database where some tables were patched by hand.
     */
    private const TABLES = [
        'fileNumber',
        'file_indexings',
        'customers_staging',
        'entities_staging',
        'kangis_grouping',
    ];

    /** column => SQL Server type/definition */
    private const COLUMNS = [
        'is_decommissioned'      => 'TINYINT NOT NULL DEFAULT 0',
        'decommissioned_at'      => 'DATETIME NULL',
        'decommissioned_by'      => 'NVARCHAR(255) NULL',
        'decommissioning_reason' => 'NVARCHAR(MAX) NULL',
        'successor_file_no'      => 'NVARCHAR(MAX) NULL',
    ];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $conn   = DB::connection('sqlsrv');

        foreach (self::TABLES as $table) {
            if (!$schema->hasTable($table)) {
                continue;
            }

            foreach (self::COLUMNS as $column => $definition) {
                if ($schema->hasColumn($table, $column)) {
                    continue;
                }

                $conn->statement("ALTER TABLE [{$table}] ADD [{$column}] {$definition}");
            }

            // The read paths all filter or badge on this flag, so it needs to seek.
            // Filtered on 1: decommissioned rows are the rare minority, which keeps
            // the index tiny while still serving "show me the decommissioned ones".
            $index = "ix_{$table}_is_decommissioned";
            if ($schema->hasColumn($table, 'is_decommissioned') && !$this->indexExists($table, $index)) {
                $conn->statement(
                    "CREATE NONCLUSTERED INDEX [{$index}] ON [{$table}] ([is_decommissioned]) WHERE [is_decommissioned] = 1"
                );
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $conn   = DB::connection('sqlsrv');

        foreach (self::TABLES as $table) {
            if (!$schema->hasTable($table)) {
                continue;
            }

            $index = "ix_{$table}_is_decommissioned";
            if ($this->indexExists($table, $index)) {
                $conn->statement("DROP INDEX [{$index}] ON [{$table}]");
            }

            foreach (array_keys(self::COLUMNS) as $column) {
                if (!$schema->hasColumn($table, $column)) {
                    continue;
                }

                // fileNumber owned is_decommissioned / decommissioning_reason before
                // this migration — rolling back must not take its original columns.
                if ($table === 'fileNumber' && in_array($column, ['is_decommissioned', 'decommissioning_reason'], true)) {
                    continue;
                }

                // SQL Server names DEFAULT constraints implicitly; drop it before the column.
                $conn->statement(
                    "DECLARE @df NVARCHAR(255);
                     SELECT @df = dc.name FROM sys.default_constraints dc
                       JOIN sys.columns c ON c.default_object_id = dc.object_id
                      WHERE dc.parent_object_id = OBJECT_ID('{$table}') AND c.name = '{$column}';
                     IF @df IS NOT NULL EXEC('ALTER TABLE [{$table}] DROP CONSTRAINT [' + @df + ']');
                     ALTER TABLE [{$table}] DROP COLUMN [{$column}]"
                );
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return (bool) DB::connection('sqlsrv')->selectOne(
            'SELECT 1 AS found FROM sys.indexes WHERE object_id = OBJECT_ID(?) AND name = ?',
            [$table, $index]
        );
    }
};
