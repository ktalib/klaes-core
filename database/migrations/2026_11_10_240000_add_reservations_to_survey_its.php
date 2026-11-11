<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | The Public I to S on the Survey Department's form (user, 2026-11-10: the template form replaces Public's): items 2
 | and 3, the road and rail reservation, as on the Private one (cadastral_survey_jobs). Additive, nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        $s->table('survey_its', function (Blueprint $t) use ($s) {
            if (! $s->hasColumn('survey_its', 'its_road_reservation')) $t->string('its_road_reservation', 100)->nullable();
            if (! $s->hasColumn('survey_its', 'its_rail_reservation')) $t->string('its_rail_reservation', 100)->nullable();
        });
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        foreach (['its_road_reservation', 'its_rail_reservation'] as $c) {
            if ($s->hasColumn('survey_its', $c)) $s->table('survey_its', fn (Blueprint $t) => $t->dropColumn($c));
        }
    }
};
