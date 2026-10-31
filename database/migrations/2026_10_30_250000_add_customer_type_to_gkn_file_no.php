<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The GKN file number generator saves the form's Customer Type on gkn_file_no (the model
 * lists it as fillable), but the column was never created, so every commission failed with
 * "Invalid column name 'customer_type'" and gkn_file_no stayed empty (found 2026-10-30
 * while adding LPPL / RDKN).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasColumn('gkn_file_no', 'customer_type')) {
            Schema::connection('sqlsrv')->table('gkn_file_no', fn (Blueprint $t) => $t->string('customer_type', 100)->nullable());
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('gkn_file_no', fn (Blueprint $t) => $t->dropColumn('customer_type'));
    }
};
