<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | The I to S's item 1 sub-items are chosen per job (user, 2026-11-10: "the 1 and 2 are actually data driven"):
 | 'control' = 1) tie to Cadastral Control proved in situ; 'detail' = 2) no tie within 3 days, tie to two points of
 | detail on the topographic map. Null prints both, as the paper form. Additive, nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if (! $s->hasColumn('survey_its', 'its_tie_method')) {
            $s->table('survey_its', fn (Blueprint $t) => $t->string('its_tie_method', 20)->nullable());
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        if ($s->hasColumn('survey_its', 'its_tie_method')) {
            $s->table('survey_its', fn (Blueprint $t) => $t->dropColumn('its_tie_method'));
        }
    }
};
