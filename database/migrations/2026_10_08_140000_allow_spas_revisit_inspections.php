<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SPAS: allow more than one inspection per file (revisits).
 *
 * UQ_spa_field_data_file_number (filtered unique, from
 * database/sql/2026_08_15_spas_offline_sync_schema.sql) allowed exactly one
 * inspection per file number. A second officer revisiting a site that was
 * already inspected now adds a new inspection to the same record, so the
 * unique index becomes an ordinary one. Duplicate pushes from a device are
 * still stopped by UQ_spa_field_data_client_uuid.
 *
 * No data is changed.
 */
return new class extends Migration {
    private function hasIndex(string $name): bool
    {
        return (bool) DB::connection('sqlsrv')->selectOne(
            "SELECT 1 AS x FROM sys.indexes WHERE object_id = OBJECT_ID('spa_field_data') AND name = ?", [$name]
        );
    }

    public function up(): void
    {
        $db = DB::connection('sqlsrv');

        if ($this->hasIndex('UQ_spa_field_data_file_number')) {
            $db->statement('DROP INDEX UQ_spa_field_data_file_number ON spa_field_data');
        }

        if (! $this->hasIndex('IX_spa_field_data_file_number')) {
            $db->statement('CREATE INDEX IX_spa_field_data_file_number ON spa_field_data (file_number) WHERE file_number IS NOT NULL');
        }
    }

    public function down(): void
    {
        // Restoring the unique index fails while any file has two inspections —
        // which is the point of up(). Remove the extra visits first.
        $db = DB::connection('sqlsrv');

        if ($this->hasIndex('IX_spa_field_data_file_number')) {
            $db->statement('DROP INDEX IX_spa_field_data_file_number ON spa_field_data');
        }

        if (! $this->hasIndex('UQ_spa_field_data_file_number')) {
            $db->statement('CREATE UNIQUE INDEX UQ_spa_field_data_file_number ON spa_field_data (file_number) WHERE file_number IS NOT NULL');
        }
    }
};
