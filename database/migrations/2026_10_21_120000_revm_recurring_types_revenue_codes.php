<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KLAES REV-M (user, 2026-10-21):
 *   - recurring bill types: Ground Rent and Re-grant, on an annual payment cycle
 *   - "the code should be based on the bill type": each bill type has its own Revenue
 *     Codes (bill_type_revenue_items → revenue_items); an on-the-fly bill offers only
 *     the codes of the bill type chosen.
 */
return new class extends Migration {
    private const TYPES = [
        ['code' => 'ground_rent', 'name' => 'Ground Rent', 'sort_order' => 112],
        ['code' => 're_grant', 'name' => 'Re-grant', 'sort_order' => 114],
    ];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $db = DB::connection('sqlsrv');

        if (!$schema->hasTable('bill_type_revenue_items')) {
            $schema->create('bill_type_revenue_items', function (Blueprint $table) {
                $table->id();
                $table->string('bill_type_code', 60);
                $table->unsignedBigInteger('revenue_item_id');
                $table->integer('sort_order')->default(0);
                $table->string('created_by', 150)->nullable();
                $table->timestamps();
                $table->unique(['bill_type_code', 'revenue_item_id'], 'UX_bill_type_revenue_items');
                $table->index('revenue_item_id', 'IX_bill_type_revenue_items_item');
            });
        }

        foreach (self::TYPES as $type) {
            if ($db->table('bill_master_types')->where('code', $type['code'])->exists()) {
                continue;
            }
            $db->table('bill_master_types')->insert($type + [
                'bill_group' => 'automated',
                'parent_code' => null,
                'is_active' => 1,
                'settings_tab' => $type['code'],
                'accepts_payment' => 1,
                'payment_methods' => 'paystack,pos,bank_transfer',
                'is_recurring' => 1,
                'payment_cycle' => 'annual',
                'remind_days_before' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $db = DB::connection('sqlsrv');
        foreach (self::TYPES as $type) {
            if (!$db->table('bill_master')->where('bill_type_code', $type['code'])->exists()) {
                $db->table('bill_master_types')->where('code', $type['code'])->delete();
            }
        }
        Schema::connection('sqlsrv')->dropIfExists('bill_type_revenue_items');
    }
};
