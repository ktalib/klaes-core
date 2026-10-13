<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastral Department intake: a Verification file is Resettlement or Direct as
 * well as Statutory or Customary (user, 2026-10-12). sub_type keeps Statutory /
 * Customary; this holds the Resettlement / Direct choice (Direct Allocation's keys).
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_12_100000_add_allocation_route_to_cadastral_cases.php
 *
 * Additive and guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if ($s->hasTable('cadastral_cases') && ! $s->hasColumn('cadastral_cases', 'allocation_route')) {
            $s->table('cadastral_cases', function (Blueprint $t) {
                $t->string('allocation_route', 30)->nullable();
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');

        if ($s->hasColumn('cadastral_cases', 'allocation_route')) {
            $s->table('cadastral_cases', function (Blueprint $t) {
                $t->dropColumn('allocation_route');
            });
        }
    }
};
