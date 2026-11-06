<?php

namespace Tests\Unit;

use App\Services\Survey\SurveyCompanyRegistry;
use PHPUnit\Framework\TestCase;

class SurveyCompanyRegistryTest extends TestCase
{
    public function test_it_assigns_the_first_unused_company_beacon_prefix(): void
    {
        $this->assertSame('E', SurveyCompanyRegistry::nextBeaconLetters(['G', 'b', 'D', 'A', 'C']));
    }

    public function test_company_prefix_sequence_rolls_over_to_two_letters(): void
    {
        $used = array_map(fn ($letter) => chr($letter), range(65, 90));

        $this->assertSame('AA', SurveyCompanyRegistry::nextBeaconLetters($used));
    }

    public function test_existing_prefixes_are_compared_without_case_or_outer_spaces(): void
    {
        $this->assertSame('B', SurveyCompanyRegistry::nextBeaconLetters([' a ']));
    }
}
