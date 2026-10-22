<?php

namespace Tests\Unit;

use App\Support\ParcelSizeSummary;
use PHPUnit\Framework\TestCase;

class ParcelSizeSummaryGroupingTest extends TestCase
{
    public function test_identical_sizes_are_counted_not_repeated(): void
    {
        $s = ParcelSizeSummary::fromValues(array_fill(0, 82, 450));

        $this->assertSame(' of 450 m² each (total 36,900 m²)', $s['phrase']);
        $this->assertSame(' measuring 450 m² each (total 36,900 m²)', $s['total_phrase']);
        $this->assertSame('82 × 450', $s['summary']);
    }

    public function test_mixed_repeats_are_grouped_with_a_total(): void
    {
        $s = ParcelSizeSummary::fromValues([450, 450, 600, 450, 1000]);

        $this->assertSame(' of 3 × 450 + 600 + 1,000 m² (total 2,950 m²)', $s['phrase']);
    }

    public function test_distinct_sizes_read_as_before(): void
    {
        $s = ParcelSizeSummary::of([['plot_size' => 1500], ['plot_size' => 2000], ['plot_size' => 3000]]);

        $this->assertSame(' of 1,500 + 2,000 + 3,000 m²', $s['phrase']);
        $this->assertSame(' measuring 1,500 + 2,000 + 3,000 m² (total 6,500 m²)', $s['total_phrase']);
        $this->assertSame(' of 1,500 m²', ParcelSizeSummary::fromValues([1500])['phrase']);
        $this->assertFalse(ParcelSizeSummary::fromValues([null, '', 0])['has']);
    }

    public function test_letters_run_past_z(): void
    {
        $this->assertSame('A', ParcelSizeSummary::letter(0));
        $this->assertSame('Z', ParcelSizeSummary::letter(25));
        $this->assertSame('AA', ParcelSizeSummary::letter(26));
        $this->assertSame('CD', ParcelSizeSummary::letter(81));
    }

    public function test_identical_dimensions_are_stated_once(): void
    {
        $this->assertSame(
            ['A – CD (82 parcels): 15.00m x 30.00m each.'],
            ParcelSizeSummary::groupedDimensionLines(array_fill(0, 82, '15.00m x 30.00m'))
        );

        $this->assertSame(
            ['A, C (2 parcels): 60m x 21m each.', 'B: 21m x 95m.', 'E: 9m x 9m.'],
            ParcelSizeSummary::groupedDimensionLines([0 => '60m x 21m', 1 => '21m x 95m', 2 => '60m x 21m', 4 => '9m x 9m'])
        );
    }
}
