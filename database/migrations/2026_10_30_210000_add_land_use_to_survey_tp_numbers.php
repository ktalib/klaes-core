<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TP Nos register: Land Use dropdown (user, 2026-10-30), same list as Private Layout plus "Other (specify)".
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('survey_tp_numbers', function (Blueprint $t) {
            $t->string('land_use', 40)->nullable()->after('layout_name');
            $t->string('land_use_other', 100)->nullable()->after('land_use');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('survey_tp_numbers', function (Blueprint $t) {
            $t->dropColumn(['land_use', 'land_use_other']);
        });
    }
};
