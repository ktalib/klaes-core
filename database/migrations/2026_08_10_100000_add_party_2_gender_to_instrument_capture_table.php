<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gender of Party 2 on the instrument capture form (/instruments/create).
 *
 * Party 2 is the incoming interest — grantee, assignee, mortgagee, lessee — so
 * this is the gender of whoever the instrument transfers TO. For grant-type
 * instruments (CofO / OP, where Kano State Government is the grantor) Party 2 is
 * the file owner, which is why the form backfills this from the selected file's
 * file_indexings.gender.
 *
 * Stores the NAME (Male|Female|Corporate|Joint — GenderNormalizer::CANON), not a
 * genders.id, matching file_indexings.gender / mls_file_no.gender /
 * st_file_numbers.gender.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('instrument_capture', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'party_2_gender')) {
                $table->string('party_2_gender', 20)->nullable()->after('party_2_name');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('instrument_capture', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'party_2_gender')) {
                $table->dropColumn('party_2_gender');
            }
        });
    }
};
