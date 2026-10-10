<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correspondence and shadow file numbers on fileNumber (MLS) and
 * oss_applications, as file_indexings already carries corresponding_fileno
 * (client brief, 2026-10-10, approved by the user as step 9).
 *
 *   corresponding_fileno  the correspondence (cadastral copy) file's number
 *   shadow_fileno         the cadastral_shadow_files ref (CSF-Ymd-His)
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_10_120000_add_correspondence_columns.php
 *
 * Additive and guarded: nullable columns, nothing existing changes. No
 * backfill: the ~48k index rows flagged without a shadow row stay as they are
 * (deliberate, see the old module's CorrespondenceFiles). New and matched
 * correspondence files fill the columns from now on (CorrespondenceColumns).
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        foreach (['fileNumber', 'oss_applications'] as $table) {
            if (! $s->hasTable($table)) {
                continue;
            }
            if (! $s->hasColumn($table, 'corresponding_fileno')) {
                $s->table($table, fn (Blueprint $t) => $t->string('corresponding_fileno', 100)->nullable());
            }
            if (! $s->hasColumn($table, 'shadow_fileno')) {
                $s->table($table, fn (Blueprint $t) => $t->string('shadow_fileno', 50)->nullable());
            }
        }

        if ($s->hasTable('cadastral_cases') && ! $s->hasColumn('cadastral_cases', 'shadow_fileno')) {
            $s->table('cadastral_cases', fn (Blueprint $t) => $t->string('shadow_fileno', 50)->nullable());
        }
    }

    public function down(): void
    {
        // Intentionally empty: no backups here; dropping columns would lose data.
    }
};
