<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bill Master: when the bill was generated in REV-M (user, 2026-10-19). A bill a
 * module sends over waits, ungenerated, until an officer generates it; only then
 * does its invoice open. Bills generated in REV-M are generated when created.
 *
 * Backfill: bills already part paid or paid count as generated (on their created
 * date); unpaid module bills wait for generation.
 */
return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasTable('bill_master') || $schema->hasColumn('bill_master', 'generated_at')) {
            return;
        }

        $schema->table('bill_master', function (Blueprint $table) {
            $table->dateTime('generated_at')->nullable();
            $table->string('generated_by', 150)->nullable();
        });

        DB::connection('sqlsrv')->table('bill_master')
            ->whereNull('generated_at')
            ->where(fn ($q) => $q->whereIn('status', ['paid', 'part_paid'])->orWhereNull('source_table'))
            ->update(['generated_at' => DB::raw('created_at'), 'generated_by' => 'backfill']);
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('bill_master', 'generated_at')) {
            return;
        }

        $schema->table('bill_master', function (Blueprint $table) {
            $table->dropColumn(['generated_at', 'generated_by']);
        });
    }
};
