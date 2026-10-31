<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Survey field apps (user, 2026-10-30): three Flutter WebView apps on the Survey
 * mobile pages: SCF (compensation fieldwork), Computation & Field Observations,
 * and Plot Confirmation.
 *
 *   survey_its_coordinates      beacons captured on the phone: client_uuid (safe
 *                               resend), the phone's GPS fix, source
 *   survey_op_showings          a showing can be of a Deeds OP (instrument_capture /
 *                               pra), not only a Survey OP record; GPS, allottee
 *                               present, client_uuid. survey_op_record_id becomes
 *                               nullable (the table had no rows when this ran).
 *   survey_op_showing_photos    where and when each photograph was taken
 *   survey_plot_checks (+photos) Plot Confirmation's ground check of a plot against
 *                               its layout and the Beacon Register
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasColumn('survey_its_coordinates', 'client_uuid')) {
            $schema->table('survey_its_coordinates', function (Blueprint $t) {
                $t->string('client_uuid', 40)->nullable()->index();
                $t->decimal('gps_latitude', 10, 7)->nullable();
                $t->decimal('gps_longitude', 10, 7)->nullable();
                $t->decimal('gps_accuracy_m', 8, 2)->nullable();
                $t->string('source', 20)->nullable();
            });
        }

        if (! $schema->hasColumn('survey_op_showings', 'op_source')) {
            $schema->table('survey_op_showings', function (Blueprint $t) {
                $t->string('op_source', 20)->nullable();
                $t->unsignedBigInteger('op_source_id')->nullable();
                $t->string('op_serial', 50)->nullable();
                $t->string('allottee_name', 255)->nullable();
                $t->string('plot_no', 100)->nullable();
                $t->string('district', 150)->nullable();
                $t->string('lga', 150)->nullable();
                $t->string('location', 500)->nullable();
                $t->boolean('allottee_present')->nullable();
                $t->decimal('latitude', 10, 7)->nullable();
                $t->decimal('longitude', 10, 7)->nullable();
                $t->decimal('accuracy_m', 8, 2)->nullable();
                $t->string('client_uuid', 40)->nullable()->index();
                $t->string('source', 20)->nullable();
                $t->index(['op_source', 'op_source_id']);
            });

            // Empty table: drop the index, relax the column, put the index back.
            DB::connection('sqlsrv')->statement('DROP INDEX survey_op_showings_survey_op_record_id_index ON survey_op_showings');
            DB::connection('sqlsrv')->statement('ALTER TABLE survey_op_showings ALTER COLUMN survey_op_record_id BIGINT NULL');
            DB::connection('sqlsrv')->statement('CREATE INDEX survey_op_showings_survey_op_record_id_index ON survey_op_showings (survey_op_record_id)');
        }

        if (! $schema->hasColumn('survey_op_showing_photos', 'taken_at')) {
            $schema->table('survey_op_showing_photos', function (Blueprint $t) {
                $t->decimal('latitude', 10, 7)->nullable();
                $t->decimal('longitude', 10, 7)->nullable();
                $t->decimal('accuracy_m', 8, 2)->nullable();
                $t->dateTime('taken_at')->nullable();
                $t->string('caption', 255)->nullable();
                $t->string('client_uuid', 40)->nullable()->index();
            });
        }

        if (! $schema->hasTable('survey_plot_checks')) {
            $schema->create('survey_plot_checks', function (Blueprint $t) {
                $t->id();
                $t->string('check_ref', 30)->nullable()->index();
                $t->string('target_type', 20);                 // op | layout
                $t->string('op_source', 20)->nullable();
                $t->unsignedBigInteger('op_source_id')->nullable();
                $t->string('op_serial', 50)->nullable();
                $t->unsignedBigInteger('survey_lpkn_id')->nullable();
                $t->string('layout_no', 100)->nullable();
                $t->string('holder_name', 255)->nullable();
                $t->string('plot_no', 100)->nullable();
                $t->string('district', 150)->nullable();
                $t->string('lga', 150)->nullable();
                $t->string('location', 500)->nullable();
                $t->date('checked_on');
                $t->string('checked_at', 5)->nullable();
                $t->unsignedBigInteger('surveyors_list_id')->nullable();
                $t->string('surveyor_name', 255);
                $t->decimal('latitude', 10, 7)->nullable();
                $t->decimal('longitude', 10, 7)->nullable();
                $t->decimal('accuracy_m', 8, 2)->nullable();
                $t->string('beacons_found', 1000)->nullable();
                $t->text('beacon_check')->nullable();          // JSON, from the Beacon Register at save
                $t->string('result', 20);                      // Confirmed | Discrepancy
                $t->string('discrepancy', 60)->nullable();
                $t->string('discrepancy_other', 255)->nullable();
                $t->string('notes', 2000)->nullable();
                $t->string('client_uuid', 40)->nullable()->index();
                $t->string('source', 20)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['op_source', 'op_source_id']);
            });
        }

        if (! $schema->hasTable('survey_plot_check_photos')) {
            $schema->create('survey_plot_check_photos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('survey_plot_check_id')->index();
                $t->string('path', 500);
                $t->string('original_name', 255)->nullable();
                $t->string('mime', 100)->nullable();
                $t->unsignedBigInteger('size')->nullable();
                $t->decimal('latitude', 10, 7)->nullable();
                $t->decimal('longitude', 10, 7)->nullable();
                $t->decimal('accuracy_m', 8, 2)->nullable();
                $t->dateTime('taken_at')->nullable();
                $t->string('caption', 255)->nullable();
                $t->string('client_uuid', 40)->nullable()->index();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->dropIfExists('survey_plot_check_photos');
        $schema->dropIfExists('survey_plot_checks');

        if ($schema->hasColumn('survey_op_showing_photos', 'taken_at')) {
            $schema->table('survey_op_showing_photos', function (Blueprint $t) {
                $t->dropIndex(['client_uuid']);
                $t->dropColumn(['latitude', 'longitude', 'accuracy_m', 'taken_at', 'caption', 'client_uuid']);
            });
        }
        if ($schema->hasColumn('survey_op_showings', 'op_source')) {
            $schema->table('survey_op_showings', function (Blueprint $t) {
                $t->dropIndex(['client_uuid']);
                $t->dropIndex(['op_source', 'op_source_id']);
                $t->dropColumn(['op_source', 'op_source_id', 'op_serial', 'allottee_name', 'plot_no', 'district', 'lga', 'location',
                                'allottee_present', 'latitude', 'longitude', 'accuracy_m', 'client_uuid', 'source']);
            });
        }
        if ($schema->hasColumn('survey_its_coordinates', 'client_uuid')) {
            $schema->table('survey_its_coordinates', function (Blueprint $t) {
                $t->dropIndex(['client_uuid']);
                $t->dropColumn(['client_uuid', 'gps_latitude', 'gps_longitude', 'gps_accuracy_m', 'source']);
            });
        }
    }
};
