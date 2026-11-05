<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KSSGOF registration asks for an address through the address builder (user, 2026-11-05). The same eight addr_*
 * columns as cadastral_surveyors, so approval can hand them straight to a new directory row.
 */
return new class extends Migration
{
    private const COLUMNS = ['addr_house', 'addr_plot', 'addr_street', 'addr_street_other', 'addr_district', 'addr_district_other', 'addr_lga', 'addr_state'];

    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('surveyor_portal_accounts', 'addr_district')) return;

        Schema::connection('sqlsrv')->table('surveyor_portal_accounts', function (Blueprint $t) {
            foreach (self::COLUMNS as $c) {
                $t->string($c, 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection('sqlsrv')->hasColumn('surveyor_portal_accounts', 'addr_district')) return;

        Schema::connection('sqlsrv')->table('surveyor_portal_accounts', function (Blueprint $t) {
            $t->dropColumn(self::COLUMNS);
        });
    }
};
