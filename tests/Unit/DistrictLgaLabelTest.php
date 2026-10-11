<?php

namespace Tests\Unit;

use App\Support\DistrictLgaLabel;
use PHPUnit\Framework\TestCase;

class DistrictLgaLabelTest extends TestCase
{
    public function test_stored_district_and_lga_are_upper_cased(): void
    {
        $this->assertSame('GAWAMMAJA, DALA', DistrictLgaLabel::for('GAWAMMAJA', 'Dala', 'Plot 90SA, 12TH AV, GAWAMMAJA, Dala, Kano'));
        $this->assertSame('RIMIN GADO, GAYA', DistrictLgaLabel::for('Rimin  Gado', 'gaya'));
    }

    public function test_missing_parts_come_from_the_location_text(): void
    {
        $this->assertSame('MAKAFIN DALA, DALA', DistrictLgaLabel::for(null, null, '11, MAKAFIN DALA, Dala'));
        $this->assertSame('TIGA VILL, BEBEJI', DistrictLgaLabel::for(null, null, 'Plot PIECE OF LAND, TIGA VILL, Bebeji, Kano'));
        $this->assertSame('PANSHEKARA, KUMBOTSO', DistrictLgaLabel::for(null, null, 'PIECE OF LAND  , PANSHEKARA , KUMBOTSO'));
        $this->assertSame('SHARADA, KANO MUNICIPAL', DistrictLgaLabel::for(null, 'Kano Municipal', 'A PIECE OF LAND, SHARADA'));
    }

    public function test_never_repeats_or_invents_a_part(): void
    {
        $this->assertSame('NASARAWA', DistrictLgaLabel::for(null, 'Nasarawa', 'Nasarawa, KANO'));
        $this->assertSame('', DistrictLgaLabel::for(null, null, null));
    }
}
