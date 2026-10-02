<?php

namespace App\Console\Commands;

use App\Services\DecommissionedParentRemovalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RemoveBackfilledRegrantParents extends Command
{
    protected $signature = 'regrant:remove-backfilled-parents
        {--apply : Permanently remove the confirmed backfilled parents; no backup}
        {--verify-rollback : Exercise the removal then roll the entire transaction back}';

    protected $description = 'Remove archived report-backfill parents from the four live-register tables only';

    public function handle(DecommissionedParentRemovalService $removal): int
    {
        if ($this->option('apply') && $this->option('verify-rollback')) {
            $this->error('Choose either --apply or --verify-rollback, not both.');
            return self::FAILURE;
        }
        $parents = [];
        $reportRows = 0;
        foreach (['regrant_mls_file_numbers.csv', 'regrant_file_indexings.csv'] as $name) {
            $path = storage_path('app/reports/' . $name);
            if (!is_file($path) || !($handle = fopen($path, 'r'))) {
                throw new RuntimeException("Required report missing: {$name}");
            }
            try {
                $header = fgetcsv($handle);
                $p = array_search('parent_file_number', $header ?: [], true);
                $c = array_search('regrant_file_number', $header ?: [], true);
                if ($p === false || $c === false) {
                    throw new RuntimeException("Unexpected report columns: {$name}");
                }
                while (($row = fgetcsv($handle)) !== false) {
                    $parent = strtoupper(trim($row[$p] ?? ''));
                    $child = strtoupper(trim($row[$c] ?? ''));
                    if ($parent === '' || $child === '' || $parent === $child) {
                        throw new RuntimeException("Invalid parent/successor in {$name}");
                    }
                    $parents[$parent][$child] = true;
                    $reportRows++;
                }
            } finally {
                fclose($handle);
            }
        }

        $db = DB::connection('sqlsrv');
        $confirmed = $db->table('decommissioned_files')
            ->where('decommissioned_by', 'System (Re-grant report catch-up)')
            ->where('decommissioning_reason', 'like', 'Re-grant%')
            ->where(fn ($q) => $q->where('false_decommissioning', '<>', 1)->orWhereNull('false_decommissioning'))
            ->pluck('mls_file_no')->map(fn ($n) => strtoupper(trim($n)))->unique()->values();
        $targets = [];
        foreach ($confirmed as $number) {
            if (!isset($parents[$number])) {
                throw new RuntimeException("Backfill parent {$number} is not in the reports; review required.");
            }
            foreach (array_keys($parents[$number]) as $child) {
                if (isset($parents[$child][$number])) {
                    throw new RuntimeException("Reciprocal lineage for {$number}; review required.");
                }
            }
            $targets[] = $number;
        }
        $this->info("{$reportRows} report rows; " . count($parents) . ' distinct parents; ' . count($targets) . ' confirmed backfill parents.');
        $totals = array_fill_keys(array_keys(DecommissionedParentRemovalService::TABLES), 0);
        foreach ($targets as $number) {
            $counts = [];
            foreach (array_keys($totals) as $table) {
                $counts[$table] = $removal->matchingRows($table, $number)->count();
                $totals[$table] += $counts[$table];
            }
            $this->line($number . ' ' . json_encode($counts));
        }
        $this->info('Target rows: ' . json_encode($totals));
        if (!$this->option('apply') && !$this->option('verify-rollback')) {
            $this->warn('Dry run only. Unconfirmed/ambiguous report parents are NOT deletion targets.');
            return self::SUCCESS;
        }

        $removal->assertNoIndirectDeletes();
        $level = $db->transactionLevel();
        $db->beginTransaction();
        try {
            $removed = array_fill_keys(array_keys($totals), 0);
            foreach ($targets as $number) {
                foreach ($removal->remove($number) as $table => $count) {
                    $removed[$table] += $count;
                }
                foreach (array_keys($totals) as $table) {
                    if ($removal->matchingRows($table, $number)->exists()) {
                        throw new RuntimeException("Parent {$number} still exists in {$table}.");
                    }
                }
            }
            if ($removed !== $totals) {
                throw new RuntimeException('Target rows changed after inspection; rolling back for review.');
            }
            if ($this->option('verify-rollback')) {
                $db->rollBack($level);
                $this->info('Verified removal and rolled it back: ' . json_encode($removed));
            } else {
                $db->commit();
                $this->info('REMOVED (no backup): ' . json_encode($removed));
            }
        } catch (\Throwable $e) {
            if ($db->transactionLevel() > $level) {
                $db->rollBack($level);
            }
            throw $e;
        }

        return self::SUCCESS;
    }
}
