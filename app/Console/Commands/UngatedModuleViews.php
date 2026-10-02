<?php

namespace App\Console\Commands;

use App\Support\Permissions\ModuleRoutes;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Finds views that still draw a create / edit / delete / print control without a @canDo guard.
 *
 * The route middleware already refuses the request, so an ungated button is not a security
 * hole — it is a button that throws a 403 in the user's face instead of not being there. This
 * command is how that second half gets finished without anyone trawling 1,600 blade files.
 *
 * Views whose module cannot be resolved are listed separately: those need a map entry in
 * config/module_permissions.php first, because nothing can gate a control whose module is
 * unknown.
 *
 * Read-only. Prints; never edits.
 */
class UngatedModuleViews extends Command
{
    protected $signature = 'permissions:ungated
                            {--module= : Only this module.}
                            {--unmapped : List the views whose module could not be resolved.}
                            {--limit=40 : Views to print per module.}';

    protected $description = 'List views drawing create/edit/delete/print controls with no @canDo guard';

    /** Control => the pattern that betrays it in a blade file. */
    private const CONTROL_PATTERNS = [
        'delete' => '/route\(\s*[\'"][^\'"]*(destroy|\.delete|-delete|bulk-delete)/i',
        'create' => '/route\(\s*[\'"][^\'"]*(\.create|-create|\.store)/i',
        'edit' => '/route\(\s*[\'"][^\'"]*(\.edit|-edit|\.update)/i',
        'print' => '/route\(\s*[\'"][^\'"]*(print|label)/i',
        'export' => '/route\(\s*[\'"][^\'"]*(export|download|pdf|csv)/i',
    ];

    public function handle(): int
    {
        $rows = $this->scan();

        $gated = array_filter($rows, fn ($r) => $r['gated'] > 0);
        $mapped = array_filter($rows, fn ($r) => $r['module'] !== null);
        $unmapped = array_filter($rows, fn ($r) => $r['module'] === null);
        $todo = array_filter($rows, fn ($r) => $r['module'] !== null && $r['gated'] === 0);

        $this->newLine();
        $this->table(['view sweep', 'count'], [
            ['views drawing a control', number_format(count($rows))],
            ['already guarded with @canDo', number_format(count($gated))],
            ['module resolved from their routes', number_format(count($mapped))],
            ['<fg=yellow>module known, not yet guarded</>', number_format(count($todo))],
            ['<fg=gray>module unknown (needs a map entry)</>', number_format(count($unmapped))],
        ]);

        if ($this->option('unmapped')) {
            $this->listUnmapped($unmapped);

            return self::SUCCESS;
        }

        $this->listTodo($todo);

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{view: string, controls: string, module: ?string, gated: int}>
     */
    private function scan(): array
    {
        $root = str_replace('\\', '/', resource_path('views'));
        $rows = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isDir() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $rel = substr(str_replace('\\', '/', $file->getPathname()), strlen($root) + 1);

            // The installer's own views and the " - Copy" backups are not part of the app.
            if (str_contains($rel, 'vendor/') || str_contains($rel, ' - Copy')) {
                continue;
            }

            $src = file_get_contents($file->getPathname());

            $controls = [];
            foreach (self::CONTROL_PATTERNS as $control => $pattern) {
                if (preg_match($pattern, $src)) {
                    $controls[] = $control;
                }
            }

            // confirm_dialog is this app's delete confirmation wrapper.
            if (!in_array('delete', $controls, true) && str_contains($src, 'confirm_dialog')) {
                $controls[] = 'delete';
            }

            if (!$controls) {
                continue;
            }

            $rows[] = [
                'view' => $rel,
                'controls' => implode(',', $controls),
                'module' => $this->moduleOf($src),
                'gated' => substr_count($src, '@canDo') + substr_count($src, '@canAnyDo'),
            ];
        }

        return $rows;
    }

    /**
     * The module a view belongs to: whichever mapped module its route() calls point at most
     * often. A view referencing one module's routes twenty times and another's once belongs
     * to the first.
     */
    private function moduleOf(string $src): ?string
    {
        preg_match_all('/route\(\s*[\'"]([a-zA-Z0-9_.\-]+)[\'"]/', $src, $m);

        $counts = [];

        foreach ($m[1] as $name) {
            if ($module = ModuleRoutes::moduleFor($name)) {
                $counts[$module] = ($counts[$module] ?? 0) + 1;
            }
        }

        if (!$counts) {
            return null;
        }

        arsort($counts);

        return array_key_first($counts);
    }

    /** @param array<int, array<string, mixed>> $todo */
    private function listTodo(array $todo): void
    {
        $filter = $this->option('module');
        $limit = max(1, (int) $this->option('limit'));

        $byModule = [];
        foreach ($todo as $row) {
            if ($filter && $row['module'] !== $filter) {
                continue;
            }
            $byModule[$row['module']][] = $row;
        }

        if (!$byModule) {
            $this->info('  Nothing left to guard' . ($filter ? " for {$filter}." : '.'));

            return;
        }

        uasort($byModule, fn ($a, $b) => count($b) <=> count($a));

        $this->newLine();
        $this->line('  <options=bold>Views to guard, largest module first</>');
        $this->line("  <fg=gray>Wrap each control in \@canDo('<module>', '<action>') … \@endcanDo.</>");

        foreach ($byModule as $module => $views) {
            $this->newLine();
            $this->line(sprintf('  <fg=cyan>%s</>  (%d views)', $module, count($views)));

            foreach (array_slice($views, 0, $limit) as $view) {
                $this->line(sprintf('      %-64s %s', $view['view'], $view['controls']));
            }

            if (count($views) > $limit) {
                $this->line(sprintf('      … %d more', count($views) - $limit));
            }
        }
    }

    /** @param array<int, array<string, mixed>> $unmapped */
    private function listUnmapped(array $unmapped): void
    {
        $this->newLine();
        $this->line('  <options=bold>Views whose module could not be resolved</>');
        $this->line('  <fg=gray>Add the route prefix to config/module_permissions.php first; a control');
        $this->line('  cannot be guarded until its module is known.</>');
        $this->newLine();

        foreach (array_slice($unmapped, 0, 200) as $row) {
            $this->line(sprintf('      %-64s %s', $row['view'], $row['controls']));
        }

        if (count($unmapped) > 200) {
            $this->line(sprintf('      … %d more', count($unmapped) - 200));
        }
    }
}
