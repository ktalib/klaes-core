<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Let a retired tree type's name be reused.
 *
 * survey_tree_types soft-deletes, but survey_tree_types_name_unique spans every
 * row including trashed ones, so a deleted type permanently occupied its name:
 * validating with whereNull('deleted_at') would pass and then die on a
 * duplicate-key error at insert.
 *
 * SQL Server filtered indexes fix this properly — the uniqueness applies only to
 * live rows. Raw SQL because Laravel's schema builder has no filtered-index API.
 *
 * Existing behaviour is unaffected: TreeController still restores a trashed row
 * when its name is re-entered, which keeps historic case lines pointing at the
 * same id (per the "decommissioning flags, never deletes" rule). This migration
 * only removes the hard database wall behind that logic.
 */
return new class extends Migration
{
    private const CONN  = 'sqlsrv';
    private const TABLE = 'survey_tree_types';
    private const INDEX = 'survey_tree_types_name_unique';

    public function up(): void
    {
        $db = DB::connection(self::CONN);

        $db->statement(sprintf(
            'IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = %s AND object_id = OBJECT_ID(%s))
                 DROP INDEX %s ON %s',
            "'" . self::INDEX . "'", "'" . self::TABLE . "'", self::INDEX, self::TABLE
        ));

        $db->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s ([name]) WHERE [deleted_at] IS NULL',
            self::INDEX, self::TABLE
        ));
    }

    public function down(): void
    {
        $db = DB::connection(self::CONN);

        $db->statement(sprintf(
            'IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = %s AND object_id = OBJECT_ID(%s))
                 DROP INDEX %s ON %s',
            "'" . self::INDEX . "'", "'" . self::TABLE . "'", self::INDEX, self::TABLE
        ));

        // Back to a plain unique index across every row, trashed included.
        $db->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s ([name])',
            self::INDEX, self::TABLE
        ));
    }
};
