<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The SuA commissioning form asks for two distinct numbers: the slip the
 * allocation was raised under (allocation_ref_no, already there and printed on
 * the Confirmation Sheet) and the allocation's own reference, which had nowhere
 * to live until now. It is recorded against the file only — the sheet prints
 * the slip no, never this.
 *
 * allocation_ref_no is deliberately left alone: the Standalone Unit Application
 * form labels that same column "Allocation Reference No", and re-pointing it
 * would silently move every value already captured there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('st_file_numbers', 'allocation_reference_no')) {
                $table->string('allocation_reference_no', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('st_file_numbers', 'allocation_reference_no')) {
                $table->dropColumn('allocation_reference_no');
            }
        });
    }
};
