<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Room for the batch wording, and for the file numbers one batch message covers.
 *
 * TWO COLUMNS, ONE REASON: a batch commissioning now sends ONE message per phone
 * number rather than one per file, so a single message can be about five files
 * (or two hundred).
 *
 * sms_settings.plural_template
 *   "A New File with FileNo X" does not work for five files. The plural form --
 *   "New Files with FileNos RES-2026-3029-3033 have been commissioned..." -- is
 *   editable in the SMS Control Centre alongside the singular, so the two stay
 *   in step when somebody rewords one. Nullable: null means "use the plural form
 *   shipped in config/klaes_sms.php", the same rule `template` follows.
 *
 * sms_dispatch_logs.file_number widened 100 -> 1000
 *   That column answers "was this file's applicant told?". One row now covers
 *   several files, so it holds the rendered range ("RES-2026-3029-3033") or, when
 *   the serials are not contiguous, a joined list. 100 characters was enough for
 *   one file number and is not enough for that.
 *
 * Pinned to sqlsrv: config('database.default') is mysql, so bare Schema calls
 * would look for these tables in the wrong database.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('sms_settings') && !$schema->hasColumn('sms_settings', 'plural_template')) {
            $schema->table('sms_settings', function (Blueprint $table) {
                $table->text('plural_template')->nullable()->after('template');
            });
        }

        if ($schema->hasTable('sms_dispatch_logs')) {
            // No ->change() here: doctrine/dbal is not installed, and a widening
            // ALTER is safe to issue directly on SQL Server.
            \Illuminate\Support\Facades\DB::connection('sqlsrv')->statement(
                'ALTER TABLE sms_dispatch_logs ALTER COLUMN file_number NVARCHAR(1000) NULL'
            );
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('sms_settings', 'plural_template')) {
            $schema->table('sms_settings', function (Blueprint $table) {
                $table->dropColumn('plural_template');
            });
        }

        // file_number is deliberately NOT narrowed back: doing so would truncate
        // any batch range already recorded in it.
    }
};
