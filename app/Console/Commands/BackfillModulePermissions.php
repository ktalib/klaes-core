<?php

namespace App\Console\Commands;

use App\Models\ModulePermission;
use App\Models\User;
use App\Support\Permissions\ModuleName;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed module_permissions from what users already hold, so that switching the gate on takes
 * nothing away from anybody.
 *
 * Why this command exists at all: 596 of 597 users have no action grants of any kind. Turning
 * on enforcement against that would be a site-wide outage. So every module a user can already
 * see is granted view + create + edit + print + export, and DELETE IS WITHHELD — delete was
 * never actually authorized by anything, so treating it as previously-granted would be
 * inventing a permission rather than preserving one.
 *
 * Keyed on the literal comma-separated tokens in users.assign_role, never on a join to
 * user_roles. 22 granted names have no registry row; 8 of those are checked by the sidebar and
 * held by 38 users. A registry join would silently skip them.
 *
 * Dry-run by default. This is a live registry with no backups — nothing writes without --commit.
 */
class BackfillModulePermissions extends Command
{
    protected $signature = 'permissions:backfill
                            {--commit : Actually write. Without this the command only reports.}
                            {--user= : Restrict to one user id, for a spot check.}
                            {--with-delete : Also grant delete. Off by default and rarely right.}
                            {--overwrite : Reset actions on rows that already exist.}';

    protected $description = 'Seed module_permissions from users.assign_role so enforcement removes nobody\'s access';

    public function handle(): int
    {
        if (!Schema::connection('sqlsrv')->hasTable('module_permissions')) {
            $this->error('module_permissions does not exist. Run the migration first:');
            $this->line('  php artisan migrate --path=database/migrations/2026_09_26_120000_create_module_permissions_table.php');

            return self::FAILURE;
        }

        $commit = (bool) $this->option('commit');
        $withDelete = (bool) $this->option('with-delete');
        $overwrite = (bool) $this->option('overwrite');

        $this->newLine();
        $this->line('  <fg=white;options=bold>Backfill module permissions</>');
        $this->line('  connection : sqlsrv / ' . config('database.connections.sqlsrv.database')
            . ' @ ' . config('database.connections.sqlsrv.host'));
        $this->line('  mode       : ' . ($commit
            ? '<fg=yellow;options=bold>COMMIT — writing to the live registry</>'
            : '<fg=green>dry run — nothing will be written</>'));
        $this->line('  delete     : ' . ($withDelete ? 'GRANTED (--with-delete)' : 'withheld'));
        $this->line('  existing   : ' . ($overwrite ? 'overwritten (--overwrite)' : 'left alone'));
        $this->newLine();

        $users = User::query()
            ->when($this->option('user'), fn ($q) => $q->where('id', (int) $this->option('user')))
            ->get(['id', 'username', 'type', 'assign_role']);

        if ($users->isEmpty()) {
            $this->warn('No users matched.');

            return self::SUCCESS;
        }

        // Every module name the registry knows, for reporting which grants are unregistered.
        $registry = [];
        foreach (DB::connection('sqlsrv')->table('user_roles')->pluck('name') as $name) {
            $registry[ModuleName::normalize($name)] = true;
        }

        $existing = $this->existingKeys($users->pluck('id')->all());

        $stats = [
            'users_seen' => 0,
            'users_granted' => 0,
            'users_super_admin' => 0,
            'users_no_modules' => 0,
            'rows_created' => 0,
            'rows_updated' => 0,
            'rows_skipped' => 0,
        ];

        $unregistered = [];
        $writes = [];
        $now = now();

        foreach ($users as $user) {
            $stats['users_seen']++;

            // A Supper Admin short-circuits in the resolver, so rows for them would be
            // dead weight that later reads could only disagree with.
            if ($user->isSuperAdmin()) {
                $stats['users_super_admin']++;
                continue;
            }

            $modules = array_filter(
                ModuleName::listFrom($user->assign_role),
                fn ($name) => !ModuleName::isSuperAdminGrant($name)
            );

            if (!$modules) {
                $stats['users_no_modules']++;
                continue;
            }

            $stats['users_granted']++;

            foreach ($modules as $module) {
                if (!isset($registry[$module])) {
                    $unregistered[$module] = ($unregistered[$module] ?? 0) + 1;
                }

                $isNew = !isset($existing[$user->id][$module]);

                if (!$isNew && !$overwrite) {
                    $stats['rows_skipped']++;
                    continue;
                }

                $isNew ? $stats['rows_created']++ : $stats['rows_updated']++;

                $writes[] = [
                    'user_id' => $user->id,
                    'module_name' => $module,
                    'can_view' => 1,
                    'can_create' => 1,
                    'can_edit' => 1,
                    'can_delete' => $withDelete ? 1 : 0,
                    'can_print' => 1,
                    'can_export' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    '_new' => $isNew,
                ];
            }
        }

        $this->report($stats, $unregistered);

        if (!$commit) {
            $this->newLine();
            $this->line('  <fg=green>Dry run complete.</> Re-run with <options=bold>--commit</> to write '
                . number_format(count($writes)) . ' row(s).');

            return self::SUCCESS;
        }

        $this->write($writes);

        $this->newLine();
        $this->info('  Backfill committed. Verify with:');
        $this->line('    php artisan permissions:audit');

        return self::SUCCESS;
    }

