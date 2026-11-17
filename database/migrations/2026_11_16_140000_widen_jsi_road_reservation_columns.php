<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The reservation list (jsi_lut_reservations) holds names up to ~160 chars
 * (FEDERAL HIGHWAYS ...), but both columns were nvarchar(100), so saving the
 * Conversion JSI failed with "String or binary data would be truncated".
 * Widen to 255 to match the controller validation (max:255). Widening only.
 */
return new class extends Migration
{
    public function up()
    {
        DB::connection('sqlsrv')->statement('ALTER TABLE joint_site_inspection_reports ALTER COLUMN existing_road_reservation NVARCHAR(255) NULL');
        DB::connection('sqlsrv')->statement('ALTER TABLE joint_site_inspection_reports ALTER COLUMN recommended_road_reservation NVARCHAR(255) NULL');
    }

    public function down()
    {
        // Narrowing back to 100 would cut saved values; left as a no-op on purpose.
    }
};
