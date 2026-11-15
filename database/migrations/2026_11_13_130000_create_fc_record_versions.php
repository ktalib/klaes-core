<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record State Versions for the FC (File Commissioning) table (Kunle, 2026-11-13):
 * every edit made from the FC Edit modal keeps the record as it stood, so the table
 * can show the earlier versions under the row. Indexed Files reads its history from
 * file_snapshots instead; FC had nothing.
 *
 * One row per version. Version 1 is the record before its first edit; each later
 * version is the record after an edit. `entity` is the FC row type, because the ids
 * of fileNumber, mls_file_no (temporary files) and plot_extensions collide.
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_11_13_130000_create_fc_record_versions.php
 *
 * Additive and guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if (! $s->hasTable('fc_record_versions')) {
            $s->create('fc_record_versions', function (Blueprint $t) {
                $t->id();
                $t->string('entity', 20);
                $t->unsignedBigInteger('record_id');
                $t->string('file_number', 255)->nullable();
                $t->unsignedInteger('version');
                // The FC row's fields at this version, as the list shows them.
                $t->text('state');
                // Field keys this version changed against the one before (null on version 1).
                $t->text('changed_fields')->nullable();
                $t->unsignedBigInteger('edited_by')->nullable();
                $t->string('edited_by_name', 255)->nullable();
                $t->dateTime('created_at')->nullable();

                $t->unique(['entity', 'record_id', 'version'], 'fc_record_versions_record_version_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('fc_record_versions');
    }
};
