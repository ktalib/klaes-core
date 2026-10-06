<?php

namespace Tests\Unit\Services;

use App\Services\TransactionFileNumberCorrectionService as Correction;
use PHPUnit\Framework\TestCase;

class TransactionFileNumberCorrectionServiceTest extends TestCase
{
    public function test_pair_is_the_same_from_either_side(): void
    {
        $expected = ['main' => 'RES-2025-10000', 'temp' => 'RES-2025-10000(T)'];

        $this->assertSame($expected, Correction::pairFor('RES-2025-10000'));
        $this->assertSame($expected, Correction::pairFor('RES-2025-10000(T)'));
        $this->assertSame($expected, Correction::pairFor(' RES-2025-10000 ( t ) '));
    }

    public function test_temp_marker_is_only_recognised_at_the_end(): void
    {
        $this->assertTrue(Correction::isTemp('CON-RES-2018-487(T)'));
        $this->assertTrue(Correction::isTemp('MLKN 349 (t)'));
        $this->assertFalse(Correction::isTemp('CON-RES-2018-487'));
        $this->assertFalse(Correction::isTemp('(T)RES-2018-487'));
    }

    public function test_assigned_number_reads_mls_before_fileno_like_legal_search(): void
    {
        $this->assertSame('RES-1-1(T)', Correction::assignedNumber((object) ['mlsFNo' => 'RES-1-1(T)', 'fileno' => 'RES-1-1']));
        $this->assertSame('RES-1-1', Correction::assignedNumber((object) ['mlsFNo' => '  ', 'fileno' => 'RES-1-1']));
        $this->assertNull(Correction::assignedNumber((object) ['mlsFNo' => null, 'fileno' => '']));
    }
}
