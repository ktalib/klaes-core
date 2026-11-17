<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Application Plan details (user, 2026-11-17): the Ministry's Application Plan form is printed from a survey plan
 | upload's row menu. A pop-up first shows its entries, backfilled from the upload where KLAES has them; the rest
 | (applicant, purpose, receipt ...) are typed in and saved here, one row per upload, so the next print has them.
 | `fields` = the form's entries as JSON (keys as in SurveyApplicationPlan::FIELDS). Additive: a new table only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('survey_application_plans')) {
            return;
        }
        Schema::connection('sqlsrv')->create('survey_application_plans', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('survey_plan_upload_id');
            $t->text('fields')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index('survey_plan_upload_id');
        });
        // One live row per upload.
        DB::connection('sqlsrv')->statement('CREATE UNIQUE INDEX ux_survey_application_plans_upload ON survey_application_plans (survey_plan_upload_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('survey_application_plans');
    }
};
