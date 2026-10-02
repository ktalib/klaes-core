<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PuA and SuA commissioning now capture the property location (and a map pin).
 * Unlike PRIMARY files — whose location lands in file_indexings when the NPFN
 * is indexed — unit file numbers have no indexing row of their own, so the
 * captured location is stored alongside the unit here.
 */
return new class extends Migration
{
    private array $columns = [
        'property_house_no',
        'property_plot_no',
        'property_street_name',
        'property_district',
        'property_lga',
        'property_state',
        'property_address',
        'latitude',
        'longitude',
    ];

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            $has = fn (string $column) => Schema::connection('sqlsrv')->hasColumn('st_file_numbers', $column);

            if (!$has('property_house_no')) $table->string('property_house_no', 100)->nullable();
            if (!$has('property_plot_no')) $table->string('property_plot_no', 100)->nullable();
            if (!$has('property_street_name')) $table->string('property_street_name', 255)->nullable();
            if (!$has('property_district')) $table->string('property_district', 255)->nullable();
            if (!$has('property_lga')) $table->string('property_lga', 255)->nullable();
            if (!$has('property_state')) $table->string('property_state', 255)->nullable();
            if (!$has('property_address')) $table->string('property_address', 500)->nullable();
            if (!$has('latitude')) $table->decimal('latitude', 12, 8)->nullable();
            if (!$has('longitude')) $table->decimal('longitude', 12, 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            $existing = array_filter(
                $this->columns,
                fn (string $column) => Schema::connection('sqlsrv')->hasColumn('st_file_numbers', $column)
            );

            if ($existing) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};
