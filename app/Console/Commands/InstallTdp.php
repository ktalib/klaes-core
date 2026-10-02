<?php

namespace App\Console\Commands;

use App\Services\Tdp\TdpLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reference data for GIS → Title Deed Plan Management. Safe to re-run.
 *
 *   - the `GIS - Title Deed Plan` role, which the module has referenced since it
 *     was built but which was never actually created
 *   - a reachability check on the configured plan store, because a library
 *     pointing at a folder that is not there is the failure everyone hits first
 *
 * Nothing already edited by an administrator is overwritten.
 */
class InstallTdp extends Command
{
    protected $signature = 'tdp:install';

    protected $description = 'Install the Title Deed Plan role and check the configured plan store is reachable';

    public function handle(): int
    {
        $this->installRoles();
        $this->checkStore();

        return self::SUCCESS;
    }

    private function installRoles(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('user_roles')) {
            $this->warn('  user_roles is not present on this connection; roles skipped.');

            return;
        }

        $columns = Schema::connection('sqlsrv')->getColumnListing('user_roles');

        foreach ((array) config('tdp.role_catalogue', []) as $name => $description) {
            $exists = DB::connection('sqlsrv')->table('user_roles')
                ->whereRaw('UPPER(LTRIM(RTRIM(name))) = ?', [mb_strtoupper($name)])
                ->exists();

            if ($exists) {
                $this->line("  kept    role {$name}");
                continue;
            }

            // guard_name is NOT NULL with no default on this database, so every column
            // is supplied explicitly, and only if user_roles actually has it — the two
            // systems' registries do not carry an identical column list.
            $row = ['name' => $name];
            foreach ([
                'guard_name' => 'web',
                'description' => $description,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ] as $column => $value) {
                if (in_array($column, $columns, true)) {
                    $row[$column] = $value;
                }
            }

            DB::connection('sqlsrv')->table('user_roles')->insert($row);
            $this->line("  added   role {$name}");
        }
    }

    /**
     * The library degrades to a "not configured / not reachable" panel rather
     * than raising, which is right for a live screen but means a misconfigured
     * root is invisible until someone searches and finds nothing. Say it plainly
     * here instead.
     */
    private function checkStore(): void
    {
        $root = (string) config('tdp.root');

        $this->newLine();
        $this->line('  Plan store: ' . ($root !== '' ? $root : '(not configured)'));

        if ($root === '') {
            $this->warn('  TDP_ROOT_PATH is empty — the library will show "not configured" on every screen.');

            return;
        }

        if (!is_dir($root)) {
            $this->warn('  That folder is NOT reachable from this machine.');
            $this->warn('  The library, the CofO TDP stage and the merged certificate all depend on it.');
            $this->warn('  On a developer box this is expected; on the GIS server it needs fixing before go-live.');

            return;
        }

        try {
            $lgas = app(TdpLibrary::class)->lgas();
            $this->info('  Reachable — ' . count($lgas) . ' LGA folder(s) found.');

            if (!$lgas) {
                $this->warn('  No LGA sub-folders yet; the library will list nothing until plans are filed.');

                return;
            }

            $this->line('  Run the reconciliation report on the module to check the folder names against');
            $this->line('  the 17 Abia LGAs before relying on the matching.');
        } catch (\Throwable $e) {
            $this->warn('  The folder exists but could not be listed: ' . $e->getMessage());
        }
    }
}
