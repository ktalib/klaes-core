<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Street Name on the MLS file-number generator's Location Details card.
 *
 * Captured beside the TP Number, as the street the parcel sits on. It is not the
 * District (a whole quarter) nor the Location (the line assembled from the parts),
 * so it gets its own column rather than being folded into either.
 *
 * Both tables the generator writes get it — mls_file_no (the register) and
 * fileNumber (the compatibility mirror) — because the records list unions them
 * and reads whichever row exists for a file. file_indexings already carries a
 * street_name column and is left alone.
 *
 * Mirrors 2026_09_09_000002_add_house_no_to_file_number_tables.
 */
return new class extends Migration {
    private array $tables = ['mls_file_no', 'fileNumber'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }
            if (Schema::connection('sqlsrv')->hasColumn($table, 'street_name')) {
                continue;
            }

            Schema::connection('sqlsrv')->table($table, function (Blueprint $blueprint) {
                $blueprint->string('street_name', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }
            if (!Schema::connection('sqlsrv')->hasColumn($table, 'street_name')) {
                continue;
            }

            Schema::connection('sqlsrv')->table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('street_name');
            });
        }
    }
};
