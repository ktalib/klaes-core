<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each SOURCE PLOT of a merger actually is.
 *
 * A plot merger is captured one card per source plot, and each card asks for that
 * plot's house no, street, district, LGA and state — because the plots being merged
 * are separate parcels with separate file numbers, not one parcel described twice.
 * The capture then threw five of those six answers away: the child row kept only the
 * plot number (in `plot_number`), the source file no, its title and the size, and the
 * location went into location_details_json and no further.
 *
 * Nobody noticed while the only thing that read a merger back was the printed memo,
 * which states the parcels by file number. Re-opening an application to add a plot is
 * what made it visible: the cards for the plots already captured came back blank, and
 * a blank card cannot be told from one nobody has filled in yet.
 *
 * `plot_number` already holds the plot no, so it is not repeated here — and the
 * location is district + LGA + state, never the plot number, which is why the two
 * are kept in separate columns rather than one address line.
 *
 * Nullable throughout, and nothing is backfilled: the rows written before this had no
 * location recorded, and inventing one from the parent application would assert that
 * every source plot sits where the merged parcel does, which is the very thing a
 * merger of separate parcels does not guarantee. They stay empty, which is true.
 *
 * Widths mirror the parent tables and the controllers' own validation (house no and
 * state 100, the rest 255) so a value that passes validation always fits.
 *
 * Shared table: subdivision, separation and extension fragments live here too and
 * simply leave these columns null — they hang off ONE mother plot whose location is
 * on the application row.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /** The columns, and the width each is validated at. */
    private const COLUMNS = [
        'house_no'    => 100,
        'street_name' => 255,
        'district'    => 255,
        'lga'         => 255,
        'state'       => 100,
    ];

    public function up(): void
    {
        $missing = array_filter(
            self::COLUMNS,
            fn ($width, $name) => !Schema::connection('sqlsrv')->hasColumn('plot_application_sizes', $name),
            ARRAY_FILTER_USE_BOTH
        );

        if (empty($missing)) {
            echo '  plot_application_sizes already carries the source-location columns; nothing to do.' . PHP_EOL;
            return;
        }

        Schema::connection('sqlsrv')->table('plot_application_sizes', function (Blueprint $table) use ($missing) {
            foreach ($missing as $name => $width) {
                $table->string($name, $width)->nullable();
            }
        });

        echo '  Added to plot_application_sizes: ' . implode(', ', array_keys($missing)) . '.' . PHP_EOL;
    }

    public function down(): void
    {
        $present = array_keys(array_filter(
            self::COLUMNS,
            fn ($width, $name) => Schema::connection('sqlsrv')->hasColumn('plot_application_sizes', $name),
            ARRAY_FILTER_USE_BOTH
        ));

        if (empty($present)) {
            return;
        }

        Schema::connection('sqlsrv')->table('plot_application_sizes', function (Blueprint $table) use ($present) {
            $table->dropColumn($present);
        });
    }
};
