<?php

namespace Tests\Unit\Services;

use App\Services\Survey\CompensationListReader;
use App\Services\Survey\KanoLgaMapCenters;
use Tests\TestCase;

class KanoLgaMapCentersTest extends TestCase
{
    public function test_every_portal_lga_has_an_in_state_map_center(): void
    {
        $this->assertEqualsCanonicalizing(CompensationListReader::LGAS, array_keys(KanoLgaMapCenters::CENTERS));

        foreach (KanoLgaMapCenters::CENTERS as [$latitude, $longitude]) {
            $this->assertGreaterThanOrEqual(10.5, $latitude);
            $this->assertLessThanOrEqual(12.7, $latitude);
            $this->assertGreaterThanOrEqual(7.6, $longitude);
            $this->assertLessThanOrEqual(9.4, $longitude);
        }
    }
}
