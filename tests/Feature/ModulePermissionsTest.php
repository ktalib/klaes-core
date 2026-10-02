<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserRole;
use App\Support\Permissions\ModuleName;
use App\Support\Permissions\ModulePermissions;
use App\Support\Permissions\ModuleRoutes;
use Tests\TestCase;

/**
 * The module permission gate.
 *
 * This is the single point every authorization decision in the app now runs through, so it is
 * worth pinning down. Before it existed there was no action-level permission anywhere: roles
 * answered only "may this user see this module", users.user_actions was written but never read,
 * and no route carried `can:` middleware.
 *
 * READ-ONLY against sqlsrv. ProductionSafetyTest guarantees writes on that connection throw
 * during tests, so these read live rows deliberately — the rules below are about production
 * data shapes (en-dashes, two spellings of Supper Admin, modules held but never registered)
 * that a fixture would not reproduce.
 */
class ModulePermissionsTest extends TestCase
{
    // ---- Name normalization -------------------------------------------------------------

    /**
     * Production spells the same module several ways. These all have to collapse, or a user
     * holding "ST – Final Conveyance" fails a check written as "ST - Final Conveyance".
     *
     * @dataProvider moduleNameProvider
     */
    public function test_module_names_normalize(string $input, string $expected): void
    {
        $this->assertSame($expected, ModuleName::normalize($input));
    }

    public static function moduleNameProvider(): array
    {
        return [
            'en-dash' => ['ST – Final Conveyance', 'st - final conveyance'],
            'em-dash' => ['ST — Final Conveyance', 'st - final conveyance'],
            'padded hyphen' => ['Deeds  -  Consent', 'deeds - consent'],
            'tight hyphen' => ['Cad-Records', 'cad - records'],
            'mixed case' => ['SURVEY - Records', 'survey - records'],
            'outer whitespace' => ['  Billing  ', 'billing'],
            'empty' => ['', ''],
        ];
    }

    /** Both spellings are in production; the misspelling is the one actually stored. */
    public function test_both_super_admin_spellings_are_recognised(): void
    {
        $this->assertTrue(ModuleName::isSuperAdminGrant('Supper Admin'));
        $this->assertTrue(ModuleName::isSuperAdminGrant('Super Admin'));
        $this->assertTrue(ModuleName::isSuperAdminGrant('supper admin'));
        $this->assertFalse(ModuleName::isSuperAdminGrant('Survey - Records'));
    }

    public function test_assign_role_csv_splits_and_deduplicates(): void
    {
        $names = ModuleName::listFrom('Dashboard, CRM - Person ,Billing,Dashboard,');

        $this->assertSame(['dashboard', 'crm - person', 'billing'], $names);
    }

    // ---- The gate -----------------------------------------------------------------------

    public function test_a_super_admin_may_do_anything(): void
    {
        $admin = User::query()->whereRaw("LOWER(type) IN ('supper admin', 'super admin')")->first()
            ?: User::query()->whereRaw("assign_role LIKE '%Supper Admin%'")->first();

        if (!$admin) {
            $this->markTestSkipped('No super admin account in this database.');
        }

        $this->assertTrue($admin->isSuperAdmin());

        foreach (['view', 'create', 'edit', 'delete', 'print', 'export'] as $action) {
            $this->assertTrue(
                $admin->canDo('Any Module At All', $action),
                "super admin should be allowed to {$action}"
            );
        }
    }

    public function test_a_user_cannot_act_on_a_module_they_cannot_see(): void
    {
        $user = $this->ordinaryUser();
        $unheld = $this->firstUnheldModule($user);

        if (!$unheld) {
            $this->markTestSkipped('This user holds every registered module.');
        }

        $this->assertFalse($user->canDo($unheld, 'view'));

        // The important half: an action is refused even if a stray grant row existed,
        // because view is checked first.
        foreach (['create', 'edit', 'delete', 'print', 'export'] as $action) {
            $this->assertFalse(
                ModulePermissions::allows($user, $unheld, $action),
                "{$action} should be refused on a module the user cannot see"
            );
        }
    }

    public function test_a_user_with_no_modules_is_refused_everything(): void
    {
        $user = User::query()->whereRaw("(assign_role IS NULL OR assign_role = '')")->first();

        if (!$user || $user->isSuperAdmin()) {
            $this->markTestSkipped('No module-less non-admin account in this database.');
        }

        $this->assertFalse($user->canDo('Billing', 'view'));
        $this->assertFalse($user->canDo('Billing', 'create'));
    }

