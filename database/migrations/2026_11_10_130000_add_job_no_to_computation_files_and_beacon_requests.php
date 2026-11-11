<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Survey Update 5a/5b (user, 2026-11-10): a Job No, generated when a public Computation File is
 | commissioned (GKN-JOB-<year>-<serial>), and the Job No quoted on every beacon request. A private
 | request quotes its Cadastral survey job number. Additive; no existing row changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        foreach (['survey_computation_files', 'survey_beacon_requests'] as $t) {
            if (! $schema->hasTable($t)) {
                throw new RuntimeException("{$t} must exist first.");
            }
            if (! $schema->hasColumn($t, 'job_no')) {
                $schema->table($t, fn (Blueprint $b) => $b->string('job_no', 40)->nullable());
            }
        }
        // One Computation File per Job No; files commissioned before today have none.
        DB::connection('sqlsrv')->statement("IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'ux_survey_computation_files_job_no')
            CREATE UNIQUE INDEX ux_survey_computation_files_job_no ON survey_computation_files (job_no) WHERE job_no IS NOT NULL");
        DB::connection('sqlsrv')->statement("IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'ix_survey_beacon_requests_job_no')
            CREATE INDEX ix_survey_beacon_requests_job_no ON survey_beacon_requests (job_no)");
    }

    public function down(): void
    {
        $db = DB::connection('sqlsrv');
        $db->statement("IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'ux_survey_computation_files_job_no') DROP INDEX ux_survey_computation_files_job_no ON survey_computation_files");
        $db->statement("IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'ix_survey_beacon_requests_job_no') DROP INDEX ix_survey_beacon_requests_job_no ON survey_beacon_requests");
        $schema = Schema::connection('sqlsrv');
        foreach (['survey_computation_files', 'survey_beacon_requests'] as $t) {
            if ($schema->hasColumn($t, 'job_no')) {
                $schema->table($t, fn (Blueprint $b) => $b->dropColumn('job_no'));
            }
        }
    }
};
