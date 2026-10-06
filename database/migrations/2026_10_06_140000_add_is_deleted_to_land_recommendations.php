<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hide a recommendation without erasing it. Records are flagged, never deleted;
 * the LandRecommendation global scope and the raw RofO listing queries skip
 * any row where this is 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'is_deleted')) {
            return;
        }

        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->boolean('is_deleted')->nullable()->default(0);
        });
    }

    public function down(): void
    {
        // Dropping a defaulted column on SQL Server needs its default constraint
        // removed first, and the flags it holds would be lost. Left in place.
    }
};
