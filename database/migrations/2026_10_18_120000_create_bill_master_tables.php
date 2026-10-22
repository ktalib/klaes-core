<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KLAES REV-M: Bill Master, one register of every bill issued.
 *
 * bill_master_types is the list of bill categories from the user's hand-drawn
 * "Bill Master table" sheet (2026-10-18): All Legacy Bill, Bill Balance,
 * Betterment, Initial (Primary / PuA / SuA), OSS, 5% Deed of Purchase,
 * Consent, LuC, Fees Plan & Description, Legal Search (TTC), Contravention.
 * "Legacy" covers the bills captured under indexing (file_indexing_bills);
 * the rest sit under Automated Billing.
 *
 * bill_master holds one row per bill. source_table + source_id point back at
 * the module row it came from (billing, file_indexing_bills, ...). No backfill
 * here; the table starts empty. (bill_no became the required bill_ref_id in
 * 2026_10_18_130000_bill_master_bill_ref_id.)
 *
 * New tables only; nothing existing is changed.
 */
return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasTable('bill_master_types')) {
            $schema->create('bill_master_types', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique();
                $table->string('name', 200);
                $table->string('bill_group', 20); // automated | legacy
                $table->string('parent_code', 60)->nullable()->index();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            $now = now();
            $types = [
                ['legacy_bill', 'All Legacy Bill', 'legacy', null],
                ['bill_balance', 'Bill Balance', 'automated', null],
                ['betterment', 'Betterment Bill', 'automated', null],
                ['initial_bill', 'Initial Bill', 'automated', null],
                ['initial_primary', 'Primary', 'automated', 'initial_bill'],
                ['initial_pua', 'PuA', 'automated', 'initial_bill'],
                ['initial_sua', 'SuA', 'automated', 'initial_bill'],
                ['oss_bill', 'OSS Bill', 'automated', null],
                ['deed_of_purchase', '5% Deed of Purchase', 'automated', null],
                ['consent_bill', 'Consent Bill', 'automated', null],
                ['land_use_charge', 'Land Use Charge (LuC)', 'automated', null],
                ['fees_plan_description', 'Fees, Plan & Description Bill', 'automated', null],
                ['legal_search', 'Legal Search Bill (TTC)', 'automated', null],
                ['contravention_bill', 'Contravention Bill', 'automated', null],
            ];

            DB::connection('sqlsrv')->table('bill_master_types')->insert(array_map(
                fn ($t, $i) => [
                    'code' => $t[0], 'name' => $t[1], 'bill_group' => $t[2], 'parent_code' => $t[3],
                    'sort_order' => ($i + 1) * 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ],
                $types,
                array_keys($types)
            ));
        }

        if (!$schema->hasTable('bill_master')) {
            $schema->create('bill_master', function (Blueprint $table) {
                $table->id();
                $table->string('bill_no', 100)->nullable()->unique();
                $table->string('bill_type_code', 60)->index();

                $table->string('file_number', 100)->nullable()->index();
                $table->unsignedBigInteger('file_indexing_id')->nullable()->index();

                // Payer
                $table->string('payer_name', 255)->nullable();
                $table->string('payer_phone', 30)->nullable();
                $table->string('payer_email', 150)->nullable();
                $table->string('payer_address', 500)->nullable();

                // Property: Plot No., District, LGA, Location
                $table->string('plot_no', 100)->nullable();
                $table->string('district', 240)->nullable();
                $table->string('lga', 240)->nullable();
                $table->string('location', 500)->nullable();

                // Bill
                $table->string('description', 500)->nullable();
                $table->decimal('amount', 18, 2)->default(0);
                $table->decimal('amount_paid', 18, 2)->default(0);
                $table->integer('from_year')->nullable();
                $table->integer('to_year')->nullable();
                $table->date('bill_date')->nullable();
                $table->date('due_date')->nullable();
                $table->string('status', 20)->default('pending'); // pending | part_paid | paid | cancelled

                // Receipt
                $table->string('receipt_no', 100)->nullable()->index();
                $table->date('receipt_date')->nullable();

                // Where the bill came from (billing, file_indexing_bills, ...)
                $table->string('source_table', 100)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->index(['source_table', 'source_id']);

                $table->text('notes')->nullable();
                $table->string('created_by', 255)->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('bill_master');
        Schema::connection('sqlsrv')->dropIfExists('bill_master_types');
    }
};