    /**
     * Delete is never implied. Nothing in the app authorized deletion before this gate, so
     * treating it as previously-granted would be inventing a permission rather than
     * preserving one — and there are no backups to undo the result.
     */
    public function test_delete_is_not_granted_by_the_backfill(): void
    {
        $user = $this->ordinaryUser();
        $held = ModuleName::listFrom($user->assign_role);

        $held = array_values(array_filter($held, fn ($n) => !ModuleName::isSuperAdminGrant($n)));

        if (!$held) {
            $this->markTestSkipped('This user holds no modules.');
        }

        $module = $held[0];

        $this->assertTrue($user->canDo($module, 'view'), 'held module should be viewable');
        $this->assertFalse($user->canDo($module, 'delete'), 'delete must not be implied');
    }

    /**
     * 22 module names are granted to users but have no user_roles row; the sidebar still
     * honours several of them. The gate reads assign_role, not the registry, precisely so
     * these keep working — a registry-driven gate would have cut off 38 users.
     */
    public function test_a_module_missing_from_the_registry_still_resolves(): void
    {
        $user = User::query()
            ->whereRaw("assign_role LIKE '%Property Index Cards Assistant%'")
            ->first();

        if (!$user || $user->isSuperAdmin()) {
            $this->markTestSkipped('No non-admin holder of an unregistered module.');
        }

        $registry = UserRole::query()->pluck('name')
            ->map(fn ($n) => ModuleName::normalize($n))
            ->all();

        $module = 'Deeds – Property Index Cards Assistant (Legacy Records)';

        $this->assertNotContains(
            ModuleName::normalize($module),
            $registry,
            'this test is meaningless if the module has since been registered'
        );

        $this->assertTrue($user->canDo($module, 'view'));

        // And the hyphen spelling has to reach the same grant.
        $this->assertTrue($user->canDo(str_replace('–', '-', $module), 'view'));
    }

    // ---- Route resolution ---------------------------------------------------------------

    /**
     * Deletes arrive as POSTs all over this codebase (805 POST routes against 131 DELETE), so
     * the route NAME is read before the verb. A verb-first reading would file most deletions
     * as "create" and hand them to anyone who can add a record.
     *
     * @dataProvider routeActionProvider
     */
    public function test_route_actions_are_inferred(string $name, string $method, string $expected): void
    {
        $this->assertSame($expected, ModuleRoutes::actionFor($name, $method));
    }

    public static function routeActionProvider(): array
    {
        return [
            'destroy' => ['users.destroy', 'DELETE', 'delete'],
            'delete as POST' => ['records.delete-batch', 'POST', 'delete'],
            'store' => ['users.store', 'POST', 'create'],
            'update' => ['users.update', 'PUT', 'edit'],
            'print' => ['cadastral_printlabel.print-template', 'GET', 'print'],
            'export' => ['api.activity-logs.export', 'GET', 'export'],
            'index is a view' => ['fileindexing.index', 'GET', 'view'],
            'explicit override' => ['user-roles.bulk-delete', 'POST', 'delete'],
            'suspend is an edit' => ['users.suspend', 'PATCH', 'edit'],
            'unknown POST falls back to verb' => ['zzz.obscure', 'POST', 'create'],
            'unknown GET falls back to verb' => ['zzz.obscure', 'GET', 'view'],
        ];
    }

    /**
     * Approving is its own permission now, not a flavour of editing.
     *
     * The patterns are anchored (`.approve`, `-approve`, `approve-`) rather than a bare
     * `*approve*`, because the loose form swallowed things that are not approvals:
     * land-recommendations.approved-recommendation.show is a listing and ...destroy is a
     * delete. Both would have demanded approval rights to reach.
     *
     * @dataProvider approveRouteProvider
     */
    public function test_approve_routes_are_classified_separately(string $name, string $method, string $expected): void
    {
        $this->assertSame($expected, ModuleRoutes::actionFor($name, $method));
    }

