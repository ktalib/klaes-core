<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per stage change: the applicant-visible timeline AND the SMS log.
 *
 * Deliberately one table rather than two. The text the applicant was sent and
 * the entry they read on their status page are written in the same call, so the
 * two can never drift apart — a message that failed to send still shows on the
 * timeline, carrying its failure reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('laas_application_events')) {
            return;
        }

        Schema::connection('sqlsrv')->create('laas_application_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('laas_application_id');
            $table->string('stage', 40);
            $table->string('title', 200);
            $table->text('body')->nullable();

            $table->string('actor_type', 20)->default('system'); // applicant | staff | system
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name', 200)->nullable();

            $table->boolean('visible_to_applicant')->default(true);

            // SMS attempt for this event, if one was made.
            $table->string('sms_to', 30)->nullable();
            $table->text('sms_body')->nullable();
            $table->dateTime('sms_sent_at')->nullable();
            $table->string('sms_status', 20)->nullable();       // sent | failed | skipped

            $table->timestamps();

            $table->index('laas_application_id');
            $table->index('stage');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('laas_application_events');
    }
};
