<?php

namespace Tests\Unit;

use App\Support\SurveyGeometry as G;
use PHPUnit\Framework\TestCase;

class SurveyGeometryTest extends TestCase
{
    public function test_dms_round_trip(): void
    {
        $this->assertSame("123°45'30.0\"", G::dms(G::fromDms(123, 45, 30)));
        $this->assertSame("0°00'00.0\"", G::dms(360.0));
        $this->assertSame("359°59'59.9\"", G::dms(359.99997));
    }

    public function test_out_of_range_dms_is_refused(): void
    {
        $this->assertNull(G::fromDms(360, 0, 0));
        $this->assertNull(G::fromDms(10, 60, 0));
        $this->assertNull(G::fromDms(10, 0, 60));
        $this->assertNull(G::fromDms('x', 0, 0));
    }

    public function test_bearing_difference_wraps_at_north(): void
    {
        $this->assertEqualsWithDelta(20, G::bearingDiffSeconds(0.0 + 10 / 3600, 360 - 10 / 3600), 1e-6);
        $this->assertEqualsWithDelta(-3600, G::bearingDiffSeconds(89.0, 90.0), 1e-6);
    }

    public function test_square_area_and_perimeter(): void
    {
        $p = G::polygon([[0, 0], [100, 0], [100, 100], [0, 100]]);
        $this->assertEqualsWithDelta(10000, $p['area'], 1e-6);
        $this->assertEqualsWithDelta(400, $p['perimeter'], 1e-6);
        $this->assertNull(G::polygon([[0, 0], [1, 1]])['area']);
    }

    public function test_closed_traverse_misclosure(): void
    {
        // A 100 m square walked N, E, S, W closes exactly.
        $m = G::misclosure([[0, 100], [90, 100], [180, 100], [270, 100]]);
        $this->assertEqualsWithDelta(0, $m['linear'], 1e-9);
        $this->assertNull($m['precision']);

        // The last leg 2 cm short: 0.02 m misclosure over 399.98 m, about 1 : 20 000.
        $m = G::misclosure([[0, 100], [90, 100], [180, 100], [270, 99.98]]);
        $this->assertEqualsWithDelta(0.02, $m['linear'], 1e-9);
        $this->assertEqualsWithDelta(19999, $m['precision'], 1);
    }
}
