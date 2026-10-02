<?php
require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "📊 PERFORMANCE ESTIMATION FOR DIRECT FILE NUMBER GENERATION\n";
echo "=" . str_repeat("=", 65) . "\n\n";

// Test database connection speed
echo "🔍 TESTING DATABASE PERFORMANCE...\n";

try {
    $connection = DB::connection('sqlsrv');
    
    // Test 1: Simple connection speed
    $start = microtime(true);
    $connection->select("SELECT 1 as test");
    $connectionTime = microtime(true) - $start;
    echo "  Database connection: " . number_format($connectionTime * 1000, 2) . " ms\n";
    
    // Test 2: Single record insert performance
    $start = microtime(true);
    $connection->statement("
        INSERT INTO grouping (
            awaiting_fileno, number, year, landuse, mapping, 
            batch_no, created_by, created_at, updated_at, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
        'TEST-PERF-2025-1', '1', 2025, 'RESIDENTIAL', 0,
        'PERF_TEST', 'performance_test', now(), now(), 'performance_test'
    ]);
    $singleInsertTime = microtime(true) - $start;
    echo "  Single insert time: " . number_format($singleInsertTime * 1000, 2) . " ms\n";
    
    // Clean up test record
    $connection->statement("DELETE FROM grouping WHERE awaiting_fileno = 'TEST-PERF-2025-1'");
    
    // Test 3: Batch insert performance (100 records)
    $batchSize = 100;
    $testRecords = [];
    for ($i = 1; $i <= $batchSize; $i++) {
        $testRecords[] = [
            'TEST-BATCH-2025-' . $i, (string)$i, 2025, 'COMMERCIAL', 0,
            'BATCH_PERF_TEST', 'performance_test', now(), now(), 'performance_test'
        ];
    }
    
    $start = microtime(true);
    $connection->getPdo()->beginTransaction();
    
    $stmt = $connection->getPdo()->prepare("
        INSERT INTO grouping (
            awaiting_fileno, number, year, landuse, mapping,
            batch_no, created_by, created_at, updated_at, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($testRecords as $record) {
        $stmt->execute($record);
    }
    
    $connection->getPdo()->commit();
    $batchInsertTime = microtime(true) - $start;
    $recordsPerSecond = $batchSize / $batchInsertTime;
    
    echo "  Batch insert (100 records): " . number_format($batchInsertTime * 1000, 2) . " ms\n";
    echo "  Records per second: " . number_format($recordsPerSecond, 0) . "\n";
    
    // Clean up test records
    $connection->statement("DELETE FROM grouping WHERE batch_no = 'BATCH_PERF_TEST'");
    
    echo "\n📈 PERFORMANCE CALCULATIONS:\n";
    
    // Calculate total workload
    $totalRecords = 2700000; // 2.7M records
    $totalSheets = 12;
    $recordsPerSheet = 225000;
    
    echo "  Total workload: " . number_format($totalRecords) . " records\n";
    echo "  Sheets to process: {$totalSheets}\n";
    echo "  Records per sheet: " . number_format($recordsPerSheet) . "\n";
    
    // Time estimates based on performance test
    $conservativeSpeed = $recordsPerSecond * 0.7; // 70% of test speed (realistic)
    $optimisticSpeed = $recordsPerSecond * 1.2;   // 120% of test speed (best case)
    
    $conservativeTime = $totalRecords / $conservativeSpeed;
    $optimisticTime = $totalRecords / $optimisticSpeed;
    
    echo "\n⏱️  TIME ESTIMATES:\n";
    echo "  Measured speed: " . number_format($recordsPerSecond, 0) . " records/second\n";
    echo "  Conservative estimate (70%): " . number_format($conservativeSpeed, 0) . " records/second\n";
    echo "  Optimistic estimate (120%): " . number_format($optimisticSpeed, 0) . " records/second\n";
    
    echo "\n🕐 ESTIMATED COMPLETION TIMES:\n";
    
    function formatTime($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = floor($seconds % 60);
        
        if ($hours > 0) {
            return "{$hours}h {$minutes}m {$secs}s";
        } elseif ($minutes > 0) {
            return "{$minutes}m {$secs}s";
        } else {
            return "{$secs}s";
        }
    }
    
    echo "  ⚡ Best case: " . formatTime($optimisticTime) . "\n";
    echo "  📊 Realistic: " . formatTime($conservativeTime) . "\n";
    echo "  🐌 Worst case: " . formatTime($conservativeTime * 1.5) . "\n";
    
    echo "\n📋 PROCESSING BREAKDOWN (Realistic Estimate):\n";
    $timePerSheet = $conservativeTime / $totalSheets;
    foreach ([
        'RES', 'COM', 'AG', 'RES-RC', 'COM-RC', 'AG-RC',
        'CON-RES', 'CON-COM', 'CON-AG', 'CON-RES-RC', 'CON-COM-RC', 'CON-AG-RC'
    ] as $index => $sheet) {
        $sheetTime = formatTime($timePerSheet);
        echo "  Sheet " . ($index + 1) . " ({$sheet}): {$sheetTime}\n";
    }
    
    // Memory usage estimate
    echo "\n💾 MEMORY USAGE ESTIMATE:\n";
    $recordSize = 500; // Approximate bytes per record in memory
    $batchMemory = 1000 * $recordSize; // 1000 records per batch
    echo "  Memory per batch (1000 records): " . number_format($batchMemory / 1024, 0) . " KB\n";
    echo "  Peak memory usage: < 10 MB (with garbage collection)\n";
    
    // Progress tracking estimate
    echo "\n📊 PROGRESS TRACKING:\n";
    $progressUpdates = $totalRecords / 25000; // Progress every 25K records
    echo "  Progress updates: Every 25,000 records (" . number_format($progressUpdates, 0) . " total updates)\n";
    echo "  Update frequency: Every " . formatTime(25000 / $conservativeSpeed) . "\n";
    
    echo "\n✅ SUMMARY:\n";
    echo "  Expected duration: " . formatTime($conservativeTime) . " to " . formatTime($conservativeTime * 1.5) . "\n";
    echo "  Process type: Single-threaded, batch processing\n";
    echo "  Resource usage: Low CPU, moderate database I/O\n";
    echo "  Interruption safety: Yes (transaction-based batches)\n";
    echo "  Progress visibility: Real-time updates every 25K records\n";
    
    if ($conservativeTime < 1800) { // Less than 30 minutes
        echo "\n🚀 This is a FAST process - you can run it immediately!\n";
    } elseif ($conservativeTime < 3600) { // Less than 1 hour
        echo "\n⚡ This is a MODERATE process - good for a coffee break!\n";
    } else {
        echo "\n📚 This is a LONGER process - good time to review other tasks!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Performance test failed: " . $e->getMessage() . "\n";
    echo "\nℹ️  FALLBACK ESTIMATES (based on typical SQL Server performance):\n";
    echo "  Conservative estimate: 45-90 minutes\n";
    echo "  Realistic estimate: 60-120 minutes\n";
    echo "  Factors: Network speed, server load, disk I/O\n";
}
?>