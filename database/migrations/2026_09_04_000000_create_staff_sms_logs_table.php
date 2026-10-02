<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per member of staff, per day, per kind of attendance SMS.
 *
 * This table is BOTH the once-a-day throttle and the audit trail. The unique
 * index on (user_id, sms_type, sent_on) is what actually enforces "once a day":
 * two simultaneous sign-ins race to insert, and the loser is rejected by the
 * database rather than by a check that could interleave.
 *
 * A row is claimed BEFORE the gateway is called and updated with the outcome
 * afterwards, so a send that fails leaves status='failed' and can be retried by
 * the next sign-in that day — the once-a-day promise is about messages that
 * actually went out, not about attempts.
 *
 * sent_on is a DATE in config('staff_sms.timezone') (Africa/Lagos), not UTC, so
 * a day here is the working day the office actually had.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('staff_sms_logs')) {
            return;
        }

        Schema::connection('sqlsrv')->create('staff_sms_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');

            // 'login' | 'logout'
            $table->string('sms_type', 20);

            // Local (Africa/Lagos) working day this message belongs to.
            $table->date('sent_on');

            // 'pending' | 'sent' | 'failed' | 'skipped'
            $table->string('status', 20)->default('pending');

            // The normalised 234XXXXXXXXXX the gateway was given.
            $table->string('phone', 20)->nullable();

            // What was actually sent, and what the gateway said about it.
            $table->text('message')->nullable();
            $table->string('gateway_code', 20)->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            // The sign-in / sign-out moment the message reports, in local time.
            $table->dateTime('event_at')->nullable();

            $table->timestamps();

            // The throttle itself.
            $table->unique(['user_id', 'sms_type', 'sent_on'], 'staff_sms_logs_daily_unique');
            $table->index(['sent_on', 'status'], 'staff_sms_logs_day_status_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('staff_sms_logs');
    }
};
