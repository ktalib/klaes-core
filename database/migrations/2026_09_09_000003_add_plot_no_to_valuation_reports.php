<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plot No on a valuation report.
 *
 * valuation_reports already carries the rest of the address in parts —
 * property_no (the number on the building), street_name, estate_quarters,
 * town_city and lga — but had nowhere to record the survey parcel number.
 * Line c) of the printed template is labelled "Plot/Property No" and prints
 * property_no, which is why the two have been conflated up to now; this column
 * separates them so the register can list a house number and a plot number side
 * by side.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('valuation_reports')) {
            return;
        }
        if (Schema::connection('sqlsrv')->hasColumn('valuation_reports', 'plot_no')) {
            return;
        }

        Schema::connection('sqlsrv')->table('valuation_reports', function (Blueprint $table) {
            $table->string('plot_no', 100)->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('valuation_reports')) {
            return;
        }
        if (!Schema::connection('sqlsrv')->hasColumn('valuation_reports', 'plot_no')) {
            return;
        }

        Schema::connection('sqlsrv')->table('valuation_reports', function (Blueprint $table) {
            $table->dropColumn('plot_no');
        });
    }
};
