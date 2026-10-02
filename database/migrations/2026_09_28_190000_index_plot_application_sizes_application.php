<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An index for the only way this table is ever read.
 *
 * plot_application_sizes carried nothing but its clustered primary key on `id`, so every
 * lookup of one application's children was a full scan. At 951 rows that is invisible,
 * which is why it has never been noticed — but subdivision capture has just been raised
 * from 600 plots to 1000, and a handful of large layouts multiply this table by an order
 * of magnitude. Ten 1000-plot subdivisions alone are ten thousand rows, scanned in full
 * every time a record is opened or a memo is printed.
 *
 * The column pair is the one PlotSubdivisionApplication::plotSizes() actually filters on:
 *
 *     hasMany(PlotApplicationSize::class, 'application_id')->where('application_type', 'subdivision')
 *
 * application_id leads because it is the selective half; application_type only separates
 * subdivision rows from merger/separation rows sharing the table.
 *
 * Pinned to sqlsrv: config('database.default') is mysql, so a bare Schema::table() here
 * would look for the table in the wrong database.
 *
 * Adding an index does not read, rewrite or delete a single row of existing data.
 */
return new class extends Migration
{
    private const INDEX = 'plot_application_sizes_application_id_type_index';

    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('plot_application_sizes')) {
            return;
        }

        if ($this->indexExists()) {
            return;
        }

        Schema::connection('sqlsrv')->table('plot_application_sizes', function (Blueprint $table) {
            $table->index(['application_id', 'application_type'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (!$this->indexExists()) {
            return;
        }

        Schema::connection('sqlsrv')->table('plot_application_sizes', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }

    /** Asked of sys.indexes directly; Schema has no hasIndex() on this Laravel version. */
    private function indexExists(): bool
    {
        return !empty(Schema::connection('sqlsrv')->getConnection()->select(
            'SELECT 1 FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?)',
            [self::INDEX, 'plot_application_sizes']
        ));
    }
};
