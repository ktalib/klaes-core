<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversion JSI (user, 2026-11-18): "Is there an extension?" Yes/No below the Existing Site
 * Measurement, and on Yes the extension's own dimensions and area covered. Additive only; stored
 * like the existing/recommended measurements (x-separated dimensions in *_length, width when 2 parts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            if (! Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'has_extension')) {
                $table->boolean('has_extension')->nullable();
                $table->text('extension_site_length')->nullable();
                $table->text('extension_site_width')->nullable();
                $table->decimal('extension_site_area', 18, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            $table->dropColumn(['has_extension', 'extension_site_length', 'extension_site_width', 'extension_site_area']);
        });
    }
};
