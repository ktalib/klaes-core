<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change of Ownership Type (user, 2026-10-23): LGA / Ordinary / Other. When it is
 * "Other" the officer must say what it is; the text is kept beside
 * instrument_subtype, which stays 'Other'. The PRA card writes pra (or pic in
 * index mode); the File History transaction card writes file_history_staging.
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_23_120000_add_instrument_subtype_other.php
 *
 * Additive and guarded.
 */
return new class extends Migration
{
    private const TABLES = ['pra', 'pic', 'file_history_staging'];

    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        foreach (self::TABLES as $table) {
            if ($s->hasTable($table) && ! $s->hasColumn($table, 'instrument_subtype_other')) {
                $s->table($table, function (Blueprint $t) {
                    $t->string('instrument_subtype_other', 100)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');

        foreach (self::TABLES as $table) {
            if ($s->hasTable($table) && $s->hasColumn($table, 'instrument_subtype_other')) {
                $s->table($table, function (Blueprint $t) {
                    $t->dropColumn('instrument_subtype_other');
                });
            }
        }
    }
};
