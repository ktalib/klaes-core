<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provenance for land_use values recovered from the file-number prefix.
 *
 * `landuse:backfill` fills empty land_use columns by reading the prefix off the
 * file number (RES-2026-1862 -> Residential), per .agent/skills/klaes/SKILL.md §5.
 * That is a derivation, not a capture, and the two must stay distinguishable —
 * same reasoning as gender_source.
 *
 *   captured    already present in the column before the backfill
 *   file_number derived from the file-number prefix
 *
 * Reverses with:
 *   UPDATE t SET land_use = NULL, land_use_source = NULL WHERE land_use_source = 'file_number'
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    private array $tables = ['pra', 'file_history_staging', 'CofO_staging', 'file_indexings'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)
                || Schema::connection('sqlsrv')->hasColumn($table, 'land_use_source')) {
                continue;
            }

            Schema::connection('sqlsrv')->table($table, function (Blueprint $t) {
                $t->string('land_use_source', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::connection('sqlsrv')->hasColumn($table, 'land_use_source')) {
                continue;
            }

            Schema::connection('sqlsrv')->table($table, function (Blueprint $t) {
                $t->dropColumn(['land_use_source']);
            });
        }
    }
};
