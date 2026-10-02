<?php

namespace App\Console\Commands;

use App\Support\Permissions\ModuleRoutes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/**
 * Lists the mutating routes config/module_permissions.php does not map, so the map can be
 * completed from what the application really exposes rather than from guesswork.
 *
 * Two sources, because they answer different questions:
 *
 *   the route table  — everything that COULD be hit. Complete, but includes plenty that
 *                      nobody has used in years.
 *   the log          — everything that WAS hit while strict_routes was off. Much smaller,
 *                      and it is the list that actually matters for turning strict mode on
 *                      without breaking someone's day.
 *
 * --stubs prints the missing entries in the shape the config expects, ready to paste.
 */
class UnmappedModuleRoutes extends Command
{
    protected $signature = 'permissions:unmapped
                            {--log : Read the log instead of the route table — only routes users actually hit.}
                            {--stubs : Print config-ready map entries for the unmapped prefixes.}
                            {--limit=100 : How many rows to print.}';

    protected $description = 'List mutating routes that config/module_permissions.php does not map to a module';

    public function handle(): int
    {
        $rows = $this->option('log') ? $this->fromLog() : $this->fromRouteTable();

        if (!$rows) {
            $this->info($this->option('log')
                ? '  Nothing unmapped has been hit since the log was last cleared.'
                : '  Every mutating route is mapped.');

            if (!config('module_permissions.strict_routes')) {
                $this->line('  <fg=gray>You can now set MODULE_PERMISSIONS_STRICT_ROUTES=true.</>');
            }

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));

        $this->newLine();
        $this->line('  <options=bold>' . count($rows) . ' unmapped mutating route(s)</>');
        $this->line('  <fg=gray>Currently allowed through — strict_routes is '
            . (config('module_permissions.strict_routes') ? 'ON, so these are DENIED' : 'off') . '.</>');
        $this->newLine();

        $this->table(
            ['methods', 'route name', 'inferred action'],
            collect($rows)->take($limit)->map(fn ($r) => [
                $r['methods'],
                $r['name'],
                ModuleRoutes::actionFor($r['name'], $r['first_method']),
            ])->all()
        );

        if (count($rows) > $limit) {
            $this->line('  <fg=gray>… ' . (count($rows) - $limit) . ' more. Raise --limit to see them.</>');
        }

        if ($this->option('stubs')) {
            $this->printStubs($rows);
        } else {
            $this->newLine();
            $this->line('  <fg=gray>Re-run with --stubs for paste-ready config entries.</>');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{name: string, methods: string, first_method: string}>
     */
    private function fromRouteTable(): array
    {
        $rows = [];

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if ($name === '') {
                continue;
            }

            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $writes = array_values(array_diff($methods, ['GET', 'OPTIONS']));

            if (!$writes) {
                continue;
            }

            // Exempt and unmapped both make decideFor() return null, so ask separately --
            // otherwise logout and password.* are reported as holes in the map.
            if (ModuleRoutes::isExemptName($name) || ModuleRoutes::moduleFor($name) !== null) {
                continue;
            }

            $rows[$name] = [
                'name' => $name,
                'methods' => implode('|', $methods),
                'first_method' => $writes[0],
            ];
        }

        ksort($rows);

        return array_values($rows);
    }

    /**
     * Routes the middleware actually recorded. Reads today's laravel log for the marker
     * EnforceModulePermission writes.
     *
     * @return array<int, array{name: string, methods: string, first_method: string}>
     */
    private function fromLog(): array
    {
        $paths = glob(storage_path('logs/laravel*.log')) ?: [];

        if (!$paths) {
            $this->warn('  No log files found under storage/logs.');

            return [];
        }

        $rows = [];

        foreach ($paths as $path) {
            $handle = @fopen($path, 'r');

            if (!$handle) {
                continue;
            }

            while (($line = fgets($handle)) !== false) {
                if (!str_contains($line, '[module-permissions] unmapped route')) {
                    continue;
                }

                if (!preg_match('/"route":"([^"]*)".*?"method":"([^"]*)"/', $line, $m)) {
                    continue;
                }

                [, $name, $method] = $m;

                if ($name === '' || $name === '(unnamed)') {
                    continue;
                }

                $rows[$name] = [
                    'name' => $name,
                    'methods' => $method,
                    'first_method' => $method,
                ];
            }

            fclose($handle);
        }

        ksort($rows);

        return array_values($rows);
    }

    /**
     * Group by route-name prefix and emit one map entry per group — a per-route map would be
     * 659 lines nobody maintains, whereas the prefixes match how the routes were named.
     *
     * @param  array<int, array{name: string, methods: string, first_method: string}>  $rows
     */
    private function printStubs(array $rows): void
    {
        $groups = [];

        foreach ($rows as $row) {
            $prefix = explode('.', $row['name'])[0];
            $groups[$prefix] = ($groups[$prefix] ?? 0) + 1;
        }

        arsort($groups);

        $this->newLine();
        $this->line('  <options=bold>Paste into config/module_permissions.php → modules:</>');
        $this->line('  <fg=yellow>Each module name below is a GUESS from the route prefix. Check it against');
        $this->line('  user_roles before trusting it — a wrong name silently grants nobody.</>');
        $this->newLine();

        foreach ($groups as $prefix => $count) {
            $this->line(sprintf(
                "        '%s.*' => '%s',%s// %d route(s)",
                $prefix,
                'TODO — module name from user_roles',
                str_repeat(' ', max(1, 44 - strlen($prefix))),
                $count
            ));
        }
    }
}
