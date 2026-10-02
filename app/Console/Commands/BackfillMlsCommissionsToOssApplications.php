<?php

namespace App\Console\Commands;

use App\Services\MlsCommissioningOssApplicationService;
use App\Support\OssOpCommissionFilter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillMlsCommissionsToOssApplications extends Command
{
    protected $signature = 'oss:backfill-mls-commissions
        {--apply : Write the missing OSS applications (without this option the command is a dry run)}
        {--file= : Process one full MLS file number only}
        {--chunk=200 : Number of MLS rows to process at a time}';

    protected $description = 'Backfill MLS File Number Generator commissions into the OSS no-change-of-name applications list';

    public function handle(MlsCommissioningOssApplicationService $syncer): int
    {
        $schema = Schema::connection('sqlsrv');
        if (!$schema->hasTable('mls_file_no') || !$schema->hasTable('oss_applications')) {
            $this->error('Both mls_file_no and oss_applications must exist on the sqlsrv connection.');
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $chunk = max(1, min((int) $this->option('chunk'), 1000));
        $counts = array_fill_keys(['eligible', 'created', 'updated', 'unchanged', 'skipped_change_of_name', 'failed'], 0);

        $query = DB::connection('sqlsrv')->table('mls_file_no')
            ->whereNotNull('full_file_number')
            ->where('full_file_number', '<>', '');

        if ($schema->hasColumn('mls_file_no', 'is_deleted')) {
            $query->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            });
        }
        if ($schema->hasColumn('mls_file_no', 'system_sub_type')) {
            $query->where(function ($q) {
                $q->whereNull('system_sub_type')->orWhere('system_sub_type', '<>', OssOpCommissionFilter::OSS);
            });
        }
        if ($file = trim((string) $this->option('file'))) {
            $query->where('full_file_number', $file);
        }

        $counts['eligible'] = (clone $query)->count();
        $this->info(($apply ? 'Applying' : 'Dry run for') . " {$counts['eligible']} eligible MLS commission(s).");

        $query->orderBy('id')->chunkById($chunk, function ($rows) use ($apply, $syncer, &$counts) {
            foreach ($rows as $row) {
                try {
                    if (!$apply) {
                        $existingRows = DB::connection('sqlsrv')->table('oss_applications')
                            ->where('file_no', trim((string) $row->full_file_number))
                            ->get();

                        if ($existingRows->contains(function ($existing) {
                            $active = !isset($existing->is_deleted) || $existing->is_deleted === null || (int) $existing->is_deleted === 0;
                            return $active && strtoupper(trim((string) ($existing->system_source ?? ''))) === MlsCommissioningOssApplicationService::CHANGE_OF_NAME_SOURCE;
                        })) {
                            $counts['skipped_change_of_name']++;
                        } elseif ($existingRows->contains(function ($existing) {
                            return !isset($existing->is_deleted) || $existing->is_deleted === null || (int) $existing->is_deleted === 0;
                        })) {
                            $counts['unchanged']++;
                        } else {
                            $counts['created']++;
                        }
                        continue;
                    }

                    $result = DB::connection('sqlsrv')->transaction(fn () => $syncer->sync($row));
                    if (isset($counts[$result['action']])) {
                        $counts[$result['action']]++;
                    }
                } catch (\Throwable $e) {
                    $counts['failed']++;
                    $this->error("{$row->full_file_number}: {$e->getMessage()}");
                }
            }
        }, 'id');

        $this->table(['Result', 'Count'], collect($counts)->map(fn ($count, $label) => [$label, $count])->values()->all());
        if (!$apply) {
            $this->warn('Dry run only. Re-run with --apply to create the mirrors.');
        }

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
