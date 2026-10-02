<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Primary Application form's Property Address section now carries a map
 * pin, backfilled from the selected file's file_indexings row or dropped by
 * the user. Store it with the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('mother_applications', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('mother_applications', 'latitude')) {
                $table->decimal('latitude', 12, 8)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('mother_applications', 'longitude')) {
                $table->decimal('longitude', 12, 8)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('mother_applications', function (Blueprint $table) {
            $existing = array_values(array_filter(
                ['latitude', 'longitude'],
                fn (string $column) => Schema::connection('sqlsrv')->hasColumn('mother_applications', $column)
            ));

            if ($existing) {
                $table->dropColumn($existing);
            }
        });
    }
};
