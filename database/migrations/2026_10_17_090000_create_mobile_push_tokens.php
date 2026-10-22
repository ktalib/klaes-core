<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push notifications for the KLAES FTM app (user, 2026-10-17): one row per
 * device's FCM registration token. A token belongs to whoever last signed in
 * on that phone, so the app re-registers on every dashboard load and the row
 * is moved to the new user; logout and FCM "unregistered" replies delete it.
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_17_090000_create_mobile_push_tokens.php
 *
 * Additive and guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if (! $s->hasTable('mobile_push_tokens')) {
            $s->create('mobile_push_tokens', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->index();
                // FCM tokens run ~160 chars; 255 keeps the unique index under SQL Server's 900-byte key limit.
                $t->string('token', 255)->unique();
                $t->string('platform', 20)->default('android');
                $t->string('device_name', 150)->nullable();
                $t->string('app_version', 30)->nullable();
                $t->dateTime('last_seen_at')->nullable();
                $t->dateTime('last_pushed_at')->nullable();
                $t->string('last_error', 500)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('mobile_push_tokens');
    }
};
