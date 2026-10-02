<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\GroundRentUnit;

/**
 * The unit an SLTR ground rent is charged by, so the letter can say something
 * other than per square metre.
 *
 * Backfill
 * --------
 * Every SLTR recommendation keyed before this column existed was charged per
 * square metre, and unlike the Land letters this was not an assumption: the
 * print template said " per sq meter" in so many words, hard-coded after the
 * figure. Stamping the existing rows preserves exactly what those letters have
 * always read, while letting the hard-coding come out of the template.
 *
 * Only rows carrying a rent are touched - a record with no ground rent has no
 * unit to state.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('sltr_recommendations', 'ground_rent_unit')) {
            Schema::connection('sqlsrv')->table('sltr_recommendations', function (Blueprint $table) {
                $table->string('ground_rent_unit', 100)->nullable();
            });
        }

        // Safe to re-run: only rows still holding no unit are stamped, so a second
        // run cannot overwrite a unit an officer has since chosen.
        $stamped = DB::connection('sqlsrv')->table('sltr_recommendations')
            ->whereNull('ground_rent_unit')
            ->whereNotNull('ground_rent')
            ->update(['ground_rent_unit' => GroundRentUnit::LEGACY_DEFAULT]);

        echo "  Stamped {$stamped} existing SLTR recommendation(s) as '" . GroundRentUnit::LEGACY_DEFAULT . "'." . PHP_EOL;
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('sltr_recommendations', function (Blueprint $table) {
            $table->dropColumn('ground_rent_unit');
        });
    }
};
