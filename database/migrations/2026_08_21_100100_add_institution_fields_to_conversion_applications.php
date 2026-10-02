<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-sheet answers for the SuA Confirmation Sheet's recipient block.
 *
 * institution_category / institution_name mirror what was captured at
 * commissioning (so a reprint can never contradict the copy already issued);
 * addressed_to is the officer this particular letter is written to, and is
 * chosen on the print card each time.
 *
 * The LGA/Conversion sheet keeps using allocation_source / allocation_entity /
 * allocation_address and is unaffected by these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('conversion_applications', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('conversion_applications', 'institution_category')) {
                // 'GOVERNMENT' | 'OTHER'
                $table->string('institution_category', 20)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('conversion_applications', 'institution_name')) {
                $table->string('institution_name', 255)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('conversion_applications', 'addressed_to')) {
                // e.g. 'MANAGING DIRECTOR' — from allocation_source_lookups
                $table->string('addressed_to', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('conversion_applications', function (Blueprint $table) {
            foreach (['institution_category', 'institution_name', 'addressed_to'] as $column) {
                if (Schema::connection('sqlsrv')->hasColumn('conversion_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
