<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.otp / users.otp_sent_at / users.is_otp_verified — the phone-number gate.
 *
 * WHY
 * The phone numbers on staff accounts cannot be trusted: 1,140 rows carry none at all
 * and 140 more carry something that is not a usable Nigerian mobile. Attendance SMS,
 * file-request notices and every future message are aimed at that column, so a number
 * nobody has ever proved reaches its owner is worse than a blank — it looks like a way
 * of contacting the person. The only proof that a number belongs to the account holder
 * is a code sent to it and typed back.
 *
 * Columns:
 *   otp              the code last sent, in plain text and short-lived. Stored rather
 *                    than hashed on purpose: ICT reads it back to a member of staff
 *                    whose handset is out of coverage, which is the only support route
 *                    available when the gateway defers overnight traffic to 08:30.
 *   otp_sent_at      when it was sent. This is what makes the code EXPIRE and what the
 *                    resend cooldown is measured from — the third column, and the one
 *                    the request did not name. Without it a code stays valid for ever,
 *                    so an SMS read off a handset months later still opens the account.
 *   is_otp_verified  0 until the code has been typed back correctly, 1 afterwards.
 *
 * EVERY EXISTING ROW STARTS AT 0, which is the point: the gate is meant to sweep the
 * whole staff list once. Nobody is locked OUT of signing in by it — sign-in itself is
 * untouched — but the system stays inert until the number is confirmed, exactly like
 * the mandatory passport photo (see RequireProfilePhoto).
 *
 * THE COLUMN IS THE SWITCH THAT MATTERS. Code deployed ahead of this migration must not
 * hold anybody: PhoneOtpService::columnsReady() checks for is_otp_verified and leaves
 * the gate open when it is absent, because a gate that cannot record a success would
 * lock out every member of staff with no way through.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('users', 'otp')) {
            $schema->table('users', function (Blueprint $table) {
                $table->string('otp', 10)->nullable();
            });
        }

        if (!$schema->hasColumn('users', 'otp_sent_at')) {
            $schema->table('users', function (Blueprint $table) {
                $table->dateTime('otp_sent_at')->nullable();
            });
        }

        if (!$schema->hasColumn('users', 'is_otp_verified')) {
            $schema->table('users', function (Blueprint $table) {
                // NOT NULL with a default of 0: an unverified account and an account
                // added tomorrow must be the same thing, and a NULL here would read as
                // "unknown" in every comparison that decides whether to hold somebody.
                $table->boolean('is_otp_verified')->default(false);
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (['otp', 'otp_sent_at', 'is_otp_verified'] as $column) {
            if ($schema->hasColumn('users', $column)) {
                $schema->table('users', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
