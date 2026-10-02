<?php

namespace App\Console\Commands;

use App\Support\Permissions\ModuleRoutes;
use Illuminate\Console\Command;

/**
 * Reads back every permission refusal the gate has recorded.
 *
 * Exists because one got to production unseen: propertycard.search is a POST, the verb
 * fallback read it as "create", and officers searching a property card were told they could
 * not create in Deeds - Property Records. Nothing surfaced it — the first anyone knew was a
 * user reporting the message.
 *
 * A refusal is one of two things and they look identical from outside:
 *
 *   the permission working   — this officer really should not be doing that;
 *   a mapping bug            — the route is filed under the wrong module or action.
 *
 * The tell is the shape of the log. One user refused once is the first. The same route
 * refusing many different users is almost always the second, which is why this groups by
 * route and shows how many distinct people each one hit.
 */
class ModulePermissionDenials extends Command
{
    protected $signature = 'permissions:denials
                            {--by=route : Group by route or user.}
                            {--limit=40 : Rows to print.}';

    protected $description = 'Show permission refusals recorded by the gate, so mapping bugs surface before users report them';

    public function handle(): int
    {
        $rows = $this->fromLog();

        if (!$rows) {
            $this->info('  No refusals recorded.');
            $this->line('  <fg=gray>Either nobody has been refused, or the logs have rotated.</>');

            return self::SUCCESS;
        }

        $this->option('by') === 'user' ? $this->byUser($rows) : $this->byRoute($rows);

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function fromLog(): array
    {
        $rows = [];

        foreach (glob(storage_path('logs/laravel*.log')) ?: [] as $path) {
            $handle = @fopen($path, 'r');

            if (!$handle) {
                continue;
            }

            while (($line = fgets($handle)) !== false) {
                if (!str_contains($line, '[module-permissions] denied')) {
                    continue;
                }

                $get = function (string $key) use ($line): string {
                    return preg_match('/"' . $key . '":"?([^",}]*)"?/', $line, $m) ? $m[1] : '';
                };

                $rows[] = [
                    'user' => $get('user'),
                    'module' => $get('module'),
                    'action' => $get('action'),
                    'route' => $get('route'),
                    'method' => $get('method'),
                ];
            }

            fclose($handle);
        }

        return $rows;
    }

    /** @param array<int, array<string, string>> $rows */
    private function byRoute(array $rows): void
    {
        $grouped = [];

        foreach ($rows as $r) {
            $key = $r['route'] . ' => ' . $r['module'] . ' / ' . $r['action'];
            $grouped[$key]['hits'] = ($grouped[$key]['hits'] ?? 0) + 1;
            $grouped[$key]['users'][$r['user']] = true;
            $grouped[$key]['route'] = $r['route'];
        }

        uasort($grouped, fn ($a, $b) => count($b['users']) <=> count($a['users']));

        $this->newLine();
        $this->line('  <options=bold>' . count($rows) . ' refusal(s) across ' . count($grouped) . ' route(s)</>');
        $this->line('  <fg=gray>Many distinct users on one route usually means the map is wrong, not the officer.</>');
        $this->newLine();

        $table = [];

        foreach (array_slice($grouped, 0, (int) $this->option('limit'), true) as $key => $g) {
            $people = count($g['users']);

            $table[] = [
                $key,
                $g['hits'],
                $people,
                $people > 2 ? '<fg=yellow>check the map</>' : '',
            ];
        }

        $this->table(['route => module / action', 'hits', 'users', ''], $table);

        // The most useful next step, spelled out.
        $suspect = collect($grouped)->filter(fn ($g) => count($g['users']) > 2)->keys()->first();

        if ($suspect) {
            $this->line('  <fg=gray>Verify one with:</>');
            $this->line('    php artisan tinker --execute="echo App\\Support\\Permissions\\ModuleRoutes::actionFor(\'<route>\', \'POST\');"');
        }
    }

    /** @param array<int, array<string, string>> $rows */
    private function byUser(array $rows): void
    {
        $grouped = [];

        foreach ($rows as $r) {
            $grouped[$r['user']]['hits'] = ($grouped[$r['user']]['hits'] ?? 0) + 1;
            $grouped[$r['user']]['modules'][$r['module'] . ' / ' . $r['action']] = true;
        }

        uasort($grouped, fn ($a, $b) => $b['hits'] <=> $a['hits']);

        $this->newLine();
        $this->line('  <options=bold>Refusals by user</>');
        $this->newLine();

        $table = [];

        foreach (array_slice($grouped, 0, (int) $this->option('limit'), true) as $user => $g) {
            $table[] = [$user, $g['hits'], implode(', ', array_slice(array_keys($g['modules']), 0, 3))];
        }

        $this->table(['user', 'hits', 'refused on'], $table);
    }
}
