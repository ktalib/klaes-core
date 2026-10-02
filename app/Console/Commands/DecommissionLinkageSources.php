<?php

namespace App\Console\Commands;

use App\Services\PlotWorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retires source ("mother") files that a Backfill Manual Process Linkage recorded but
 * never decommissioned.
 *
 * ManualFileLinkageController::store() used to drop a source file from the decommission
 * list whenever the browser listed it in unindexed_file_numbers[], even though the file
 * was indexed — the linkage rows and child plots saved, the mother stayed active, and the
 * Legal Search timeline kept showing it as a live file. store() now decides from the
 * database instead, but linkages already saved need this catch-up pass.
 *
 * Dry-run by default; --apply performs the archive + delete through PlotWorkflowService,
 * exactly as the linkage screen would have.
 */
class DecommissionLinkageSources extends Command
{
    protected $signature = 'linkage:decommission-sources
                            {file? : Only this source file number (e.g. CON-RES-2024-308)}
                            {--apply : Perform the decommission (default is a dry run)}';

    protected $description = 'Decommission manual-linkage source files that were linked but left active';

    public function handle(PlotWorkflowService $workflowService): int
    {
        $conn   = DB::connection('sqlsrv');
        $only   = $this->argument('file') ? strtoupper(trim((string) $this->argument('file'))) : null;
        $apply  = (bool) $this->option('apply');

        // Group every linkage by its source file: one source may have many children.
        $sources = [];
        foreach ($conn->table('manual_file_linkages')->orderBy('id')->get() as $row) {
            $olds = json_decode((string) $row->old_file_numbers, true) ?: [];
            foreach ($olds as $old) {
                $old = strtoupper(trim((string) $old));
                if ($old === '' || ($only && $old !== $only)) {
                    continue;
                }
                $sources[$old]['workflow_type'] = $row->workflow_type;
                $sources[$old]['successors'][]  = trim((string) $row->new_file_number);
                $sources[$old]['processed_by']  = $row->processed_by;
            }
        }

        if (empty($sources)) {
            $this->warn($only ? "No linkage recorded for {$only}." : 'No manual linkages found.');
            return self::SUCCESS;
        }

        $pending = [];
        foreach ($sources as $fileNo => $meta) {
            // Real decommissions only. A title-status flag (false_decommissioning = 1) never
            // decommissioned this file, so it must not make the command skip a genuine
            // decommissioning it still needs to perform. An ST handover (2) counts as done.
            $alreadyArchived = $conn->table('decommissioned_files')
                ->where('mls_file_no', $fileNo)
                ->where(function ($q) {
                    $q->where('false_decommissioning', '<>', \App\Support\DecommissionScope::FALSE_DECOMMISSIONING)->orWhereNull('false_decommissioning');
                })
                ->exists();

            // Decommissioning no longer deletes rows, so "has a row" no longer means "active".
            // A file is still active only while its rows are unflagged.
            $activeIn = function (string $table, callable $match) use ($conn) {
                $query = $conn->table($table)->where($match);

                if (Schema::connection('sqlsrv')->hasColumn($table, 'is_decommissioned')) {
                    $query->where(function ($q) {
                        $q->where('is_decommissioned', 0)->orWhereNull('is_decommissioned');
                    });
                }

                return $query->exists();
            };

            $stillActive = $activeIn('fileNumber', function ($q) use ($fileNo) {
                    $q->where('mlsfNo', $fileNo)->orWhere('kangisFileNo', $fileNo);
                })
                || $activeIn('file_indexings', function ($q) use ($fileNo) {
                    $q->where('file_number', $fileNo)->orWhere('kangis_file_no', $fileNo);
                });

            if ($alreadyArchived || !$stillActive) {
                $this->line(sprintf('  <fg=gray>skip</> %-22s %s', $fileNo,
                    $alreadyArchived ? 'already decommissioned' : 'no active record left'));
                continue;
            }

            $successors = array_values(array_unique(array_filter($meta['successors'])));
            $pending[$fileNo] = [
                'workflow_type' => $meta['workflow_type'],
                'successors'    => $successors,
                'processed_by'  => $meta['processed_by'],
            ];
            $this->line(sprintf('  <fg=yellow>pending</> %-19s %s → %d successor(s): %s',
                $fileNo, $meta['workflow_type'], count($successors),
                \Illuminate\Support\Str::limit(implode(', ', $successors), 90)));
        }

        if (empty($pending)) {
            $this->info('Nothing to do — every linkage source is already decommissioned.');
            return self::SUCCESS;
        }

        if (!$apply) {
            $this->newLine();
            $this->warn(count($pending) . ' source file(s) would be decommissioned. Re-run with --apply to perform it.');
            return self::SUCCESS;
        }

        foreach ($pending as $fileNo => $meta) {
            $successorList = implode(', ', $meta['successors']);
            // Same reason/successor format store() writes, so the Decommissioned Files list
            // and Legal Search's lineage resolver read these rows like any other.
            $reason = $meta['workflow_type'] === 'Subdivision'
                ? 'Subdivision → ' . $successorList
                : "Manual Linkage: {$meta['workflow_type']} → " . $successorList;

            $summary = $workflowService->decommissionFiles(
                [$fileNo],
                $reason,
                $meta['processed_by'] ?: 'System (linkage catch-up)',
                $successorList
            );

            if (in_array($fileNo, $summary['archived'] ?? [], true)) {
                $this->info("  decommissioned {$fileNo}");
            } else {
                $this->error("  FAILED {$fileNo}: " . implode('; ', $summary['errors'] ?: ['unknown reason']));
            }
        }

        return self::SUCCESS;
    }
}
