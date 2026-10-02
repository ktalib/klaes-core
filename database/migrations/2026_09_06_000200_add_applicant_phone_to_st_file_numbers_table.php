<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Somewhere to keep the ST applicant's phone number.
 *
 * st_file_numbers records applicant_title, first_name, surname, corporate_name
 * and rc_number, but no way to contact the person any of that describes. The ST
 * commissioning SMS needs one, and the alternatives were all worse: the ST
 * commissioning form does not go through mother_applications for a Direct
 * Allocation, and file_indexings is a shared-number minefield (one number in it
 * sits on 1,211 files).
 *
 * Nullable, because every existing row has no number and back-filling one would
 * be inventing data.
 *
 * Pinned to sqlsrv: config('database.default') is mysql, so a bare
 * Schema::table() here would look for the table in the wrong database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('st_file_numbers')) {
            return;
        }

        if (Schema::connection('sqlsrv')->hasColumn('st_file_numbers', 'applicant_phone')) {
            return;
        }

        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            $table->string('applicant_phone', 100)->nullable()->after('rc_number');
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('st_file_numbers', 'applicant_phone')) {
            return;
        }

        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            $table->dropColumn('applicant_phone');
        });
    }
};
