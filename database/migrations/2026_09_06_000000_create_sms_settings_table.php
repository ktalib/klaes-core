<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per transactional SMS message: is it on, and what does it say.
 *
 * The rows are written by the SMS Control Centre, not seeded. A key with no row
 * falls back to config('klaes_sms.messages.<key>'), where default_enabled is
 * false -- so an empty table means "send nothing", which is the state a fresh
 * deployment should be in.
 *
 * `template` is nullable and holds ONLY an override. Leaving it null keeps the
 * message on the wording shipped in config/klaes_sms.php, so a later correction
 * to the Ministry's wording reaches every server that has not overridden it.
 *
 * Pinned to sqlsrv on purpose: config('database.default') is mysql, and a bare
 * Schema::create() here would build the table in the wrong database while the
 * model reads from the right one. That has already happened once in this
 * codebase -- see 2025_11_27_120000_create_user_notification_settings_table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('sms_settings')) {
            return;
        }

        Schema::connection('sqlsrv')->create('sms_settings', function (Blueprint $table) {
            $table->id();

            // 'master', 'land_fc', ... -- the App\Models\SmsSetting::KEY_* set.
            $table->string('message_key', 64);

            $table->boolean('enabled')->default(false);

            // Ministry override of the shipped wording. Null = use the default.
            $table->text('template')->nullable();

            // users.name of whoever last changed it, for the control page.
            $table->string('updated_by', 191)->nullable();

            $table->timestamps();

            $table->unique('message_key', 'sms_settings_key_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sms_settings');
    }
};
