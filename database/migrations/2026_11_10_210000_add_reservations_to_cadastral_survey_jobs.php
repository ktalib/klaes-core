<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Private I to S captured in Survey on the Survey Department's form (user, 2026-11-10, the paper template): items 2
 | and 3 give the road and the rail reservation, "parallel to and ___ from the centre line". Additive, nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        $s->table('cadastral_survey_jobs', function (Blueprint $t) use ($s) {
            if (! $s->hasColumn('cadastral_survey_jobs', 'its_road_reservation')) $t->string('its_road_reservation', 100)->nullable();
            if (! $s->hasColumn('cadastral_survey_jobs', 'its_rail_reservation')) $t->string('its_rail_reservation', 100)->nullable();
        });
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        foreach (['its_road_reservation', 'its_rail_reservation'] as $c) {
            if ($s->hasColumn('cadastral_survey_jobs', $c)) $s->table('cadastral_survey_jobs', fn (Blueprint $t) => $t->dropColumn($c));
        }
    }
};
