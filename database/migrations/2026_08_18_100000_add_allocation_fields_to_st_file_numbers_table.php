<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allocation Information (source / entity / reference no) is answered once, on
 * the SuA File Number Commissioning form, instead of being re-typed on every
 * Standalone Unit Application. The SuA record is the only thing that exists at
 * commissioning time, so the answer is kept here and back-filled into the
 * subapplication form when its file number is selected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('st_file_numbers', 'allocation_source')) {
                // 'State Government' | 'Local Government'
                $table->string('allocation_source', 100)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('st_file_numbers', 'allocation_entity')) {
                // KSIP/HOUSING/KUNPDA for State Government, an LGA name otherwise
                $table->string('allocation_entity', 100)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('st_file_numbers', 'allocation_ref_no')) {
                // e.g. ALS/2025/001
                $table->string('allocation_ref_no', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            foreach (['allocation_source', 'allocation_entity', 'allocation_ref_no'] as $column) {
                if (Schema::connection('sqlsrv')->hasColumn('st_file_numbers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
