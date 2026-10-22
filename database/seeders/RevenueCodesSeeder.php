<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * KLAES REV-M: one Revenue Code per bill type (user, 2026-10-21: "the code should be
 * for the bill type, not bill items"). Primary / PuA / SuA use Initial Bill's.
 *
 * There is no Kano Revenue Sub-Heads sheet on the server (ALAES's register is Abia's),
 * so the codes are provisional, built from the bill type: 9 TT 0000, TT = the bill
 * type no. (Ground Rent 17, Re-grant 18). They start with 9 so they never clash with
 * the state's 4000xxx codes; replace them with the Ministry's in Bill Settings.
 *
 * Idempotent: a bill type that already has a code keeps it.
 *
 *     php artisan db:seed --class=RevenueCodesSeeder --force
 */
class RevenueCodesSeeder extends Seeder
{
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

    public function run(): void
    {
        $db = DB::connection('sqlsrv');
        $set = 0;
        foreach (self::CODES as $type => $code) {
            $set += $db->table('bill_master_types')->where('code', $type)->whereNull('revenue_code')
                ->update(['revenue_code' => $code, 'updated_at' => now()]);
        }
        $this->command?->info("Revenue Codes: {$set} bill type(s) given a code; the others already had one.");
    }
}
