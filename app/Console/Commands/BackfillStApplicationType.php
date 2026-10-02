<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillStApplicationType extends Command
{
    protected $signature = 'st:backfill-application-type
                            {--connection=sqlsrv : Database connection to run against}
                            {--reclassify : Re-evaluate rows that already have a value (overwrite)}
                            {--dry-run : Print counts without writing}';

    protected $description = 'Backfill file_indexings.st_application_type (primary | pua | sua) from mother_applications and subapplications. Treats subapplications.unit_type IS NULL as PUA.';

    public function handle(): int
    {
        $conn = DB::connection($this->option('connection'));

        if (!Schema::connection($this->option('connection'))->hasColumn('file_indexings', 'st_application_type')) {
            $this->error('file_indexings.st_application_type column is missing. Run the migration first.');
            return self::FAILURE;
        }

        $reclassify = (bool) $this->option('reclassify');
        $onlyUnset  = $reclassify ? '' : 'AND fi.st_application_type IS NULL';

        $this->info('Pre-flight counts:');
        $total      = (int) $conn->table('file_indexings')->count();
        $classified = (int) $conn->table('file_indexings')->whereNotNull('st_application_type')->count();
        $this->line(sprintf('  file_indexings rows    : %d', $total));
        $this->line(sprintf('  Already classified     : %d', $classified));
        $this->line(sprintf('  Reclassify mode        : %s', $reclassify ? 'YES (overwrite existing values)' : 'no (only fill NULL)'));

        if ($this->option('dry-run')) {
            $this->warn('--dry-run set: no writes will be performed.');
            return self::SUCCESS;
        }

        $conn->transaction(function () use ($conn, $onlyUnset) {
            // 1) Primary: main_application_id links to a real mother_applications row,
            //    and there is no subapplication linkage taking precedence.
            $conn->statement("
                UPDATE fi SET fi.st_application_type = 'primary'
                FROM file_indexings fi
                INNER JOIN mother_applications ma ON ma.id = fi.main_application_id
                WHERE (fi.subapplication_id IS NULL OR fi.subapplication_id = 0)
                  $onlyUnset
            ");

            // 2) SUA: subapplication_id links to a subapplications row with unit_type='SUA' (any casing).
            $conn->statement("
                UPDATE fi SET fi.st_application_type = 'sua'
                FROM file_indexings fi
                INNER JOIN subapplications sa ON sa.id = fi.subapplication_id
                WHERE UPPER(LTRIM(RTRIM(sa.unit_type))) = 'SUA'
                  $onlyUnset
            ");

            // 3) PUA: subapplication_id links to a subapplications row where unit_type
            //    is NULL/blank or explicitly 'PUA'. NULL is the legacy default for parented units.
            $conn->statement("
                UPDATE fi SET fi.st_application_type = 'pua'
                FROM file_indexings fi
                INNER JOIN subapplications sa ON sa.id = fi.subapplication_id
                WHERE (sa.unit_type IS NULL
                       OR LTRIM(RTRIM(sa.unit_type)) = ''
                       OR UPPER(LTRIM(RTRIM(sa.unit_type))) = 'PUA')
                  $onlyUnset
            ");
        });

        $afterPrimary = (int) $conn->table('file_indexings')->where('st_application_type', 'primary')->count();
        $afterPua     = (int) $conn->table('file_indexings')->where('st_application_type', 'pua')->count();
        $afterSua     = (int) $conn->table('file_indexings')->where('st_application_type', 'sua')->count();
        $afterNull    = (int) $conn->table('file_indexings')->whereNull('st_application_type')->count();

        $this->info('Backfill complete.');
        $this->table(
            ['Type', 'Count'],
            [
                ['primary', $afterPrimary],
                ['pua',     $afterPua],
                ['sua',     $afterSua],
                ['unset',   $afterNull],
            ]
        );

        return self::SUCCESS;
    }
}
