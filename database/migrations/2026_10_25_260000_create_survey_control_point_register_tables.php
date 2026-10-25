<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Survey Examination/Verification > Control Points (user transcript, 2026-10-25:
 * "we already have this beacon register, just copy it and call this one control
 * points"). Same shape as the Beacon Register's three tables as they stand after
 * their 2026-10-22..23 migrations, kept separate so control point numbers and
 * beacon numbers never collide. Column names match the Beacon Register's so the
 * copied code reads the same; only the screens say "control point".
 *
 * Additive only: three new tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        $db = DB::connection('sqlsrv');

        if (! $s->hasTable('survey_control_point_register')) {
            $s->create('survey_control_point_register', function (Blueprint $t) {
                $t->id();
                $t->string('register_no', 30)->nullable();
                $t->date('issue_date');
                $t->tinyInteger('quarter');
                $t->tinyInteger('month');
                $t->smallInteger('year');
                $t->string('beacon_from', 30);                    // first control point number
                $t->string('beacon_to', 30)->nullable();
                $t->string('beacon_series', 30)->nullable();
                $t->integer('beacon_start_no')->nullable();
                $t->integer('beacon_end_no')->nullable();
                $t->integer('total_issued');
                $t->string('plan_no', 100)->nullable();
                $t->string('surveyor_name', 255)->nullable();
                $t->string('surveyor_registration', 50)->nullable();
                $t->string('location', 255)->nullable();
                $t->string('payment_mode', 30)->nullable();
                $t->string('payment_mode_other', 100)->nullable();
                $t->string('teller_no', 100)->nullable();
                $t->decimal('amount', 15, 2)->nullable();
                $t->date('date_received')->nullable();
                $t->string('received_by', 255)->nullable();
                $t->string('remark', 1000)->nullable();
                $t->unsignedBigInteger('reviewed_by')->nullable();
                $t->dateTime('reviewed_at')->nullable();
                $t->unsignedBigInteger('audited_by')->nullable();
                $t->dateTime('audited_at')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->string('surveyor_source', 10)->nullable();
                $t->unsignedBigInteger('surveyors_list_id')->nullable();
                $t->unsignedBigInteger('cadastral_surveyor_id')->nullable();
                $t->string('prop_house', 100)->nullable();
                $t->string('prop_plot', 100)->nullable();
                $t->string('prop_street', 500)->nullable();
                $t->string('prop_street_other', 255)->nullable();
                $t->string('prop_district', 500)->nullable();
                $t->string('prop_district_other', 255)->nullable();
                $t->string('prop_lga', 500)->nullable();
                $t->string('prop_state', 255)->nullable()->default('Kano');
                $t->string('layout_type', 20)->nullable();
                $t->string('file_number', 100)->nullable();
                $t->decimal('coord_x', 18, 4)->nullable();   // Easting
                $t->decimal('coord_y', 18, 4)->nullable();   // Northing

                $t->index('issue_date', 'ix_cp_register_issue_date');
                $t->index(['year', 'quarter', 'month'], 'ix_cp_register_period');
                $t->index(['beacon_series', 'beacon_start_no', 'beacon_end_no'], 'ix_cp_range');
                $t->index('surveyor_name', 'ix_cp_register_surveyor');
                $t->index('layout_type', 'ix_cp_register_layout_type');
                $t->index('file_number', 'ix_cp_register_file_number');
            });
        }

        if (! $s->hasTable('survey_control_point_numbers')) {
            $s->create('survey_control_point_numbers', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('survey_control_point_register_id');
                $t->string('beacon_no', 30);
                $t->string('beacon_series', 30);
                $t->integer('beacon_seq');
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();

                $t->index('survey_control_point_register_id', 'ix_cp_numbers_register');
                $t->index(['beacon_series', 'beacon_seq'], 'ix_cp_numbers_series_seq');
            });
            // A control point number is issued once.
            $db->statement('CREATE UNIQUE INDEX ux_survey_cp_numbers_live ON survey_control_point_numbers (beacon_series, beacon_seq) WHERE deleted_at IS NULL');
        }

        if (! $s->hasTable('survey_control_point_register_files')) {
            $s->create('survey_control_point_register_files', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('survey_control_point_register_id');
                $t->string('file_number', 100);
                $t->smallInteger('sort_order')->default(0);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();

                $t->index('survey_control_point_register_id', 'ix_cp_files_register');
                $t->index('file_number', 'ix_cp_files_file_number');
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        $s->dropIfExists('survey_control_point_register_files');
        $s->dropIfExists('survey_control_point_numbers');
        $s->dropIfExists('survey_control_point_register');
    }
};
