<?php

namespace Tests\Unit;

use App\Services\Survey\SurveyCompanyRegistry;
use PHPUnit\Framework\TestCase;

class SurveyCompanyRegistryTest extends TestCase
{
    // New prefixes start at AA; the single-letter gaps are held, not filled (user, 2026-11-12).
    public function test_new_company_prefixes_start_at_two_letters(): void
    {
        $this->assertSame('AA', SurveyCompanyRegistry::nextBeaconLetters(['A', 'B', 'C', 'D', 'E', 'G', 'H', 'I', 'K', 'S', 'T', 'Z']));
    }

    public function test_it_assigns_the_first_unused_two_letter_prefix(): void
    {
        $this->assertSame('AC', SurveyCompanyRegistry::nextBeaconLetters(['AA', 'ab', 'A']));
    }

    public function test_company_prefix_sequence_rolls_over_to_three_letters(): void
    {
        $used = [];
        foreach (range('A', 'Z') as $a) {
            foreach (range('A', 'Z') as $b) {
                $used[] = $a . $b;
            }
        }

        $this->assertSame('AAA', SurveyCompanyRegistry::nextBeaconLetters($used));
    }

    public function test_existing_prefixes_are_compared_without_case_or_outer_spaces(): void
    {
        $this->assertSame('AB', SurveyCompanyRegistry::nextBeaconLetters([' aa ']));
    }
}
