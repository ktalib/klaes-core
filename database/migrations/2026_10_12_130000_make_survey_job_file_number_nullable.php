<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A survey job is assigned to a surveyor without a file (user, 2026-10-12): the
 * file is attached later, at Assign to Surveyor (I to S). So
 * cadastral_survey_jobs.file_number may be empty until then.
 *
 * SQL Server will not alter a column an index depends on, so the file_number
 * index is dropped and recreated around the change. No data changes.
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_12_130000_make_survey_job_file_number_nullable.php
 */
return new class extends Migration
{
    private const INDEX = 'cadastral_survey_jobs_file_number_index';

    public function up(): void
    {
        $this->alter('NULL');
    }

    public function down(): void
    {
        // Only possible while every job has a file.
        $this->alter('NOT NULL');
    }

    private function alter(string $nullability): void
    {
        if (! Schema::connection('sqlsrv')->hasTable('cadastral_survey_jobs')) {
            return;
        }
        $db = DB::connection('sqlsrv');
        $hasIndex = $db->selectOne("SELECT 1 AS x FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID('cadastral_survey_jobs')", [self::INDEX]);

        if ($hasIndex) {
            $db->statement('DROP INDEX ' . self::INDEX . ' ON cadastral_survey_jobs');
        }
        $db->statement("ALTER TABLE cadastral_survey_jobs ALTER COLUMN file_number NVARCHAR(100) {$nullability}");
        $db->statement('CREATE INDEX ' . self::INDEX . ' ON cadastral_survey_jobs (file_number)');
    }
};
