<?php

namespace Tests\Feature\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Services\CadastralDept\CaseWorkflow;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Tests\TestCase;

/**
 * Moving a file to any step from the step bar is Super Admin / Central Admin only and needs a
 * reason (2026-10-26). Every case here is refused before anything is written.
 */
class AdminJumpTest extends TestCase
{
    private function openCase(): CadastralCase
    {
        $case = CadastralCase::query()->open()->first();
        if (!$case) {
            $this->markTestSkipped('No open Cadastral case to try.');
        }

        return $case;
    }

    public function test_a_user_without_the_admin_roles_is_refused(): void
    {
        $this->expectException(WorkflowGuardException::class);
        $this->expectExceptionMessage('Only Super Admin or Central Admin');
        app(CaseWorkflow::class)->jumpTo($this->openCase(), 'pnd_instruction', 'test', null);
    }
}
