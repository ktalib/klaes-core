<?php

namespace App\Console\Commands;

use App\Models\Laas\LaasApplication;
use App\Services\Laas\LaasLineageService;
use App\Services\Laas\LaasTransferService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nightly safety net for the LAAS Portal (plan §4.3). The hooks in
 * PlotWorkflowService, RegrantCorrectionService, RestoreDecommissionedFile and
 * Land Registration do this as it happens; this catches whatever they miss
 * (one-off scripts, UndoSubdivision, a hook that failed).
 *
 *  1. lifecycle_status: every portal file that is retired in the registry, or
 *     is marked retired on the portal, is re-read and corrected.
 *  2. transfers: every registered transfer-type instrument on a portal file is
 *     considered; new candidates are queued for staff. Nothing is moved.
 *
 *   php artisan laas:reconcile [--dry-run]
 */
class LaasReconcile extends Command
{
    protected $signature = 'laas:reconcile {--dry-run : Report what would change; write nothing}';

    protected $description = 'Bring LAAS Portal retired badges and the transfer queue in line with the registry';

    public function handle(LaasLineageService $lineage, LaasTransferService $transfers): int
    {
        $db = DB::connection('sqlsrv');

        // Files the registry has retired (real decommissionings only), plus files
        // the portal has badged retired (to catch a restore).
        $retiredInRegistry = $db->table('laas_applications as a')
            ->join('decommissioned_files as d', function ($j) {
                $j->on('d.file_no', '=', 'a.file_number')->orOn('d.mls_file_no', '=', 'a.file_number');
            })
            ->where(fn ($q) => $q->whereNull('d.false_decommissioning')->orWhere('d.false_decommissioning', '<>', \App\Support\DecommissionScope::FALSE_DECOMMISSIONING))
            ->distinct()
            ->pluck('a.file_number');
        $badged = LaasApplication::where('lifecycle_status', LaasApplication::LIFECYCLE_RETIRED)->distinct()->pluck('file_number');
        $files = $retiredInRegistry->merge($badged)->unique()->values()->all();

        $this->info(count($files) . ' portal file(s) to check for retirement / restore.');

        if ($this->option('dry-run')) {
            $changes = 0;
            foreach ($files as $file) {
                $isRetired = $lineage->isRetired($file);
                $rows = LaasApplication::where('file_number', $file)->current()->get();
                foreach ($rows as $row) {
                    if ($row->isRetired() !== $isRetired) {
                        $this->line(sprintf('  %s  %s -> %s', $file, $row->lifecycle_status ?: 'active', $isRetired ? 'retired' : 'active'));
                        $changes++;
                    }
                }
            }
            $this->info("{$changes} badge change(s) would be made. Transfer scan skipped in a dry run.");

            return self::SUCCESS;
        }

        $counts = $lineage->refresh($files);
        $this->info("Retired: {$counts['retired']}  Restored: {$counts['restored']}");

        $found = $transfers->scanAll();
        $this->info("{$found} new transfer candidate(s) queued for staff.");

        return self::SUCCESS;
    }
}
