<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One live case per portion of a surveyor's allocation (2026-10-25). CaseController
 * already refuses a taken portion; this closes the gap where two officers save the
 * same portion at the same moment. Filtered, so cases without a portion and deleted
 * cases are not constrained. Additive only: an index.
 */
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('sqlsrv');
        $exists = $db->selectOne("SELECT 1 AS x FROM sys.indexes WHERE name = 'ux_survey_cases_portion_live' AND object_id = OBJECT_ID('survey_comp_cases')");
        if ($exists) return;

        $db->statement('CREATE UNIQUE INDEX ux_survey_cases_portion_live ON survey_comp_cases (survey_project_surveyor_id, portion_no)
            WHERE survey_project_surveyor_id IS NOT NULL AND portion_no IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::connection('sqlsrv')->statement("IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'ux_survey_cases_portion_live' AND object_id = OBJECT_ID('survey_comp_cases'))
            DROP INDEX ux_survey_cases_portion_live ON survey_comp_cases");
    }
};
