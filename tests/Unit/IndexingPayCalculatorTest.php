<?php

namespace Tests\Unit;

use App\Services\IndexingPay\EarningsService;
use App\Services\IndexingPay\IndexingPaySettings;
use Tests\TestCase;

/**
 * The approved indexing rate card (Kunle, 2026-11-01) and its worked examples.
 * Pure arithmetic: no database.
 */
class IndexingPayCalculatorTest extends TestCase
{
    private function card(array $over = []): array
    {
        return IndexingPaySettings::normalise($over);
    }

    /** Pay for a file + its records, as the card's formula writes it. */
    private function pay(array $card, string $type, int $gr, int $bb, int $trans): float
    {
        return EarningsService::amountFor($card, 'file', $type)
            + $gr * EarningsService::amountFor($card, 'gr', $type)
            + $bb * EarningsService::amountFor($card, 'bb', $type)
            + $trans * EarningsService::amountFor($card, 'trans', $type);
    }

    public function test_the_approved_card_is_the_default(): void
    {
        $c = $this->card();
        $this->assertSame(['regular' => 20.0, 'sltr' => 35.0, 'kangis_land' => 35.0, 'kangis' => 40.0, 'itf' => 35.0], $c['base']);
        $this->assertSame(['gr' => 2.0, 'bb' => 2.0, 'trans' => 3.0], $c['rates']);
        $this->assertFalse($c['sltr_pays_gr_bb']);
        $this->assertSame('2026-11-02', $c['start_date']);
        $this->assertSame('2026-12-31', $c['end_date']);
    }

    public function test_worked_example_1_regular_file(): void
    {
        // 20 + 4 + 4 + 9 = 37
        $this->assertSame(37.0, $this->pay($this->card(), 'regular', 2, 2, 3));
    }

    public function test_worked_example_2_sltr_file(): void
    {
        // 35 + 15 = 50
        $this->assertSame(50.0, $this->pay($this->card(), 'sltr', 0, 0, 5));
    }

    public function test_sltr_ground_rent_and_bill_balance_are_not_paid(): void
    {
        $this->assertSame(50.0, $this->pay($this->card(), 'sltr', 4, 3, 5));
        $this->assertSame(64.0, $this->pay($this->card(['sltr_pays_gr_bb' => true]), 'sltr', 4, 3, 5));
    }

    public function test_worked_example_3_itf_rate_when_it_is_switched_on(): void
    {
        // 35 + 8 + 6 + 30 = 79 (ITF is dormant; the stored fee is still the approved one).
        $this->assertSame(79.0, $this->pay($this->card(), 'itf', 4, 3, 10));
    }

    public function test_worked_example_4_kangis_bundle(): void
    {
        $land = $this->pay($this->card(), 'kangis_land', 2, 2, 4);   // 35+4+4+12
        $kangis = $this->pay($this->card(), 'kangis', 1, 1, 2);      // 40+2+2+6
        $this->assertSame(55.0, $land);
        $this->assertSame(50.0, $kangis);
        $this->assertSame(105.0, $land + $kangis);
    }

    public function test_large_bundle_is_205_not_209(): void
    {
        $land = $this->pay($this->card(), 'kangis_land', 5, 5, 15);
        $kangis = $this->pay($this->card(), 'kangis', 5, 5, 15);
        $this->assertSame(100.0, $land);
        $this->assertSame(105.0, $kangis);
        $this->assertSame(205.0, $land + $kangis);
    }

    public function test_two_land_files_in_a_bundle_are_each_priced_on_their_own_records(): void
    {
        $total = $this->pay($this->card(), 'kangis_land', 2, 2, 4)   // 55
            + $this->pay($this->card(), 'kangis_land', 0, 0, 1)      // 38
            + $this->pay($this->card(), 'kangis', 1, 1, 2);          // 50
        $this->assertSame(143.0, $total);
    }

    public function test_file_type_rules(): void
    {
        $this->assertSame('kangis', EarningsService::fileType('KANGIS', 'KNML 123', 0));
        $this->assertSame('kangis', EarningsService::fileType('kangis', 'MLKN 1', 3));
        $this->assertSame('sltr', EarningsService::fileType('SLTR', 'SLTR-RES-2020-1', 0));
        $this->assertSame('sltr', EarningsService::fileType(null, 'SLTR-COM-2019-44', 0));
        $this->assertSame('kangis_land', EarningsService::fileType('2', 'RES-2014-241', 2));
        $this->assertSame('regular', EarningsService::fileType('2', 'RES-2014-1203', 0));
        $this->assertSame('regular', EarningsService::fileType(null, 'CON-COM-2015-9', null));
        // ITF is dormant: nothing is ever classed as ITF.
        $this->assertSame('regular', EarningsService::fileType('In-transit', 'RES-2020-5', 0));
    }

    public function test_totals_add_up(): void
    {
        $item = fn ($kind, $type, $amount) => ['kind' => $kind, 'type' => $type, 'amount' => $amount, 'date' => '2026-11-02'];
        $t = EarningsService::totals([
            $item('file', 'regular', 20.0), $item('file', 'sltr', 35.0),
            $item('trans', 'regular', 3.0), $item('trans', 'sltr', 3.0),
            $item('gr', 'regular', 2.0), $item('bb', 'sltr', 0.0),
        ]);
        $this->assertSame(63.0, $t['amount']);
        $this->assertSame(2, $t['files']);
        $this->assertSame(4, $t['records']);
        $this->assertSame(55.0, $t['base_amount']);
        $this->assertSame(8.0, $t['records_amount']);
        $this->assertSame(2, $t['by_record']['trans']['count']);
        $this->assertSame(1, $t['by_record']['bb']['count']);
        $this->assertSame(0.0, $t['by_record']['bb']['amount']);
    }

    public function test_normalise_never_trusts_bad_values(): void
    {
        $c = $this->card(['base' => ['regular' => '-5', 'sltr' => 'abc'], 'rates' => ['trans' => '3.50'], 'start_date' => 'nonsense', 'eligible_modules' => ' Indexing , ,Extra ']);
        $this->assertSame(0.0, $c['base']['regular']);
        $this->assertSame(0.0, $c['base']['sltr']);
        $this->assertSame(3.5, $c['rates']['trans']);
        $this->assertSame('2026-11-02', $c['start_date']);
        $this->assertSame(['Indexing', 'Extra'], $c['eligible_modules']);
    }
}
