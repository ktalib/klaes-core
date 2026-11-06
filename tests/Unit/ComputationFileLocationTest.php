<?php

namespace Tests\Unit;

use App\Support\AddressBuilder;
use Tests\TestCase;

class ComputationFileLocationTest extends TestCase
{
    public function test_computation_file_form_uses_searchable_district_and_lga_selects(): void
    {
        $source = file_get_contents(resource_path('views/survey_module/gkn/computation_files.blade.php'));

        $this->assertNotFalse($source);
        $this->assertStringContainsString('class="cf-location-select"', $source);
        $this->assertStringContainsString("route('survey-module.lookup.districts')", $source);
        $this->assertStringContainsString("route('survey-module.lookup.lgas')", $source);
        $this->assertStringContainsString('id="cfLocation"', $source);
        $this->assertNotEmpty(app('blade.compiler')->compileString($source));
    }

    public function test_selected_district_and_lga_compose_the_property_location(): void
    {
        $this->assertSame(
            'Gwale, Nassarawa, Kano',
            AddressBuilder::propertyLocation(['district' => 'Gwale', 'lga' => 'Nassarawa', 'state' => 'Kano'])
        );
    }

    public function test_location_composition_omits_unselected_segments(): void
    {
        $this->assertSame(
            'Nassarawa, Kano',
            AddressBuilder::propertyLocation(['district' => null, 'lga' => 'Nassarawa', 'state' => 'Kano'])
        );
    }
}
