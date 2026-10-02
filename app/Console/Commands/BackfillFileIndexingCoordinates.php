<?php

namespace App\Console\Commands;

use App\Services\FileIndexingCoordinateBackfillService;
use Illuminate\Console\Command;

class BackfillFileIndexingCoordinates extends Command
{
    protected $signature = 'fileindexing:backfill-coordinates
                            {--limit=100 : Max rows to process this run (Nominatim allows ~1 request/second)}
                            {--batch-size=100 : Rows fetched per internal batch}
                            {--dry-run : Resolve and report without writing}
                            {--force : Also re-geocode rows that already have coordinates}
                            {--skip-lga-only : Refuse LGA-tier matches (the LGA town centre, identical for every file in that LGA)}';

    protected $description = 'Backfill file_indexings.latitude/longitude via OpenStreetMap Nominatim, using the same query chain as the web form\'s "Pin on Map" control.';

    public function handle(FileIndexingCoordinateBackfillService $service): int
    {
        $limit     = max(1, (int) $this->option('limit'));
        $batchSize = max(1, (int) $this->option('batch-size'));
        $dryRun    = (bool) $this->option('dry-run');
        $force     = (bool) $this->option('force');
        $skipLga   = (bool) $this->option('skip-lga-only');

        $service->skipLgaTier($skipLga);

        $this->info("Geocoding up to {$limit} row(s)" . ($dryRun ? ' [dry-run]' : '') . ($force ? ' [force]' : '') . '…');

        $totalCounts = [];
        $totalWritten = 0;
        $totalProcessed = 0;
        $afterId = null;
        $remaining = null;

        // Loop in sub-batches, advancing the id cursor each time, so a run of
        // unaddressable rows (SKIPPED_NO_ADDRESS — never written to) doesn't
        // keep getting re-selected as "the first N missing coordinates" forever.
        while ($totalProcessed < $limit) {
            $result = $service->runBatch(min($batchSize, $limit - $totalProcessed), $dryRun, $force, $afterId);

            foreach ($result['counts'] as $status => $n) {
                $totalCounts[$status] = ($totalCounts[$status] ?? 0) + $n;
            }
            $totalWritten += $result['written'];
            $totalProcessed += $result['processed'];
            $afterId = $result['last_id'];
            $remaining = $result['remaining'];

            $summary = collect($result['counts'])->map(fn ($n, $s) => "{$s}={$n}")->implode(', ') ?: 'no rows';
            $this->line("  batch: processed {$result['processed']} ({$summary}), remaining {$remaining}");

            // Every row in the batch failed at the transport layer: the host cannot
            // reach nominatim.openstreetmap.org (blocked outbound, DNS, rate limit).
            // Nothing is gained by grinding through the rest of the run.
            $errors = $result['counts']['ERROR'] ?? 0;
            if ($errors > 0 && $errors === $result['processed']) {
                $this->error('Could not reach nominatim.openstreetmap.org: ' . ($result['last_error'] ?? 'unknown error'));
                $this->line('  Check outbound HTTPS from this host before re-running.');
                return self::FAILURE;
            }

            if ($result['processed'] === 0) {
                break; // no more rows left to consider
            }
        }

        $this->info("Processed {$totalProcessed} row(s) total.");
        $this->info('Outcome breakdown (the tier in brackets is how precise the hit was):');
        foreach ($totalCounts as $status => $n) {
            $this->line(sprintf('  %-24s %d', $status, $n));
        }
        if (!empty($totalCounts['OK (lga)'])) {
            $this->warn("  Note: {$totalCounts['OK (lga)']} row(s) matched only at LGA level — that is the LGA's");
            $this->warn('  town centre, the same point for every file in the LGA. Use --skip-lga-only to refuse those.');
        }
        $this->info($dryRun ? 'Dry-run complete (no writes).' : "Backfill complete — {$totalWritten} row(s) updated.");
        $this->info("Remaining without coordinates: {$remaining}");

        return self::SUCCESS;
    }
}
