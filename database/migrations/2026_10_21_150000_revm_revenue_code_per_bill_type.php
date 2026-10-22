<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KLAES REV-M (user, 2026-10-21): "the code should be for the bill type, not bill items".
 *
 *   - bill_master_types.revenue_code: ONE Revenue Code per bill type (Primary / PuA / SuA
 *     use Initial Bill's). Provisional codes 9 TT 0000 (TT = the bill type no., as
 *     chosen earlier the same day), to be replaced by the Ministry's.
 *   - The 48 per-charge codes seeded earlier (revenue_items rows by "Revenue Codes seeder
 *     (provisional)", unused by any bill; CSV backup in Downloads) and the
 *     bill_type_revenue_items links are removed.
 */
return new class extends Migration {
    public const SEEDED_BY = 'Revenue Codes seeder (provisional)';

    public const CODES = [
        'bill_balance' => '9020000',
        'betterment' => '9030000',
        'initial_bill' => '9040000',
        'oss_bill' => '9080000',
        'deed_of_purchase' => '9090000',
        'consent_bill' => '9100000',
        'land_use_charge' => '9110000',
        'fees_plan_description' => '9120000',
        'legal_search' => '9130000',
        'contravention_bill' => '9140000',
        'phs_onboarding' => '9150000',
        'phs_token_topup' => '9160000',
        'ground_rent' => '9170000',
        're_grant' => '9180000',
    ];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $db = DB::connection('sqlsrv');

        if (!$schema->hasColumn('bill_master_types', 'revenue_code')) {
            $schema->table('bill_master_types', fn (Blueprint $t) => $t->string('revenue_code', 20)->nullable());
            $db->statement('CREATE UNIQUE NONCLUSTERED INDEX UX_bill_master_types_revenue_code ON dbo.bill_master_types (revenue_code) WHERE revenue_code IS NOT NULL');
        }
        foreach (self::CODES as $type => $code) {
            $db->table('bill_master_types')->where('code', $type)->whereNull('revenue_code')->update(['revenue_code' => $code, 'updated_at' => now()]);
        }

        // The per-charge codes are no longer wanted. Only the seeder's own rows, and only if unused.
        if ($schema->hasTable('bill_type_revenue_items')) {
            $ids = $db->table('revenue_items')->where('updated_by_name', self::SEEDED_BY)->pluck('id');
            $codes = $db->table('revenue_items')->whereIn('id', $ids)->pluck('revenue_code');
            $used = $db->table('bill_items')->whereIn('item_code', $codes)->exists()
                || $db->table('billing_type_items')->whereIn('revenue_item_id', $ids)->exists();
            if (!$used) {
                $db->table('bill_type_revenue_items')->delete();
                $db->table('revenue_items')->whereIn('id', $ids)->delete();
                $schema->drop('bill_type_revenue_items');
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('bill_master_types', 'revenue_code')) {
            DB::connection('sqlsrv')->statement('DROP INDEX IF EXISTS UX_bill_master_types_revenue_code ON dbo.bill_master_types');
            $schema->table('bill_master_types', fn (Blueprint $t) => $t->dropColumn('revenue_code'));
        }
    }
};
