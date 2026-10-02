<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Somewhere to keep the land-use sub-type a recommendation was priced from.
 *
 * The sub-type picker has always existed on the form, but only as a way of LOOKING UP the
 * processing fee -- the choice itself was never stored, so a saved recommendation showed a
 * fee with nothing recording which line of the schedule produced it.
 *
 * That gap matters most for "Other", where the schedule has no line at all and the officer
 * types the fee by hand. Without this column the one thing that explains an off-schedule
 * amount -- what the parcel actually is -- was discarded the moment the form was saved.
 *
 * Nullable, because every existing row was captured before the sub-type was recorded and
 * back-filling one would be inventing the reason for a fee somebody else agreed.
 *
 * Pinned to sqlsrv: config('database.default') is mysql, so a bare Schema::table() here
 * would look for the table in the wrong database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sltr_recommendations')) {
            return;
        }

        if (Schema::connection('sqlsrv')->hasColumn('sltr_recommendations', 'land_use_subtype')) {
            return;
        }

        Schema::connection('sqlsrv')->table('sltr_recommendations', function (Blueprint $table) {
            $table->string('land_use_subtype', 255)->nullable()->after('land_use');
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('sltr_recommendations', 'land_use_subtype')) {
            return;
        }

        Schema::connection('sqlsrv')->table('sltr_recommendations', function (Blueprint $table) {
            $table->dropColumn('land_use_subtype');
        });
    }
};
