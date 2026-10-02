<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.otp_channel — which way the verification code was sent.
 *
 * WHY
 * The gate now offers two routes to the same code: the member of staff's EMAIL
 * ADDRESS (the default) and their mobile number. The reason email leads is the
 * data: of 1,562 active accounts, 495 carry an email address that is a real
 * mailbox against 282 carrying a number the SMS gateway can reach — and the SMS
 * route is promotional traffic that the telcos hold between 19:45 and 08:00 and
 * never deliver to a DND-blocked handset. (1,555 carry SOMETHING address-shaped;
 * the difference is a thousand seeded placeholders at example.org and the like,
 * which the application refuses to send to.) Email is the route that reaches more
 * people, on more days, for nothing.
 *
 * WHAT THE COLUMN IS FOR
 *   1. The card after a reload. A code sent five minutes ago has a cooldown
 *      running against it; without knowing WHICH address it went to, the card
 *      cannot say "we sent a code to a***@gmail.com" or resend to the right
 *      place.
 *   2. Voiding the right proof. A verified account whose EMAIL is edited has
 *      lost its proof only if it was the email that was proved; the same for the
 *      phone number. Without this column the model has to guess, and guessing
 *      means re-locking people who changed the other one (see User::booted()).
 *   3. Support. `phone:verify-status --user=<id>` can say how somebody was
 *      verified, which is the first question asked when they cannot get in.
 *
 * NULL MEANS SMS. Every account verified before this column existed went through
 * the SMS card, and every reader here treats a missing value as 'sms' — so the
 * column can be absent altogether and nothing changes shape.
 *
 * THE GATE DOES NOT DEPEND ON IT. PhoneOtpService::columnsReady() asks only for
 * the three original columns; this one is written only when it exists
 * (channelColumnReady()). Code deployed ahead of this migration sends codes
 * exactly as before — it simply cannot remember which route it used.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('users', 'otp_channel')) {
            $schema->table('users', function (Blueprint $table) {
                // Nullable rather than defaulted: NULL is not "unknown" here, it is
                // "the SMS card, before there was a choice", and every read maps it
                // to 'sms' explicitly.
                $table->string('otp_channel', 10)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('users', 'otp_channel')) {
            $schema->table('users', function (Blueprint $table) {
                $table->dropColumn('otp_channel');
            });
        }
    }
};
