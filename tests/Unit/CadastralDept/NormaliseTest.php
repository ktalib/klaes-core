<?php

namespace Tests\Unit\CadastralDept;

use App\Services\CadastralDept\Normalise;
use PHPUnit\Framework\TestCase;

/** Text clean-up rules (development standard 3). No database. */
class NormaliseTest extends TestCase
{
    public function test_free_text_keeps_its_case(): void
    {
        $this->assertSame('Sent to KAMMA for advice', Normalise::text("  Sent to   KAMMA\tfor advice "));
        $this->assertSame('', Normalise::text(null));
    }

    public function test_codes_are_upper_case(): void
    {
        $this->assertSame('BLK 12A', Normalise::code(' blk  12a '));
        $this->assertNull(Normalise::code('   '));
    }

    public function test_names_are_capitalised_but_acronyms_kept(): void
    {
        $this->assertSame('Lands Records Room', Normalise::name('lands RECORDS room'));
        $this->assertSame('KNUPDA Office', Normalise::name('KNUPDA office'));
        $this->assertSame('Ministry of Works', Normalise::name('  ministry   OF works '));
    }
}
