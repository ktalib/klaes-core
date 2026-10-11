<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPAS Bill Items: items added to a bill only when needed.
 *
 * A Penalty Fee is not part of every contravention bill — the officer adds it
 * to a particular bill from the Generate Bill form, at the amount set on the
 * tariff. Such items are flagged is_on_demand and are never composed into a
 * bill automatically.
 *
 * The existing "Penalty Fee" item is flagged here; it was already on the tariff
 * at 0, which is why it had never been charged.
 *
 * Additive only.
 */
return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasColumn('spa_bill_items', 'is_on_demand')) {
            $schema->table('spa_bill_items', fn (Blueprint $table) => $table->boolean('is_on_demand')->default(false));
        }

        DB::connection('sqlsrv')->table('spa_bill_items')
            ->where('name', 'Penalty Fee')
            ->update(['is_on_demand' => 1]);
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('spa_bill_items', 'is_on_demand')) {
            // SQL Server keeps a default constraint on the column; Laravel drops it with the column.
            $schema->table('spa_bill_items', fn (Blueprint $table) => $table->dropColumn('is_on_demand'));
        }
    }
};
