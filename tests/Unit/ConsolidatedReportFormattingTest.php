<?php

namespace Tests\Unit;

use App\Support\ConsolidatedReportFormatting as Format;
use PHPUnit\Framework\TestCase;

class ConsolidatedReportFormattingTest extends TestCase
{
    public function test_structured_location_excludes_plot_and_street(): void
    {
        $this->assertSame('NASSARAWA, NASSARAWA, KANO', Format::location(
            'Nassarawa', 'Nassarawa', 'Kano', 'Plot 213, Road 5, Nassarawa, Nassarawa, Kano', '213'
        ));
    }

    public function test_legacy_location_recovers_only_the_administrative_suffix(): void
    {
        $this->assertSame('TUKUNTAWA, KANO MUNICIPAL, KANO', Format::location(null, 'Kano Municipal', null, '213, Road 5, Tukuntawa, Kano Municipal, Kano', '213'));
        $this->assertSame('KUMBOTSO, KANO STATE', Format::location(null, null, null, '1540, Kumbotso, Kano State', '1540'));
    }

    public function test_unknown_free_text_is_not_used_as_a_district(): void
    {
        $this->assertSame('KUMBOTSO', Format::location(null, 'Kumbotso', null, 'Plot 1540 on Road 5'));
    }

    public function test_oss_two_part_location_uses_saved_property_lga_and_state(): void
    {
        $this->assertSame('TSAMIYAR KARA -PHASE II, GEZAWA, KANO', Format::location(
            null, 'Gezawa', 'Kano', 'TSAMIYAR KARA -PHASE II, GEZAWA', '2832'
        ));
        $this->assertSame('GEZAWA, KANO', Format::location(null, 'Gezawa', 'Kano', 'Plot 2832, GEZAWA', '2832'));
    }

    public function test_caps_preserve_numbers_and_dates(): void
    {
        $this->assertSame(['name' => 'AMINA BELLO', 'amount' => 2696, 'date' => '2026-10-04'], Format::row([
            'name' => 'Amina Bello', 'amount' => 2696, 'date' => '2026-10-04',
        ]));
    }
}
