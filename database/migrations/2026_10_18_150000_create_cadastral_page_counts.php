<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The main file's page count when Registry sends it to Land and when it comes back
 * (recording, 2026-10-18). More pages back than out triggers the DMS update.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('cadastral_page_counts')) {
            return;
        }
        Schema::connection('sqlsrv')->create('cadastral_page_counts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cadastral_case_id')->index();
            $table->string('step', 60);
            $table->string('direction', 10);              // out | back
            $table->unsignedInteger('pages');              // what Registry entered
            $table->unsignedInteger('dms_pages')->nullable();   // pages DMS held for the file then
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->dateTime('recorded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('cadastral_page_counts');
    }
};
