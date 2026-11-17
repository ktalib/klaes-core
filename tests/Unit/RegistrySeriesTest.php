<?php

namespace Tests\Unit;

use App\Support\RegistrySeries;
use PHPUnit\Framework\TestCase;

/** Scan Upload's registry check (2026-11-16), same rules as the Blind Scan page. */
class RegistrySeriesTest extends TestCase
{
    public function test_st_numbers_belong_in_the_st_registry(): void
    {
        $this->assertNull(RegistrySeries::mismatch('ST-RES-2026-2', 'ST Registry'));
        $this->assertNull(RegistrySeries::mismatch('ST-COM-2025-1-001', 'ST Registry'));

        $m = RegistrySeries::mismatch('ST-RES-2026-2', 'Lands Registry');
        $this->assertSame('block', $m['level']);
        $this->assertSame('ST Registry', $m['expected_name']);

        $this->assertSame('block', RegistrySeries::mismatch('ST-RES-2026-2', 'SLTR Registry')['level']);
    }

    public function test_a_lands_number_in_a_series_registry_is_confirmed(): void
    {
        $m = RegistrySeries::mismatch('RES-2025-115', 'ST Registry');
        $this->assertSame('warn', $m['level']);
        $this->assertSame('Lands Registry', $m['expected_name']);
        $this->assertNull(RegistrySeries::mismatch('RES-2025-115', 'Lands Registry'));
    }

    public function test_registries_that_hold_other_numbers_are_never_checked(): void
    {
        foreach (['Cadastral Registry', 'Secret Registry', 'Deeds Registry'] as $registry) {
            $this->assertNull(RegistrySeries::mismatch('ST-RES-2026-2', $registry));
        }
        $this->assertNull(RegistrySeries::mismatch('CO-9-2018', 'ST Registry'));   // unknown series
    }

    public function test_sit_numbers_are_kept_under_lands(): void
    {
        $this->assertNull(RegistrySeries::mismatch('SIT-2001-122', 'Lands Registry'));
        $m = RegistrySeries::mismatch('SIT-2001-122', 'ST Registry');
        $this->assertSame('block', $m['level']);
        $this->assertSame('Lands Registry', $m['expected_name']);
    }
}
