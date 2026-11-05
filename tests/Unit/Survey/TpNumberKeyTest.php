<?php

namespace Tests\Unit\Survey;

use App\Support\TpNumberKey;
use PHPUnit\Framework\TestCase;

/** CR 03112026 item 2: spellings of one layout group together; different layouts stay apart. */
class TpNumberKeyTest extends TestCase
{
    /** @dataProvider sameLayout */
    public function test_spellings_of_one_layout_share_a_key(string $key, array $spellings): void
    {
        foreach ($spellings as $s) {
            $this->assertSame($key, TpNumberKey::key($s), $s);
        }
    }

    public static function sameLayout(): array
    {
        return [
            'Kano abbreviated or in full' => ['KNUPDA/215M', ['TP/K/215M', 'TP/KN/215M', 'TP/KNUP/215M', 'TP/KNUPDA/215M', 'TP/KUNPDA/215M', 'tp/kn/215m', 'TP\KN/215M', 'TP/ KN/215M']],
            'a typing slip in MLPP'       => ['DKD/1', ['TP/MLPP/DKD/001', 'TP/MIPP/DKD/001', 'TP/MLPP/DKD/1']],
            'the UC part is noise'        => ['DKD/14', ['TP/KN/UC/DKD/14', 'TP/K/UC/DKD/14', 'TP/KNUP/UC/DKD/14']],
            'a doubled slash'             => ['DTF/15', ['TP/KNUPDA//DTF/15', 'TP/KN/UC/DTF/15', 'TP/UC/DTF/15']],
            'backslashes throughout'      => ['DTF/17', ['TP\KN\UC\DTF\17', 'TP/KNUPDA/UC/DTF/17']],
            'GZ written GZW'              => ['GZ/2', ['TP/K/UC/GZW/02', 'TP/KN/UC/GZ/02', 'TP//KN/UC/GZ/2']],
            'a pipe for a slash'          => ['KASEPPA/244', ['TP/KASEPPA|244', 'TP/KAS/244', 'TP/KASSEPA/244']],
            'a dot for a slash'           => ['KBT/338G', ['TP.MLPP/KBT/338G', 'TP/MLPP/KBT/338G']],
            'spaces inside'               => ['RDKN123', ['RDKN 123', 'RDKN123']],
        ];
    }

    public function test_different_authorities_stay_apart(): void
    {
        $this->assertNotSame(TpNumberKey::key('TP/MB/5A'), TpNumberKey::key('TP/UDB/5A'));
        $this->assertNotSame(TpNumberKey::key('TP/KAS/277'), TpNumberKey::key('TP/KN/277'));
        $this->assertNotSame(TpNumberKey::key('TP/MB/14'), TpNumberKey::key('TP/K/UC/DKD/14'));
    }

    public function test_it_breaks_a_number_into_its_parts(): void
    {
        $p = TpNumberKey::parse('TP/MLPP/KBT/256G');
        $this->assertSame('TP', $p['prefix']);
        $this->assertSame('MLPP', $p['agency']);
        $this->assertSame('KBT', $p['code']);
        $this->assertSame('256G', $p['serial']);
    }

    public function test_blanks_have_no_key(): void
    {
        foreach ([null, '', '   ', '-', 'NIL', 'n/a'] as $blank) {
            $this->assertNull(TpNumberKey::key($blank));
        }
    }

    /** The OP TP No rule (user, 2026-11-05). tests/js/tp-no-rule.test.js holds the same lists for the form's copy. */
    public static function goodTpNumbers(): array
    {
        return array_map(fn ($t) => [$t], ['TP/KN/215M', 'TP/K/358E', 'TP/MLPP/DKD/001', 'TP/K/UC/DKD/14', 'TP/KNUPDA/UC/DTF/17',
            'TP/KAS/277', 'TP/KASEPPA/244', 'BLM/PP/005', 'TP/UDB/5A', 'TP/MB/4', 'tp/kn/215m', ' TP / KN / 215M ', 'TP/MLPP/KBT/256G']);
    }

    public static function badTpNumbers(): array
    {
        return [
            'blank'               => ['', 'required'],
            'spaces only'         => ['   ', 'required'],
            'letter O for zero'   => ['TP/MLPP/DKD/0O1', 'not a layout number'],
            'comma in agency'     => ['TP/,LPP/DKD/001', 'not a known agency'],
            'unknown agency KNP'  => ['TP/KNP/215M', 'not a known agency'],
            'MLLP'                => ['TP/MLLP/KBT/338G', 'not a known agency'],
            'no agency'           => ['TP/UC/GZ/02', 'not a known agency'],
            'dot separator'       => ['TP.MLPP/KBT/338G', 'must start with TP/'],
            'backslashes'         => ['TP\\KN\\UC\\DTF\\16', 'must start with TP/'],
            'pipe separator'      => ['TP/KASEPPA|244', 'needs the agency and the layout number'],
            'agency only'         => ['TP/KNUPDA', 'needs the agency and the layout number'],
            'double slash'        => ['TP/MLPP//KBT/307D', 'empty part'],
            'serial joined'       => ['TP/MLPP/KBT3070', 'not a layout number'],
            'digit in area code'  => ['TP/KN/UC/G2/02', 'not an area code'],
            'RD number'           => ['RDKN 123', 'must start with TP/'],
            'Other placeholder'   => ['__other__', 'must start with TP/'],
        ];
    }

    /** @dataProvider goodTpNumbers */
    public function test_a_well_formed_tp_number_is_accepted(string $tp): void
    {
        $this->assertNull(TpNumberKey::problem($tp));
    }

    /** @dataProvider badTpNumbers */
    public function test_a_blank_or_misspelt_tp_number_is_refused(string $tp, string $why): void
    {
        $this->assertStringContainsString($why, (string) TpNumberKey::problem($tp));
    }
}
