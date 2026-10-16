<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Occupancy Permit Type: when it is "Other", the officer must say what it is
 * (user, 2026-10-15). Kept beside op_type, which stays 'Other'.
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_15_120000_add_op_type_other_to_pra.php
 *
 * Additive and guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if ($s->hasTable('pra') && ! $s->hasColumn('pra', 'op_type_other')) {
            $s->table('pra', function (Blueprint $t) {
                $t->string('op_type_other', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');

        if ($s->hasColumn('pra', 'op_type_other')) {
            $s->table('pra', function (Blueprint $t) {
                $t->dropColumn('op_type_other');
            });
        }
    }
};
