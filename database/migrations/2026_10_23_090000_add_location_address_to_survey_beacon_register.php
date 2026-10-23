<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beacon Register: Location is now the standard address builder (user,
 * 2026-10-23), so it gets the prop_* group the other survey tables carry.
 * Street / district / LGA are multi-select, hence 500 like the widened
 * survey columns (2026_10_02_090000). `location` stays as the composed
 * snapshot. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if ($s->hasColumn('survey_beacon_register', 'prop_district')) {
            return;
        }

        $s->table('survey_beacon_register', function (Blueprint $t) {
            $t->string('prop_house', 100)->nullable();
            $t->string('prop_plot', 100)->nullable();
            $t->string('prop_street', 500)->nullable();
            $t->string('prop_street_other', 255)->nullable();
            $t->string('prop_district', 500)->nullable();
            $t->string('prop_district_other', 255)->nullable();
            $t->string('prop_lga', 500)->nullable();
            $t->string('prop_state', 255)->nullable()->default('Kano');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('survey_beacon_register', function (Blueprint $t) {
            $t->dropColumn(['prop_house', 'prop_plot', 'prop_street', 'prop_street_other',
                            'prop_district', 'prop_district_other', 'prop_lga', 'prop_state']);
        });
    }
};
