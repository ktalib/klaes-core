<?php

namespace Tests\Unit;

use App\Services\ChangeOfPurposeDetails;
use PHPUnit\Framework\TestCase;

class ChangeOfPurposeDetailsTest extends TestCase
{
    public function test_indexing_fields_map_file_indexing_columns(): void
    {
        $fields = ChangeOfPurposeDetails::indexingFields([
            'file_title' => 'AMINU BELLO', 'plot_number' => '12', 'tp_no' => 'TP/45',
            'street_name' => 'ZOO ROAD', 'district' => 'GYADI GYADI', 'lga' => 'TARAUNI',
            'residence_address' => '4, FANSHEKARA, KUMBOTSO, KANO', 'plot_size' => '450',
        ]);

        $this->assertSame('AMINU BELLO', $fields['applicant_name']);
        $this->assertSame('12', $fields['plot_no']);
        $this->assertSame('TP/45', $fields['tp_no']);
        $this->assertSame('ZOO ROAD', $fields['street_name']);
        $this->assertSame('4, FANSHEKARA, KUMBOTSO, KANO', $fields['residential_address']);
        $this->assertSame('450', $fields['land_size']);
        $this->assertNull($fields['house_no']);
    }

    public function test_fill_blanks_keeps_entered_values_and_fills_only_blanks(): void
    {
        $filled = ChangeOfPurposeDetails::fillBlanks(
            ['address' => 'ENTERED ADDRESS', 'tp_no' => '  ', 'plot_no' => null, 'lga' => 'NASARAWA'],
            ['address' => 'INDEXED ADDRESS', 'tp_no' => 'TP/9', 'plot_no' => '7', 'lga' => 'GWALE', 'district' => '']
        );

        $this->assertSame('ENTERED ADDRESS', $filled['address']);
        $this->assertSame('TP/9', $filled['tp_no']);
        $this->assertSame('7', $filled['plot_no']);
        $this->assertSame('NASARAWA', $filled['lga']);
        $this->assertArrayNotHasKey('district', $filled);
    }
}
