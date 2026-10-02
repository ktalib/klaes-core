<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * House Number on the Deeds Bill Balance address builder.
 *
 * The applicant block and the property block each already carry a plot number,
 * street, district, LGA and state (see the 2026_04_23 address-components
 * migration). A house number is a separate thing from a plot number — the plot
 * is the survey parcel, the house number is what is painted on the building —
 * so it gets its own column on both sides rather than overloading plot_number.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('deeds_bill_balances_metadata')) {
            return;
        }

        Schema::connection('sqlsrv')->table('deeds_bill_balances_metadata', function (Blueprint $table) {
            foreach (['app_house_number', 'loc_house_number'] as $name) {
                if (!Schema::connection('sqlsrv')->hasColumn('deeds_bill_balances_metadata', $name)) {
                    $table->string($name, 50)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('deeds_bill_balances_metadata')) {
            return;
        }

        Schema::connection('sqlsrv')->table('deeds_bill_balances_metadata', function (Blueprint $table) {
            foreach (['app_house_number', 'loc_house_number'] as $name) {
                if (Schema::connection('sqlsrv')->hasColumn('deeds_bill_balances_metadata', $name)) {
                    $table->dropColumn($name);
                }
            }
        });
    }
};
