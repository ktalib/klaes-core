<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * User request 2026-11-16: allow up to 1,000 characters for the Conversion JSI
 * Existing / Recommended Reservation (was 255 after 2026_11_16_140000, 100 before).
 * Widening only; validation in PlanningRecommendationController matches (max:1000).
 */
return new class extends Migration
{
    public function up()
    {
        DB::connection('sqlsrv')->statement('ALTER TABLE joint_site_inspection_reports ALTER COLUMN existing_road_reservation NVARCHAR(1000) NULL');
        DB::connection('sqlsrv')->statement('ALTER TABLE joint_site_inspection_reports ALTER COLUMN recommended_road_reservation NVARCHAR(1000) NULL');
    }

    public function down()
    {
        // Narrowing would cut saved values; left as a no-op on purpose.
    }
};
