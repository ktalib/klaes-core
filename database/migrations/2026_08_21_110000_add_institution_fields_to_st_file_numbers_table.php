<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The SuA commissioning form now answers "Allocation Source" with the name of
 * the allocating institution, picked from allocation_source_lookups, instead of
 * the binary State/Local Government question.
 *
 * allocation_source / allocation_entity are still written alongside these — the
 * Standalone Unit Application form, instrument registration and the LGA sheet
 * all still read them — but the institution name is what the Confirmation Sheet
 * shows back, and only these columns can hold it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('st_file_numbers', 'institution_category')) {
                // 'GOVERNMENT' | 'OTHER' — which of the two lookup lists the name came from.
                $table->string('institution_category', 20)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('st_file_numbers', 'institution_name')) {
                $table->string('institution_name', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            foreach (['institution_category', 'institution_name'] as $column) {
                if (Schema::connection('sqlsrv')->hasColumn('st_file_numbers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
