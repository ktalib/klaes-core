<?php

namespace App\Console\Commands;

use App\Models\FileIndexing;
use App\Models\PrintLabelBatch;
use App\Models\PrintLabelBatchItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillFileIndexingsShelfLocation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'printlabel:backfill-shelf-location 
        {--batch-id= : Specific batch ID to backfill}
        {--all : Backfill all batches}
        {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill shelf_location in file_indexings table from print_label_batch_items';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting shelf_location backfill...');

        $dryRun = $this->option('dry-run');
        $batchId = $this->option('batch-id');
        $all = $this->option('all');

        if (!$batchId && !$all) {
            $this->error('Please specify either --batch-id=ID or --all option');
            return 1;
        }

        try {
            if ($all) {
                $result = $this->backfillAll($dryRun);
            } else {
                $result = $this->backfillBatch((int) $batchId, $dryRun);
            }

            $this->displayResults($result, $dryRun);
            return 0;
        } catch (\Exception $e) {
            $this->error('Error during backfill: ' . $e->getMessage());
            Log::error('BackfillFileIndexingsShelfLocation error', [
                'error' => $e->getMessage(),
                'batch_id' => $batchId,
            ]);
            return 1;
        }
    }

    /**
     * Backfill a specific batch
     */
    private function backfillBatch(int $batchId, bool $dryRun): array
    {
        $batch = PrintLabelBatch::find($batchId);

        if (!$batch) {
            throw new \RuntimeException("Batch ID {$batchId} not found");
        }

        $this->info("Backfilling batch: {$batch->id} (Created: {$batch->created_at})");

        return $this->performBackfill(
            PrintLabelBatchItem::where('batch_id', $batchId),
            $dryRun
        );
    }

    /**
     * Backfill all batches
     */
    private function backfillAll(bool $dryRun): array
    {
        $totalBatches = PrintLabelBatch::count();
        $this->info("Found {$totalBatches} total batches");

        return $this->performBackfill(
            PrintLabelBatchItem::query(),
            $dryRun
        );
    }

    /**
     * Perform the actual backfill operation
     */
    private function performBackfill($query, bool $dryRun): array
    {
        $totalItems = $query->count();
        $this->info("Processing {$totalItems} items...");

        $skippedCount = 0;
        $updatedCount = 0;
        $orphanedCount = 0;

        if ($totalItems === 0) {
            return [
                'total' => 0,
                'updated' => 0,
                'skipped' => 0,
                'orphaned' => 0,
            ];
        }

        // Process in chunks
        $chunk = 500;
        $processed = 0;

        $query->select('file_indexing_id', 'shelf_location', 'batch_id')
            ->chunk($chunk, function ($items) use (&$processed, &$updatedCount, &$skippedCount, &$orphanedCount, $dryRun, $totalItems) {
                foreach ($items as $item) {
                    $processed++;

                    if ($processed % 500 === 0 || $processed === $totalItems) {
                        $this->output->write("\rProcessing: {$processed}/{$totalItems}");
                    }

                    // Check if file_indexing exists
                    $fileIndexing = FileIndexing::on('sqlsrv')->find($item->file_indexing_id);

                    if (!$fileIndexing) {
                        $orphanedCount++;
                        Log::debug('Skipping orphaned item during backfill', [
                            'file_indexing_id' => $item->file_indexing_id,
                            'batch_id' => $item->batch_id,
                        ]);
                        continue;
                    }

                    // Check if already has value
                    if ($fileIndexing->shelf_location !== null && $fileIndexing->shelf_location === $item->shelf_location) {
                        $skippedCount++;
                        continue;
                    }

                    // Update if doing actual backfill
                    if (!$dryRun) {
                        $fileIndexing->update([
                            'shelf_location' => $item->shelf_location,
                        ]);
                    }

                    $updatedCount++;
                }
            });

        $this->line('');

        return [
            'total' => $totalItems,
            'updated' => $updatedCount,
            'skipped' => $skippedCount,
            'orphaned' => $orphanedCount,
        ];
    }

    /**
     * Display backfill results
     */
    private function displayResults(array $result, bool $dryRun): void
    {
        $this->line('');
        $this->info('═══════════════════════════════════════════');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes made');
        }

        $this->line("Total items:        {$result['total']}");
        $this->line("Would update:       {$result['updated']} (new or changed values)");
        $this->line("Already correct:    {$result['skipped']} (matching values)");
        $this->line("Orphaned (skipped): {$result['orphaned']} (file_indexing not found)");

        if ($dryRun && $result['updated'] > 0) {
            $this->comment("Run without --dry-run to apply changes");
        }

        $this->info('═══════════════════════════════════════════');
    }
}