    /**
     * Rows already present, as [user_id][module_name] => true, so the command is re-runnable
     * without re-granting what an admin has since narrowed by hand.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, array<string, true>>
     */
    private function existingKeys(array $userIds): array
    {
        $out = [];

        foreach (array_chunk($userIds, 500) as $chunk) {
            DB::connection('sqlsrv')
                ->table('module_permissions')
                ->select('user_id', 'module_name')
                ->whereIn('user_id', $chunk)
                ->orderBy('id')
                ->chunk(2000, function ($rows) use (&$out) {
                    foreach ($rows as $row) {
                        $out[$row->user_id][ModuleName::normalize($row->module_name)] = true;
                    }
                });
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $writes
     */
    private function write(array $writes): void
    {
        if (!$writes) {
            $this->warn('  Nothing to write.');

            return;
        }

        $conn = DB::connection('sqlsrv');
        $bar = $this->output->createProgressBar(count($writes));
        $bar->start();

        // A fresh builder per statement: table() carries the table name, newQuery() does not,
        // and reusing one builder both loses the table and accumulates where clauses.
        $table = fn () => $conn->table('module_permissions');

        foreach (array_chunk($writes, 200) as $chunk) {
            $conn->transaction(function () use ($chunk, $table, $bar) {
                foreach ($chunk as $row) {
                    $isNew = $row['_new'];
                    unset($row['_new']);

                    if ($isNew) {
                        // Guard the unique key: a concurrent save from the user modal may
                        // have inserted the same pair since existingKeys() was read.
                        // SQL Server has no INSERT OR IGNORE, so the constraint is the check.
                        try {
                            $table()->insert($row);
                        } catch (QueryException $e) {
                            if (!$this->isDuplicateKey($e)) {
                                throw $e;
                            }
                        }
                    } else {
                        $table()
                            ->where('user_id', $row['user_id'])
                            ->where('module_name', $row['module_name'])
                            ->update([
                                'can_view' => $row['can_view'],
                                'can_create' => $row['can_create'],
                                'can_edit' => $row['can_edit'],
                                'can_delete' => $row['can_delete'],
                                'can_print' => $row['can_print'],
                                'can_export' => $row['can_export'],
                                'updated_at' => $row['updated_at'],
                            ]);
                    }

                    $bar->advance();
                }
            });
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * @param  array<string, int>  $stats
     * @param  array<string, int>  $unregistered
     */
    private function report(array $stats, array $unregistered): void
    {
        $this->table(['', 'count'], [
            ['users examined', number_format($stats['users_seen'])],
            ['  super admins (skipped, they short-circuit)', number_format($stats['users_super_admin'])],
            ['  no modules in assign_role', number_format($stats['users_no_modules'])],
            ['  to be granted', number_format($stats['users_granted'])],
            ['rows to create', number_format($stats['rows_created'])],
            ['rows to update', number_format($stats['rows_updated'])],
            ['rows left alone (already present)', number_format($stats['rows_skipped'])],
        ]);

        if (!$unregistered) {
            return;
        }

        arsort($unregistered);

        $this->newLine();
        $this->line('  <fg=yellow>' . count($unregistered) . ' granted module name(s) have no user_roles row.</>');
        $this->line('  <fg=gray>Granted anyway — these are real access that the registry has lost track of.');
        $this->line('  The ones the sidebar still checks are why this command reads assign_role directly.</>');

        $rows = [];
        foreach (array_slice($unregistered, 0, 25, true) as $name => $count) {
            $rows[] = [$name, $count];
        }

        $this->table(['unregistered module', 'users'], $rows);
    }

    /**
     * Laravel 9 does not raise UniqueConstraintViolationException on sqlsrv, so the driver
     * error number is the signal: 2601 is a duplicate index key, 2627 a unique/PK violation.
     */
    private function isDuplicateKey(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? '');

        return in_array($code, ['2601', '2627'], true)
            || str_contains($e->getMessage(), 'module_permissions_user_module_unique');
    }
}
