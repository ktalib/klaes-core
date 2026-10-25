<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Survey GKN > Surveyors Report (user, 2026-10-25), from docs/SURVEYOR_s_report-1.docx:
 * one column per blank on the form; the "is / is not" clauses are yes/no fields.
 *
 * Attached to an Instruction to Survey (user decision): Survey's own (survey_its)
 * or one issued by Cadastral (cadastral_survey_jobs). One live report per I to S.
 * Additive only: a new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if ($s->hasTable('survey_surveyor_reports')) {
            return;
        }

        $s->create('survey_surveyor_reports', function (Blueprint $t) {
            $t->id();
            $t->string('report_ref', 30)->unique();          // SR-2026-0001
            $t->string('its_source', 20);                     // survey | cadastral
            $t->unsignedBigInteger('survey_its_id')->nullable()->index();
            $t->unsignedBigInteger('cadastral_survey_job_id')->nullable()->index();
            $t->string('its_number', 40)->nullable();
            $t->string('job_ref', 30)->nullable();

            // File Number, Name of Grantee, Location, District
            $t->string('file_number', 100)->index();
            $t->string('grantee_name', 500)->nullable();
            $t->string('plot_no', 100)->nullable();
            $t->string('district', 255)->nullable();
            $t->string('lga', 255)->nullable();
            $t->string('location', 500)->nullable();

            $t->string('grid_origin', 255)->nullable();               // origin of local grid
            $t->string('control_points', 500)->nullable();            // the three control points
            $t->string('boundaries_pointed_by', 255)->nullable();
            $t->date('boundaries_pointed_on')->nullable();
            $t->date('commenced_on')->nullable();
            $t->date('completed_on')->nullable();
            $t->decimal('distance_km', 10, 2)->nullable();
            $t->string('computed_on', 255)->nullable();               // "Computation were done on ..."
            $t->string('delay_reason', 2000)->nullable();
            $t->boolean('on_layout_plan')->nullable();                // is / is not on an approved lay-out plan
            $t->string('layout_plan_no', 100)->nullable();
            $t->boolean('corners_demarcated')->nullable();            // were / were not demarcated before
            $t->string('hard_by', 500)->nullable();
            $t->boolean('encroachment')->default(false);              // "There are no encroachment noticed"
            $t->string('encroachment_details', 2000)->nullable();
            $t->boolean('developed')->nullable();                     // is / is not developed with ...
            $t->string('developed_with', 500)->nullable();
            $t->string('beacons_not_emplaced', 500)->nullable();      // survey beacons ... were not emplaced
            $t->string('beacons_reason', 1000)->nullable();
            $t->string('previous_kn', 100)->nullable();               // boundaries of previous KN ...
            $t->boolean('previous_kn_coincides')->nullable();
            $t->string('transport_by', 20)->nullable();               // applicant | ministry
            $t->string('remarks', 4000)->nullable();
            $t->string('field_jacket_checklist', 1000)->nullable();   // JSON list of the records filed
            $t->string('surveyor_name', 255)->nullable();
            $t->date('report_date')->nullable();

            $t->string('status', 20)->default('Draft');               // Draft | Submitted
            $t->dateTime('submitted_at')->nullable();
            $t->unsignedBigInteger('submitted_by')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        // One live report per I to S.
        $db = DB::connection('sqlsrv');
        $db->statement('CREATE UNIQUE INDEX survey_sr_its_unique ON survey_surveyor_reports (survey_its_id) WHERE survey_its_id IS NOT NULL AND deleted_at IS NULL');
        $db->statement('CREATE UNIQUE INDEX survey_sr_cadjob_unique ON survey_surveyor_reports (cadastral_survey_job_id) WHERE cadastral_survey_job_id IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('survey_surveyor_reports');
    }
};
