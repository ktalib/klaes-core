<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beacon Register: Layout Type, Government or Private layout, chosen first on
 * the form (user, 2026-10-23). Nullable so lines entered before it stay valid;
 * the form requires it on every save. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if ($s->hasColumn('survey_beacon_register', 'layout_type')) {
            return;
        }

        $s->table('survey_beacon_register', function (Blueprint $t) {
            $t->string('layout_type', 20)->nullable();   // Government | Private
            $t->index('layout_type');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('survey_beacon_register', function (Blueprint $t) {
            $t->dropIndex(['layout_type']);
            $t->dropColumn('layout_type');
        });
    }
};
