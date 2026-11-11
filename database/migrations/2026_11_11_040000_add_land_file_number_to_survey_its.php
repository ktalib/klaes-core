<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | The I to S's Land File (CR 10112026 call: "where is the attached file number? ... it has to be optional ... a land
 | file"; user 2026-11-11: a fourth field, "Land File", with the Select File Number button). file_number stays the one the
 | Computation File gives.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if (! $s->hasColumn('survey_its', 'land_file_number')) {
            $s->table('survey_its', fn (Blueprint $t) => $t->string('land_file_number', 100)->nullable());
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        if ($s->hasColumn('survey_its', 'land_file_number')) {
            $s->table('survey_its', fn (Blueprint $t) => $t->dropColumn('land_file_number'));
        }
    }
};
