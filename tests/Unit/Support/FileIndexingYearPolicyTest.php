<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\FileIndexingYearPolicy;
use Tests\TestCase;

/**
 * Server-side twin of the File Number selector's blocked-year rule, plus the
 * Super Admin override. Only formats that carry a year are refused; KANGIS-style
 * serials that happen to read 2026 must stay indexable.
 */
class FileIndexingYearPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['fileindexing.non_indexable_years' => [2026]]);
    }

    private function user(bool $superAdmin): User
    {
        $user = $this->getMockBuilder(User::class)->onlyMethods(['isSuperAdmin'])->getMock();
        $user->method('isSuperAdmin')->willReturn($superAdmin);

        return $user;
    }

    public function test_extracts_year_only_from_year_bearing_formats(): void
    {
        $this->assertSame(2026, FileIndexingYearPolicy::extractYear('RES-2026-12'));
        $this->assertSame(2026, FileIndexingYearPolicy::extractYear('CON-RES-RC-2026-10'));
        $this->assertSame(2026, FileIndexingYearPolicy::extractYear('RES-2026-10(T)'));
        $this->assertSame(2026, FileIndexingYearPolicy::extractYear('RES-2026-10 AND EXTENSION'));
        $this->assertSame(2019, FileIndexingYearPolicy::extractYear('ST-COM-2019-2026-001'));

        $this->assertNull(FileIndexingYearPolicy::extractYear('MLKN 2026'));
        $this->assertNull(FileIndexingYearPolicy::extractYear('KN2026'));
        $this->assertNull(FileIndexingYearPolicy::extractYear('SLTR-2026'));
        $this->assertNull(FileIndexingYearPolicy::extractYear('GKN-2026'));
        $this->assertNull(FileIndexingYearPolicy::extractYear(null));
    }

    public function test_refuses_blocked_year_without_override(): void
    {
        $this->assertNotNull(FileIndexingYearPolicy::refusal(['RES-2026-12'], false, $this->user(false)));
        $this->assertNotNull(FileIndexingYearPolicy::refusal(['RES-2026-12'], false, $this->user(true)));
        $this->assertNull(FileIndexingYearPolicy::refusal(['RES-2025-12', 'KN 2026', null], false, $this->user(false)));
    }

    public function test_override_only_works_for_super_admin(): void
    {
        $this->assertNull(FileIndexingYearPolicy::refusal(['RES-2026-12'], true, $this->user(true)));
        $this->assertNotNull(FileIndexingYearPolicy::refusal(['RES-2026-12'], true, $this->user(false)));
        $this->assertNotNull(FileIndexingYearPolicy::refusal(['RES-2026-12'], true, null));
    }

    public function test_no_override_offered_when_no_years_are_blocked(): void
    {
        config(['fileindexing.non_indexable_years' => []]);

        $this->assertFalse(FileIndexingYearPolicy::canOverride($this->user(true)));
        $this->assertNull(FileIndexingYearPolicy::refusal(['RES-2026-12'], false, $this->user(false)));
    }
}
