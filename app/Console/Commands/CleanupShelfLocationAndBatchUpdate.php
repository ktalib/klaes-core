<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Exception;

class CleanupShelfLocationAndBatchUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fileindexing:cleanup-shelf-batch {--dry-run : Run in dry-run mode without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up shelf_location field and update fileindexing_batch statistics';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('🔍 Running in DRY-RUN mode - no changes will be made');
        } else {
            $this->info('🚀 Running cleanup and update process...');
        }

        try {
            // Start transaction
            DB::connection('sqlsrv')->beginTransaction();

            // Step 1: Get initial statistics
            $this->info('📊 Getting initial statistics...');
            $initialStats = $this->getInitialStats();
            $this->displayStats('Initial State', $initialStats);

            // Step 2: Update shelf_location from full_label
            $this->info('🔄 Updating shelf_location with full_label...');
            $updatedLocations = $this->updateShelfLocations($isDryRun);
            $this->line("   Updated shelf_location for {$updatedLocations} records");

            // Step 3: Report (never clear) shelf_location values without a shelf_label_id.
            // Those come from print-label batches, the file tracker and imports, none of
            // which set shelf_label_id - they are real shelves, not orphans.
            $this->info('🔎 Counting shelf_location values not linked to a shelf label...');
            $unlinked = $this->countUnlinkedShelfLocations();
            $this->line("   {$unlinked} records carry a shelf_location without shelf_label_id (kept)");

            // Step 4: Update Rack_Shelf_Labels is_used flags
            $this->info('🏷️  Updating Rack_Shelf_Labels is_used flags...');
            [$markedUsed, $markedUnused] = $this->updateShelfUsageFlags($isDryRun);
            $this->line("   Marked {$markedUsed} shelves as used");
            $this->line("   Marked {$markedUnused} shelves as unused");

            // Step 5: Update fileindexing_batch statistics
            $this->info('📦 Updating fileindexing_batch statistics...');
            $updatedBatches = $this->updateBatchStatistics($isDryRun);
            $this->line("   Updated statistics for {$updatedBatches} batches");

            // Step 6: Assign batch_id to file_indexings
            $this->info('🔗 Assigning batch_id to file_indexings records...');
            $assignedBatchIds = $this->assignBatchIds($isDryRun);
            $this->line("   Assigned batch_id to {$assignedBatchIds} records");

            // Step 7: Get final statistics and validate
            $this->info('✅ Validating results...');
            $finalStats = $this->getInitialStats();
            $this->displayStats('Final State', $finalStats);

            // Check for inconsistencies
            $inconsistencies = $this->validateData();
            if ($inconsistencies > 0) {
                $this->warn("⚠️  Found {$inconsistencies} inconsistencies - please review");
            } else {
                $this->info('✅ No inconsistencies found - data is clean!');
            }

            // Display batch summary
            $this->displayBatchSummary();

            if ($isDryRun) {
                DB::connection('sqlsrv')->rollBack();
                $this->info('🔍 DRY-RUN completed - no changes were made');
                return 0;
            } else {
                DB::connection('sqlsrv')->commit();
                $this->info('✅ Cleanup completed successfully - changes committed');
                return 0;
            }

        } catch (Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            $this->error('❌ Error occurred: ' . $e->getMessage());
            $this->error('Transaction rolled back');
            return 1;
        }
    }

    private function getInitialStats()
    {
        return [
            'total_file_indexings' => DB::connection('sqlsrv')->table('file_indexings')->count(),
            'with_shelf_id' => DB::connection('sqlsrv')->table('file_indexings')->whereNotNull('shelf_label_id')->count(),
            'with_shelf_location' => DB::connection('sqlsrv')->table('file_indexings')
                ->whereNotNull('shelf_location')
                ->where('shelf_location', '!=', '')
                ->count(),
            'with_batch_id' => DB::connection('sqlsrv')->table('file_indexings')->whereNotNull('batch_id')->count(),
            'total_batches' => DB::connection('sqlsrv')->table('fileindexing_batch')->count(),
            'used_shelves' => DB::connection('sqlsrv')->table('Rack_Shelf_Labels')->where('is_used', 1)->count(),
        ];
    }

    private function updateShelfLocations($isDryRun)
    {
        $query = DB::connection('sqlsrv')->table('file_indexings as fi')
            ->join('Rack_Shelf_Labels as rsl', 'fi.shelf_label_id', '=', 'rsl.id')
            ->whereNotNull('fi.shelf_label_id')
            ->where(function($query) {
                $query->whereNull('fi.shelf_location')
                      ->orWhere('fi.shelf_location', '')
                      ->orWhereRaw('fi.shelf_location != rsl.full_label');
            });

        $count = $query->count();

        if (!$isDryRun && $count > 0) {
            DB::connection('sqlsrv')->statement("
                UPDATE fi
                SET fi.shelf_location = rsl.full_label,
                    fi.updated_at = GETDATE()
                FROM dbo.file_indexings AS fi
                INNER JOIN dbo.Rack_Shelf_Labels AS rsl ON fi.shelf_label_id = rsl.id
                WHERE fi.shelf_label_id IS NOT NULL
                  AND (fi.shelf_location IS NULL 
                       OR fi.shelf_location = '' 
                       OR fi.shelf_location != rsl.full_label)
            ");
        }

        return $count;
    }

    private function countUnlinkedShelfLocations()
    {
        return DB::connection('sqlsrv')->table('file_indexings')
            ->whereNull('shelf_label_id')
            ->whereNotNull('shelf_location')
            ->where('shelf_location', '!=', '')
            ->count();
    }

    private function updateShelfUsageFlags($isDryRun)
    {
        // Count shelves to mark as used
        $toMarkUsed = DB::connection('sqlsrv')->table('Rack_Shelf_Labels as rsl')
            ->join('file_indexings as fi', 'fi.shelf_label_id', '=', 'rsl.id')
            ->where(function($query) {
                $query->where('rsl.is_used', '!=', 1)
                      ->orWhereNull('rsl.is_used');
            })
            ->count();

        // Count shelves to mark as unused
        $toMarkUnused = DB::connection('sqlsrv')->table('Rack_Shelf_Labels as rsl')
            ->leftJoin('file_indexings as fi', 'fi.shelf_label_id', '=', 'rsl.id')
            ->whereNull('fi.shelf_label_id')
            ->where(function($query) {
                $query->where('rsl.is_used', 1)
                      ->orWhereNull('rsl.is_used');
            })
            ->count();

        if (!$isDryRun) {
            // Mark as used
            if ($toMarkUsed > 0) {
                DB::connection('sqlsrv')->statement("
                    UPDATE rsl
                    SET rsl.is_used = 1
                    FROM dbo.Rack_Shelf_Labels AS rsl
                    INNER JOIN dbo.file_indexings AS fi ON fi.shelf_label_id = rsl.id
                    WHERE rsl.is_used != 1 OR rsl.is_used IS NULL
                ");
            }

            // Mark as unused
            if ($toMarkUnused > 0) {
                DB::connection('sqlsrv')->statement("
                    UPDATE rsl
                    SET rsl.is_used = 0
                    FROM dbo.Rack_Shelf_Labels AS rsl
                    LEFT JOIN dbo.file_indexings AS fi ON fi.shelf_label_id = rsl.id
                    WHERE fi.shelf_label_id IS NULL 
                      AND (rsl.is_used = 1 OR rsl.is_used IS NULL)
                ");
            }
        }

        return [$toMarkUsed, $toMarkUnused];
    }

    private function updateBatchStatistics($isDryRun)
    {
        // Check if batch table exists
        $batchTableExists = DB::connection('sqlsrv')->select("
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_NAME = 'fileindexing_batch'
        ");

        if (empty($batchTableExists)) {
            $this->warn('fileindexing_batch table not found - skipping batch statistics update');
            return 0;
        }

        // Get count of batches that need updating
        $batchesToUpdate = DB::connection('sqlsrv')->select("
            SELECT COUNT(*) as count
            FROM dbo.fileindexing_batch fb
            WHERE fb.used_shelves != (
                SELECT COUNT(fi.id)
                FROM dbo.file_indexings fi
                WHERE fi.shelf_label_id BETWEEN fb.start_shelf_id AND fb.end_shelf_id
            )
        ")[0]->count ?? 0;

        if (!$isDryRun && $batchesToUpdate > 0) {
            DB::connection('sqlsrv')->statement("
                WITH batch_usage AS (
                    SELECT 
                        fb.id AS batch_id,
                        fb.batch_number,
                        fb.start_shelf_id,
                        fb.end_shelf_id,
                        fb.shelf_count,
                        COUNT(fi.id) AS actual_used_shelves
                    FROM dbo.fileindexing_batch AS fb
                    LEFT JOIN dbo.file_indexings AS fi 
                        ON fi.shelf_label_id BETWEEN fb.start_shelf_id AND fb.end_shelf_id
                    GROUP BY fb.id, fb.batch_number, fb.start_shelf_id, fb.end_shelf_id, fb.shelf_count
                )
                UPDATE fb
                SET fb.used_shelves = bu.actual_used_shelves,
                    fb.is_full = CASE 
                        WHEN bu.actual_used_shelves >= fb.shelf_count THEN 1 
                        ELSE 0 
                    END,
                    fb.updated_at = GETDATE()
                FROM dbo.fileindexing_batch AS fb
                INNER JOIN batch_usage AS bu ON bu.batch_id = fb.id
                WHERE fb.used_shelves != bu.actual_used_shelves 
                   OR fb.is_full != CASE WHEN bu.actual_used_shelves >= fb.shelf_count THEN 1 ELSE 0 END
            ");
        }

        return $batchesToUpdate;
    }

    private function assignBatchIds($isDryRun)
    {
        // Check if batch table exists
        $batchTableExists = DB::connection('sqlsrv')->select("
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_NAME = 'fileindexing_batch'
        ");

        if (empty($batchTableExists)) {
            $this->warn('fileindexing_batch table not found - skipping batch_id assignment');
            return 0;
        }

        // Check if batch_id column exists
        $batchIdColumnExists = DB::connection('sqlsrv')->select("
            SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'batch_id'
        ");

        if (empty($batchIdColumnExists)) {
            $this->warn('batch_id column not found in file_indexings - skipping batch_id assignment');
            return 0;
        }

        // Count records that need batch_id assignment
        $toAssign = DB::connection('sqlsrv')->select("
            SELECT COUNT(*) as count
            FROM dbo.file_indexings fi
            INNER JOIN dbo.fileindexing_batch fb
                ON fi.shelf_label_id BETWEEN fb.start_shelf_id AND fb.end_shelf_id
            WHERE fi.shelf_label_id IS NOT NULL 
              AND fi.batch_id IS NULL
        ")[0]->count ?? 0;

        if (!$isDryRun && $toAssign > 0) {
            DB::connection('sqlsrv')->statement("
                UPDATE fi
                SET fi.batch_id = fb.id
                FROM dbo.file_indexings AS fi
                INNER JOIN dbo.fileindexing_batch AS fb
                    ON fi.shelf_label_id BETWEEN fb.start_shelf_id AND fb.end_shelf_id
                WHERE fi.shelf_label_id IS NOT NULL 
                  AND fi.batch_id IS NULL
            ");
        }

        return $toAssign;
    }

    private function validateData()
    {
        $inconsistencies = 0;

        // Check: Records with shelf_label_id but no shelf_location
        $missingLocation = DB::connection('sqlsrv')->table('file_indexings')
            ->whereNotNull('shelf_label_id')
            ->where(function($query) {
                $query->whereNull('shelf_location')
                      ->orWhere('shelf_location', '');
            })
            ->count();

        if ($missingLocation > 0) {
            $this->warn("   {$missingLocation} records have shelf_label_id but missing shelf_location");
            $inconsistencies++;
        }

        // Check: Records with shelf_location but no shelf_label_id
        $orphanedLocation = DB::connection('sqlsrv')->table('file_indexings')
            ->whereNull('shelf_label_id')
            ->whereNotNull('shelf_location')
            ->where('shelf_location', '!=', '')
            ->count();

        if ($orphanedLocation > 0) {
            $this->warn("   {$orphanedLocation} records have shelf_location but missing shelf_label_id");
            $inconsistencies++;
        }

        return $inconsistencies;
    }

    private function displayStats($title, $stats)
    {
        $this->line("📈 {$title}:");
        $this->line("   Total file_indexings: " . number_format($stats['total_file_indexings']));
        $this->line("   With shelf_label_id: " . number_format($stats['with_shelf_id']));
        $this->line("   With shelf_location: " . number_format($stats['with_shelf_location']));
        $this->line("   With batch_id: " . number_format($stats['with_batch_id']));
        $this->line("   Total batches: " . number_format($stats['total_batches']));
        $this->line("   Used shelves: " . number_format($stats['used_shelves']));
    }

    private function displayBatchSummary()
    {
        // Check if batch table exists
        $batchTableExists = DB::connection('sqlsrv')->select("
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_NAME = 'fileindexing_batch'
        ");

        if (empty($batchTableExists)) {
            return;
        }

        $this->info('📦 Batch Summary:');
        
        $batches = DB::connection('sqlsrv')->select("
            SELECT 
                fb.batch_number,
                fb.shelf_count,
                fb.used_shelves,
                (fb.shelf_count - fb.used_shelves) AS available_shelves,
                CASE 
                    WHEN fb.shelf_count > 0 
                    THEN CAST((fb.used_shelves * 100.0 / NULLIF(fb.shelf_count, 0)) AS DECIMAL(10,2)) 
                    ELSE 0 
                END AS usage_percent,
                CASE 
                    WHEN fb.is_full = 1 THEN 'FULL'
                    WHEN fb.is_active = 0 THEN 'INACTIVE' 
                    ELSE 'ACTIVE' 
                END AS status
            FROM dbo.fileindexing_batch fb
            ORDER BY fb.batch_number
        ");

        if (empty($batches)) {
            $this->line('   No batches found');
            return;
        }

        $headers = ['Batch #', 'Total', 'Used', 'Available', 'Usage %', 'Status'];
        $rows = array_map(function($batch) {
            return [
                $batch->batch_number,
                $batch->shelf_count,
                $batch->used_shelves,
                $batch->available_shelves,
                $batch->usage_percent . '%',
                $batch->status
            ];
        }, $batches);

        $this->table($headers, array_slice($rows, 0, 10)); // Show first 10 batches

        if (count($batches) > 10) {
            $this->line("   ... and " . (count($batches) - 10) . " more batches");
        }
    }
}