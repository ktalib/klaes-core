<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A manual "Update Location Status" on Quick Search is stamped on the file's
 * latest File Search Request, so the request logs (web + mobile) show what the
 * file's status became after the SCB response. Additive and nullable only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
            if (! Schema::connection('sqlsrv')->hasColumn('file_search_requests', 'location_status')) {
                $table->string('location_status', 50)->nullable();
                $table->dateTime('location_status_at')->nullable();
                $table->unsignedBigInteger('location_status_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_search_requests', function (Blueprint $table) {
            $table->dropColumn(['location_status', 'location_status_at', 'location_status_by']);
        });
    }
};
