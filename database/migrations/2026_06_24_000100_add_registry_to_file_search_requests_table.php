<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capture the origin Registry (+ its short code) chosen when a File Search Request
 * is raised from File Search — mirrors the Registry dropdown on Create File Tracker.
 */
return new class extends Migration {
    public function up()
    {
        if (! Schema::connection('sqlsrv')->hasTable('file_search_requests')) {
            return;
        }

        if (! Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'registry')) {
            Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
                $table->string('registry', 255)->nullable();
            });
        }
        if (! Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'registry_code')) {
            Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
                $table->string('registry_code', 20)->nullable();
            });
        }
    }

    public function down()
    {
        foreach (['registry', 'registry_code'] as $col) {
            if (Schema::connection('sqlsrv')->hasColumn('file_search_requests', $col)) {
                Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
