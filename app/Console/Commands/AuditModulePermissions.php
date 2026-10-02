<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Permissions\ModuleName;
use App\Support\Permissions\ModuleRoutes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only health report for the permission system.
 *
 * Answers the three questions worth asking before tightening anything:
 *
 *   1. Did the backfill reach everyone who has access today?
 *   2. How much of the route table does the map actually cover?
 *   3. Which module names are granted to users but unknown to the registry?
 *
 * Writes nothing. Safe to run against production at any time.
 */
class AuditModulePermissions extends Command
{
    protected $signature = 'permissions:audit {--routes : List the mutating routes the map does not cover.}';

    protected $description = 'Report backfill coverage, route-map coverage and unregistered module grants';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <options=bold>Module permissions audit</>');
        $this->line('  <fg=gray>sqlsrv / ' . config('database.connections.sqlsrv.database')
            . ' @ ' . config('database.connections.sqlsrv.host') . '</>');
        $this->newLine();

        $this->postureSection();
        $this->coverageSection();
        $this->routeSection();

        return self::SUCCESS;
    }

    private function postureSection(): void
    {
        $strictRoutes = config('module_permissions.strict_routes') ? 'STRICT (unmapped writes denied)' : 'rollout (unmapped allowed + logged)';
        $strictActions = config('module_permissions.strict_actions') ? 'STRICT (missing row denies)' : 'rollout (missing row allows all but delete)';

        $enforced = (array) config('module_permissions.enforced_actions', ['delete']);
        $observed = array_diff(\App\Models\ModulePermission::ACTIONS, $enforced);

        $this->table(['posture', 'value'], [
            ['enabled', config('module_permissions.enabled') ? 'yes' : 'NO — gate is a no-op'],
            ['<fg=cyan>enforced actions</>', implode(', ', $enforced)],
            ['<fg=gray>observed only (logged, allowed)</>', implode(', ', $observed) ?: '—'],
            ['strict_routes', $strictRoutes],
            ['strict_actions', $strictActions],
        ]);

        if ($observed) {
            $this->line('  <fg=gray>Observed actions are recorded but never refused: the route map files an endpoint');
            $this->line('  by name prefix, which misfiles anything one module borrows from another.');
            $this->line('  Review with `permissions:denials`, then add an action to enforced_actions.</>');
        }
    }

    private function coverageSection(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('module_permissions')) {
            $this->warn('  module_permissions does not exist yet — run the migration, then permissions:backfill.');
            $this->newLine();

            return;
        }

        $users = User::query()->get(['id', 'type', 'assign_role']);

        $withAccess = 0;
        $superAdmins = 0;
        $noModules = 0;
        $expectedPairs = 0;
        $unregistered = [];

        $registry = [];
        foreach (DB::connection('sqlsrv')->table('user_roles')->pluck('name') as $name) {
            $registry[ModuleName::normalize($name)] = true;
        }

        foreach ($users as $user) {
            if ($user->isSuperAdmin()) {
                $superAdmins++;
                continue;
            }

            $modules = array_filter(
                ModuleName::listFrom($user->assign_role),
                fn ($n) => !ModuleName::isSuperAdminGrant($n)
            );

            if (!$modules) {
                $noModules++;
                continue;
            }

            $withAccess++;
            $expectedPairs += count($modules);

            foreach ($modules as $m) {
                if (!isset($registry[$m])) {
                    $unregistered[$m] = ($unregistered[$m] ?? 0) + 1;
                }
            }
        }

        $rows = DB::connection('sqlsrv')->table('module_permissions');
        $actualRows = $rows->count();
        $usersWithRows = $rows->distinct()->count('user_id');
        $deleteGrants = DB::connection('sqlsrv')->table('module_permissions')->where('can_delete', 1)->count();

        $gap = $withAccess - $usersWithRows;

        $this->table(['backfill coverage', 'count'], [
            ['users total', number_format($users->count())],
            ['  super admins (need no rows)', number_format($superAdmins)],
            ['  no modules in assign_role', number_format($noModules)],
            ['  hold at least one module', number_format($withAccess)],
            ['users with grant rows', number_format($usersWithRows)],
            ['  <fg=' . ($gap > 0 ? 'yellow' : 'green') . '>not yet backfilled</>', number_format(max($gap, 0))],
            ['(user, module) pairs expected', number_format($expectedPairs)],
            ['grant rows present', number_format($actualRows)],
            ['rows granting delete', number_format($deleteGrants)],
        ]);

        if ($gap > 0) {
            $this->line('  <fg=yellow>Backfill is incomplete. Do not set strict_actions until this reads 0.</>');
            $this->line('  <fg=gray>Sidebar access is unaffected either way — view resolves from assign_role.</>');
        } else {
            $this->line('  <fg=green>Every user holding a module has grant rows.</>');
        }

        if ($unregistered) {
            arsort($unregistered);
            $this->newLine();
            $this->line('  <fg=yellow>' . count($unregistered) . ' module name(s) granted to users but absent from user_roles:</>');
            foreach (array_slice($unregistered, 0, 12, true) as $name => $count) {
                $this->line(sprintf('    %-58s %d user(s)', $name, $count));
            }
        }

        $this->newLine();
    }

    private function routeSection(): void
    {
        $mapped = 0;
        $unmappedWrites = [];
        $total = 0;

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if ($name === '') {
                continue;
            }

            $methods = array_diff($route->methods(), ['HEAD']);
            $isWrite = (bool) array_diff($methods, ['GET', 'OPTIONS']);
            $total++;

            if (ModuleRoutes::moduleFor($name) !== null) {
                $mapped++;
                continue;
            }

            if ($isWrite && !ModuleRoutes::isExemptName($name)) {
                $unmappedWrites[$name] = implode('|', $methods);
            }
        }

        $pct = $total > 0 ? round($mapped / $total * 100, 1) : 0;

        $this->table(['route map', 'count'], [
            ['named routes', number_format($total)],
            ['mapped to a module', number_format($mapped) . '  (' . $pct . '%)'],
            ['unmapped MUTATING routes', number_format(count($unmappedWrites))],
        ]);

        if ($unmappedWrites) {
            $this->line('  <fg=gray>Unmapped mutating routes are currently ALLOWED (strict_routes is off).');
            $this->line('  Add them to config/module_permissions.php modules map, then enable strict_routes.</>');

            if ($this->option('routes')) {
                $this->newLine();
                foreach (array_slice($unmappedWrites, 0, 200, true) as $name => $verbs) {
                    $this->line(sprintf('    %-10s %s', $verbs, $name));
                }
                if (count($unmappedWrites) > 200) {
                    $this->line('    … ' . (count($unmappedWrites) - 200) . ' more');
                }
            } else {
                $this->line('  <fg=gray>Re-run with --routes to list them.</>');
            }
        }

        $this->newLine();
    }
}
