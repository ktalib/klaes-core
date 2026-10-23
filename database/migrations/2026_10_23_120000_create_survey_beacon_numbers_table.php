<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Beacon Register: one row per beacon number (user, 2026-10-23).
 *
 * An issue line is entered as a range (C9302B – C9306B); saving it expands the
 * range into one row per number here, each belonging to that line's surveyor,
 * date, location and payment (read through survey_beacon_register_id).
 *
 * A beacon number is issued once: the filtered unique index enforces it for
 * live rows. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if ($s->hasTable('survey_beacon_numbers')) {
            return;
        }

        $s->create('survey_beacon_numbers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('survey_beacon_register_id');
            $t->string('beacon_no', 30);          // e.g. C9303B
            $t->string('beacon_series', 30);      // e.g. C|B
            $t->integer('beacon_seq');            // e.g. 9303
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->index('survey_beacon_register_id', 'ix_beacon_numbers_register');
            $t->index(['beacon_series', 'beacon_seq'], 'ix_beacon_numbers_series_seq');
        });

        DB::connection('sqlsrv')->statement(
            'CREATE UNIQUE INDEX ux_survey_beacon_numbers_live ON survey_beacon_numbers (beacon_series, beacon_seq) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('survey_beacon_numbers');
    }
};
