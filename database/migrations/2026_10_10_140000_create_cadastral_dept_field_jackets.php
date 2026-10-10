<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastral Department workflow, step 10: Field Jacket, job, surveyor, I to S.
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_10_140000_create_cadastral_dept_field_jackets.php
 *
 * Additive and guarded. AD P&D commissions one field jacket per case; doing so
 * registers a survey job in the old module's cadastral_survey_jobs (same
 * numbering, same I to S columns), so the old Survey Jobs screen lists it too.
 * The jacket's surveyor is locked; a reassignment ends the current assignment
 * row and starts a new one, with the reason, so the history stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if (! $s->hasTable('cadastral_field_jackets')) {
            $s->create('cadastral_field_jackets', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cadastral_case_id')->unique();
                $t->string('field_jacket_no', 30)->unique();
                $t->unsignedBigInteger('cadastral_survey_job_id')->nullable();
                $t->unsignedBigInteger('cadastral_surveyor_id')->nullable();
                $t->string('file_number', 100);
                $t->string('file_title', 500)->nullable();
                $t->string('plot_no', 100)->nullable();
                $t->string('district', 255)->nullable();
                $t->string('lga', 255)->nullable();
                $t->string('location', 500)->nullable();
                $t->string('remarks', 2000)->nullable();
                $t->unsignedBigInteger('commissioned_by')->nullable();
                $t->dateTime('commissioned_at');
                $t->unsignedInteger('print_count')->default(0);
                $t->timestamps();
                $t->index('cadastral_survey_job_id');
            });
        }

        if (! $s->hasTable('cadastral_field_jacket_assignments')) {
            $s->create('cadastral_field_jacket_assignments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cadastral_field_jacket_id');
                $t->unsignedBigInteger('cadastral_surveyor_id');
                $t->string('surveyor_name', 255)->nullable();
                $t->string('firm_name', 255)->nullable();
                $t->string('reason', 1000)->nullable();     // why this assignment replaced the last one
                $t->unsignedBigInteger('assigned_by')->nullable();
                $t->dateTime('assigned_at');
                $t->unsignedBigInteger('ended_by')->nullable();
                $t->dateTime('ended_at')->nullable();
                $t->timestamps();
                $t->index('cadastral_field_jacket_id');
            });
        }

        if ($s->hasTable('cadastral_survey_jobs')) {
            if (! $s->hasColumn('cadastral_survey_jobs', 'cadastral_case_id')) {
                $s->table('cadastral_survey_jobs', fn (Blueprint $t) => $t->unsignedBigInteger('cadastral_case_id')->nullable());
            }
            if (! $s->hasColumn('cadastral_survey_jobs', 'field_jacket_no')) {
                $s->table('cadastral_survey_jobs', fn (Blueprint $t) => $t->string('field_jacket_no', 30)->nullable());
            }
        }
    }

    public function down(): void
    {
        // Left in place on purpose: no backups here, and the rows are records.
    }
};
