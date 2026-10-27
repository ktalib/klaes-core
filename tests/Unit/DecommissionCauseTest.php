<?php

namespace Tests\Unit;

use App\Support\DecommissionCause;
use PHPUnit\Framework\TestCase;

/** The Decommissioned Files list shows the cause of each reason as a coloured badge (2026-10-26). */
class DecommissionCauseTest extends TestCase
{
    public function test_reasons_map_to_their_cause(): void
    {
        $cases = [
            'Title Status: resettled — The file was resettled' => 'Resettled',
            'Title Status: subdivision, change_of_purpose — both' => 'Subdivision',
            'Title Status: change_of_name — new holder' => 'Change of Name',
            'Manual Linkage: Merger → CON-RES-2026-4838' => 'Merger',
            'Manual Linkage: Change of Purpose → CON-COM-2026-1' => 'Change of Purpose',
            'Subdivision → COM-2026-196, COM-2026-197' => 'Subdivision',
            'Plot Subdivision into 217 fragments' => 'Subdivision',
            'Plot Merger to CON-RES-2026-4838' => 'Merger',
            'Merged into IND-2026-3 (Merger)' => 'Merger',
            'Re-grant → COM-2017-129' => 'Re-grant',
            'Change of Purpose to CON-COM' => 'Change of Purpose',
            'Plot Extension to CON-COM-2026-9' => 'Extension',
            'KANGIS Recertification to CON-AG-1' => 'Recertification',
            'ST Direct Allocation → ST-RES-2026-1' => 'Sectional Titling',
            'Superseded by COM-2022-519' => 'Superseded',
        ];
        foreach ($cases as $reason => $cause) {
            $this->assertSame($cause, DecommissionCause::of($reason), $reason);
        }
        $this->assertNull(DecommissionCause::of('  '));
    }

    public function test_every_cause_has_its_own_colour(): void
    {
        $pairs = array_map(fn ($c) => implode('/', $c), DecommissionCause::COLOURS);
        $this->assertCount(count($pairs), array_unique($pairs));
        $this->assertNotSame(DecommissionCause::colours('Subdivision'), DecommissionCause::colours('Something new'));
    }

    public function test_unknown_reason_shows_its_opening_words(): void
    {
        $this->assertSame('Boundary dispute', DecommissionCause::of('Boundary dispute: plot overlaps CON-AG-1'));
    }
}
