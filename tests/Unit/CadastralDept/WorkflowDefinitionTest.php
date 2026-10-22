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
        [$desks, $end] = $this->walk('conversion', ['gis_buffer' => 'inside_10km']);

        // Updated workflow doc (2026-10-15): 28 steps, after Registry's intake, which waits for
        // the Head of Registry's approval (client, 2026-10-16).
        $this->assertSame('completed', $end);
        $this->assertSame([
            'registry',                                                                         // intake
            'director_cadastral', 'deputy_director_cadastral', 'ad_information', 'ad_report',   // phase 1 (1-4)
            'compensation_unit', 'dsg', 'director_cadastral', 'cadastral_gis',                 // phase 2 (5-8)
            'kamma', 'permanent_secretary', 'director_cadastral',                              // phase 3 (9-11)
            'ad_information', 'registry', 'director_land', 'registry',                         // phase 4 (12-15)
            'director_cadastral', 'deputy_director_cadastral', 'ad_pnd', 'ad_information',     // phase 5 (16-20)
            'field_survey',
            'ad_information', 'examination_unit', 'ad_drawing', 'surveyor_general',            // phase 6 (21-24)
            'ad_information', 'ad_pnd', 'ad_information', 'registry',                          // phase 7 (25-28)
        ], $desks);
    }

    public function test_conversion_outside_buffer_goes_to_physical_planning(): void
    {
        [$desks] = $this->walk('conversion', ['gis_buffer' => 'outside_10km']);

        $this->assertContains('physical_planning', $desks);
        $this->assertNotContains('kamma', $desks);
    }

    public function test_d_cad_refusal_returns_through_registry(): void
    {
        foreach (['conversion', 'direct', 'verification'] as $wf) {
            [$desks, $end] = $this->walk($wf, ['dc_review' => 'not_recommended'], 'resettlement');
            $this->assertSame('returned', $end, $wf);
            $this->assertSame(['registry', 'director_cadastral', 'registry'], $desks, $wf);
        }
    }

    public function test_dd_cad_refusal_goes_back_to_d_cad_with_a_reason(): void
    {
        // Direct Allocation: DD Cad's refusal returns to Registry (updated workflow doc, 2026-10-15).
        $this->assertSame('registry_return', $this->engine->nextStep('direct', 'ddc_review', 'not_recommended'));
        foreach (['conversion', 'verification'] as $wf) {
            $this->assertSame('dc_review', $this->engine->nextStep($wf, 'ddc_review', 'not_recommended'), $wf);
        }
        foreach (['conversion', 'direct', 'verification'] as $wf) {
            $this->assertTrue($this->def->outcomeNeedsReason($wf, 'ddc_review', 'not_recommended'), $wf);
            $this->assertTrue($this->def->outcomeNeedsReason($wf, 'dc_review', 'not_recommended'), $wf);
            $this->assertFalse($this->def->outcomeNeedsReason($wf, 'dc_review', 'recommended'), $wf);
        }
    }

    public function test_government_acquisition_rejects_the_file(): void
    {
        // Yes or No, Compensation sends to DSG, DSG to D Cad; D Cad returns a rejected file to
        // Registry (transcript, 2026-10-16).
        foreach (['conversion' => null, 'direct' => 'resettlement'] as $wf => $sub) {
            $this->assertSame('dsg_review', $this->engine->nextStep($wf, 'compensation_check', 'yes'), $wf);
            $this->assertSame('dsg_review', $this->engine->nextStep($wf, 'compensation_check', 'no'), $wf);
            $this->assertTrue($this->def->outcomeNeedsReason($wf, 'compensation_check', 'yes'), $wf);
            [$desks, $end] = $this->walk($wf, ['compensation_check' => 'yes', 'dc_buffer' => 'rejected'], $sub);
            $this->assertSame('returned', $end, $wf);
            $this->assertSame(['compensation_unit', 'dsg', 'director_cadastral', 'registry'], array_slice($desks, -4), $wf);
            $this->assertTrue($this->def->outcomeNeedsReason($wf, 'dc_buffer', 'rejected'), $wf);
        }
    }

    public function test_pnd_commissions_the_jacket_and_cad_info_attaches_the_job(): void
    {
        // Transcript, 2026-10-16: P&D does a plain field jacket and I to S; AD Cad Info attaches
        // the Job Book job and the surveyor and issues the job's instruction, then sends to Survey.
        foreach (['conversion', 'direct'] as $wf) {
            $this->assertSame(['field_jacket', 'its'], array_keys($this->def->step($wf, 'pnd_instruction')['tasks']), $wf);
            $this->assertSame('ad_information', $this->def->step($wf, 'info_job_assign')['desk'], $wf);
            $this->assertSame(['job_attached', 'job_its'], array_keys($this->def->step($wf, 'info_job_assign')['tasks']), $wf);
            $this->assertSame('field_survey', $this->def->step($wf, 'info_job_assign')['next'], $wf);
        }
    }

    public function test_units_have_a_head_and_directors_do_not(): void
    {
        $heads = array_keys(array_filter($this->def->desks(), fn ($d) => !empty($d['head'])));
        $this->assertEqualsCanonicalizing(['registry', 'ad_information', 'ad_report', 'ad_pnd', 'ad_drawing', 'cadastral_gis'], $heads);
        $this->assertArrayNotHasKey('ad_cadastral', $this->def->desks());
        foreach ($heads as $desk) {
            $this->assertNotEmpty($this->def->desks()[$desk]['post'] ?? null, $desk);
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
        // From D Cad on (step 7, after the intake), both follow the Conversion sequence to the end.
        [$conversion] = $this->walk('conversion');
        foreach ([$resettlement, $allocation] as $desks) {
            $this->assertSame(array_slice($conversion, 7), array_slice($desks, array_search('cadastral_gis', $desks) - 1));
        }
        $this->assertSame(array_slice($conversion, 0, 8), array_slice($resettlement, 0, 8));
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
            'registry', 'director_cadastral', 'deputy_director_cadastral',
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
        $this->assertSame(['land12', 'yes_no', 'correspondence_created'], $this->engine->missingTasks('conversion', 'report_charting', ['charted']));
        $this->assertSame('director_cadastral', $this->def->actingDesk('conversion', 'kamma_advice'));
        $this->assertSame('registry', $this->def->actingDesk('conversion', 'land_processing'));
        $this->assertSame('ad_information', $this->def->actingDesk('conversion', 'field_survey'));
        $this->assertSame('cadastral_gis', $this->def->actingDesk('conversion', 'gis_buffer'));
        $this->assertSame('ad_drawing', $this->def->actingDesk('direct', 'survey_examination'));
        $this->assertSame('ad_information', $this->def->actingDesk('direct', 'sg_signing'));
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

    public function test_registered_file_starts_at_registry_and_goes_to_d_cad(): void
    {
        // The client (2026-10-16): no file goes to D Cad without the Head of Registry's approval.
        foreach (['conversion', 'direct', 'verification'] as $wf) {
            $this->assertSame('registry_intake', $this->def->startStep($wf), $wf);
            $this->assertSame('registry', $this->def->step($wf, 'registry_intake')['desk'], $wf);
            $this->assertSame('dc_review', $this->engine->nextStep($wf, 'registry_intake', $this->engine->actions($wf, 'registry_intake')[0]['outcome']), $wf);
        }
    }
}
