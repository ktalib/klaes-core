<?php

namespace Tests\Unit;

use App\Services\FileNumber\CrossedOutFiles;
use PHPUnit\Framework\TestCase;

class CrossedOutFilesTest extends TestCase
{
    public function test_the_table_gets_the_numbers_back_without_the_notes(): void
    {
        $row = (object) [
            'mlsfNo' => null,
            'deleted_numbers' => json_encode(['mlsfNo' => 'RES-2026-1', 'mls_tombstone' => 'DELETED-9', 'list' => 'New']),
        ];

        CrossedOutFiles::restoreNumbers($row);

        $this->assertSame('RES-2026-1', $row->mlsfNo);
        $this->assertFalse(isset($row->mls_tombstone));
        $this->assertFalse(isset($row->list));
    }

    public function test_only_rows_with_moved_numbers_are_crossed_out(): void
    {
        $this->assertTrue(CrossedOutFiles::isCrossedOut((object) ['deleted_numbers' => '{}']));
        $this->assertFalse(CrossedOutFiles::isCrossedOut((object) ['deleted_numbers' => null]));
        $this->assertFalse(CrossedOutFiles::isCrossedOut((object) ['is_deleted' => 1]));
    }
}
