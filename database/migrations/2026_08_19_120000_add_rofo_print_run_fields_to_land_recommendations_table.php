<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up()
    {
        // A batch print can be run as two passes — the Originals on the colour /
        // security stock, then the Duplicate and Triplicate on plain paper. Between
        // them the operator changes the paper, and that is where the run gets
        // abandoned: the tab is closed, the shift ends, the tray is empty. Until now
        // nothing recorded which half had been put on paper, so coming back meant
        // reprinting the Originals to reach the office copies.
        //
        // One timestamp per half is what lets the dialog resume: Originals stamped
        // and office copies still NULL is precisely "run 1 done, run 2 outstanding".
        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'rofo_originals_printed_at')) {
                $table->dateTime('rofo_originals_printed_at')->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'rofo_office_copies_printed_at')) {
                $table->dateTime('rofo_office_copies_printed_at')->nullable();
            }
            // 'all' | 'split' — how the last batch run was started. A half-finished
            // 'all' run is not resumable (its one pass carries all three copies), so
            // the resume prompt only offers itself for a run the operator split.
            if (!Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'rofo_print_run_mode')) {
                $table->string('rofo_print_run_mode', 20)->nullable();
            }
        });
    }

    public function down()
    {
        foreach (['rofo_originals_printed_at', 'rofo_office_copies_printed_at', 'rofo_print_run_mode'] as $column) {
            if (Schema::connection('sqlsrv')->hasColumn('land_recommendations', $column)) {
                Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
