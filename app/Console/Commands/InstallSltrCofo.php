<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the SLTR CofO Workflow modules in user_roles.
 *
 * One module per screen, as the workflow is split:
 *
 *     SLTR - CofO Front Page   CofO Workflow -> Front Page  (generate / correct / print the front page)
 *     SLTR - CofO              CofO Workflow -> CofO        (attach the TDP, print the complete certificate)
 *
 * Roles only. Nobody is assigned: users are given these through the normal user screen, and
 * actions (edit / print) through module_permissions as for every other module. Supper Admin
 * passes every check already, so it needs nothing written.
 *
 * Idempotent — an existing role is kept untouched.
 */
class InstallSltrCofo extends Command
{
    protected $signature = 'sltr-cofo:install';

    protected $description = 'Register the SLTR CofO Workflow roles (SLTR - CofO Front Page, SLTR - CofO); assigns them to nobody';

    /** SLTR's department, as the existing SLTR - * rows carry it. */
    private const SLTR_DEPARTMENT_ID = 12;

    public const ROLES = [
        'SLTR - CofO Front Page' => 'SLTR CofO Workflow: Front Page — generate, correct and print the CofO front page',
        'SLTR - CofO' => 'SLTR CofO Workflow: CofO — attach the Title Deed Plan and print the complete certificate',
    ];

    public function handle(): int
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasTable('user_roles')) {
            $this->error('user_roles is not present on the sqlsrv connection.');

            return self::FAILURE;
        }

        $columns = $schema->getColumnListing('user_roles');

        foreach (self::ROLES as $name => $description) {
            $exists = DB::connection('sqlsrv')->table('user_roles')
                ->whereRaw('UPPER(LTRIM(RTRIM(name))) = ?', [mb_strtoupper($name)])
                ->exists();

            if ($exists) {
                $this->line("  kept    role {$name}");
                continue;
            }

            // Shaped like the existing SLTR - * rows; only columns this registry has.
            $row = ['name' => $name];
            foreach ([
                'guard_name' => 'web',
                'description' => $description,
                'department_id' => self::SLTR_DEPARTMENT_ID,
                'level' => 'High',
                'user_type' => 'Operations',
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

        $this->newLine();
        $this->info('Roles registered. No user has been assigned them — assign through the user screen.');

        return self::SUCCESS;
    }
}
