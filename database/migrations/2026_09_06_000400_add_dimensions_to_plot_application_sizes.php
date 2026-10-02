<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Somewhere to keep a parcel's sides, beside the area they give.
 *
 * The parcel-update forms now ask for plot sizes the way the Duplex parcel
 * update does — the officer types the sides off the survey plan and the area is
 * derived — so the sides themselves need a home. plot_size stays the authority
 * everything downstream calculates with (ParcelSizeSummary prints the memo from
 * it, in m²); this column records what was actually measured, which is how the
 * Ministry's memo states a parcel: "60 x 21 x 46 x 21 x 42.71".
 *
 * A string, not a pair of decimals: a parcel is a polygon of any number of
 * sides, and the memo prints the run verbatim.
 *
 * Nullable, because every existing row was captured as a single figure with no
 * sides behind it, and back-filling one would be inventing data.
 *
 * Pinned to sqlsrv: config('database.default') is mysql, so a bare
 * Schema::table() here would look for the table in the wrong database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('plot_application_sizes')) {
            return;
        }

        if (Schema::connection('sqlsrv')->hasColumn('plot_application_sizes', 'dimensions')) {
            return;
        }

        Schema::connection('sqlsrv')->table('plot_application_sizes', function (Blueprint $table) {
            $table->string('dimensions', 255)->nullable()->after('plot_size');
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('plot_application_sizes', 'dimensions')) {
            return;
        }

        Schema::connection('sqlsrv')->table('plot_application_sizes', function (Blueprint $table) {
            $table->dropColumn('dimensions');
        });
    }
};
