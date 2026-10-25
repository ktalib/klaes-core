<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Survey Examination/Verification (user, 2026-10-25; docs/KLAES-CORE Survey Module.md 5.2,
 * 5.3, 5.7):
 *
 * - survey_boundary_disputes: disputes found or reported during verification, worked
 *   Open -> Under Investigation -> Resolved / Escalated with a notes trail.
 * - survey_old_surveys: historical surveys checked for authenticity.
 * - survey_change_reports: the Surveyors' Report (Changes), the printed list of changes
 *   required when a submitted plan does not match the records; raised from a Register
 *   File examination line.
 *
 * Each may point at a Register File line (survey_register_files). Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if (! $s->hasTable('survey_boundary_disputes')) {
            $s->create('survey_boundary_disputes', function (Blueprint $t) {
                $t->id();
                $t->string('dispute_ref', 30)->unique();          // BD-2026-001
                $t->unsignedBigInteger('survey_register_file_id')->nullable()->index();
                $t->string('file_number', 100)->nullable()->index();
                $t->string('other_file_number', 100)->nullable();  // the neighbouring / disputed file, if any
                $t->string('complainant_name', 255);
                $t->string('complainant_phone', 50)->nullable();
                $t->string('respondent_name', 255)->nullable();    // the other party
                $t->string('nature', 100);
                $t->string('plot_no', 100)->nullable();
                $t->string('district', 255)->nullable();
                $t->string('lga', 255)->nullable();
                $t->string('location', 500)->nullable();
                $t->string('description', 4000)->nullable();
                $t->date('received_date');
                $t->string('status', 30)->default('Open');
                $t->string('notes', 4000)->nullable();
                $t->dateTime('resolved_at')->nullable();
                $t->unsignedBigInteger('resolved_by')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (! $s->hasTable('survey_old_surveys')) {
            $s->create('survey_old_surveys', function (Blueprint $t) {
                $t->id();
                $t->string('old_survey_ref', 30)->unique();       // OS-2026-001
                $t->unsignedBigInteger('survey_register_file_id')->nullable()->index();
                $t->string('file_number', 100)->nullable()->index();
                $t->string('old_plan_no', 100)->nullable()->index();
                $t->string('kn_number', 100)->nullable();
                $t->string('surveyor_name', 255)->nullable();
                $t->date('survey_date')->nullable();
                $t->string('record_source', 100)->nullable();      // where the historical record was found
                $t->string('plot_no', 100)->nullable();
                $t->string('district', 255)->nullable();
                $t->string('lga', 255)->nullable();
                $t->string('location', 500)->nullable();
                $t->string('verification_status', 30)->default('Pending');
                $t->string('findings', 4000)->nullable();
                $t->unsignedBigInteger('verified_by')->nullable();
                $t->dateTime('verified_at')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (! $s->hasTable('survey_change_reports')) {
            $s->create('survey_change_reports', function (Blueprint $t) {
                $t->id();
                $t->string('report_ref', 30)->unique();           // SRC-2026-001
                $t->unsignedBigInteger('survey_register_file_id')->nullable()->index();
                $t->string('file_number', 100)->nullable()->index();
                $t->string('file_title', 500)->nullable();
                $t->string('plot_no', 100)->nullable();
                $t->string('district', 255)->nullable();
                $t->string('lga', 255)->nullable();
                $t->string('plan_no', 100)->nullable();
                $t->string('surveyor_name', 255)->nullable();      // who prepared the plan
                $t->string('discrepancies', 4000);                 // what does not match the records
                $t->string('required_changes', 4000);              // what must be changed
                $t->date('report_date')->nullable();
                $t->string('examined_by', 255)->nullable();
                $t->string('status', 20)->default('Draft');        // Draft | Issued
                $t->dateTime('issued_at')->nullable();
                $t->unsignedBigInteger('issued_by')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        $s->dropIfExists('survey_change_reports');
        $s->dropIfExists('survey_old_surveys');
        $s->dropIfExists('survey_boundary_disputes');
    }
};
