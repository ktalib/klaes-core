<?php

namespace Tests\Feature\CadastralDept;

use App\Http\Middleware\CadastralDeskAccess;
use App\Models\CadastralDept\CadastralCase;
use App\Models\User;
use App\Services\CadastralDept\DeskAccess;
use App\Support\Permissions\ModulePermissions;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Cadastral desk roles (2026-10-15): a desk role opens its own desk only; Cad - Records
 * covers every desk; an external step is acted on by the desk that records its return.
 * Users are unsaved models, so nothing is written.
 */
class DeskAccessTest extends TestCase
{
    private int $nextId = -9000;

    private function user(string $roles): User
    {
        $user = new User();
        $user->id = $this->nextId--;
        $user->assign_role = $roles;
        $user->type = 'user';
        ModulePermissions::forget($user->id);

        return $user;
    }

    private function access(): DeskAccess
    {
        return app(DeskAccess::class);
    }

    public function test_every_internal_desk_has_a_role(): void
    {
        $internal = array_keys(array_filter((array) config('cadastral_dept.desks'), fn ($d) => !empty($d['internal'])));
        $this->assertEqualsCanonicalizing($internal, array_keys(DeskAccess::deskRoles()));
    }

    public function test_a_desk_role_opens_only_its_desk(): void
    {
        $registry = $this->user('Cad - Registry');

        $this->assertTrue($this->access()->canWorkDesk($registry, 'registry'));
        $this->assertFalse($this->access()->canWorkDesk($registry, 'director_cadastral'));
        $this->assertFalse($this->access()->canWorkDesk($registry, 'ad_pnd'));
        $this->assertTrue($this->access()->inDepartment($registry));
    }

    public function test_department_role_covers_every_desk(): void
    {
        $records = $this->user('Cad - Records');
        foreach (array_keys(DeskAccess::deskRoles()) as $desk) {
            $this->assertTrue($this->access()->canWorkDesk($records, $desk), $desk);
        }
    }

    public function test_no_cadastral_role_opens_nothing(): void
    {
        $other = $this->user('Survey - Records');
        $this->assertFalse($this->access()->canWorkDesk($other, 'registry'));
        $this->assertFalse($this->access()->inDepartment($other));
        $this->assertFalse(DeskAccess::can($other, 'print'));
    }

    public function test_desk_scoped_can_ignores_other_desks(): void
    {
        $registry = $this->user('Cad - Registry');
        $pnd = $this->user('Cad - P&D');

        $this->assertTrue(DeskAccess::can($registry, 'print'));
        $this->assertFalse(DeskAccess::can($registry, 'create', 'ad_pnd'));
        $this->assertTrue(DeskAccess::can($pnd, 'create', 'ad_pnd'));
        // Delete is never implied by a view grant.
        $this->assertFalse(DeskAccess::can($pnd, 'delete', 'ad_pnd'));
    }

    public function test_external_step_is_acted_on_by_its_recorder(): void
    {
        $case = new CadastralCase(['workflow' => 'conversion', 'current_step' => 'land_processing', 'current_desk' => 'director_land']);

        $this->assertSame('registry', $this->access()->actingDesk($case));
        $this->assertTrue($this->access()->canActOn($this->user('Cad - Registry'), $case));
        $this->assertFalse($this->access()->canActOn($this->user('Cad - D Cad'), $case));
    }

    public function test_middleware_refuses_another_desks_page(): void
    {
        $request = $this->deskRequest('director_cadastral', $this->user('Cad - Registry'));

        try {
            app(CadastralDeskAccess::class)->handle($request, fn () => response('ok'));
            $this->fail('Expected a 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_middleware_lets_the_desk_holder_through(): void
    {
        $request = $this->deskRequest('registry', $this->user('Cad - Registry'));
        $response = app(CadastralDeskAccess::class)->handle($request, fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_middleware_refuses_intake_without_registry(): void
    {
        $request = Request::create('/cadastral-dept/cases', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $route = (new Route('POST', 'cadastral-dept/cases', []))->name('cadastral-dept.cases.store')->bind($request);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $this->user('Cad - D Cad'));

        $response = app(CadastralDeskAccess::class)->handle($request, fn () => response('ok'));
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_central_admin_works_every_desk_and_acts_on_any_file(): void
    {
        $central = $this->user('Cad - Central Admin');

        $this->assertTrue(DeskAccess::isCentralAdmin($central));
        $this->assertTrue($this->access()->inDepartment($central));
        foreach (array_keys(DeskAccess::deskRoles()) as $desk) {
            $this->assertTrue($this->access()->canWorkDesk($central, $desk), $desk);
            $this->assertFalse($this->access()->officerCanWorkDesk($central, $desk), $desk);
        }
        $away = new CadastralCase(['workflow' => 'conversion', 'current_step' => 'land_processing', 'current_desk' => 'director_land']);
        $this->assertTrue($this->access()->canActOn($central, $away));
        $this->assertTrue(DeskAccess::can($central, 'create', 'ad_pnd'));
    }

    public function test_central_admin_stands_in_for_every_unit_head(): void
    {
        $heads = app(\App\Services\CadastralDept\HeadApproval::class);
        $central = $this->user('Cad - Registry, Cad - Central Admin');

        $this->assertTrue(DeskAccess::holdsCentralAdminRole($central));
        foreach (['registry', 'ad_information', 'ad_report', 'ad_pnd', 'ad_drawing', 'cadastral_gis'] as $desk) {
            $this->assertTrue($heads->isHead($central, $desk), $desk);
        }
    }

    public function test_desk_officer_is_not_central_admin(): void
    {
        $records = $this->user('Cad - Records');

        $this->assertFalse(DeskAccess::isCentralAdmin($records));
        $this->assertFalse(DeskAccess::holdsCentralAdminRole($records));
    }

    public function test_middleware_lets_central_admin_onto_any_desk_page(): void
    {
        $request = $this->deskRequest('director_cadastral', $this->user('Cad - Central Admin'));
        $response = app(CadastralDeskAccess::class)->handle($request, fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    private function deskRequest(string $desk, User $user): Request
    {
        $request = Request::create("/cadastral-dept/desks/{$desk}", 'GET');
        $route = (new Route('GET', 'cadastral-dept/desks/{desk}', []))->name('cadastral-dept.desks.show')->bind($request);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
