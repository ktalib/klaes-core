<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastral Department workflow: the Yes/No form ("Report on Application –
 * Cadastral Department", client PDF 2026-10-11), filled at AD Cad Report with
 * Land 12. One per case. The columns copy the old module's questionnaire on
 * cadastral_reports (2026_10_02_100000), so both read alike; Yes/No is
 * nvarchar(3) holding 'Yes' / 'No', and area is stored once, in hectares.
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_11_090000_create_cadastral_dept_yes_no_forms.php
 *
 * Additive and guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if (! $s->hasTable('cadastral_yes_no_forms')) {
            $s->create('cadastral_yes_no_forms', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cadastral_case_id')->unique();
                $t->string('file_number', 100);
                $t->string('lkn_no', 100)->nullable();
                $t->string('sltr_no', 100)->nullable();
                $t->string('govt_item_no', 100)->nullable();
                $t->string('sit_no', 100)->nullable();
                $t->string('q1_plan_sufficient', 3)->nullable();
                $t->string('q2_ground_open', 3)->nullable();
                $t->string('q2_overlapping_title', 500)->nullable();
                $t->string('q3_beaconed', 3)->nullable();
                $t->string('q3_tracing_no', 100)->nullable();
                $t->string('q3_deposition_plan_no', 100)->nullable();
                $t->string('q3_unapproved_town_plan_no', 100)->nullable();
                $t->string('q3_layout_no', 100)->nullable();
                $t->string('q3_separate_survey', 3)->nullable();
                $t->string('q4_town_plan', 3)->nullable();
                $t->string('q4_town_plan_no', 100)->nullable();
                $t->string('q4_shape_agrees', 3)->nullable();
                $t->string('q4_purpose', 100)->nullable();
                $t->string('q4_area_for_purpose', 3)->nullable();
                $t->string('q5_previous_title', 3)->nullable();
                $t->string('q5_details', 1000)->nullable();
                $t->string('q6_railway', 3)->nullable();
                $t->string('q7_trunk_road', 3)->nullable();
                $t->decimal('area_applied_ha', 14, 4)->nullable();
                $t->unsignedBigInteger('saved_by')->nullable();
                $t->dateTime('completed_at')->nullable();    // all seven questions answered
                $t->unsignedInteger('print_count')->default(0);
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Left in place on purpose: no backups here, and the rows are records.
    }
};
