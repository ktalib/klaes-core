<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The SuA Confirmation Sheet quotes the unit's Parcel Number where the
 * conversion sheet quotes a plot number. It is answered once, on the SuA File
 * Number Commissioning form, and carried onto the sheet.
 *
 * The institution the sheet is addressed to is NOT captured here: it is a
 * per-letter choice made on the print card and stored on
 * conversion_applications. Allocation Source / Entity on the commissioning form
 * are unchanged, and are what that card's suggestion is derived from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('st_file_numbers', 'parcel_no')) {
                $table->string('parcel_no', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('st_file_numbers', 'parcel_no')) {
                $table->dropColumn('parcel_no');
            }
        });
    }
};
