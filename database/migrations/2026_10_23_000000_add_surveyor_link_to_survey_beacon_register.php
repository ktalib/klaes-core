<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beacon Register: the surveyor is now picked from a list with an "Other"
 * option (user, 2026-10-22). Record which list the name came from.
 * surveyor_name stays the snapshot shown on the register. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if ($s->hasColumn('survey_beacon_register', 'surveyor_source')) {
            return;
        }

        $s->table('survey_beacon_register', function (Blueprint $t) {
            $t->string('surveyor_source', 10)->nullable();           // staff | surcon | other
            $t->unsignedBigInteger('surveyors_list_id')->nullable();
            $t->unsignedBigInteger('cadastral_surveyor_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('survey_beacon_register', function (Blueprint $t) {
            $t->dropColumn(['surveyor_source', 'surveyors_list_id', 'cadastral_surveyor_id']);
        });
    }
};
