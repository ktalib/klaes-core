<?php

namespace Tests\Unit;

use App\Services\TemporaryFileConditionService as Condition;
use PHPUnit\Framework\TestCase;

class TemporaryFileConditionServiceTest extends TestCase
{
    public function test_main_number_strips_the_temp_marker(): void
    {
        $this->assertSame('RES-1987-1451', Condition::mainNumber('RES-1987-1451(T)'));
        $this->assertSame('RES-1987-1451', Condition::mainNumber('RES-1987-1451 (T)'));
        $this->assertNull(Condition::mainNumber('RES-1987-1451'));
        $this->assertNull(Condition::mainNumber(null));
    }

    /** Real pairs from duplicate_fileno vs file_indexings that name the same holder. */
    public function test_same_holder_is_not_flagged(): void
    {
        $same = [
            ['ALHAJI ILLU MAI UNGUWA', 'ALH ILLU MAI UNGUWA'],
            ['MAJOR A GARKO', 'MAJOR A. GARKO'],
            ['IBRAHIM ISYAKU NAABBA', "IBRAHIM ISYAKU NA'ABBA"],
            ['MAJ GEN MOHD MAGORO (RTD)', 'MAJOR GEN MAGORO (RTD)'],
            ['ALASAN AMADU', 'ALHASSAN AMADU'],
            ['AUWALU ZUBAIRU DANGORA', 'auwalu zubairu dangora'],
        ];

        foreach ($same as [$a, $b]) {
            $this->assertFalse(Condition::titlesDiffer($a, $b), "$a vs $b");
        }
    }

    public function test_different_holder_is_flagged(): void
    {
        $different = [
            ['A.JIBRIN DOGARAI', 'YUSUF MAITAMA IBRAHIM'],
            ['DANJUMA', 'LAGAZAN SHARIF'],
            ['HAJARA MOHD GARBA BABA', 'HAJARA MOHD LAWAN BABA'],
        ];

        foreach ($different as [$a, $b]) {
            $this->assertTrue(Condition::titlesDiffer($a, $b), "$a vs $b");
        }
    }

    public function test_a_missing_title_is_never_flagged(): void
    {
        $this->assertFalse(Condition::titlesDiffer('', 'YUSUF MAITAMA IBRAHIM'));
        $this->assertFalse(Condition::titlesDiffer('ALHAJI', 'YUSUF MAITAMA IBRAHIM'));
        $this->assertFalse(Condition::titlesDiffer(null, null));
    }
}
