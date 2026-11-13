<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Survey Plan Check (CR 11112026-2 item 8; user, 2026-11-12): the uploader picks the coordinate system the plan's
 | X / Y are in, so the checker opens the plan on the map in that system. EPSG code: 26391 / 26392 / 26393 (NTM West /
 | Mid / East Belt, Minna) or 32632 (UTM 32N, WGS 84). Plans uploaded before have none. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasColumn('survey_plan_uploads', 'coord_system')) {
            Schema::connection('sqlsrv')->table('survey_plan_uploads', function (Blueprint $t) {
                $t->string('coord_system', 10)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('survey_plan_uploads', 'coord_system')) {
            Schema::connection('sqlsrv')->table('survey_plan_uploads', function (Blueprint $t) {
                $t->dropColumn('coord_system');
            });
        }
    }
};
