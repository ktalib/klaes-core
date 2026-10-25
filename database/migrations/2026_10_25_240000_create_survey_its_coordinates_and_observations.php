<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Survey GKN > List of Coordinates and Computation Field Observations (user,
 * 2026-10-25), both attached to an Instruction to Survey: Survey's own (survey_its)
 * or Cadastral's (cadastral_survey_jobs).
 *
 * - survey_its_coordinates: the official beacon list for the survey.
 * - survey_its_observations: field readings (station to station, observed bearing and
 *   distance), entered here from paper or, later, synced from Mobile App 2 (source).
 *
 * Additive only: two new tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if (! $s->hasTable('survey_its_coordinates')) {
            $s->create('survey_its_coordinates', function (Blueprint $t) {
                $t->id();
                $t->string('its_source', 20);
                $t->unsignedBigInteger('survey_its_id')->nullable()->index();
                $t->unsignedBigInteger('cadastral_survey_job_id')->nullable()->index();
                $t->string('its_number', 40)->nullable();
                $t->string('file_number', 100)->nullable()->index();
                $t->unsignedInteger('sort_order')->default(0);
                $t->string('beacon_id', 50);
                $t->decimal('northing', 15, 3);
                $t->decimal('easting', 15, 3);
                $t->decimal('elevation', 15, 3)->nullable();
                $t->string('remarks', 500)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (! $s->hasTable('survey_its_observations')) {
            $s->create('survey_its_observations', function (Blueprint $t) {
                $t->id();
                $t->string('its_source', 20);
                $t->unsignedBigInteger('survey_its_id')->nullable()->index();
                $t->unsignedBigInteger('cadastral_survey_job_id')->nullable()->index();
                $t->string('its_number', 40)->nullable();
                $t->string('file_number', 100)->nullable();
                $t->unsignedInteger('sort_order')->default(0);
                $t->string('from_station', 50);
                $t->string('to_station', 50);
                $t->decimal('observed_bearing', 12, 6);      // decimal degrees, entered as D M S
                $t->decimal('observed_distance', 12, 3);     // metres
                $t->date('observed_on')->nullable();
                $t->string('observer', 255)->nullable();
                $t->string('source', 20)->default('desktop'); // desktop | mobile
                $t->string('remarks', 500)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('survey_its_observations');
        Schema::connection('sqlsrv')->dropIfExists('survey_its_coordinates');
    }
};
