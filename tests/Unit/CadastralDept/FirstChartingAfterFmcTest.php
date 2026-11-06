<?php

namespace Tests\Unit\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Services\CadastralDept\ChartingCards;
use App\Services\CadastralDept\DeskQueues;
use App\Services\CadastralDept\WorkflowDefinition;
use App\Services\CadastralDept\WorkflowEngine;
use PHPUnit\Framework\TestCase;

/**
 * Direct Allocation: the FMC and 1st charting are tasks of one step, so a new
 * file must show on the FMC list only, until its FMC is commissioned. No database.
 */
class FirstChartingAfterFmcTest extends TestCase
{
    private array $config;
    private DeskQueues $queues;

    protected function setUp(): void
    {
        $this->config = require __DIR__ . '/../../../config/cadastral_dept.php';
        $this->queues = new DeskQueues(new WorkflowEngine(new WorkflowDefinition($this->config)));
    }

    private function directCase(array $doneTasks): CadastralCase
    {
        $case = new CadastralCase();
        $case->forceFill(['workflow' => 'direct', 'current_step' => 'info_commission']);
        $case->setRelation('tasks', collect(array_map(
            fn ($t) => (object) ['step' => 'info_commission', 'task_key' => $t],
            $doneTasks
        )));

        return $case;
    }

    private function onList(string $key, CadastralCase $case): bool
    {
        return $this->queues->matches($case, $this->config['queues']['ad_information']['entries'][$key]);
    }

    private function cards(): ChartingCards
    {
        return (new \ReflectionClass(ChartingCards::class))->newInstanceWithoutConstructor();
    }

    public function test_new_file_is_on_the_fmc_list_only(): void
    {
        $case = $this->directCase([]);

        $this->assertTrue($this->onList('fmc', $case));
        $this->assertFalse($this->onList('first_charting', $case));
    }

    public function test_file_moves_to_first_charting_once_the_fmc_is_commissioned(): void
    {
        $case = $this->directCase(['fmc_commissioned']);

        $this->assertFalse($this->onList('fmc', $case));
        $this->assertTrue($this->onList('first_charting', $case));
    }

    public function test_file_leaves_first_charting_once_charted(): void
    {
        $case = $this->directCase(['fmc_commissioned', 'chart_first']);

        $this->assertFalse($this->onList('first_charting', $case));
    }

    public function test_first_charting_is_blocked_until_the_fmc_is_commissioned(): void
    {
        $this->assertNotNull($this->cards()->blocker($this->directCase([]), 'chart_first'));
        $this->assertNull($this->cards()->blocker($this->directCase(['fmc_commissioned']), 'chart_first'));
    }

    public function test_other_charting_stages_are_not_blocked(): void
    {
        foreach (['charted', 'chart_second', 'charting_checked', 'final_charting'] as $task) {
            $this->assertNull($this->cards()->blocker($this->directCase([]), $task), $task);
        }
    }
}
