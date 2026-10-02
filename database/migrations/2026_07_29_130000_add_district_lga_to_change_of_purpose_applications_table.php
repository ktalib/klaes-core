<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Change of Purpose form has always captured District and LGA, but only the
 * composite `location` string was persisted. The MLS file-number generator needs
 * the individual values to backfill its District / LGA dropdowns, so store them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('change_of_purpose_applications', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('change_of_purpose_applications', 'district')) {
                $table->string('district', 255)->nullable()->after('location');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('change_of_purpose_applications', 'lga')) {
                $table->string('lga', 255)->nullable()->after('district');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('change_of_purpose_applications', function (Blueprint $table) {
            $table->dropColumn(['district', 'lga']);
        });
    }
};
