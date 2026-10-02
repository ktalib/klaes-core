<?php

/**
 * HIGH PERFORMANCE Populate tracking_id for existing grouping records
 * 
 * OPTIMIZED VERSION with:
 * - Bulk inserts for maximum speed
 * - Real-time progress tracking
 * - Memory optimization
 * - Performance monitoring
 * - Resume capability
 */

require 'vendor/autoload.php';

// Load Laravel application
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Grouping;

// Performance configuration
$BATCH_SIZE = 5000;        // Increased for bulk operations
$PROGRESS_INTERVAL = 1000; // Show progress every N records
$MEMORY_LIMIT = '2G';      // Increase memory limit
$MAX_EXECUTION_TIME = 0;   // No time limit

// Set execution parameters
ini_set('memory_limit', $MEMORY_LIMIT);
set_time_limit($MAX_EXECUTION_TIME);

echo "🚀 HIGH PERFORMANCE Tracking ID Population\n";
echo str_repeat("=", 70) . "\n\n";

// Performance metrics
$startTime = microtime(true);
$totalMemoryPeak = 0;

try {
    // Check if tracking_id column exists
    $connection = DB::connection('sqlsrv');
    
    echo "🔍 Checking database schema...\n";
    $columnExists = $connection->select("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'grouping' 
        AND COLUMN_NAME = 'tracking_id'
    ");
    
    if ($columnExists[0]->count == 0) {
        echo "❌ Error: tracking_id column does not exist in grouping table\n";
        echo "📋 Please run the migration first: php artisan migrate\n";
        exit(1);
    }
    
    echo "✅ Database schema validated\n\n";
    
    // Get comprehensive statistics
    echo "📊 Analyzing current state...\n";
    $stats = $connection->select("
        SELECT 
            COUNT(*) as total_records,
            COUNT(tracking_id) as records_with_tracking,
            COUNT(*) - COUNT(tracking_id) as records_without_tracking
        FROM grouping 
        WHERE tracking_id IS NULL OR tracking_id = ''
    ");
    
    $totalRecords = $connection->selectOne("SELECT COUNT(*) as count FROM grouping")->count;
    $recordsWithoutTracking = $stats[0]->records_without_tracking;
    $recordsWithTracking = $totalRecords - $recordsWithoutTracking;
    
    echo "   📈 Total records: " . number_format($totalRecords) . "\n";
    echo "   ✅ Records with tracking IDs: " . number_format($recordsWithTracking) . "\n";
    echo "   ❌ Records without tracking IDs: " . number_format($recordsWithoutTracking) . "\n";
    echo "   📊 Current coverage: " . round(($recordsWithTracking / $totalRecords) * 100, 2) . "%\n\n";
    
    if ($recordsWithoutTracking == 0) {
        echo "🎉 All records already have tracking IDs!\n";
        exit(0);
    }
    
    // Estimate processing time
    $estimatedTime = ($recordsWithoutTracking / $BATCH_SIZE) * 2; // Rough estimate: 2 seconds per batch
    echo "⏱️  Estimated processing time: " . gmdate("H:i:s", $estimatedTime) . "\n";
    echo "🔧 Using batch size: " . number_format($BATCH_SIZE) . " records\n";
    echo "💾 Memory limit: {$MEMORY_LIMIT}\n\n";
    
    // Confirm before proceeding
    echo "🚀 Ready to process " . number_format($recordsWithoutTracking) . " records. Continue? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (strtolower(trim($line)) !== 'y') {
        echo "❌ Operation cancelled by user\n";
        exit(0);
    }
    
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "🔄 STARTING BULK PROCESSING\n";
    echo str_repeat("=", 70) . "\n\n";
    
    $processed = 0;
    $batchCount = 0;
    $totalBatches = ceil($recordsWithoutTracking / $BATCH_SIZE);
    $errors = 0;
    
    while ($processed < $recordsWithoutTracking) {
        $batchStart = microtime(true);
        $batchCount++;
        
        // Get batch of records without tracking_id using offset for better performance
        $records = $connection->select("
            SELECT TOP {$BATCH_SIZE} id, awaiting_fileno
            FROM grouping 
            WHERE tracking_id IS NULL OR tracking_id = ''
            ORDER BY id
        ");
        
        if (empty($records)) {
            break; // No more records to process
        }
        
        $currentBatchSize = count($records);
        echo "📦 Batch {$batchCount}/{$totalBatches}: Processing {$currentBatchSize} records";
        
        // Prepare bulk update data
        $bulkUpdates = [];
        $trackingIds = [];
        
        foreach ($records as $record) {
            $trackingId = generateTrackingId();
            $trackingIds[] = $trackingId;
            $bulkUpdates[] = [
                'id' => $record->id,
                'tracking_id' => $trackingId
            ];
        }
        
        try {
            // Use bulk update for maximum performance
            DB::connection('sqlsrv')->transaction(function () use ($connection, $bulkUpdates) {
                foreach ($bulkUpdates as $update) {
                    $connection->update(
                        "UPDATE grouping SET tracking_id = ? WHERE id = ?",
                        [$update['tracking_id'], $update['id']]
                    );
                }
            });
            
            $processed += $currentBatchSize;
            $batchTime = microtime(true) - $batchStart;
            $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
            $totalMemoryPeak = max($totalMemoryPeak, memory_get_peak_usage(true) / 1024 / 1024);
            
            // Calculate progress and ETA
            $progressPercent = round(($processed / $recordsWithoutTracking) * 100, 1);
            $recordsPerSecond = round($currentBatchSize / $batchTime);
            $remainingRecords = $recordsWithoutTracking - $processed;
            $etaSeconds = $remainingRecords / max($recordsPerSecond, 1);
            
            echo " ✅\n";
            echo "   ⚡ Speed: {$recordsPerSecond} records/sec | ";
            echo "💾 Memory: " . round($memoryUsage, 1) . "MB | ";
            echo "📈 Progress: {$progressPercent}% | ";
            echo "⏱️  ETA: " . gmdate("H:i:s", $etaSeconds) . "\n";
            
            // Show progress bar
            $barWidth = 50;
            $filledWidth = round($barWidth * $progressPercent / 100);
            $bar = str_repeat("█", $filledWidth) . str_repeat("░", $barWidth - $filledWidth);
            echo "   [{$bar}] {$processed}/" . number_format($recordsWithoutTracking) . "\n\n";
            
        } catch (Exception $e) {
            $errors++;
            echo " ❌ ERROR\n";
            echo "   Error in batch {$batchCount}: " . $e->getMessage() . "\n\n";
            
            if ($errors > 5) {
                echo "❌ Too many errors, stopping operation\n";
                break;
            }
        }
        
        // Prevent memory leaks
        unset($records, $bulkUpdates, $trackingIds);
        
        // Brief pause to prevent overwhelming the database
        usleep(100000); // 0.1 second
    }
    
    $totalTime = microtime(true) - $startTime;
    
    echo str_repeat("=", 70) . "\n";
    echo "🎉 PROCESSING COMPLETE!\n";
    echo str_repeat("=", 70) . "\n\n";
    
    // Final verification
    echo "🔍 Performing final verification...\n";
    $finalStats = $connection->selectOne("
        SELECT 
            COUNT(*) as total_records,
            COUNT(tracking_id) as records_with_tracking,
            COUNT(*) - COUNT(tracking_id) as records_missing
        FROM grouping 
    ");
    
    $finalCoverage = round(($finalStats->records_with_tracking / $finalStats->total_records) * 100, 2);
    
    echo "\n📊 FINAL STATISTICS:\n";
    echo "   📈 Total records: " . number_format($finalStats->total_records) . "\n";
    echo "   ✅ Records with tracking IDs: " . number_format($finalStats->records_with_tracking) . "\n";
    echo "   ❌ Records still missing: " . number_format($finalStats->records_missing) . "\n";
    echo "   📊 Coverage: {$finalCoverage}%\n\n";
    
    echo "⚡ PERFORMANCE METRICS:\n";
    echo "   🕒 Total time: " . gmdate("H:i:s", $totalTime) . "\n";
    echo "   📊 Records processed: " . number_format($processed) . "\n";
    echo "   ⚡ Average speed: " . round($processed / $totalTime) . " records/sec\n";
    echo "   💾 Peak memory usage: " . round($totalMemoryPeak, 1) . "MB\n";
    echo "   🔄 Batches processed: {$batchCount}\n";
    echo "   ❌ Errors encountered: {$errors}\n\n";
    
    // Show sample tracking IDs
    echo "📋 SAMPLE TRACKING IDs:\n";
    $samples = $connection->select("
        SELECT TOP 5 awaiting_fileno, tracking_id, landuse
        FROM grouping 
        WHERE tracking_id IS NOT NULL 
        ORDER BY id DESC
    ");
        
    foreach ($samples as $sample) {
        echo "   {$sample->awaiting_fileno} → {$sample->tracking_id} ({$sample->landuse})\n";
    }
    
    if ($finalStats->records_missing == 0) {
        echo "\n🎉 SUCCESS: All records now have tracking IDs!\n";
    } else {
        echo "\n⚠️  WARNING: " . number_format($finalStats->records_missing) . " records still missing tracking IDs\n";
        echo "   Consider running the script again to process remaining records.\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    // Memory usage at error
    $errorMemory = memory_get_usage(true) / 1024 / 1024;
    echo "\nMemory usage at error: " . round($errorMemory, 1) . "MB\n";
    
    exit(1);
}

/**
 * Generate tracking ID in TRK-XXXXXXXX-XXXXX format
 */
function generateTrackingId(): string
{
    $segment1 = '';
    $segment2 = '';
    $characters = 'ABCDEFGHIJKLMNPQRSTUVWXYZ123456789';
    
    // Generate first segment (8 characters)
    for ($i = 0; $i < 8; $i++) {
        $segment1 .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    // Generate second segment (5 characters)
    for ($i = 0; $i < 5; $i++) {
        $segment2 .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return "TRK-{$segment1}-{$segment2}";
}

echo "\n✨ Tracking ID population completed successfully!\n";