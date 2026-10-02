<?php

namespace App\Jobs;

use App\Models\FileIndexing;
use App\Models\PrintLabelBatchItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SyncShelfLocationToFileIndexings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The batch ID to sync shelf_location for
     */
    protected int $batchId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $batchId)
    {
        $this->batchId = $batchId;
    }

    /**
     * Execute the job.
     * Syncs shelf_location from print_label_batch_items to file_indexings table
     * Silently skips items where file_indexing_id doesn't exist
     */
    public function handle(): array
    {
        Log::info('SyncShelfLocationToFileIndexings job started', [
            'batch_id' => $this->batchId,
        ]);

        try {
            // Get all items to sync from this batch
            $itemsToSync = PrintLabelBatchItem::where('batch_id', $this->batchId)
                ->select('file_indexing_id', 'shelf_location')
                ->get();

            $totalItems = $itemsToSync->count();

            if ($totalItems === 0) {
                Log::info('No items to sync for batch', ['batch_id' => $this->batchId]);
                return [
                    'synced' => 0,
                    'total' => 0,
                    'skipped' => 0,
                ];
            }

            $skippedCount = 0;
            $syncedCount = 0;

            // Process items in chunks to avoid locking issues
            foreach ($itemsToSync->chunk(500) as $chunk) {
                // Get valid file_indexing IDs in this chunk
                $fileIndexingIds = $chunk->pluck('file_indexing_id')->toArray();
                
                $validIds = FileIndexing::on('sqlsrv')
                    ->whereIn('id', $fileIndexingIds)
                    ->pluck('id')
                    ->toArray();

                // Separate valid from orphaned items
                $validItems = $chunk->filter(function ($item) use ($validIds) {
                    if (in_array($item->file_indexing_id, $validIds)) {
                        return true;
                    }
                    // Silently skip orphaned items
                    Log::debug('Skipping orphaned file_indexing_id during sync', [
                        'file_indexing_id' => $item->file_indexing_id,
                        'batch_id' => $this->batchId,
                    ]);
                    return false;
                });

                // Count skipped items
                $skippedCount += $chunk->count() - $validItems->count();

                // Update only valid items in this chunk
                if ($validItems->isNotEmpty()) {
                    $syncedCount += $this->updateFileIndexingsChunk($validItems);
                }
            }

            Log::info('SyncShelfLocationToFileIndexings job completed', [
                'batch_id' => $this->batchId,
                'total_items' => $totalItems,
                'synced' => $syncedCount,
                'skipped' => $skippedCount,
            ]);

            return [
                'synced' => $syncedCount,
                'total' => $totalItems,
                'skipped' => $skippedCount,
            ];
        } catch (\Exception $e) {
            Log::error('SyncShelfLocationToFileIndexings job failed', [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            // Re-throw to allow queue retry mechanism
            throw $e;
        }
    }

    /**
     * Update file_indexings for a chunk of valid items
     * Uses direct SQL update to avoid N+1 queries
     */
    private function updateFileIndexingsChunk($validItems): int
    {
        // Build update cases for SQL
        $cases = [];
        $fileIndexingIds = [];

        foreach ($validItems as $item) {
            $cases[] = "WHEN {$item->file_indexing_id} THEN " . DB::connection('sqlsrv')->getPdo()->quote($item->shelf_location);
            $fileIndexingIds[] = $item->file_indexing_id;
        }

        if (empty($cases)) {
            return 0;
        }

        // Build the CASE statement
        $caseStatement = implode(' ', $cases);
        $inClause = implode(',', $fileIndexingIds);

        // Execute batch update with CASE statement
        $updatedCount = DB::connection('sqlsrv')
            ->update("
                UPDATE [dbo].[file_indexings]
                SET [shelf_location] = CASE [id]
                    {$caseStatement}
                END,
                [updated_at] = GETDATE()
                WHERE [id] IN ({$inClause})
            ");

        return $updatedCount;
    }

    /**
     * Determine the number of seconds the job should be retried until for.
     * This allows the job to be retried for up to 24 hours.
     */
    public function backoff(): array
    {
        return [60, 300, 900, 1800]; // Exponential backoff: 1min, 5min, 15min, 30min
    }

    /**
     * Get the number of seconds to wait before retrying the job.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }
}
