<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastral Department intake: when File source is "Other", the officer must
 * say what it is (user, 2026-10-12). Kept beside file_source, which stays 'other'.
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_12_090000_add_file_source_other_to_cadastral_cases.php
 *
 * Additive and guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if ($s->hasTable('cadastral_cases') && ! $s->hasColumn('cadastral_cases', 'file_source_other')) {
            $s->table('cadastral_cases', function (Blueprint $t) {
                $t->string('file_source_other', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');

        if ($s->hasColumn('cadastral_cases', 'file_source_other')) {
            $s->table('cadastral_cases', function (Blueprint $t) {
                $t->dropColumn('file_source_other');
            });
        }
    }
};
