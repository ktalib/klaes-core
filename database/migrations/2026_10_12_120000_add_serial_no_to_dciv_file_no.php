<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free-text "Serial No" captured on DCIV commissioning. It is a separate
 * attribute of the file, NOT the serial_number that builds the file number
 * (DCIV-2026-506), so it is unvalidated and stored as entered.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::connection('sqlsrv')->hasColumn('dciv_file_no', 'serial_no')) {
            return;
        }

        Schema::connection('sqlsrv')->table('dciv_file_no', function (Blueprint $table) {
            $table->text('serial_no')->nullable();
        });
    }

    public function down()
    {
        if (Schema::connection('sqlsrv')->hasColumn('dciv_file_no', 'serial_no')) {
            Schema::connection('sqlsrv')->table('dciv_file_no', function (Blueprint $table) {
                $table->dropColumn('serial_no');
            });
        }
    }
};
