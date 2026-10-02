<?php

namespace App\Console\Commands;

use App\Support\DepartmentNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fold file_tracker.department onto the canonical `departments` names.
 *
 * The column is free text and never referenced the departments table, so the
 * commissioner dashboard — which counts DISTINCT department — reported 60
 * departments against 15 real ones. See DepartmentNormalizer for the mapping.
 *
 * Every change is written to file_tracker_department_backfill first, so the
 * original strings survive the merge and the run can be reversed with --revert.
 */
class NormalizeFileTrackerDepartments extends Command
{
    protected $signature = 'file-tracker:normalize-departments
                            {--apply : Write the changes. Without this flag the command only reports.}
                            {--revert : Restore the original departments from the backup table.}';

    protected $description = 'Normalize file_tracker.department to the canonical departments table names';

    private const BACKUP_TABLE = 'file_tracker_department_backfill';

    public function handle(): int
    {
        if ($this->option('revert')) {
            return $this->revert();
        }

        $conn = DB::connection('sqlsrv');
        $apply = (bool) $this->option('apply');

        if ($apply) {
            $this->ensureBackupTable();
        }

        // Group by the raw value: ~64 distinct strings covering ~29k rows, so
        // one UPDATE per distinct value instead of one per row.
        $groups = $conn->table('file_tracker')
            ->selectRaw("ISNULL(department, '') as raw_department, COUNT(*) as total")
            ->groupBy(DB::raw("ISNULL(department, '')"))
            ->get();

        $changed = 0;
        $rowsAffected = 0;
        $rows = [];

        foreach ($groups as $group) {
            $raw = $group->raw_department;
            $canonical = DepartmentNormalizer::normalize($raw);

            if ($canonical === $raw || ($canonical === null && trim($raw) === '')) {
                continue;
            }

            $changed++;
            $rowsAffected += (int) $group->total;
            $rows[] = [
                'From' => $raw === '' ? '(blank)' : $raw,
                'To' => $canonical ?? '(blank)',
                'Files' => $group->total,
            ];

            if (!$apply) {
                continue;
            }

            // Snapshot before the update so --revert has the ids to restore.
            $conn->statement(
                'INSERT INTO ' . self::BACKUP_TABLE . ' (file_tracker_id, old_department, new_department, backfilled_at)
                 SELECT id, department, ?, GETDATE() FROM file_tracker WHERE ISNULL(department, \'\') = ?',
                [$canonical, $raw]
            );

            $conn->table('file_tracker')
                ->whereRaw("ISNULL(department, '') = ?", [$raw])
                ->update(['department' => $canonical]);
        }

        $this->table(['From', 'To', 'Files'], $rows);

        $distinctAfter = $conn->table('file_tracker')
            ->selectRaw("COUNT(DISTINCT ISNULL(NULLIF(LTRIM(RTRIM(department)), ''), 'Unassigned')) as total")
            ->value('total');

        $this->info(sprintf(
            '%s %d distinct values (%d file rows). Distinct departments now: %d.',
            $apply ? 'Rewrote' : 'Would rewrite',
            $changed,
            $rowsAffected,
            $distinctAfter
        ));

        if (!$apply) {
            $this->comment('Dry run — re-run with --apply to write.');
        }

        return self::SUCCESS;
    }

    private function ensureBackupTable(): void
    {
        if (Schema::connection('sqlsrv')->hasTable(self::BACKUP_TABLE)) {
            return;
        }

        Schema::connection('sqlsrv')->create(self::BACKUP_TABLE, function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('file_tracker_id')->index();
            $table->string('old_department', 150)->nullable();
            $table->string('new_department', 150)->nullable();
            $table->dateTime('backfilled_at');
        });

        $this->line('Created ' . self::BACKUP_TABLE . '.');
    }

    private function revert(): int
    {
        $conn = DB::connection('sqlsrv');

        if (!Schema::connection('sqlsrv')->hasTable(self::BACKUP_TABLE)) {
            $this->error(self::BACKUP_TABLE . ' not found — nothing to revert.');
            return self::FAILURE;
        }

        // Oldest snapshot per tracker holds the pre-normalization string, so
        // that is the one to restore however many times the command has run.
        $conn->statement(
            'UPDATE ft SET ft.department = b.old_department
             FROM file_tracker ft
             INNER JOIN (
                 SELECT file_tracker_id, old_department,
                        ROW_NUMBER() OVER (PARTITION BY file_tracker_id ORDER BY id ASC) AS rn
                 FROM ' . self::BACKUP_TABLE . '
             ) b ON b.file_tracker_id = ft.id AND b.rn = 1'
        );

        $conn->table(self::BACKUP_TABLE)->delete();

        $this->info('Reverted file_tracker.department from the backup table.');

        return self::SUCCESS;
    }
}
