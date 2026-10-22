<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KLAES REV-M (user, 2026-10-21): payment cycles for recurring bills, reconciliation
 * of payments, and on-the-fly bills. Additive only; no existing row changes.
 *
 *   bill_master_types  is_recurring, payment_cycle, remind_days_before (Bill Settings)
 *   bill_master        payment_cycle, period_start/end, next_due_date, previous_bill_ref,
 *                      raised_via ('on_the_fly' | 'cycle')
 *   bill_payments      reconciliation_status / _note, reconciled_at / _by, settlement_ref
 */
return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('bill_master_types', 'is_recurring')) {
            $schema->table('bill_master_types', function (Blueprint $table) {
                $table->boolean('is_recurring')->default(false);
                $table->string('payment_cycle', 20)->nullable();
                $table->integer('remind_days_before')->default(7);
            });
        }

        if (!$schema->hasColumn('bill_master', 'payment_cycle')) {
            $schema->table('bill_master', function (Blueprint $table) {
                $table->string('payment_cycle', 20)->nullable();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->date('next_due_date')->nullable();
                $table->string('previous_bill_ref', 40)->nullable();
                $table->string('raised_via', 30)->nullable();
                $table->index('next_due_date', 'IX_bill_master_next_due_date');
            });
        }

        if (!$schema->hasColumn('bill_payments', 'reconciliation_status')) {
            $schema->table('bill_payments', function (Blueprint $table) {
                $table->string('reconciliation_status', 20)->nullable();
                $table->dateTime('reconciled_at')->nullable();
                $table->string('reconciled_by', 150)->nullable();
                $table->string('reconciliation_note', 500)->nullable();
                $table->string('settlement_ref', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('bill_payments', 'reconciliation_status')) {
            $schema->table('bill_payments', fn (Blueprint $t) => $t->dropColumn(['reconciliation_status', 'reconciled_at', 'reconciled_by', 'reconciliation_note', 'settlement_ref']));
        }
        if ($schema->hasColumn('bill_master', 'payment_cycle')) {
            $schema->table('bill_master', function (Blueprint $t) {
                $t->dropIndex('IX_bill_master_next_due_date');
                $t->dropColumn(['payment_cycle', 'period_start', 'period_end', 'next_due_date', 'previous_bill_ref', 'raised_via']);
            });
        }
        if ($schema->hasColumn('bill_master_types', 'is_recurring')) {
            $schema->table('bill_master_types', fn (Blueprint $t) => $t->dropColumn(['is_recurring', 'payment_cycle', 'remind_days_before']));
        }
    }
};
