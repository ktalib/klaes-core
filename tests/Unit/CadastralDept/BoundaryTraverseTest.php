<?php

namespace Tests\Unit\CadastralDept;

use App\Services\CadastralDept\BoundaryTraverse;
use PHPUnit\Framework\TestCase;

/** Plan Description boundary lines (change request 26-10-2026): area from bearings and distances. */
class BoundaryTraverseTest extends TestCase
{
    private function line(float $d, int $deg, int $min = 0, float $sec = 0): array
    {
        return ['distance_m' => $d, 'bearing_deg' => $deg, 'bearing_min' => $min, 'bearing_sec' => $sec];
    }

    public function test_a_closed_square_gives_its_area_and_no_gap(): void
    {
        $m = BoundaryTraverse::measure([$this->line(50, 0), $this->line(50, 90), $this->line(50, 180), $this->line(50, 270)]);

        $this->assertSame(2500.0, $m['area_sqm']);
        $this->assertSame(0.0, $m['misclosure_m']);
    }

    public function test_a_right_triangle_with_minutes_and_seconds(): void
    {
        // 30 m north, 40 m east, back on 233° 07' 48.37" (the 3-4-5 hypotenuse).
        $m = BoundaryTraverse::measure([$this->line(30, 0), $this->line(40, 90), $this->line(50, 233, 7, 48.37)]);

        $this->assertEqualsWithDelta(600.0, $m['area_sqm'], 0.05);
        $this->assertLessThan(0.01, $m['misclosure_m']);
    }

    public function test_an_open_shape_is_measured_not_refused(): void
    {
        // Irregular plot that does not close: area from the corners reached, gap reported.
        $m = BoundaryTraverse::measure([$this->line(50, 0), $this->line(50, 90), $this->line(40, 180)]);

        $this->assertSame(2250.0, $m['area_sqm']);
        $this->assertEqualsWithDelta(50.99, $m['misclosure_m'], 0.01);
    }

    public function test_fewer_than_three_lines_or_a_missing_bearing_gives_no_area(): void
    {
        $this->assertNull(BoundaryTraverse::measure([$this->line(50, 0), $this->line(50, 90)])['area_sqm']);
        $this->assertNull(BoundaryTraverse::measure([$this->line(50, 0), $this->line(50, 90),
            ['distance_m' => 50, 'bearing_deg' => null, 'bearing_min' => null, 'bearing_sec' => null]])['area_sqm']);
    }

    public function test_bearing_format_matches_the_form(): void
    {
        $this->assertSame("71° 57'", BoundaryTraverse::format(71, 57, 0));
        $this->assertSame("158° 05' 30\"", BoundaryTraverse::format(158, 5, 30));
        $this->assertSame('', BoundaryTraverse::format(null, null, null));
    }
}
