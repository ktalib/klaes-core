<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch Change of Purpose: how many plots the one file covers.
 *
 * A batch here is NOT several files. It is one Right of Occupancy whose holding
 * runs to several plots, all taking the new purpose together. The officer names
 * the file once and says how many plots it carries, and that count is the batch
 * size the whole way through - it is the number of file numbers commissioning
 * will mint off it.
 *
 * So there is one application row, as there always was, carrying a count. Null or
 * 1 is an ordinary single Change of Purpose and behaves exactly as before.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('change_of_purpose_applications', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('change_of_purpose_applications', 'plot_count')) {
                $table->integer('plot_count')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('change_of_purpose_applications', function (Blueprint $table) {
            $table->dropColumn('plot_count');
        });
    }
};
