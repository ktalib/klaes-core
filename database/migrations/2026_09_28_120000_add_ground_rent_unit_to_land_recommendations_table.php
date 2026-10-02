<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The unit the ground rent is charged by - per square metre, per hectare, or
 * whatever the grant actually says.
 *
 * Printed straight after the figure on the Recommendation and the RofO
 * ("Ground Rent N5,000.00 Per Square Meters"), so it is stored as the literal
 * words that appear on the letter rather than a code. "Other" is resolved to the
 * text the officer typed before it reaches this column: nothing downstream should
 * have to know which of the fixed choices it came from.
 *
 * Backfill
 * --------
 * Every recommendation keyed before this column existed was charged per square
 * metre - that is what the letters issued off them say - so they are stamped with
 * it here rather than printing a bare figure where every other record now names
 * its unit. Only rows that actually carry a rent are touched: a record with no
 * ground rent has no unit to state, and stamping one would put
 * "0.00 Per Square Meters" on a letter that has never said it.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    private const LEGACY_UNIT = 'Per Square Meters';

    public function up(): void
    {
        $fresh = !Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'ground_rent_unit');

        if ($fresh) {
            Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
                $table->string('ground_rent_unit', 100)->nullable();
            });
        }

        // Safe to re-run: only rows still holding no unit are stamped, so a second
        // run cannot overwrite a unit an officer has since chosen.
        $stamped = DB::connection('sqlsrv')->table('land_recommendations')
            ->whereNull('ground_rent_unit')
            ->whereNotNull('ground_rent')
            ->update(['ground_rent_unit' => self::LEGACY_UNIT]);

        echo "  Stamped {$stamped} existing recommendation(s) as '" . self::LEGACY_UNIT . "'." . PHP_EOL;
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->dropColumn('ground_rent_unit');
        });
    }
};
