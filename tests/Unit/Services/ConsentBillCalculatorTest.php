<?php

namespace Tests\Unit\Services;

use App\Services\ConsentBillCalculator;
use Tests\TestCase;

/**
 * The transaction-type rates, checked against the approved specimen letters
 * (COM-2023-652, consideration ₦20,000,000.00).
 */
class ConsentBillCalculatorTest extends TestCase
{
    private function bill(?string $type): array
    {
        return app(ConsentBillCalculator::class)->compute(20000000, $type);
    }

    public function test_individual_to_individual_matches_the_specimen(): void
    {
        $bill = $this->bill('individual_to_individual');

        $this->assertEquals(1000000, $bill['registration_fee']);
        $this->assertEquals(12000, $bill['processing_fee']);
        $this->assertEquals(1012000, $bill['bill_total']);
        $this->assertEquals(600000, $bill['stamp_duty_amount']);
        $this->assertSame('KIRS', $bill['stamp_duty_payee']);
    }

    public function test_company_transactions_pay_one_and_a_half_percent_to_firs(): void
    {
        foreach (['individual_to_company', 'company_to_individual', 'company_to_company'] as $type) {
            $bill = $this->bill($type);

            $this->assertEquals(1012000, $bill['bill_total'], $type);
            $this->assertEquals(300000, $bill['stamp_duty_amount'], $type);
            $this->assertSame('FIRS', $bill['stamp_duty_payee'], $type);
        }
    }

    public function test_mortgage_matches_the_specimen(): void
    {
        $bill = $this->bill('mortgage');

        // Processing ₦32,000 and stamp duty to FIRS, as corrected by the Ministry.
        $this->assertEquals(400000, $bill['registration_fee']);
        $this->assertEquals(32000, $bill['processing_fee']);
        $this->assertEquals(432000, $bill['bill_total']);
        $this->assertEquals(75000, $bill['stamp_duty_amount']);
        $this->assertSame('FIRS', $bill['stamp_duty_payee']);
    }

    public function test_gift_is_billed_as_individual_to_individual(): void
    {
        $gift = $this->bill('gift');
        $individual = $this->bill('individual_to_individual');

        unset($gift['stamp_duty_payee'], $individual['stamp_duty_payee']);
        $this->assertEquals($individual, $gift);
        $this->assertSame('Gift', ConsentBillCalculator::transactionType('gift')['consent_type']);
    }

    public function test_stamp_duty_is_never_part_of_a_typed_total(): void
    {
        foreach (array_keys(config('consent_bill.transaction_types')) as $type) {
            $bill = $this->bill($type);

            $this->assertEquals(
                round($bill['registration_fee'] + $bill['processing_fee'], 2),
                $bill['bill_total'],
                $type
            );
        }
    }

    public function test_an_untyped_consent_keeps_the_legacy_bill(): void
    {
        $bill = $this->bill(null);

        $this->assertNull($bill['stamp_duty_payee']);
        $this->assertEquals(
            round($bill['stamp_duty_amount'] + $bill['registration_fee'] + $bill['processing_fee'], 2),
            $bill['bill_total']
        );
        $this->assertNull(ConsentBillCalculator::transactionType('bogus'));
    }

    public function test_an_edited_percentage_replaces_the_type_rate(): void
    {
        $bill = app(ConsentBillCalculator::class)->compute(20000000, 'individual_to_individual', ['stamp_duty' => 2.5]);

        $this->assertEquals(500000, $bill['stamp_duty_amount']);
        $this->assertEquals(2.5, $bill['stamp_duty_rate']);
        // The other rate, and the total, are untouched.
        $this->assertEquals(1000000, $bill['registration_fee']);
        $this->assertEquals(1012000, $bill['bill_total']);

        $bill = app(ConsentBillCalculator::class)->compute(20000000, 'mortgage', ['registration' => 3]);
        $this->assertEquals(600000, $bill['registration_fee']);
        $this->assertEquals(632000, $bill['bill_total']);
    }

    public function test_rates_print_without_rounding_the_mortgage_stamp_duty(): void
    {
        $this->assertSame('0.375%', ConsentBillCalculator::formatRate(0.375));
        $this->assertSame('1.5%', ConsentBillCalculator::formatRate('1.5000'));
        $this->assertSame('5%', ConsentBillCalculator::formatRate(5));
    }
}
