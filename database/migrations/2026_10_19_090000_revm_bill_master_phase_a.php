<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KLAES REV-M Bill Master, Phase A (docs/plans/KLAES_REVM_Bill_Master_Plan.md).
 *
 *   bill_master_types  legacy_bill removed (legacy has its own table); PHS
 *                      Onboarding / Subscription and PHS Token Top-up added;
 *                      settings_tab, accepts_payment, payment_methods.
 *   bill_master        legacy_ref (a module's old ref, kept when renumbering);
 *                      status "pending" becomes "unpaid".
 *   bill_legacy        legacy bills captured under File Indexing (LGB- refs).
 *   bill_items         one line per charge on a bill (system or legacy).
 *   bill_payments      payments that settle a bill, by Bill Ref ID.
 *   bill_receipts      official Ministry receipts (RCT- numbers, 3 copies).
 *
 * Only Bill Master's own tables are touched; they hold no bills yet.
 */
return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $db = DB::connection('sqlsrv');
        $now = now();

        // ---- bill_master_types -------------------------------------------------
        $schema->table('bill_master_types', function (Blueprint $table) use ($schema) {
            if (!$schema->hasColumn('bill_master_types', 'settings_tab')) {
                $table->string('settings_tab', 60)->nullable();
            }
            if (!$schema->hasColumn('bill_master_types', 'accepts_payment')) {
                $table->boolean('accepts_payment')->default(true);
            }
            if (!$schema->hasColumn('bill_master_types', 'payment_methods')) {
                $table->string('payment_methods', 200)->nullable(); // comma list: paystack,pos,bank_transfer
            }
        });

        if (!$db->table('bill_master')->where('bill_type_code', 'legacy_bill')->exists()) {
            $db->table('bill_master_types')->where('code', 'legacy_bill')->delete();
        }

        foreach ([
            ['phs_onboarding', 'PHS Onboarding / Subscription', 150],
            ['phs_token_topup', 'PHS Token Top-up', 160],
        ] as [$code, $name, $order]) {
            if (!$db->table('bill_master_types')->where('code', $code)->exists()) {
                $db->table('bill_master_types')->insert([
                    'code' => $code, 'name' => $name, 'bill_group' => 'automated', 'parent_code' => null,
                    'sort_order' => $order, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // Each type's Bill Settings sub-tab: its own code; Primary / PuA / SuA sit under Initial Bill.
        $db->table('bill_master_types')->whereNull('settings_tab')->update(['settings_tab' => DB::raw('code')]);
        $db->table('bill_master_types')->where('parent_code', 'initial_bill')->update(['settings_tab' => 'initial_bill']);
        $db->table('bill_master_types')->whereNull('payment_methods')->update(['payment_methods' => 'paystack,pos,bank_transfer']);

        // ---- bill_master -------------------------------------------------------
        if (!$schema->hasColumn('bill_master', 'legacy_ref')) {
            $schema->table('bill_master', function (Blueprint $table) {
                $table->string('legacy_ref', 100)->nullable()->index();
            });
        }

        $db->table('bill_master')->where('status', 'pending')->update(['status' => 'unpaid']);
        $this->setStatusDefault($db, 'bill_master', 'unpaid');

        // ---- bill_legacy -------------------------------------------------------
        if (!$schema->hasTable('bill_legacy')) {
            $schema->create('bill_legacy', function (Blueprint $table) {
                $table->id();
                $table->string('bill_ref_id', 100)->unique();      // LGB-YYYY-######
                $table->string('bill_type_code', 60)->index();     // bill_balance | grant_rent

                $table->string('file_number', 100)->nullable()->index();
                $table->unsignedBigInteger('file_indexing_id')->nullable()->index();

                $table->string('payer_name', 255)->nullable();
                $table->string('payer_phone', 30)->nullable();
                $table->string('payer_email', 150)->nullable();
                $table->string('payer_address', 500)->nullable();

                // Property: Plot No., District, LGA, Location
                $table->string('plot_no', 100)->nullable();
                $table->string('district', 240)->nullable();
                $table->string('lga', 240)->nullable();
                $table->string('location', 500)->nullable();

                $table->string('description', 500)->nullable();
                $table->decimal('amount', 18, 2)->default(0);
                $table->decimal('amount_paid', 18, 2)->default(0);
                $table->integer('from_year')->nullable();
                $table->integer('to_year')->nullable();
                $table->date('bill_date')->nullable();
                $table->string('status', 20)->default('unpaid');  // unpaid | part_paid | paid | cancelled

                // The receipt captured at indexing (legacy bills keep it).
                $table->string('transaction_id', 100)->nullable()->index();
                $table->string('receipt_no', 100)->nullable()->index();
                $table->date('receipt_date')->nullable();

                $table->string('source_table', 100)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->index(['source_table', 'source_id']);

                $table->text('notes')->nullable();
                $table->string('created_by', 255)->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->timestamps();
            });
        }

        // ---- bill_items --------------------------------------------------------
        if (!$schema->hasTable('bill_items')) {
            $schema->create('bill_items', function (Blueprint $table) {
                $table->id();
                $table->string('bill_ref_id', 100)->index();       // BILL- or LGB-
                $table->string('item_code', 80)->nullable();
                $table->string('name', 255);
                $table->decimal('quantity', 18, 4)->default(1);
                $table->decimal('rate', 18, 2)->default(0);
                $table->decimal('amount', 18, 2)->default(0);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // ---- bill_payments -----------------------------------------------------
        if (!$schema->hasTable('bill_payments')) {
            $schema->create('bill_payments', function (Blueprint $table) {
                $table->id();
                $table->string('bill_ref_id', 100)->index();
                $table->string('method', 30);                      // paystack | pos | bank_transfer
                $table->string('transaction_id', 100)->nullable()->index();
                $table->decimal('amount', 18, 2)->default(0);
                $table->dateTime('paid_at')->nullable();
                $table->string('status', 20)->default('pending'); // pending | successful | failed | rejected
                $table->text('gateway_response')->nullable();
                $table->string('recorded_by', 255)->nullable();
                $table->string('validated_by', 255)->nullable();
                $table->dateTime('validated_at')->nullable();
                $table->string('receipt_no', 100)->nullable()->index(); // official receipt, filled under Receipting
                $table->date('receipt_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // ---- bill_receipts -----------------------------------------------------
        if (!$schema->hasTable('bill_receipts')) {
            $schema->create('bill_receipts', function (Blueprint $table) {
                $table->id();
                $table->string('receipt_no', 100)->unique();       // RCT-YYYY-######
                $table->string('bill_ref_id', 100)->index();
                $table->unsignedBigInteger('bill_payment_id')->nullable()->index();
                $table->decimal('amount', 18, 2)->default(0);
                $table->string('status', 20)->default('issued');  // issued | cancelled
                $table->string('issued_by', 255)->nullable();
                $table->dateTime('issued_at')->nullable();
                $table->integer('copies_printed')->default(0);     // Original, Duplicate, Triplicate
                $table->integer('reprint_count')->default(0);
                $table->dateTime('last_printed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $db = DB::connection('sqlsrv');

        $schema->dropIfExists('bill_receipts');
        $schema->dropIfExists('bill_payments');
        $schema->dropIfExists('bill_items');
        $schema->dropIfExists('bill_legacy');

        if ($schema->hasColumn('bill_master', 'legacy_ref')) {
            $schema->table('bill_master', function (Blueprint $table) {
                $table->dropIndex('bill_master_legacy_ref_index');
            });
            $schema->table('bill_master', function (Blueprint $table) {
                $table->dropColumn('legacy_ref');
            });
        }
        $db->table('bill_master')->where('status', 'unpaid')->update(['status' => 'pending']);
        $this->setStatusDefault($db, 'bill_master', 'pending');

        $db->table('bill_master_types')->whereIn('code', ['phs_onboarding', 'phs_token_topup'])->delete();
        if (!$db->table('bill_master_types')->where('code', 'legacy_bill')->exists()) {
            $db->table('bill_master_types')->insert([
                'code' => 'legacy_bill', 'name' => 'All Legacy Bill', 'bill_group' => 'legacy', 'parent_code' => null,
                'sort_order' => 10, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach (['settings_tab', 'accepts_payment', 'payment_methods'] as $col) {
            if ($schema->hasColumn('bill_master_types', $col)) {
                $this->dropDefault($db, 'bill_master_types', $col);
                $schema->table('bill_master_types', fn (Blueprint $table) => $table->dropColumn($col));
            }
        }
    }

    /** SQL Server keeps a column default as a named constraint; swap it. */
    private function setStatusDefault($db, string $table, string $value): void
    {
        $this->dropDefault($db, $table, 'status');
        $db->unprepared("ALTER TABLE [{$table}] ADD CONSTRAINT [DF_{$table}_status] DEFAULT '{$value}' FOR [status]");
    }

    private function dropDefault($db, string $table, string $column): void
    {
        $name = $db->selectOne(
            'SELECT dc.name FROM sys.default_constraints dc
             JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id
             WHERE dc.parent_object_id = OBJECT_ID(?) AND c.name = ?',
            [$table, $column]
        );
        if ($name) {
            $db->unprepared("ALTER TABLE [{$table}] DROP CONSTRAINT [{$name->name}]");
        }
    }
};
