<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File indexing Occupancy Permit section: "Source" of the permit — Ministry, KANGIS
 * or LGA (user, 2026-10-15). LGA moved here out of OP Type. Not pra.source, which
 * already records the capture channel ('indexing').
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_15_100000_add_op_source_to_pra.php
 *
 * Additive and guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if ($s->hasTable('pra') && ! $s->hasColumn('pra', 'op_source')) {
            $s->table('pra', function (Blueprint $t) {
                $t->string('op_source', 50)->nullable();
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');

        if ($s->hasColumn('pra', 'op_source')) {
            $s->table('pra', function (Blueprint $t) {
                $t->dropColumn('op_source');
            });
        }
    }
};
