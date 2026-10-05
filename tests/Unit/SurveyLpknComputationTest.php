<?php

namespace Tests\Unit;

use App\Http\Controllers\Survey\LpknController;
use PHPUnit\Framework\TestCase;

class SurveyLpknComputationTest extends TestCase
{
    /** @dataProvider cardinalDirections */
    public function test_grid_bearings_are_clockwise_from_north($north, $east, $bearing): void
    {
        $leg = LpknController::computeLeg(0, 0, $north, $east);
        $this->assertEqualsWithDelta(10, $leg['distance'], 0.000001);
        $this->assertEqualsWithDelta($bearing, $leg['bearing'], 0.000001);
    }

    public function cardinalDirections(): array
    {
        return [[10, 0, 0], [0, 10, 90], [-10, 0, 180], [0, -10, 270]];
    }

    public function test_diagonal_distance_and_bearing(): void
    {
        $leg = LpknController::computeLeg(100, 200, 103, 204);
        $this->assertEqualsWithDelta(5, $leg['distance'], 0.000001);
        $this->assertEqualsWithDelta(53.130102, $leg['bearing'], 0.000001);
    }

    public function test_missing_and_coincident_coordinates_do_not_invent_bearings(): void
    {
        $this->assertSame(['distance' => null, 'bearing' => null], LpknController::computeLeg(null, 0, 1, 1));
        $leg = LpknController::computeLeg(0, 0, 0, 0);
        $this->assertEquals(0, $leg['distance']);
        $this->assertNull($leg['bearing']);
    }
}
