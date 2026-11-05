<?php

namespace Tests\Unit\Survey;

use App\Models\Survey\SurveyBeaconRegister;
use App\Services\Survey\BeaconPool;
use PHPUnit\Framework\TestCase;

/** CR 03112026 items 13-15: the beacon number rules, without touching survey_beacon_* (in live use). */
class BeaconNumbersTest extends TestCase
{
    /** @dataProvider prefixes */
    public function test_the_next_prefix(string $from, string $to): void
    {
        $this->assertSame($to, BeaconPool::nextPrefix($from));
    }

    public static function prefixes(): array
    {
        return [
            'the sheet: after KDJ'   => ['KDJ', 'KDK'],
            'then KDL'               => ['KDK', 'KDL'],
            'last letter rolls over' => ['KDZ', 'KEA'],
            'two letters roll'       => ['KZZ', 'LAA'],
            'single letter'          => ['A', 'B'],
            'Z goes to AA'           => ['Z', 'AA'],
            'ZZ goes to AAA'         => ['ZZ', 'AAA'],
            'ZZZ goes to AAAA'       => ['ZZZ', 'AAAA'],
            'lower case is read'     => ['kdj', 'KDK'],
        ];
    }

    public function test_numbers_are_stored_without_a_space_and_shown_with_one(): void
    {
        $this->assertSame('KDJ0001', BeaconPool::number('KDJ', 1));
        $this->assertSame('KDJ9999', BeaconPool::number('KDJ', 9999));
        $this->assertSame('KDJ 0001', SurveyBeaconRegister::display('KDJ0001'));
    }

    public function test_older_numbers_show_as_they_are_stored(): void
    {
        foreach (['KB1088', 'A1404B', 'A0875A', 'A0001AB', '1166', 'A46075', 'C9302B'] as $no) {
            $this->assertSame($no, SurveyBeaconRegister::display($no));
        }
        $this->assertSame('', SurveyBeaconRegister::display(null));
    }

    public function test_a_typed_number_with_a_space_is_the_same_number(): void
    {
        $this->assertSame('KDJ0001', SurveyBeaconRegister::normalise('kdj 0001'));
        $this->assertSame('KDJ|', SurveyBeaconRegister::parseBeacon('KDJ 0001')['series']);
        $this->assertSame(1, SurveyBeaconRegister::parseBeacon('KDJ 0001')['n']);
    }

    public function test_private_numbers_keep_their_letters_after_the_serial(): void
    {
        $one = SurveyBeaconRegister::parseBeacon('A0001AB');
        $this->assertSame('A', $one['prefix']);
        $this->assertSame('AB', $one['suffix']);
        $this->assertSame('A|AB', $one['series']);
        $this->assertSame([1 => 'A0001B', 2 => 'A0002B', 3 => 'A0003B', 4 => 'A0004B'],
            SurveyBeaconRegister::expand(SurveyBeaconRegister::parseRange('A0001B', 'A0004B')));
    }

    public function test_group_names(): void
    {
        $this->assertSame('KB', SurveyBeaconRegister::seriesLabel('KB|'));
        $this->assertSame('A…B', SurveyBeaconRegister::seriesLabel('A|B'));
        $this->assertSame('A…AB', SurveyBeaconRegister::seriesLabel('A|AB'));
        $this->assertSame('No letters', SurveyBeaconRegister::seriesLabel('|'));
    }

    public function test_numbers_taken_become_unbroken_runs(): void
    {
        $row = fn ($p, $s) => (object) ['prefix' => $p, 'seq' => $s, 'beacon_no' => BeaconPool::number($p, $s)];

        $this->assertSame([['prefix' => 'KDJ', 'from' => 'KDJ0001', 'to' => 'KDJ0003']],
            BeaconPool::runs([$row('KDJ', 1), $row('KDJ', 2), $row('KDJ', 3)]));

        // A gap, and a quantity crossing into the next prefix, each start a new register line.
        $this->assertSame([
            ['prefix' => 'KDJ', 'from' => 'KDJ9998', 'to' => 'KDJ9998'],
            ['prefix' => 'KDK', 'from' => 'KDK0001', 'to' => 'KDK0002'],
        ], BeaconPool::runs([$row('KDJ', 9998), $row('KDK', 1), $row('KDK', 2)]));

        $this->assertCount(2, BeaconPool::runs([$row('KDJ', 1), $row('KDJ', 3)]));
    }

    public function test_government_shows_as_public_and_the_stored_value_stays(): void
    {
        $this->assertSame('Public', SurveyBeaconRegister::LAYOUT_TYPES['Government']);
        $this->assertArrayNotHasKey('Public', SurveyBeaconRegister::LAYOUT_TYPES);
    }
}
