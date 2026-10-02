<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop uq_rfn_source from related_file_number where an environment actually has it.
 *
 * The create migration declared unique(source_table, source_id), but one source row
 * legitimately carries several related numbers -- 486 source_ids do on the live table, which
 * was built from database/migrations/manual/create_related_file_number_table.sql and never had
 * the constraint. Where the Laravel migration did run, a multi-link insert is rejected whole,
 * and the callers log the failure as a warning rather than surfacing it, so the links vanish
 * silently. A plain non-unique index covers the same lookups.
 *
 * No-op on the live schema (the index is not there). Not reversible -- restoring the
 * constraint would reject data the application is meant to store.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('related_file_number')) {
            return;
        }

        $conn = DB::connection($this->connection);

        $exists = $conn->selectOne("
            SELECT name FROM sys.indexes
            WHERE object_id = OBJECT_ID('related_file_number') AND name = 'uq_rfn_source'
        ");

        if (!$exists) {
            return;
        }

        $conn->statement('DROP INDEX [uq_rfn_source] ON [related_file_number]');
        $conn->statement('
            CREATE NONCLUSTERED INDEX [IX_related_file_number_source_pair]
            ON [related_file_number] ([source_table], [source_id])
        ');
    }

    public function down(): void
    {
        // Intentionally irreversible: the unique constraint contradicts the data model.
    }
};
