<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Item 1's sub-items both print; their bold words are the data (user, 2026-11-10): 1) "...a tie must be made to
 | control <proved to be in situ>", 2) "...two points of detail <Identifiable> on the topographic map", each a
 | dropdown with Other (specify). its_tie_method (a 1)/2) choice, added the same day, never filled) goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        $s->table('survey_its', function (Blueprint $t) use ($s) {
            if (! $s->hasColumn('survey_its', 'its_control_condition')) $t->string('its_control_condition', 150)->nullable();
            if (! $s->hasColumn('survey_its', 'its_detail_condition')) $t->string('its_detail_condition', 150)->nullable();
        });
        if ($s->hasColumn('survey_its', 'its_tie_method')) {
            $s->table('survey_its', fn (Blueprint $t) => $t->dropColumn('its_tie_method'));
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        if (! $s->hasColumn('survey_its', 'its_tie_method')) {
            $s->table('survey_its', fn (Blueprint $t) => $t->string('its_tie_method', 20)->nullable());
        }
        foreach (['its_control_condition', 'its_detail_condition'] as $c) {
            if ($s->hasColumn('survey_its', $c)) $s->table('survey_its', fn (Blueprint $t) => $t->dropColumn($c));
        }
    }
};
