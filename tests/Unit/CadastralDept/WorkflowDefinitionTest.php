<?php

namespace Tests\Unit\CadastralDept;

use App\Services\CadastralDept\WorkflowDefinition;
use App\Services\CadastralDept\WorkflowEngine;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use PHPUnit\Framework\TestCase;

/**
 * The Cadastral Department workflow graph (config/cadastral_dept.php) against
 * the user's "Cadastral Department Workflow" doc and the 2026-10-10 client
 * brief. No database.
 */
class WorkflowDefinitionTest extends TestCase
{
    private WorkflowDefinition $def;
    private WorkflowEngine $engine;

    protected function setUp(): void
    {
        $this->def = new WorkflowDefinition(require __DIR__ . '/../../../config/cadastral_dept.php');
        $this->engine = new WorkflowEngine($this->def);
    }

    /** Follow outcomes from the start; returns the desks visited, in order. */
    private function walk(string $wf, array $decisions = [], ?string $subType = null): array
    {
        $step = $this->def->startStep($wf);
        $desks = [];
        for ($i = 0; $i < 100; $i++) {
            $desks[] = $this->def->step($wf, $step)['desk'];
            $outcome = $decisions[$step] ?? ($this->engine->actions($wf, $step, $subType)[0]['outcome']);
            $next = $this->engine->nextStep($wf, $step, $outcome, $subType);
            if ($next === null) {
                return [$desks, $this->def->step($wf, $step)['end']];
            }
            $step = $next;
        }
        $this->fail("$wf never ends");
    }

    public function test_graph_has_no_problems(): void
    {
        $this->assertSame([], $this->def->validate());
    }

    public function test_conversion_main_path_follows_the_doc(): void
    {
        [$desks, $end] = $this->walk('conversion', ['dc_buffer' => 'inside_10km']);

        $this->assertSame('completed', $end);
        $this->assertSame([
            'director_cadastral', 'deputy_director_cadastral',                         // phase 1 (Registry registers before the case starts)
            'ad_information', 'ad_report',                                            // phase 2
            'compensation_unit', 'dsg', 'director_cadastral', 'kamma',                // phase 3
            'permanent_secretary', 'director_cadastral', 'ad_information',
            'registry', 'director_land', 'registry',                                  // phase 4
            'director_cadastral', 'deputy_director_cadastral', 'ad_pnd',              // phase 5
            'field_survey', 'ad_pnd', 'ad_cadastral', 'registry',
        ], $desks);
    }

    public function test_conversion_outside_buffer_goes_to_physical_planning(): void
    {
        [$desks] = $this->walk('conversion', ['dc_buffer' => 'outside_10km']);

        $this->assertContains('physical_planning', $desks);
        $this->assertNotContains('kamma', $desks);
    }

    public function test_d_cad_refusal_returns_through_registry(): void
    {
        foreach (['conversion', 'direct', 'verification'] as $wf) {
            [$desks, $end] = $this->walk($wf, ['dc_review' => 'not_recommended'], 'resettlement');
            $this->assertSame('returned', $end, $wf);
            $this->assertSame(['director_cadastral', 'registry'], $desks, $wf);
        }
    }

    public function test_dd_cad_refusal_goes_back_to_d_cad_with_a_reason(): void
    {
        foreach (['conversion', 'direct', 'verification'] as $wf) {
            $this->assertSame('dc_review', $this->engine->nextStep($wf, 'ddc_review', 'not_recommended'), $wf);
            $this->assertTrue($this->def->outcomeNeedsReason($wf, 'ddc_review', 'not_recommended'), $wf);
            $this->assertTrue($this->def->outcomeNeedsReason($wf, 'dc_review', 'not_recommended'), $wf);
            $this->assertFalse($this->def->outcomeNeedsReason($wf, 'dc_review', 'recommended'), $wf);
        }
    }

    public function test_government_acquisition_rejects_the_file(): void
    {
        foreach (['conversion' => null, 'direct' => 'resettlement'] as $wf => $sub) {
            [$desks, $end] = $this->walk($wf, ['compensation_check' => 'yes'], $sub);
            $this->assertSame('returned', $end, $wf);
            $this->assertSame(['compensation_unit', 'registry'], array_slice($desks, -2), $wf);
        }
    }

