<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crossed-out files (user, 2026-11-06): a deleted file stays on its table, struck through
 * and read-only, with is_deleted = 1, and its number is free to use again.
 *
 * deleted_numbers holds the numbers the row carried (JSON). They are moved off the live
 * columns so the many reads and joins by file number treat the row as gone, and so the
 * unique rules on file_indexings.file_number and mls_file_no.full_file_number still hold.
 * pra.deleted_record_type keeps a deleted OSS row on its own tab, 'fc' or 'fefr'.
 *
 * Nullable columns only: nothing reads them until the code does, and no row changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['fileNumber', 'file_indexings', 'mls_file_no'] as $table) {
            if (! Schema::connection('sqlsrv')->hasColumn($table, 'deleted_numbers')) {
                Schema::connection('sqlsrv')->table($table, function (Blueprint $t) {
                    $t->string('deleted_numbers', 2000)->nullable();
                });
            }
        }

        if (! Schema::connection('sqlsrv')->hasColumn('pra', 'deleted_record_type')) {
            Schema::connection('sqlsrv')->table('pra', function (Blueprint $t) {
                $t->string('deleted_record_type', 10)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['fileNumber', 'file_indexings', 'mls_file_no'] as $table) {
            if (Schema::connection('sqlsrv')->hasColumn($table, 'deleted_numbers')) {
                Schema::connection('sqlsrv')->table($table, fn (Blueprint $t) => $t->dropColumn('deleted_numbers'));
            }
        }

        if (Schema::connection('sqlsrv')->hasColumn('pra', 'deleted_record_type')) {
            Schema::connection('sqlsrv')->table('pra', fn (Blueprint $t) => $t->dropColumn('deleted_record_type'));
        }
    }
};
