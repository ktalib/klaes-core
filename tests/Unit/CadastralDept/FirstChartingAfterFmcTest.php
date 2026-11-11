<?php

namespace Tests\Unit\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Services\AuditService;
use App\Services\Cadastral\CadastralRegistryLookup;
use App\Services\CadastralDept\ChartingCards;
use App\Services\CadastralDept\DeskQueues;
use App\Services\CadastralDept\FileMovementCards;
use App\Services\CadastralDept\WorkflowDefinition;
use App\Services\CadastralDept\WorkflowEngine;
use PHPUnit\Framework\TestCase;

/**
 * Direct Allocation: 1st charting, the layout check and the FMC are tasks of one
 * step. Charting comes first, the layout check second, the FMC last (recording
 * 365, 2026-11-09; the FMC used to come first). A new file shows on the 1st
 * Charting list, and reaches the FMC list once charting and the layout check
 * are done. No database.
 */
class FirstChartingAfterFmcTest extends TestCase
{
    private array $config;
    private DeskQueues $queues;
    private WorkflowDefinition $def;

    protected function setUp(): void
    {
        $this->config = require __DIR__ . '/../../../config/cadastral_dept.php';
        $this->def = new WorkflowDefinition($this->config);
        $this->queues = new DeskQueues(new WorkflowEngine($this->def));
    }

    private function caseAt(string $workflow, array $doneTasks): CadastralCase
    {
        $case = new CadastralCase();
        $case->forceFill(['workflow' => $workflow, 'current_step' => 'info_commission']);
        $case->setRelation('tasks', collect(array_map(
            fn ($t) => (object) ['step' => 'info_commission', 'task_key' => $t],
            $doneTasks
        )));

        return $case;
    }

    private function directCase(array $doneTasks): CadastralCase
    {
        return $this->caseAt('direct', $doneTasks);
    }

    private function onList(string $key, CadastralCase $case): bool
    {
        return $this->queues->matches($case, $this->config['queues']['ad_information']['entries'][$key]);
    }

    private function charting(): ChartingCards
    {
        return (new \ReflectionClass(ChartingCards::class))->newInstanceWithoutConstructor();
    }

    private function fmc(): FileMovementCards
    {
        return new FileMovementCards(
            (new \ReflectionClass(CadastralRegistryLookup::class))->newInstanceWithoutConstructor(),
            $this->def,
            (new \ReflectionClass(AuditService::class))->newInstanceWithoutConstructor()
        );
    }

    public function test_tasks_run_charting_then_layout_then_fmc(): void
    {
        $this->assertSame(['chart_first', 'layout_checked', 'fmc_commissioned'],
            array_keys($this->def->step('direct', 'info_commission')['tasks']));
    }

    public function test_new_file_is_on_the_first_charting_list_only(): void
    {
        $case = $this->directCase([]);

        $this->assertTrue($this->onList('first_charting', $case));
        $this->assertFalse($this->onList('fmc', $case));
    }

    public function test_file_waits_for_the_layout_check_before_the_fmc_list(): void
    {
        $case = $this->directCase(['chart_first']);

        $this->assertFalse($this->onList('first_charting', $case));
        $this->assertFalse($this->onList('fmc', $case));
    }

    public function test_file_reaches_the_fmc_list_after_charting_and_the_layout_check(): void
    {
        $case = $this->directCase(['chart_first', 'layout_checked']);

        $this->assertTrue($this->onList('fmc', $case));
        $this->assertFalse($this->onList('first_charting', $case));
    }

    public function test_fmc_is_blocked_until_charting_and_the_layout_check_are_done(): void
    {
        $this->assertNotNull($this->fmc()->blocker($this->directCase([])));
        $this->assertNotNull($this->fmc()->blocker($this->directCase(['chart_first'])));
        $this->assertNull($this->fmc()->blocker($this->directCase(['chart_first', 'layout_checked'])));
    }

    public function test_other_workflows_reach_the_fmc_at_once(): void
    {
        foreach (['conversion', 'verification'] as $wf) {
            if (!isset($this->def->workflows()[$wf]['steps']['info_commission']['tasks']['fmc_commissioned'])) {
                continue;
            }
            $case = $this->caseAt($wf, []);
            $this->assertNull($this->fmc()->blocker($case), $wf);
            $this->assertTrue($this->onList('fmc', $case), $wf);
        }
    }

    public function test_charting_is_never_blocked_by_the_fmc(): void
    {
        foreach (['chart_first', 'charted', 'chart_second', 'charting_checked', 'final_charting'] as $task) {
            $this->assertNull($this->charting()->blocker($this->directCase([]), $task), $task);
        }
    }
}
