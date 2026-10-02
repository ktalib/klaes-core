<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Allocation List is being re-designed to capture EXISTING allocations:
 * the operator picks a file number, and the file title / location come back
 * with it while the year is derived from the number itself.
 *
 * The original form only captured a name (title / first / middle / last), so
 * the table has no room for any of that. first_name / last_name also become
 * nullable because the new form captures one free-text "Name" — it is still
 * split into the legacy columns on a best-effort basis, but a single-token
 * name ("DANGOTE") legitimately has no surname to store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('allocation_list_stage', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('allocation_list_stage', 'file_no')) {
                $table->string('file_no', 100)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('allocation_list_stage', 'file_title')) {
                $table->string('file_title', 255)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('allocation_list_stage', 'allottee_name')) {
                $table->string('allottee_name', 255)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('allocation_list_stage', 'location')) {
                $table->string('location', 255)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('allocation_list_stage', 'allocation_year')) {
                // Auto-detected from the file number, but operator-editable, so
                // keep it as text rather than an int the UI has to police.
                $table->string('allocation_year', 10)->nullable();
            }
        });

        // The new single "Name" field cannot always fill both legacy columns.
        // ->change() would need doctrine/dbal, so ALTER directly.
        foreach (['first_name', 'last_name'] as $column) {
            DB::connection('sqlsrv')->statement(
                "ALTER TABLE dbo.allocation_list_stage ALTER COLUMN {$column} nvarchar(100) NULL"
            );
        }

        // The list is looked up by file number once existing allocations land in it.
        if (!$this->indexExists('IX_allocation_list_stage_file_no')) {
            DB::connection('sqlsrv')->statement(
                'CREATE INDEX IX_allocation_list_stage_file_no ON dbo.allocation_list_stage (file_no)'
            );
        }
    }

    public function down(): void
    {
        if ($this->indexExists('IX_allocation_list_stage_file_no')) {
            DB::connection('sqlsrv')->statement(
                'DROP INDEX IX_allocation_list_stage_file_no ON dbo.allocation_list_stage'
            );
        }

        Schema::connection('sqlsrv')->table('allocation_list_stage', function (Blueprint $table) {
            foreach (['file_no', 'file_title', 'allottee_name', 'location', 'allocation_year'] as $column) {
                if (Schema::connection('sqlsrv')->hasColumn('allocation_list_stage', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // Rows captured through the new form may hold NULLs, so blank them
        // before restoring NOT NULL or the ALTER fails.
        foreach (['first_name', 'last_name'] as $column) {
            DB::connection('sqlsrv')->statement(
                "UPDATE dbo.allocation_list_stage SET {$column} = '' WHERE {$column} IS NULL"
            );
            DB::connection('sqlsrv')->statement(
                "ALTER TABLE dbo.allocation_list_stage ALTER COLUMN {$column} nvarchar(100) NOT NULL"
            );
        }
    }

    private function indexExists(string $name): bool
    {
        return DB::connection('sqlsrv')->selectOne(
            "SELECT 1 AS found FROM sys.indexes
              WHERE name = ? AND object_id = OBJECT_ID('dbo.allocation_list_stage')",
            [$name]
        ) !== null;
    }
};
