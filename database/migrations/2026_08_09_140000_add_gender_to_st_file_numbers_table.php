<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gender is captured on the ST commissioning form (required), the same way the
 * MLS generator captures it. mls_file_no and file_indexings already have the
 * column; the ST record needs its own so ST-only files keep the value too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('st_file_numbers', 'gender')) {
                // Male | Female | Corporate | Joint — GenderNormalizer::CANON
                $table->string('gender', 20)->nullable()->after('applicant_type');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('st_file_numbers', 'gender')) {
                $table->dropColumn('gender');
            }
        });
    }
};
