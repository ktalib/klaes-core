<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Master Delete keeps the file's other records, crossed out (user, 2026-11-15).
 *
 * The tables whose rows a delete now flags instead of removing get is_deleted, deleted_at,
 * deleted_by and deleted_numbers where they lack them (App\Services\FileNumber\CrossedOutFiles::
 * crossOutRelated). All nullable, no defaults: on SQL Server that is a metadata-only change,
 * so the big tables (scannings, pra) are not rewritten or locked. NULL reads as "not deleted".
 */
return new class extends Migration
{
    private const TABLES = [
        'pra', 'CofO_staging', 'file_history_staging', 'deed_registrations', 'caveats', 'file_tracker',
        'oss_applications', 'scannings', 'pagetypings', 'thumbnails', 'file_indexing_bills',
    ];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        foreach (self::TABLES as $table) {
            if (!$schema->hasTable($table)) {
                continue;
            }
            $schema->table($table, function (Blueprint $t) use ($schema, $table) {
                if (!$schema->hasColumn($table, 'is_deleted')) {
                    $t->boolean('is_deleted')->nullable();
                }
                if (!$schema->hasColumn($table, 'deleted_at')) {
                    $t->dateTime('deleted_at')->nullable();
                }
                if (!$schema->hasColumn($table, 'deleted_by')) {
                    $t->string('deleted_by', 150)->nullable();
                }
                if (!$schema->hasColumn($table, 'deleted_numbers')) {
                    $t->text('deleted_numbers')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Additive only. Columns that already existed before this migration (is_deleted on pra,
        // deleted_at on pagetypings, ...) are not this migration's to drop, so nothing is removed.
    }
};
