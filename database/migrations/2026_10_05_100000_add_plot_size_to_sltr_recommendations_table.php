<?php

use App\Support\SltrPlotSize;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The plot size (m²) an SLTR ground rent is charged on.
 *
 * ground_rent is the RATE; the amount owed is rate x plot size, and until now
 * the record had no size to multiply by.
 *
 * Backfill
 * --------
 * Every recommendation whose file number has an indexed plot size takes it, read
 * as square metres by SltrPlotSize::parse(). Rows the indexing has no usable size
 * for stay NULL for the officer to key in.
 *
 * Safe to re-run: only rows still holding no size are filled.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('sltr_recommendations', 'plot_size')) {
            Schema::connection('sqlsrv')->table('sltr_recommendations', function (Blueprint $table) {
                $table->decimal('plot_size', 18, 2)->nullable();
            });
        }

        $filled = 0;
        $rows = DB::connection('sqlsrv')->table('sltr_recommendations')
            ->whereNull('plot_size')
            ->whereNotNull('sltr_number')
            ->get(['id', 'sltr_number']);

        foreach ($rows as $row) {
            $sqm = SltrPlotSize::fromIndexing($row->sltr_number);
            if ($sqm === null) {
                continue;
            }
            $filled += DB::connection('sqlsrv')->table('sltr_recommendations')
                ->where('id', $row->id)->whereNull('plot_size')
                ->update(['plot_size' => $sqm]);
        }

        echo "  Backfilled plot size on {$filled} of {$rows->count()} SLTR recommendation(s) from file indexing." . PHP_EOL;
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('sltr_recommendations', function (Blueprint $table) {
            $table->dropColumn('plot_size');
        });
    }
};
