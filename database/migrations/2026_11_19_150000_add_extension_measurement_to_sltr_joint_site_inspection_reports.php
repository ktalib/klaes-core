<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SLTR JSI (user, 2026-11-19): the "Is there an extension?" Yes/No and the extension's own
 * dimensions and area, as on the Conversion JSI (2026_11_18_150000). Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('sltr_joint_site_inspection_reports', function (Blueprint $table) {
            if (! Schema::connection('sqlsrv')->hasColumn('sltr_joint_site_inspection_reports', 'has_extension')) {
                $table->boolean('has_extension')->nullable();
                $table->text('extension_site_length')->nullable();
                $table->text('extension_site_width')->nullable();
                $table->decimal('extension_site_area', 18, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('sltr_joint_site_inspection_reports', function (Blueprint $table) {
            $table->dropColumn(['has_extension', 'extension_site_length', 'extension_site_width', 'extension_site_area']);
        });
    }
};
