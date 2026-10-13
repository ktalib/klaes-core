<?php

namespace Tests\Unit;

use App\Services\StInitialBillSchedule;
use PHPUnit\Framework\TestCase;

class StInitialBillScheduleTest extends TestCase
{
    public function test_residential_is_the_default(): void
    {
        $expected = ['application_fee' => 10000.0, 'processing_fee' => 20000.0, 'site_plan_fee' => 10000.0];

        $this->assertSame($expected, StInitialBillSchedule::forLandUse('Residential'));
        $this->assertSame($expected, StInitialBillSchedule::forLandUse(null));
        $this->assertSame($expected, StInitialBillSchedule::forLandUse(''));
    }

    public function test_commercial_and_industrial_use_commercial_rates_case_insensitively(): void
    {
        foreach (['Commercial', 'COMMERCIAL', 'Industrial'] as $landUse) {
            $this->assertSame(80000.0, StInitialBillSchedule::total($landUse), $landUse);
        }
    }

    public function test_mixed_is_residential_plus_commercial(): void
    {
        foreach (['Mixed', 'Mixed Use', 'MIXED'] as $landUse) {
            $this->assertTrue(StInitialBillSchedule::isMixed($landUse));
            $this->assertSame(
                ['application_fee' => 30000.0, 'processing_fee' => 70000.0, 'site_plan_fee' => 20000.0],
                StInitialBillSchedule::forLandUse($landUse)
            );
        }
    }

    public function test_no_fee_reaches_a_million(): void
    {
        foreach (['Residential', 'Commercial', 'Industrial', 'Mixed'] as $landUse) {
            foreach (StInitialBillSchedule::forLandUse($landUse) as $fee => $amount) {
                $this->assertLessThan(1000000, $amount, "$landUse $fee");
            }
        }
    }
}
