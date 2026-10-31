<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GKN → Public Survey → Private Layout (user, 2026-10-30): the register of privately
 * owned layouts (owners / developers and their surveyors), kept apart from the
 * Government's Public Layout Plans (survey_lpkn).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasTable('survey_private_layouts')) return;

        $schema->create('survey_private_layouts', function (Blueprint $t) {
            $t->id();
            $t->string('layout_ref', 30)->unique();          // PVL-YYYY-0001
            $t->string('layout_no', 100)->nullable()->index(); // the plan number as written
            $t->string('layout_name', 255);
            $t->string('owner_name', 255);
            $t->string('owner_phone', 50)->nullable();
            $t->string('surveyor_name', 255)->nullable();
            $t->string('surveyor_registration', 100)->nullable();
            $t->integer('plot_count')->nullable();
            $t->decimal('area_ha', 14, 4)->nullable();
            $t->string('land_use', 60)->nullable();
            $t->string('land_use_other', 255)->nullable();
            $t->string('prop_house', 100)->nullable();
            $t->string('prop_plot', 100)->nullable();
            $t->string('prop_street', 500)->nullable();
            $t->string('prop_street_other', 255)->nullable();
            $t->string('prop_district', 500)->nullable();
            $t->string('prop_district_other', 255)->nullable();
            $t->string('prop_lga', 500)->nullable();
            $t->string('prop_state', 255)->nullable();
            $t->string('status', 30);
            $t->date('submitted_on')->nullable();
            $t->date('approved_on')->nullable();
            $t->string('remarks', 2000)->nullable();
            $t->string('plan_path', 500)->nullable();
            $t->string('plan_name', 255)->nullable();
            $t->string('plan_mime', 100)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('survey_private_layouts');
    }
};
