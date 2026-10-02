<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carry the full Statutory Right of Occupancy form on a LAAS application.
 *
 * The paper form runs to roughly thirty answers and differs per land type, so
 * the answers live in one JSON column rather than thirty-odd nullable columns
 * that would be empty for three types out of four.
 *
 * The keys inside `form_data` are the `oss_applications` column names, so when
 * an approved application is promoted into the live OSS table the payload maps
 * across by name with no translation step. See App\Support\Laas\SroFormSchema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('laas_applications', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('laas_applications', 'land_type')) {
                // residential | commercial | industrial | agricultural
                $table->string('land_type', 30)->nullable()->after('stage');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('laas_applications', 'form_data')) {
                $table->text('form_data')->nullable()->after('land_type');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('laas_applications', function (Blueprint $table) {
            foreach (['land_type', 'form_data'] as $column) {
                if (Schema::connection('sqlsrv')->hasColumn('laas_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
