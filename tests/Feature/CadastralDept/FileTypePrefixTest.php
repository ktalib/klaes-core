<?php

namespace Tests\Feature\CadastralDept;

use App\Services\CadastralDept\CaseWorkflow;
use App\Services\CadastralDept\IntakeLookup;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Tests\TestCase;

/**
 * A Conversion File takes only CON- numbers (2026-10-26: RES-2010-3876 was saved as one).
 * The guard throws before anything is written.
 */
class FileTypePrefixTest extends TestCase
{
    public function test_a_res_file_cannot_be_registered_as_a_conversion_file(): void
    {
        foreach (['conversion', 'verification'] as $workflow) {
            try {
                app(CaseWorkflow::class)->open(['workflow' => $workflow, 'file_type' => 'conversion_file',
                    'file_number' => 'RES-2010-3876', 'file_source' => 'land'], null);
                $this->fail("Expected a refusal for {$workflow}.");
            } catch (WorkflowGuardException $e) {
                $this->assertStringContainsString('must start with CON-', $e->getMessage());
            }
        }
    }

    /** Verification is Conversion or Direct Allocation; the file must be from the group chosen. */
    public function test_verification_file_must_match_its_group(): void
    {
        $cases = [
            ['conversion', 'quarry', 'RES-2010-3876', 'not a Conversion file'],
            ['direct', 'gkn', 'CON-AG-1981-1', 'not a Direct Allocation file'],
            ['direct', 'conversion_file', 'CON-AG-1981-1', 'is not a Direct Allocation file'],
        ];
        foreach ($cases as [$group, $type, $number, $expect]) {
            try {
                app(CaseWorkflow::class)->open(['workflow' => 'verification', 'verification_group' => $group, 'file_type' => $type,
                    'file_number' => $number, 'file_source' => 'land'], null);
                $this->fail("Expected a refusal for {$group} / {$number}.");
            } catch (WorkflowGuardException $e) {
                $this->assertStringContainsString($expect, $e->getMessage());
            }
        }
    }

    /** The Verification list follows the group (READ ONLY query on file_indexings). */
    public function test_verification_list_follows_the_group(): void
    {
        $lookup = app(IntakeLookup::class);

        foreach ($lookup->options('verification', '', 30, 'conversion') as $row) {
            $this->assertStringStartsWith('CON-', strtoupper($row['file_number']));
        }
        foreach ($lookup->options('verification', '', 30, 'direct') as $row) {
            $this->assertMatchesRegularExpression('/^(RES|COM|IND|AG)-/', strtoupper($row['file_number']));
        }
    }
}
