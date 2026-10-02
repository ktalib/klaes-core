<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which sender ID one message goes out under.
 *
 * Bulk-SMS.ng takes the sender ID as a per-request field, so one account sends
 * under more than one name. The Ministry uses two, split by who RECEIVES the
 * message: KLAES for staff (sign-in, Quick Search, file tracking, verification
 * codes) and KANOMLPP for the public (commissioning, Letters of Grant, deeds,
 * caveats).
 *
 * Nullable, and null means "use the group this message is declared under in
 * config/klaes_sms.php". An override is only written when somebody moves a
 * message on the control page, so a later correction to the shipped grouping
 * still reaches every server that has not overridden it -- the same rule the
 * `template` column follows.
 *
 * Stores the GROUP KEY ('staff' / 'department'), not the literal sender ID. If
 * the Ministry ever re-registers under a different name, that is one edit in
 * config/klaes_sms.php rather than an UPDATE across this table.
 *
 * Pinned to sqlsrv: config('database.default') is mysql, so a bare
 * Schema::table() would look for the table in the wrong database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sms_settings')) {
            return;
        }

        if (Schema::connection('sqlsrv')->hasColumn('sms_settings', 'sender')) {
            return;
        }

        Schema::connection('sqlsrv')->table('sms_settings', function (Blueprint $table) {
            $table->string('sender', 32)->nullable()->after('template');
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('sms_settings', 'sender')) {
            return;
        }

        Schema::connection('sqlsrv')->table('sms_settings', function (Blueprint $table) {
            $table->dropColumn('sender');
        });
    }
};
