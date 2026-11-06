<?php

namespace Tests\Unit;

use App\Services\Survey\TpLargeFormat as L;
use PHPUnit\Framework\TestCase;

/** Survey Doc-WARE's Scanned Map Layouts tab (user, 2026-11-06) lists the folders named from a TP or BLM number. */
class TpLargeFormatFolderTest extends TestCase
{
    public function test_layout_folders(): void
    {
        foreach (['TP-KNUPDA-358E', 'tp-k-246', 'EDMS/BLIND_SCAN/Survey_Registry_Raw/TP-KNUPDA-105E', 'BLM-PP-005', 'x\\TP-MLPP-KBT-338G'] as $p) {
            $this->assertTrue(L::isLayoutFolder($p), $p);
        }
    }

    public function test_everything_else_is_a_file(): void
    {
        foreach (['CO-1-2006', 'MISC KN 605', 'RES-2026-2575', 'GKN-12', 'EDMS/BLIND_SCAN/Survey_Registry_Raw/CO-TP-1', 'TPX-1'] as $p) {
            $this->assertFalse(L::isLayoutFolder($p), $p);
        }
    }
}
