<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every transactional SMS this system decides about -- sent, failed or skipped.
 *
 * THIS TABLE IS THE DOUBLE-SEND GUARD AS WELL AS THE AUDIT TRAIL.
 *
 * `dedupe_key` carries a caller-supplied identity for the EVENT, not the
 * message: 'land_fc:RES-2026-3026', 'deed_registered:8123'. The unique index
 * on it is what actually stops a second send, because the row is claimed BEFORE
 * the gateway is called. A read-then-write check would not do: a double-clicked
 * Generate button and a browser retry both produce two requests that interleave
 * happily, and the applicant gets told twice. The same reasoning, and the same
 * shape, as staff_sms_logs.
 *
 * A row left at 'failed' IS handed back to the next attempt, so a gateway outage
 * does not permanently consume the event's one message. 'sent' and 'pending'
 * rows are not: pending means a send is in flight, and re-sending on top of it
 * is how you double-charge the wallet.
 *
 * dedupe_key is NULLABLE because test sends from the control page deliberately
 * have no identity -- an officer may send the same test twice. SQL Server counts
 * NULLs as equal in a plain UNIQUE index, so a filtered index is created below
 * instead; without it the second test send would collide with the first.
 *
 * Pinned to sqlsrv: config('database.default') is mysql and the model reads from
 * sqlsrv, so a bare Schema::create() would build this in the wrong database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('sms_dispatch_logs')) {
            return;
        }

        Schema::connection('sqlsrv')->create('sms_dispatch_logs', function (Blueprint $table) {
            $table->id();

            // Which catalogue message this is. App\Models\SmsSetting::KEY_*.
            $table->string('message_key', 64);

            // The normalised 234XXXXXXXXXX the gateway was given, when we got
            // far enough to have one.
            $table->string('phone', 20)->nullable();

            // What was actually sent -- the wording the gateway ACCEPTED, which
            // may be the fallback rather than the one we tried first.
            $table->text('message')->nullable();

            // pending | sent | failed | skipped. See the model for what each
            // one means; they are not interchangeable.
            $table->string('status', 20)->default('pending');

            $table->string('gateway_code', 20)->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            // Denormalised so the control page and the doctor command can answer
            // "was this file's applicant told?" without joining five tables.
            $table->string('file_number', 100)->nullable();

            // What the message was about: 'mls_file_no', 'caveats', ...
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 64)->nullable();

            // The double-send guard. Null for test sends -- see the note above.
            $table->string('dedupe_key', 191)->nullable();

            // The business moment the message reports, on the Lagos clock.
            $table->dateTime('event_at')->nullable();

            // users.name of the officer whose action triggered it, when there
            // was one (the caveat expiry command has none).
            $table->string('created_by', 191)->nullable();

            $table->timestamps();

            $table->index(['message_key', 'status'], 'sms_dispatch_logs_key_status_idx');
            $table->index('file_number', 'sms_dispatch_logs_file_number_idx');
            $table->index('created_at', 'sms_dispatch_logs_created_idx');
        });

        /*
         | The guard itself. A FILTERED unique index, because SQL Server treats
         | two NULLs as duplicates in an ordinary UNIQUE index -- which would let
         | the first test send (dedupe_key NULL) block every later one.
         */
        DB::connection('sqlsrv')->statement(
            'CREATE UNIQUE INDEX sms_dispatch_logs_dedupe_unique
               ON sms_dispatch_logs (dedupe_key)
             WHERE dedupe_key IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sms_dispatch_logs');
    }
};