    public static function approveRouteProvider(): array
    {
        return [
            'plain approve' => ['land-recommendations.approve', 'POST', 'approve'],
            'batch approve' => ['land-recommendations.batch-approve', 'POST', 'approve'],
            'reject' => ['digital-request.reject', 'POST', 'approve'],
            'approve-prefixed' => ['ptq-control.approve-for-archiving', 'POST', 'approve'],
            'bare reject' => ['api.file-trackers.reject', 'POST', 'approve'],
            'approved listing is not an approval' => ['land-recommendations.approved-recommendation.show', 'GET', 'view'],
            'deleting an approved record is a delete' => ['land-recommendations.approved-recommendation.destroy', 'DELETE', 'delete'],
            'ordinary update is still an edit' => ['users.update', 'PUT', 'edit'],
        ];
    }

    /**
     * Approve is offered only where a module has something to approve.
     */
    public function test_only_some_modules_are_approvable(): void
    {
        $this->assertTrue(ModuleRoutes::isApprovable('SLTR - Approvals'));
        $this->assertTrue(ModuleRoutes::isApprovable('Letter of Grant (RofO)'));
        // Named for approval, so approvable even before its routes are mapped.
        $this->assertTrue(ModuleRoutes::isApprovable('ST - Approvals'));

        $this->assertFalse(ModuleRoutes::isApprovable('Billing'));
        $this->assertFalse(ModuleRoutes::isApprovable('Indexing'));
        $this->assertFalse(ModuleRoutes::isApprovable(''));
    }

    /**
     * The column was added to a table where every user already had a row, so a default of 0
     * would have denied approval to everyone who had it. It is seeded from can_edit, which is
     * the permission that used to carry approve/reject.
     */
    public function test_approve_was_seeded_from_edit(): void
    {
        if (!\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('module_permissions', 'can_approve')) {
            $this->markTestSkipped('can_approve migration has not run here.');
        }

        /*
         | The invariant is NOT "approve equals edit everywhere" — that only held in the
         | instant after the migration. Saving a user through the modal makes
         | ModulePermissionSync force approve to 0 on any module with no approval step, which
         | is the rule working, not drift.
         |
         | What must stay true: approve is never granted where it cannot mean anything, and
         | where it differs from edit the reason is always that the module is not approvable.
        */
        $rows = \Illuminate\Support\Facades\DB::connection('sqlsrv')
            ->table('module_permissions')
            ->whereRaw('can_approve <> can_edit')
            ->get(['module_name', 'can_approve', 'can_edit']);

        $unexplained = [];

        foreach ($rows as $row) {
            $approvable = ModuleRoutes::isApprovable($row->module_name);

            if ($approvable || (int) $row->can_approve !== 0) {
                $unexplained[] = $row->module_name;
            }
        }

        $this->assertSame([], $unexplained,
            'approve differs from edit only where the module has no approval step');

        $granted = \Illuminate\Support\Facades\DB::connection('sqlsrv')
            ->table('module_permissions')
            ->where('can_approve', 1)
            ->pluck('module_name')
            ->unique();

        foreach ($granted as $module) {
            $this->assertTrue(ModuleRoutes::isApprovable($module),
                "approve is granted on {$module}, which has no approval step");
        }
    }

    public function test_approve_is_one_of_the_actions(): void
    {
        $this->assertContains('approve', \App\Models\ModulePermission::ACTIONS);
        $this->assertSame('can_approve', \App\Models\ModulePermission::column('approve'));
    }

    public function test_routes_map_to_modules(): void
    {
        $this->assertSame('User Account', ModuleRoutes::moduleFor('users.edit'));
        $this->assertSame('User Roles', ModuleRoutes::moduleFor('user-roles.index'));
        $this->assertNull(ModuleRoutes::moduleFor('no.such.route.prefix'));
    }

    /**
     * Exempt is not the same as unmapped, even though both make decideFor() return null.
     * Conflating them made the reporting commands list logout and password.* as gaps to fill.
     */
    public function test_auth_routes_are_exempt_rather_than_unmapped(): void
    {
        foreach (['logout', 'login', 'password.update', 'verification.send'] as $name) {
            $this->assertTrue(ModuleRoutes::isExemptName($name), "{$name} should be exempt");
            $this->assertNull(ModuleRoutes::decideFor($name, 'POST'));
        }

        $this->assertFalse(ModuleRoutes::isExemptName('users.destroy'));
    }

    // ---- Rollout posture ----------------------------------------------------------------

