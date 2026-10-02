<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Clean up rows left behind by Master Deletes that ran before the cascade covered them.
 *
 * The MLS Master Delete purged six tables. Commissioning, however, also PUBLISHES the file
 * into `oss_applications` (which is what /lands-one-stop-shop/applications?type=no-change-of-name
 * lists) and opens a `file_tracker` request. Neither was purged, so a deleted file kept
 * appearing on the applications page and as an ACTIVE tracking request — IND-2026-272 was
 * gone from all six tables and still listed in both.
 *
 * The cascade now covers them. This repairs the files deleted before that.
 *
 * Safety:
 *  - The candidate list comes from the `audit_logs` master-delete trail, never from a
 *    blanket predicate. Only file numbers this system recorded deleting are considered.
 *  - A file number that is LIVE again is skipped. Numbers get re-issued (CON-COM-2026-333
 *    was deleted at 12:09 and re-commissioned at 12:45 the same day), and purging then
 *    would destroy the new file's records.
 *  - Reports by default. Nothing is written without --force.
 */
class PurgeMasterDeleteOrphans extends Command
{
    protected $signature = 'mls:purge-delete-orphans
                            {--force : Actually delete. Without this the command only reports.}
                            {--file= : Restrict to a single file number.}';

    protected $description = 'Remove oss_applications and file_tracker rows orphaned by earlier MLS Master Deletes';

    public function handle(): int
    {
        $db = DB::connection('sqlsrv');
        $force = (bool) $this->option('force');
        $only = trim((string) $this->option('file'));

        $candidates = $this->masterDeletedFileNumbers($db);

        if ($only !== '') {
            $candidates = array_values(array_filter(
                $candidates,
                fn ($n) => strcasecmp($n, $only) === 0
            ));

            if (empty($candidates)) {
                $this->warn("{$only} has no master-delete audit entry — nothing to do.");

                return self::SUCCESS;
            }
        }

        $this->info(count($candidates) . ' file number(s) recorded as master-deleted.');

        $orphans = [];
        $skippedLive = [];

        foreach ($candidates as $number) {
            // Re-issued numbers belong to a NEW file. Leave them alone.
            $live = $db->table('fileNumber')->where('mlsfNo', $number)->count()
                + $db->table('mls_file_no')->where('full_file_number', $number)->count();

            if ($live > 0) {
                $skippedLive[] = $number;
                continue;
            }

            $ossCount = $db->table('oss_applications')->where('file_no', $number)->count();
            $trackerIds = $db->table('file_tracker')->where('file_number', $number)->pluck('id')->all();

            if ($ossCount === 0 && empty($trackerIds)) {
                continue;
            }

            $orphans[$number] = ['oss' => $ossCount, 'tracker_ids' => $trackerIds];
        }

        if (!empty($skippedLive)) {
            $this->line('  Skipped ' . count($skippedLive) . ' number(s) that are live again (re-issued): '
                . implode(', ', array_slice($skippedLive, 0, 8))
                . (count($skippedLive) > 8 ? ' …' : ''));
        }

        if (empty($orphans)) {
            $this->info('No orphaned rows found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['File Number', 'oss_applications', 'file_tracker'],
            array_map(
                fn ($n, $c) => [$n, $c['oss'], count($c['tracker_ids'])],
                array_keys($orphans),
                $orphans
            )
        );

        $totalOss = array_sum(array_column($orphans, 'oss'));
        $totalTracker = array_sum(array_map(fn ($c) => count($c['tracker_ids']), $orphans));

        if (!$force) {
            $this->newLine();
            $this->warn("DRY RUN — nothing was deleted.");
            $this->line("Would remove {$totalOss} oss_applications row(s) and {$totalTracker} file_tracker row(s).");
            $this->line('Re-run with --force to apply.');

            return self::SUCCESS;
        }

        $db->beginTransaction();

        try {
            $removedOss = 0;
            $removedTracker = 0;

            foreach ($orphans as $number => $counts) {
                $removedOss += $db->table('oss_applications')->where('file_no', $number)->delete();

                if (!empty($counts['tracker_ids'])) {
                    foreach (['kangis_checkout_approvals', 'file_tracker_department_backfill'] as $child) {
                        try {
                            $db->table($child)->whereIn('file_tracker_id', $counts['tracker_ids'])->delete();
                        } catch (\Throwable $e) {
                            $this->warn("  {$child}: {$e->getMessage()}");
                        }
                    }

                    // An indexing_duplicates row documents indexing, not the tracking
                    // request — clear the pointer instead of deleting the record.
                    try {
                        $db->table('indexing_duplicates')
                            ->whereIn('file_tracker_id', $counts['tracker_ids'])
                            ->update(['file_tracker_id' => null]);
                    } catch (\Throwable $e) {
                        $this->warn("  indexing_duplicates: {$e->getMessage()}");
                    }

                    $removedTracker += $db->table('file_tracker')
                        ->whereIn('id', $counts['tracker_ids'])->delete();
                }
            }

            $db->commit();

            $this->newLine();
            $this->info("Removed {$removedOss} oss_applications row(s) and {$removedTracker} file_tracker row(s).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $db->rollBack();
            $this->error('Rolled back: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * File numbers recorded in the master-delete audit trail.
     *
     * @return array<int, string>
     */
    private function masterDeletedFileNumbers($db): array
    {
        $numbers = [];

        $rows = $db->table('audit_logs')
            ->where('resource_type', 'mls_file_record')
            ->where('action', 'DELETED')
            ->pluck('old_values');

        foreach ($rows as $raw) {
            $decoded = json_decode((string) $raw, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            if (!is_array($decoded)) {
                continue;
            }

            foreach (['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no'] as $key) {
                $value = trim((string) ($decoded[$key] ?? ''));
                if ($value !== '') {
                    $numbers[] = $value;
                }
            }
        }

        return array_values(array_unique($numbers));
    }
}
