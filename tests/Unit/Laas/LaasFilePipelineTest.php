<?php

namespace Tests\Unit\Laas;

use App\Models\Laas\LaasApplication;
use App\Services\Laas\LaasFilePipeline;
use PHPUnit\Framework\TestCase;

/** The portal pipeline's step states (LAAS Portal Flow doc, 2026-11-16), from given evidence: no DB. */
class LaasFilePipelineTest extends TestCase
{
    private function build(LaasApplication $app, array $ev): array
    {
        $m = new \ReflectionMethod(LaasFilePipeline::class, 'build');
        $m->setAccessible(true);

        return $m->invoke(new LaasFilePipeline(), $app, $ev);
    }

    private function states(array $p): array
    {
        $out = [];
        foreach ($p['groups'] as $g) {
            foreach ($g['steps'] as $s) {
                $out[$s['key']] = $s['state'];
            }
        }

        return $out;
    }

    private function commissioned(string $stage = LaasApplication::STAGE_FILENO_ASSIGNED): LaasApplication
    {
        return new LaasApplication(['stage' => $stage, 'origin' => LaasApplication::ORIGIN_COMMISSIONING, 'file_number' => 'RES-2026-1']);
    }

    public function test_steps_behind_a_recorded_step_are_passed_and_the_next_is_current(): void
    {
        $s = $this->states($this->build($this->commissioned(), ['done' => ['recommendation' => true]]));

        $this->assertSame('done', $s['commission']);
        $this->assertSame('done', $s['payment']);            // implied by Commission File
        $this->assertSame('passed', $s['cad_phase_1']);
        $this->assertSame('passed', $s['correspondence']);
        $this->assertSame('current', $s['rofo']);
        $this->assertSame('todo', $s['land12']);
    }

    public function test_op_steps_always_show_first(): void
    {
        $p = $this->build($this->commissioned(), []);
        $this->assertSame(['Land', 'Deeds', 'Land/OSS'], array_slice(array_column($p['groups'], 'dept'), 0, 3));
        $without = $this->states($p);
        $this->assertSame('todo', $without['issue_op']);       // no OP: grey, never "passed"
        $this->assertSame('todo', $without['register_op']);
        $this->assertSame('todo', $p['groups'][0]['state']);

        $with = $this->states($this->build($this->commissioned(), ['has_op' => true, 'done' => ['issue_op' => true, 'register_op' => true]]));
        $this->assertSame('passed', $with['allocation_list']);
        $this->assertSame('done', $with['op_payment']);      // implied by Register OP
    }

    public function test_a_live_cadastral_step_stays_current_and_keeps_the_steps_ahead_open(): void
    {
        $s = $this->states($this->build($this->commissioned(), [
            'done' => ['rofo' => true],
            'current' => ['cad_phase_2' => true],
        ]));

        $this->assertSame('passed', $s['cad_phase_1']);
        $this->assertSame('current', $s['cad_phase_2']);
        $this->assertSame('todo', $s['planning_recommendation']);
        $this->assertSame('done', $s['rofo']);
    }

    public function test_recertification_is_its_own_line(): void
    {
        $none = $this->build($this->commissioned(), ['done' => ['bill_balance' => true]]);
        $s = $this->states($none);
        $this->assertSame('passed', $s['land12']);
        $this->assertSame('done', $s['land_archive']);       // implied by Bill Balance
        $this->assertSame('todo', $s['recert_payment']);     // not started: nothing current on that line
        $this->assertTrue($none['complete']);
        $this->assertSame(100, $none['percent']);

        $started = $this->states($this->build($this->commissioned(), ['done' => ['recert_capture' => true]]));
        $this->assertSame('passed', $started['recert_payment']);
        $this->assertSame('current', $started['recert_verification']);
        $this->assertSame('current', $started['cad_phase_1']); // the main line is not moved by it
    }

    public function test_a_new_portal_application_starts_at_the_allocation_list(): void
    {
        $app = new LaasApplication(['stage' => LaasApplication::STAGE_SUBMITTED]);
        $p = $this->build($app, []);

        $this->assertNotContains('Application', array_column($p['groups'], 'dept'));
        $this->assertSame('Land/OSS: Payment', $p['current']);   // the OP steps stay grey
        $this->assertSame('todo', $this->states($p)['allocation_list']);
        $this->assertSame('todo', $this->states($p)['commission']);
    }
}