    /**
     * Guards the two switches that make this safe to deploy. Turning either on before the
     * matching command reports clean locks people out of a live land registry, so a change
     * to the default should have to be deliberate enough to update this test.
     */
    public function test_rollout_defaults_are_permissive(): void
    {
        $this->assertFalse(
            config('module_permissions.strict_routes'),
            'strict_routes denies every unmapped mutating route — only enable once permissions:unmapped is quiet'
        );

        $this->assertFalse(
            config('module_permissions.strict_actions'),
            'strict_actions denies any module without a grant row — only enable once permissions:audit reports 0 un-backfilled users'
        );
    }

    /**
     * Only delete is enforced at the route layer for now.
     *
     * This is not timidity, it is a correction. The map files a route under a module by its
     * NAME PREFIX, which is wrong for every endpoint one module borrows from another: 84 such
     * endpoints exist. property-records.storeFromIndexing is posted from the File Indexing
     * screen, and propertycard/legal_search calls back the Property Transaction History card —
     * both refused officers who were doing their job, and both reached production.
     *
     * Everything else is recorded and allowed through until `permissions:denials` is quiet.
     * A change here should be deliberate enough to update this test.
     */
    public function test_only_delete_is_enforced_at_the_route_layer(): void
    {
        $this->assertSame(
            ['delete'],
            (array) config('module_permissions.enforced_actions'),
            'widen this only once permissions:denials is quiet for the action being added'
        );
    }

    /**
     * Read-only endpoints must not be read as writes.
     *
     * This app posts a great many things that only read. The verb fallback calls every POST a
     * "create", which is how searching a property card came to demand create rights.
     *
     * @dataProvider readOnlyPostProvider
     */
    public function test_read_only_posts_are_classified_as_view(string $name): void
    {
        $this->assertSame('view', ModuleRoutes::actionFor($name, 'POST'));
    }

    public static function readOnlyPostProvider(): array
    {
        return [
            ['propertycard.search'],
            ['propertycard.navigate'],
            ['legalsearch.getRecord'],
            ['edms.file-type.preview'],
            ['digital-request.check-availability'],
            ['caveat.api.check-duplicates'],
            ['survey_record.fetch-primary-surveys'],
            ['land-recommendations.batch-file-details'],
            ['printlabel.api.override-lookup'],
        ];
    }

    /**
     * ...but a name that merely reads like a lookup and actually writes must not be a view.
     * InstrumentController::resolveOpDuplicates updates rows.
     */
    public function test_a_lookup_shaped_write_is_still_a_write(): void
    {
        $this->assertSame('edit', ModuleRoutes::actionFor('instruments.resolveOpDuplicates', 'POST'));
        $this->assertSame('create', ModuleRoutes::actionFor('property-records.storeFromIndexing', 'POST'));
        $this->assertSame('delete', ModuleRoutes::actionFor('users.destroy', 'DELETE'));
    }

    /**
     * The middleware itself lets ordinary work through.
     *
     * Not a reasoning exercise about config: this builds a real Request, binds it to the real
     * route and pushes it through EnforceModulePermission as a user who does NOT hold the
     * module, then checks it reached $next.
     *
     * Written after two refusals reached production. Both were map bugs, not permission
     * decisions — endpoints one module borrows from another (property-records.storeFromIndexing
     * is posted from File Indexing; legal_search.print.data backs the Property Transaction
     * History card). 84 such borrowed endpoints exist, so no amount of reading the map proves
     * this; only running the gate does.
     */
    public function test_the_middleware_does_not_refuse_ordinary_work(): void
    {
        $user = User::query()
            ->whereNotNull('assign_role')->where('assign_role', '<>', '')
            ->whereRaw("assign_role NOT LIKE '%Supper Admin%'")
            ->whereRaw("LOWER(ISNULL(type, '')) NOT IN ('supper admin', 'super admin')")
            ->first();

        if (!$user) {
            $this->markTestSkipped('No ordinary user in this database.');
        }

        $middleware = new \App\Http\Middleware\EnforceModulePermission();
        $refused = [];
        $checked = 0;

        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if ($name === '' || ModuleRoutes::isExemptName($name) || !ModuleRoutes::moduleFor($name)) {
                continue;
            }

            if (!array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS'])) {
                continue;
            }

            // delete is withheld on purpose and is asserted separately below.
            if (ModuleRoutes::actionFor($name, 'POST') === 'delete') {
                continue;
            }

            $checked++;

            if (!$this->middlewarePasses($middleware, $user, $route, 'POST')) {
                $refused[] = $name;
            }
        }