    public function test_only_op_files_ask_for_a_sub_type(): void
    {
        $asks = array_keys(array_filter($this->def->fileTypes(), fn ($t) => !empty($t['asks_sub_type'])));

        $this->assertSame(['op'], $asks);
        $this->assertSame('direct_allocation', $this->def->defaultSubType('direct'));
        $this->assertNull($this->def->defaultSubType('conversion'));
        // Verification takes any file type.
        $this->assertSame(array_keys($this->def->fileTypes()), array_keys($this->def->fileTypesFor('verification')));
        $this->assertArrayNotHasKey('op', $this->def->fileTypesFor('conversion'));
    }

    public function test_direct_sub_types_branch_after_report_charting(): void
    {
        [$resettlement] = $this->walk('direct', [], 'resettlement');
        [$allocation] = $this->walk('direct', [], 'direct_allocation');

        $this->assertContains('compensation_unit', $resettlement);
        $this->assertNotContains('compensation_unit', $allocation);
        // Direct Allocation: Land checks genuineness, then later processes the R of O.
        $this->assertSame(2, count(array_keys($allocation, 'director_land')));
        // Both end with the survey plan attached and the bill raised.
        foreach ([$resettlement, $allocation] as $desks) {
            $this->assertSame(['ad_pnd', 'ad_cadastral', 'registry'], array_slice($desks, -3));
        }
    }

    public function test_direct_cannot_forward_from_report_without_sub_type(): void
    {
        $this->expectException(WorkflowGuardException::class);
        $this->engine->nextStep('direct', 'report_charting', 'forward', null);
    }

    public function test_verification_path_follows_the_doc(): void
    {
        [$desks, $end] = $this->walk('verification');

        $this->assertSame('completed', $end);
        $this->assertSame([
            'director_cadastral', 'deputy_director_cadastral',
            'ad_information', 'ad_report', 'deputy_director_cadastral', 'director_cadastral',
            'ad_information', 'registry',
        ], $desks);
    }

    public function test_unknown_outcome_is_refused(): void
    {
        $this->expectException(WorkflowGuardException::class);
        $this->engine->nextStep('conversion', 'dc_review', 'forward');
    }

    public function test_missing_tasks_and_external_recorder(): void
    {
        $this->assertSame(['land12', 'correspondence_created'], $this->engine->missingTasks('conversion', 'report_charting', ['charted']));
        $this->assertSame('director_cadastral', $this->def->actingDesk('conversion', 'kamma_advice'));
        $this->assertSame('registry', $this->def->actingDesk('conversion', 'land_processing'));
        $this->assertSame('ad_pnd', $this->def->actingDesk('conversion', 'field_survey'));
    }

    public function test_validate_catches_a_dangling_next(): void
    {
        $config = require __DIR__ . '/../../../config/cadastral_dept.php';
        $config['workflows']['verification']['steps']['report_check']['next'] = 'nowhere';

        $problems = (new WorkflowDefinition($config))->validate();

        $this->assertContains('verification.report_check: next step [nowhere] does not exist', $problems);
    }

    public function test_send_to_deeds_is_checked_and_labelled(): void
    {
        foreach (array_keys($this->def->workflows()) as $wf) {
            foreach ($this->def->steps($wf) as $key => $step) {
                foreach ((array) ($step['checks'] ?? []) as $check) {
                    $this->assertArrayHasKey($check, \App\Services\CadastralDept\DeedsDispatchChecks::LABELS, "{$wf}.{$key}: unknown check {$check}");
                }
            }
        }

        foreach (['conversion', 'direct'] as $wf) {
            $this->assertSame(['fmc', 'charting', 'correspondence', 'bill', 'detached'], $this->def->step($wf, 'registry_dispatch')['checks']);
            $this->assertSame('Send to Deeds', $this->engine->actions($wf, 'registry_dispatch')[0]['label']);
        }
        $this->assertSame('Return main file to requester', $this->engine->actions('verification', 'registry_return_requester')[0]['label']);
    }
}
