<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DCIV "Serial No" (a free-text attribute, not the serial that builds the file
 * number) is renamed dciv_file_no.serial_no -> dciv_serial_no, and carried onto
 * file_indexings as dciv_serial_no, because DCIV commissioning writes the file's
 * indexing row too. file_indexings already has its own unrelated serial_no.
 *
 * dciv_file_no.serial_no was added on 2026-10-12 and still held no values when
 * this was written, so the rename moves no data.
 */
return new class extends Migration
{
    public function up()
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('dciv_file_no', 'serial_no') && !$schema->hasColumn('dciv_file_no', 'dciv_serial_no')) {
            DB::connection('sqlsrv')->statement("EXEC sp_rename 'dbo.dciv_file_no.serial_no', 'dciv_serial_no', 'COLUMN'");
        }

        if (!$schema->hasColumn('file_indexings', 'dciv_serial_no')) {
            $schema->table('file_indexings', function (Blueprint $table) {
                $table->text('dciv_serial_no')->nullable();
            });
        }
    }

    public function down()
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('file_indexings', 'dciv_serial_no')) {
            $schema->table('file_indexings', function (Blueprint $table) {
                $table->dropColumn('dciv_serial_no');
            });
        }

        if ($schema->hasColumn('dciv_file_no', 'dciv_serial_no') && !$schema->hasColumn('dciv_file_no', 'serial_no')) {
            DB::connection('sqlsrv')->statement("EXEC sp_rename 'dbo.dciv_file_no.dciv_serial_no', 'serial_no', 'COLUMN'");
        }
    }
};
