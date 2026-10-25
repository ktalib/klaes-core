<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Survey rework, Compensation step 1-2 (user, 2026-10-25; docs/KLAES-CORE Survey Module.md).
 *
 * - survey_co_files: the CO (Compensation) file, Prj-YYYY-0001. One CO file holds
 *   many projects (transcript), so projects point at it.
 * - survey_project_surveyors: surveyors picked from surveyors_list for a project,
 *   each with a number of portions and a size per portion.
 * - Each case is the form for one portion of one surveyor's allocation.
 *
 * Additive only: new tables and nullable columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if (! $s->hasTable('survey_co_files')) {
            $s->create('survey_co_files', function (Blueprint $t) {
                $t->id();
                $t->string('co_file_no', 50)->unique();
                $t->string('title', 255);
                $t->date('commission_date')->nullable();
                $t->string('status', 30)->default('Commissioned');
                // 'commissioned' = numbered here; 'captured' = an existing paper CO file keyed in.
                $t->string('source', 20)->default('commissioned');
                $t->string('remarks', 4000)->nullable();
                $t->string('prop_house', 100)->nullable();
                $t->string('prop_plot', 100)->nullable();
                $t->string('prop_street', 1000)->nullable();
                $t->string('prop_street_other', 255)->nullable();
                $t->string('prop_district', 1000)->nullable();
                $t->string('prop_district_other', 255)->nullable();
                $t->string('prop_lga', 1000)->nullable();
                $t->string('prop_state', 100)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (! $s->hasColumn('survey_projects', 'survey_co_file_id')) {
            $s->table('survey_projects', function (Blueprint $t) {
                $t->unsignedBigInteger('survey_co_file_id')->nullable()->index();
                $t->decimal('acquisition_size_ha', 14, 4)->nullable();
            });
        }

        if (! $s->hasTable('survey_project_surveyors')) {
            $s->create('survey_project_surveyors', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('survey_project_id')->index();
                $t->unsignedBigInteger('surveyor_id')->nullable()->index();   // surveyors_list.id
                $t->string('surveyor_name', 255);                              // as listed when assigned
                $t->integer('portions');
                $t->decimal('portion_size_ha', 14, 4);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (! $s->hasColumn('survey_comp_cases', 'survey_project_surveyor_id')) {
            $s->table('survey_comp_cases', function (Blueprint $t) {
                $t->unsignedBigInteger('survey_project_surveyor_id')->nullable()->index();
                $t->integer('portion_no')->nullable();
                $t->decimal('portion_size_ha', 14, 4)->nullable();
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');

        if ($s->hasColumn('survey_comp_cases', 'survey_project_surveyor_id')) {
            $s->table('survey_comp_cases', function (Blueprint $t) {
                $t->dropIndex(['survey_project_surveyor_id']);
                $t->dropColumn(['survey_project_surveyor_id', 'portion_no', 'portion_size_ha']);
            });
        }
        $s->dropIfExists('survey_project_surveyors');
        if ($s->hasColumn('survey_projects', 'survey_co_file_id')) {
            $s->table('survey_projects', function (Blueprint $t) {
                $t->dropIndex(['survey_co_file_id']);
                $t->dropColumn(['survey_co_file_id', 'acquisition_size_ha']);
            });
        }
        $s->dropIfExists('survey_co_files');
    }
};
