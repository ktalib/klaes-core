<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chunked (multi-batch) commissioning for parcel-update applications.
 *
 * The file-number generator's batch mode caps at 200 files per run, so a 500-plot
 * subdivision has to be commissioned across several runs (200 + 200 + 100). These
 * columns are what makes a run resumable: how many fragments have been minted so
 * far, and the log of the chunks that minted them. The application only flips to
 * 'commissioned' once commissioned_count reaches num_plots.
 */
return new class extends Migration {
    // Subdivision only for now: it is the 1 -> N flow whose N can exceed the
    // generator's 200-file batch cap. Separation caps at 50 and merger is N -> 1,
    // so neither needs chunk accounting yet.
    private array $tables = [
        'plot_subdivision_applications',
    ];

    public function up()
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::connection('sqlsrv')->hasTable($tableName)) {
                continue;
            }

            Schema::connection('sqlsrv')->table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::connection('sqlsrv')->hasColumn($tableName, 'commissioned_count')) {
                    $table->integer('commissioned_count')->default(0)->nullable();
                }
                // JSON array of {batch, quantity, first_file, last_file, files, at, by}
                if (!Schema::connection('sqlsrv')->hasColumn($tableName, 'commissioned_batches')) {
                    $table->text('commissioned_batches')->nullable();
                }
                if (!Schema::connection('sqlsrv')->hasColumn($tableName, 'commissioning_completed_at')) {
                    $table->timestamp('commissioning_completed_at')->nullable();
                }
            });
        }

        // Applications commissioned before chunking existed were single-shot: whatever
        // num_plots said was minted in one run. Seed their counter so the progress
        // column does not read 0 / N for work that is actually finished.
        foreach ($this->tables as $tableName) {
            if (!Schema::connection('sqlsrv')->hasTable($tableName)) {
                continue;
            }
            \Illuminate\Support\Facades\DB::connection('sqlsrv')->table($tableName)
                ->where('status', 'commissioned')
                ->whereNull('commissioned_batches')
                ->update(['commissioned_count' => \Illuminate\Support\Facades\DB::raw('ISNULL(num_plots, 0)')]);
        }
    }

    public function down()
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::connection('sqlsrv')->hasTable($tableName)) {
                continue;
            }
            Schema::connection('sqlsrv')->table($tableName, function (Blueprint $table) {
                $table->dropColumn(['commissioned_count', 'commissioned_batches', 'commissioning_completed_at']);
            });
        }
    }
};
