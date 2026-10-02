<?php

namespace Tests\Unit\Services;

use App\Services\KanoLgaDirectory;
use Tests\TestCase;

class KanoLgaDirectoryTest extends TestCase
{
    protected KanoLgaDirectory $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = new KanoLgaDirectory();
    }

    /** Kano State has exactly 44 Local Governments; a 43 or 45 here is a real defect. */
    public function test_it_lists_exactly_forty_four_authorities(): void
    {
        $this->assertCount(44, $this->directory->fullNames());
        $this->assertCount(44, $this->directory->shortNames());
        $this->assertSame(
            $this->directory->shortNames(),
            array_unique($this->directory->shortNames()),
            'The list must not repeat an LGA.'
        );
    }

    /** The stored value is the full authority name, which is what party_1 must hold. */
    public function test_full_names_carry_the_local_government_suffix(): void
    {
        foreach ($this->directory->fullNames() as $name) {
            $this->assertStringEndsWith(' Local Government', $name);
        }

        $this->assertContains('Fagge Local Government', $this->directory->fullNames());
        $this->assertContains('Gezawa Local Government', $this->directory->fullNames());
    }

    /** Garki is a Jigawa LGA. Kano's is Garko — an easy and silent mix-up. */
    public function test_it_holds_garko_and_not_garki(): void
    {
        $this->assertContains('Garko', $this->directory->shortNames());
        $this->assertNotContains('Garki', $this->directory->shortNames());
        $this->assertNull($this->directory->shortName('Garki Local Government'));
    }

    /**
     * A full name has to reduce back to the `lgas` table name so an LGA-issued permit can
     * later be grouped by, or mapped to, the LGA it was issued in.
     *
     * @dataProvider shortNameCases
     */
    public function test_it_reduces_a_value_to_its_lgas_table_name(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->directory->shortName($input));
    }

    public static function shortNameCases(): array
    {
        return [
            'full name'            => ['Fagge Local Government', 'Fagge'],
            'bare name'            => ['Fagge', 'Fagge'],
            'lower case'           => ['fagge local government', 'Fagge'],
            'upper case'           => ['GEZAWA LOCAL GOVERNMENT', 'Gezawa'],
            'padded and doubled'   => ['  Dawakin   Kudu  ', 'Dawakin Kudu'],
            'two-word LGA'         => ['Garun Mallam Local Government', 'Garun Mallam'],
            'the State is not one' => ['KANO STATE GOVERNMENT', null],
            'a person is not one'  => ['MUHAMMAD BALA KHALID', null],
            'empty'                => ['', null],
            'null'                 => [null, null],
        ];
    }

    /** The pre-select helper must refuse anything that is not one of the 44. */
    public function test_full_name_returns_null_for_a_non_authority(): void
    {
        $this->assertSame('Kura Local Government', $this->directory->fullName('Kura'));
        $this->assertSame('Kura Local Government', $this->directory->fullName('Kura Local Government'));
        $this->assertNull($this->directory->fullName('KANO STATE GOVERNMENT'));
        $this->assertNull($this->directory->fullName(null));
    }

    public function test_is_lga_authority_matches_short_name(): void
    {
        $this->assertTrue($this->directory->isLgaAuthority('Ungogo Local Government'));
        $this->assertTrue($this->directory->isLgaAuthority('Ungogo'));
        $this->assertFalse($this->directory->isLgaAuthority('KANO STATE GOVERNMENT'));
        $this->assertFalse($this->directory->isLgaAuthority(''));
    }
}
