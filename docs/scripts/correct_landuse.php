<?php
/**
 * Land Use Correction Script with Progress Tracking
 * 
 * Corrects the misinterpreted CON (conversion) prefixes
 * CON-COM -> COMMERCIAL
 * CON-RES -> RESIDENTIAL  
 * CON-AG -> AGRICULTURE
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔧 Land Use Correction Script with Progress\n";
echo "==========================================\n\n";

echo "📊 Current incorrect distribution:\n";
$currentStats = DB::connection('sqlsrv')
    ->table('grouping')
    ->select('landuse', DB::raw('COUNT(*) as count'))
    ->groupBy('landuse')
    ->orderBy('count', 'desc')
    ->get();

$totalRecords = 0;
foreach ($currentStats as $stat) {
    echo "  {$stat->landuse}: " . number_format($stat->count) . "\n";
    $totalRecords += $stat->count;
}

echo "\nTotal records: " . number_format($totalRecords) . "\n";
echo str_repeat("=", 50) . "\n";

// Count records that need correction
$conComCount = DB::connection('sqlsrv')->table('grouping')->where('awaiting_fileno', 'LIKE', 'CON-COM%')->count();
$conResCount = DB::connection('sqlsrv')->table('grouping')->where('awaiting_fileno', 'LIKE', 'CON-RES%')->count();
$conAgCount = DB::connection('sqlsrv')->table('grouping')->where('awaiting_fileno', 'LIKE', 'CON-AG%')->count();

$recordsToUpdate = $conComCount + $conResCount + $conAgCount;
echo "📝 Records to correct: " . number_format($recordsToUpdate) . "\n\n";

if ($recordsToUpdate == 0) {
    echo "✅ No records need correction!\n";
    exit(0);
}

$updatedCount = 0;
$startTime = time();

function showProgress($current, $total, $action) {
    $percentage = round(($current / $total) * 100, 1);
    $bar = str_repeat("█", intval($percentage / 2));
    $bar = str_pad($bar, 50, "░");
    echo "\r{$action}: [{$bar}] {$percentage}% (" . number_format($current) . "/" . number_format($total) . ")";
    if ($current == $total) echo "\n";
    flush();
}

echo "🚀 Starting corrections...\n\n";

try {
    // 1. Update CON-COM records to COMMERCIAL
    if ($conComCount > 0) {
        echo "🔄 Updating CON-COM records to COMMERCIAL...\n";
        $affected = DB::connection('sqlsrv')
            ->table('grouping')
            ->where('awaiting_fileno', 'LIKE', 'CON-COM%')
            ->update(['landuse' => 'COMMERCIAL', 'updated_at' => now()]);
        
        $updatedCount += $affected;
        showProgress($updatedCount, $recordsToUpdate, "Progress");
        echo "✅ CON-COM → COMMERCIAL: " . number_format($affected) . " records\n\n";
    }
    
    // 2. Update CON-RES records to RESIDENTIAL
    if ($conResCount > 0) {
        echo "🔄 Updating CON-RES records to RESIDENTIAL...\n";
        $affected = DB::connection('sqlsrv')
            ->table('grouping')
            ->where('awaiting_fileno', 'LIKE', 'CON-RES%')
            ->update(['landuse' => 'RESIDENTIAL', 'updated_at' => now()]);
        
        $updatedCount += $affected;
        showProgress($updatedCount, $recordsToUpdate, "Progress");
        echo "✅ CON-RES → RESIDENTIAL: " . number_format($affected) . " records\n\n";
    }
    
    // 3. Update CON-AG records to AGRICULTURE (if any)
    if ($conAgCount > 0) {
        echo "🔄 Updating CON-AG records to AGRICULTURE...\n";
        $affected = DB::connection('sqlsrv')
            ->table('grouping')
            ->where('awaiting_fileno', 'LIKE', 'CON-AG%')
            ->update(['landuse' => 'AGRICULTURE', 'updated_at' => now()]);
        
        $updatedCount += $affected;
        showProgress($updatedCount, $recordsToUpdate, "Progress");
        echo "✅ CON-AG → AGRICULTURE: " . number_format($affected) . " records\n\n";
    }
    
    $endTime = time();
    $duration = $endTime - $startTime;
    
    echo str_repeat("=", 60) . "\n";
    echo "📊 CORRECTED DISTRIBUTION\n";
    echo str_repeat("=", 60) . "\n";
    
    $correctedStats = DB::connection('sqlsrv')
        ->table('grouping')
        ->select('landuse', DB::raw('COUNT(*) as count'))
        ->groupBy('landuse')
        ->orderBy('count', 'desc')
        ->get();
    
    foreach ($correctedStats as $stat) {
        echo "  {$stat->landuse}: " . number_format($stat->count) . "\n";
    }
    
    echo "\n🎉 Correction completed successfully!\n";
    echo "⏱️  Duration: {$duration} seconds\n";
    echo "📝 Records updated: " . number_format($updatedCount) . "\n";
    echo "💾 All changes committed to database\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Error during correction: " . $e->getMessage() . "\n";
    echo "🔄 Please check the database and try again.\n";
    exit(1);
}
?>