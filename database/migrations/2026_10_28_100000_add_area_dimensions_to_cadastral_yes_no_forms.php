<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yes/No form Q8 "Area applied for" is worked out from dimensions (user, 2026-10-28): rows of
 * length x width in metres, kept as text ("46.13 m x 49.52 m; 20.68 m x 10 m") so the rows come
 * back when the form is reopened. The area itself stays in area_applied_ha. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('cadastral_yes_no_forms', 'area_dimensions')) {
            Schema::connection('sqlsrv')->table('cadastral_yes_no_forms', function (Blueprint $t) {
                $t->string('area_dimensions', 4000)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('cadastral_yes_no_forms', function (Blueprint $t) {
            $t->dropColumn('area_dimensions');
        });
    }
};
