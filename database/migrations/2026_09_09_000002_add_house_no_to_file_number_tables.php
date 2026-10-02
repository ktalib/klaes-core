<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * House No on the MLS file-number generator's Location Details card.
 *
 * A house number is not a plot number: the plot is the survey parcel, the house
 * number is what is painted on the building, and a parcel can carry either, both
 * or neither. It therefore gets its own column rather than being folded into
 * plot_no.
 *
 * Both tables the generator writes get it — mls_file_no (the register) and
 * fileNumber (the compatibility mirror) — because the records list unions them
 * and reads whichever row exists for a file.
 */
return new class extends Migration {
    private array $tables = ['mls_file_no', 'fileNumber'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }
            if (Schema::connection('sqlsrv')->hasColumn($table, 'house_no')) {
                continue;
            }

            Schema::connection('sqlsrv')->table($table, function (Blueprint $blueprint) {
                $blueprint->string('house_no', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }
            if (!Schema::connection('sqlsrv')->hasColumn($table, 'house_no')) {
                continue;
            }

            Schema::connection('sqlsrv')->table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('house_no');
            });
        }
    }
};
