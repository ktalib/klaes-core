<?php

namespace Tests\Unit;

use App\Support\SubdivisionWorkflow;
use PHPUnit\Framework\TestCase;

class SubdivisionWorkflowTest extends TestCase
{
    public function test_workflow_and_approval_reset(): void
    {
        $record = (object) ['status' => 'pending', 'commissioned_count' => 0,
            'recommendation_generated_at' => null, 'application_generated_at' => null];
        $enabled = function (bool $cleared) use ($record): array {
            return array_keys(array_filter(SubdivisionWorkflow::blockers($record, $cleared), fn ($reason) => $reason === null));
        };
        $this->assertSame(['planning'], $enabled(false));
        $this->assertSame(['generate-recommendation'], $enabled(true));
        $record->recommendation_generated_at = '2026-10-09 12:00:00';
        $this->assertSame(['print-recommendation', 'decision'], $enabled(true));
        $record->status = 'approved';
        $this->assertSame(['print-recommendation', 'generate-application'], $enabled(true));
        $record->application_generated_at = '2026-10-09 12:30:00';
        $this->assertSame(['print-recommendation', 'print-application'], $enabled(true));
        $record->status = 'pending';
        $this->assertSame(['print-recommendation', 'decision'], $enabled(true));
        $record->status = 'rejected';
        $this->assertSame(['print-recommendation'], $enabled(true));
        $record->status = 'commissioned';
        $record->commissioned_count = 2;
        $this->assertSame(['print-recommendation', 'print-application'], $enabled(true));
        $this->assertSame([], $enabled(false));
    }
}
