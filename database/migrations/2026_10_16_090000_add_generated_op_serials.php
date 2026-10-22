<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * System-generated OP serials (user, 2026-10-16). An OP with no serial gets one from
 * the system: six digits, zero-padded (000001, 000002, ...), so a leading zero means
 * "made by the system" -- typed serials never carry one.
 *
 *   pra.op_serial_generated           1 while op_serial_number is a system serial
 *   pra.op_serial_generated_replaced  the system serial a real one later replaced
 *   op_serial_sequences               the counter (one row), bumped inside the save
 *                                      transaction so a rolled-back save returns its number
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_16_090000_add_generated_op_serials.php
 *
 * Additive and guarded. Nullable columns with no default: metadata-only on SQL Server.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if ($s->hasTable('pra') && ! $s->hasColumn('pra', 'op_serial_generated')) {
            $s->table('pra', function (Blueprint $t) {
                $t->boolean('op_serial_generated')->nullable();
            });
        }
        if ($s->hasTable('pra') && ! $s->hasColumn('pra', 'op_serial_generated_replaced')) {
            $s->table('pra', function (Blueprint $t) {
                $t->string('op_serial_generated_replaced', 20)->nullable();
            });
        }

        if (! $s->hasTable('op_serial_sequences')) {
            $s->create('op_serial_sequences', function (Blueprint $t) {
                $t->string('name', 50)->primary();
                $t->integer('last_value')->default(0);
                $t->timestamps();
            });
            DB::connection('sqlsrv')->table('op_serial_sequences')->insert([
                'name' => 'op_serial', 'last_value' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');

        $s->dropIfExists('op_serial_sequences');
        foreach (['op_serial_generated', 'op_serial_generated_replaced'] as $column) {
            if ($s->hasColumn('pra', $column)) {
                $s->table('pra', function (Blueprint $t) use ($column) {
                    $t->dropColumn($column);
                });
            }
        }
    }
};
