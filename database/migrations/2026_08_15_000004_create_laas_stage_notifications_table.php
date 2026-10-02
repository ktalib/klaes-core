<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal desk alerts — the staff half of the workflow.
 *
 * Spec step (h): when Cadastral returns a completed Land 12, the Land Office /
 * OSS Unit must be told that a recommendation is now due. That alert is for
 * staff, not the applicant, so it does not belong on the applicant timeline in
 * laas_application_events.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('laas_stage_notifications')) {
            return;
        }

        Schema::connection('sqlsrv')->create('laas_stage_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('laas_application_id');
            $table->string('department', 100)->nullable();
            $table->string('stage', 40);
            $table->string('title', 200);
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->dateTime('read_at')->nullable();
            $table->unsignedBigInteger('read_by')->nullable();
            $table->timestamps();

            $table->index('laas_application_id');
            $table->index('is_read');
            $table->index('department');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('laas_stage_notifications');
    }
};
