<?php

namespace Tests\Feature\FileNumber;

use App\Http\Controllers\MlsFileNoController;
use Tests\TestCase;

/**
 * An Extension is commissioned only with an approved Plot Extension application, as a
 * Subdivision needs its approved application (2026-10-26: RES-2011-1433 was accepted with none).
 * READ ONLY: the check only looks applications up.
 */
class ExtensionNeedsApprovedApplicationTest extends TestCase
{
    private function refusal(?string $fileNo, $appId = null): ?string
    {
        $m = new \ReflectionMethod(MlsFileNoController::class, 'extensionApplicationRefusal');
        $m->setAccessible(true);

        return $m->invoke(app(MlsFileNoController::class), $fileNo, $appId);
    }

    public function test_file_without_an_approved_application_is_refused(): void
    {
        $this->assertStringContainsString('has no approved Plot Extension application', (string) $this->refusal('RES-2011-1433'));
    }

    public function test_missing_file_is_refused(): void
    {
        $this->assertSame('Select the existing file number to extend.', $this->refusal('  '));
    }

    public function test_application_linked_to_another_file_does_not_count(): void
    {
        $approved = \App\Models\PlotExtensionApplication::query()
            ->where('status', \App\Models\PlotExtensionApplication::STATUS_APPROVED)->first();
        if (!$approved) {
            $this->markTestSkipped('No approved Plot Extension application to borrow.');
        }
        $this->assertNotNull($this->refusal('RES-2011-1433', $approved->id));
        $this->assertNull($this->refusal($approved->file_no, $approved->id));
    }
}
