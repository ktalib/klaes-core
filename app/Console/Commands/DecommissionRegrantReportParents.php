<?php

namespace App\Console\Commands;

use App\Services\PlotWorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decommission the parent files explicitly listed in the Re-grant audit reports.
 *
 * This is deliberately report-scoped: it does not sweep all title-status records or
 * all manual links. It is safe to re-run; files already genuinely archived or no
 * longer active are skipped.
 */
class DecommissionRegrantReportParents extends Command
{
    protected $signature = 'regrant:decommission-report-parents
                            {--apply : Perform the archive and live-record flagging (default is a dry run)}';

    protected $description = 'Decommission active parents listed in the Re-grant CSV reports';

    private const REPORTS = [
        'storage/app/reports/regrant_mls_file_numbers.csv',
        'storage/app/reports/regrant_file_indexings.csv',
    ];

    public function handle(PlotWorkflowService $workflow): int
    {
        $parents = $this->parentsFromReports();
        if ($parents === []) {
            $this->warn('No parent files were found in the Re-grant reports.');
            return self::SUCCESS;
        }

        $conn = DB::connection('sqlsrv');
        $pending = [];
        $ambiguous = $this->reciprocalParents($parents);

        foreach ($parents as $parent => $successors) {
            // Older title-status data sometimes records the same Re-grant in both
            // directions. A three-column report cannot tell which side is the retired
            // source in that case; never retire either side by inference.
            if (isset($ambiguous[$parent])) {
                $this->line(sprintf('  <fg=red>skip</> %-24s reciprocal Re-grant relationship; review required', $parent));
                continue;
            }

            // A false-decommissioning is a title-status marker, not a retirement.
            $archived = $conn->table('decommissioned_files')
                ->where(function ($query) use ($parent) {
                    $query->where('mls_file_no', $parent)->orWhere('file_no', $parent);
                })
                ->where(function ($query) {
                    $query->where('false_decommissioning', '<>', 1)->orWhereNull('false_decommissioning');
                })
                ->exists();

            $active = $this->isActive($parent);
            if ($archived || !$active) {
                $this->line(sprintf('  <fg=gray>skip</> %-24s %s', $parent, $archived ? 'already decommissioned' : 'no active record'));
                continue;
            }

            $pending[$parent] = $successors;
            $this->line(sprintf('  <fg=yellow>pending</> %-21s Re-grant → %s', $parent, implode(', ', $successors)));
        }

        if ($pending === []) {
            $this->info('Nothing to do — every reported parent is already decommissioned or has no active record.');
            return self::SUCCESS;
        }

        if (!(bool) $this->option('apply')) {
            $this->warn(count($pending) . ' parent file(s) would be decommissioned. Re-run with --apply to perform it.');
            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($pending as $parent => $successors) {
            $successorList = implode(', ', $successors);
            $summary = $workflow->decommissionFiles(
                [$parent],
                'Re-grant → ' . $successorList,
                'System (Re-grant report catch-up)',
                $successorList
            );

            if (in_array($parent, $summary['archived'], true)) {
                $this->info("  decommissioned {$parent}");
            } else {
                $failed++;
                $this->error("  FAILED {$parent}: " . implode('; ', $summary['errors'] ?: ['unknown reason']));
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, array<int, string>> parent => unique successor numbers */
    private function parentsFromReports(): array
    {
        $parents = [];
        foreach (self::REPORTS as $report) {
            $path = base_path($report);
            if (!is_file($path) || ($handle = fopen($path, 'r')) === false) {
                $this->warn("Report unavailable: {$report}");
                continue;
            }

            $header = fgetcsv($handle) ?: [];
            $childColumn = array_search('regrant_file_number', $header, true);
            $parentColumn = array_search('parent_file_number', $header, true);
            if ($childColumn === false || $parentColumn === false) {
                fclose($handle);
                $this->warn("Report has unexpected columns: {$report}");
                continue;
            }

            while (($row = fgetcsv($handle)) !== false) {
                $parent = strtoupper(trim((string) ($row[$parentColumn] ?? '')));
                $child = strtoupper(trim((string) ($row[$childColumn] ?? '')));
                if ($parent !== '' && $child !== '') {
                    $parents[$parent][$child] = $child;
                }
            }
            fclose($handle);
        }

        return array_map(static fn (array $children): array => array_values($children), $parents);
    }

    private function isActive(string $fileNo): bool
    {
        $conn = DB::connection('sqlsrv');

        foreach ([
            ['fileNumber', ['mlsfNo', 'kangisFileNo']],
            ['file_indexings', ['file_number', 'kangis_file_no']],
        ] as [$table, $columns]) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            $query = $conn->table($table)->where(function ($query) use ($columns, $fileNo) {
                $query->where($columns[0], $fileNo)->orWhere($columns[1], $fileNo);
            });
            if (Schema::connection('sqlsrv')->hasColumn($table, 'is_decommissioned')) {
                $query->where(function ($query) {
                    $query->where('is_decommissioned', 0)->orWhereNull('is_decommissioned');
                });
            }
            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, array<int, string>> $parents @return array<string, true> */
    private function reciprocalParents(array $parents): array
    {
        $ambiguous = [];
        foreach ($parents as $parent => $successors) {
            foreach ($successors as $child) {
                if (isset($parents[$child]) && in_array($parent, $parents[$child], true)) {
                    $ambiguous[$parent] = true;
                    $ambiguous[$child] = true;
                }
            }
        }

        return $ambiguous;
    }
}
