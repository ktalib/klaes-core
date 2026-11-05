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
}
