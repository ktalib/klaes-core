<?php

namespace Tests\Feature\CadastralDept;

use App\Http\Controllers\CadastralDept\CentralAdminController;
use App\Models\User;
use App\Services\CadastralDept\WorkflowEngine;
use App\Support\Permissions\ModulePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Central Admin (CADASTRAL UPDATE, 2026-10-26): the table opens for the Central Admin and,
 * view only, for the rest of the department; it is refused to everyone else.
 *
 * READ-ONLY against sqlsrv: the listing only reads; role changes are made on the in-memory user.
 */
class CentralAdminPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /** A real, non-admin account with its roles replaced in memory only (never saved). */
    private function userWithRoles(string $roles): User
    {
        $user = User::query()->whereRaw("LOWER(ISNULL(type, '')) NOT IN ('supper admin', 'super admin')")
            ->orderBy('id')->limit(50)->get()
            ->first(fn (User $u) => !$u->isSuperAdmin());
        if (!$user) {
            $this->markTestSkipped('No non-super-admin account in this database.');
        }
        $user->assign_role = $roles;
        ModulePermissions::forget($user->id);

        return $user;
    }

    /** Straight to the controller: the route's XSS middleware reads mysql settings, absent from klaes_testing. */
    private function open(User $user)
    {
        $this->actingAs($user);
        $request = Request::create('/cadastral-dept/central-admin', 'GET');
        $request->setUserResolver(fn () => $user);

        return app(CentralAdminController::class)->index($request, app(WorkflowEngine::class));
    }

    public function test_central_admin_gets_the_live_table(): void
    {
        $view = $this->open($this->userWithRoles('Dashboard, Cad - Central Admin'));

        $this->assertSame('cadastral_dept.central_admin.index', $view->name());
        $this->assertTrue($view->getData()['canAct']);
        $this->assertSame($view->getData()['rows']->count(), count($view->getData()['menus']));
    }

    public function test_department_officer_gets_it_view_only(): void
    {
        $view = $this->open($this->userWithRoles('Dashboard, Cad - Registry'));

        $this->assertFalse($view->getData()['canAct']);
        $this->assertSame('Cad - Central Admin', $view->getData()['centralRole']);
    }

    public function test_outside_the_department_is_refused(): void
    {
        try {
            $this->open($this->userWithRoles('Dashboard'));
            $this->fail('Expected a 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