        $this->assertGreaterThan(100, $checked, 'expected a meaningful number of write routes');
        $this->assertSame([], $refused, 'these routes would refuse an ordinary user doing their job');
    }

    /**
     * ...while delete stays refused. The retreat on the other actions must not quietly
     * disable the one thing that is genuinely enforced.
     */
    public function test_the_middleware_still_refuses_delete(): void
    {
        $user = User::query()
            ->whereNotNull('assign_role')->where('assign_role', '<>', '')
            ->whereRaw("assign_role NOT LIKE '%Supper Admin%'")
            ->whereRaw("LOWER(ISNULL(type, '')) NOT IN ('supper admin', 'super admin')")
            ->first();

        if (!$user) {
            $this->markTestSkipped('No ordinary user in this database.');
        }

        $middleware = new \App\Http\Middleware\EnforceModulePermission();

        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if ($name === '' || !ModuleRoutes::moduleFor($name)) {
                continue;
            }

            if (ModuleRoutes::actionFor($name, 'POST') !== 'delete') {
                continue;
            }

            $this->assertFalse(
                $this->middlewarePasses($middleware, $user, $route, 'POST'),
                "{$name} is a delete and should have been refused"
            );

            return;
        }

        $this->markTestSkipped('No mapped delete route to check.');
    }

    /** Push one request through the middleware; true when it reached $next. */
    private function middlewarePasses($middleware, User $user, $route, string $method): bool
    {
        $request = \Illuminate\Http\Request::create('/' . ltrim($route->uri(), '/'), $method);
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(fn () => $route);

        \Illuminate\Support\Facades\Auth::setUser($user);
        ModulePermissions::forget($user->id);

        $reached = false;

        $middleware->handle($request, function () use (&$reached) {
            $reached = true;

            return response('ok');
        });

        return $reached;
    }

    // ---- Blade guards -------------------------------------------------------------------

    /**
     * Every @canDo in a blade file must have a matching close.
     *
     * Worth a test because the failure is so easy to cause and so confusing to read: Blade
     * does not know what a comment is, so writing the directive's NAME inside a `{{-- --}}`
     * or a `//` line registers a real, unclosed conditional. The template then dies with
     * "unexpected end of file, expecting elseif" pointing at the last line of the file,
     * nowhere near the prose that caused it.
     *
     * The close is @endcanDo, not @endCanDo: Blade::if() builds the end directive as the
     * literal string "end" plus the name, and directives are case-sensitive.
     */
    public function test_blade_permission_guards_are_balanced(): void
    {
        $unbalanced = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($files as $file) {
            if ($file->isDir() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $src = file_get_contents($file->getPathname());

            foreach (['canDo' => 'endcanDo', 'canAnyDo' => 'endcanAnyDo'] as $open => $close) {
                // canDo is a prefix of canAnyDo, so count whole directives only.
                $opens = preg_match_all('/@' . $open . '\s*\(/', $src);
                $closes = substr_count($src, '@' . $close);

                if ($opens !== $closes) {
                    $unbalanced[] = sprintf(
                        '%s: @%s x%d vs @%s x%d',
                        str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $file->getPathname()),
                        $open, $opens, $close, $closes
                    );
                }
            }

            // The wrong-case close compiles to nothing and leaves the guard open.
            $this->assertStringNotContainsString(
                '@endCanDo',
                $src,
                $file->getFilename() . ' uses @endCanDo; Blade only recognises @endcanDo'
            );
        }

        $this->assertSame([], $unbalanced, "Unbalanced permission guards:\n" . implode("\n", $unbalanced));
    }

    // ---- Helpers ------------------------------------------------------------------------

    private function ordinaryUser(): User
    {
        $user = User::query()
            ->whereNotNull('assign_role')
            ->where('assign_role', '<>', '')
            ->whereRaw("assign_role NOT LIKE '%Supper Admin%'")
            ->whereRaw("LOWER(ISNULL(type, '')) NOT IN ('supper admin', 'super admin')")
            ->first();

        if (!$user) {
            $this->markTestSkipped('No ordinary user with modules in this database.');
        }

        return $user;
    }

    /** First registered module this user does not hold — never a hard-coded guess. */
    private function firstUnheldModule(User $user): ?string
    {
        $held = ModuleName::listFrom($user->assign_role);

        foreach (UserRole::query()->pluck('name') as $candidate) {
            if (!in_array(ModuleName::normalize($candidate), $held, true)) {
                return $candidate;
            }
        }

        return null;
    }
}
