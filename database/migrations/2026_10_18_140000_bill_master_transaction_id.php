<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bill Master: the payment's Transaction ID (user, 2026-10-18), as on
 * instrument_payments: the ID on the deposit slip (bank / KANGIS teller) or
 * from the gateway (Paystack, Central Pay). Empty until the bill is paid.
 * Indexed for look-up, not unique: one teller payment can settle several bills.
 */
return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasTable('bill_master') || $schema->hasColumn('bill_master', 'transaction_id')) {
            return;
        }

        $schema->table('bill_master', function (Blueprint $table) {
            $table->string('transaction_id', 100)->nullable()->index();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('bill_master', 'transaction_id')) {
            return;
        }

        $schema->table('bill_master', function (Blueprint $table) {
            $table->dropIndex('bill_master_transaction_id_index');
        });

        $schema->table('bill_master', function (Blueprint $table) {
            $table->dropColumn('transaction_id');
        });
    }
};
