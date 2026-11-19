<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Same fix as 2026_11_16_140000 / 160000 for the SLTR copy of the JSI table: the
 * reservation list has names up to ~160 chars and Existing Reservation is typed
 * (user, 2026-11-19), but the columns were nvarchar(100). Widened to 1000 like
 * Conversion (written for 255 on 2026-11-16, held, never run). Widening only.
 */
return new class extends Migration
{
    public function up()
    {
        DB::connection('sqlsrv')->statement('ALTER TABLE sltr_joint_site_inspection_reports ALTER COLUMN existing_road_reservation NVARCHAR(1000) NULL');
        DB::connection('sqlsrv')->statement('ALTER TABLE sltr_joint_site_inspection_reports ALTER COLUMN recommended_road_reservation NVARCHAR(1000) NULL');
    }

    public function down()
    {
        // Narrowing back to 100 would cut saved values; left as a no-op on purpose.
    }
};
