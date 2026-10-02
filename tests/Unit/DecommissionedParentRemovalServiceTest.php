<?php

namespace Tests\Unit;

use App\Services\DecommissionedParentRemovalService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DecommissionedParentRemovalServiceTest extends TestCase
{
    public function test_only_the_four_live_register_tables_are_removal_targets(): void
    {
        $this->assertSame(['customers_staging', 'entities_staging', 'file_indexings', 'fileNumber'], array_keys(DecommissionedParentRemovalService::TABLES));
        foreach (DecommissionedParentRemovalService::TABLES as $columns) {
            $this->assertNotContains('prop_id', $columns);
            $this->assertNotContains('parent_prop_id', $columns);
            $this->assertNotContains('NewKANGISFileNo', $columns);
            $this->assertNotContains('related_fileno', $columns);
        }
    }

    /** @dataProvider invalidTargets */
    public function test_unsafe_targets_are_rejected_before_accessing_the_database(string $table, string $number): void
    {
        $this->expectException(RuntimeException::class);
        (new DecommissionedParentRemovalService())->matchingRows($table, $number);
    }

    public static function invalidTargets(): array
    {
        return [['pra', 'RES-2015-7156'], ['deed_registrations', 'RES-2015-7156'], ['fileNumber', ''], ['file_indexings', '  '], ['customers_staging', 'N/A']];
    }
}
