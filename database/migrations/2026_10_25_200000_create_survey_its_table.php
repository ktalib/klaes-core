<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Survey GKN > Instruction to Survey (user, 2026-10-25): Survey's own survey jobs and
 * their instructions, for GKN / LPKN / Misc KN files and Government surveyors (Survey
 * Department staff from surveyors_list). The I to S is the anchor the Surveyors
 * Report, List of Coordinates and Field Observations attach to.
 *
 * Cadastral's instructions stay in cadastral_survey_jobs and are only read.
 * Additive only: a new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if ($s->hasTable('survey_its')) {
            return;
        }

        $s->create('survey_its', function (Blueprint $t) {
            $t->id();
            $t->string('job_ref', 30)->unique();          // SJ-2026-0001, from registration
            $t->string('its_number', 40)->nullable();     // SRV/ITS/2026/0001, when issued
            $t->string('file_number', 100)->index();
            $t->string('file_type', 10)->nullable();      // gkn | lpkn | lppl | misc
            $t->string('file_title', 500)->nullable();
            $t->string('plot_no', 100)->nullable();
            $t->string('district', 255)->nullable();
            $t->string('lga', 255)->nullable();
            $t->string('location', 500)->nullable();
            $t->unsignedBigInteger('surveyor_id')->nullable()->index();   // surveyors_list.id
            $t->string('surveyor_name', 255)->nullable();
            $t->string('surveyor_rank', 150)->nullable();
            $t->string('job_scope', 4000)->nullable();
            $t->text('its_instructions')->nullable();
            $t->date('its_issued_at')->nullable();
            $t->string('its_issued_by', 255)->nullable();
            $t->unsignedBigInteger('its_issued_by_user_id')->nullable();
            $t->string('status', 30)->default('Draft');
            $t->dateTime('issued_at')->nullable();
            $t->dateTime('completed_at')->nullable();
            $t->string('remarks', 2000)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        DB::connection('sqlsrv')->statement(
            'CREATE UNIQUE INDEX survey_its_its_number_unique ON survey_its (its_number) WHERE its_number IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('survey_its');
    }
};
