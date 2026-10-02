<?php
/**
 * Verify Land Use Correction Results
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 Verifying Land Use Correction Results\n";
echo "=======================================\n\n";

// Show final distribution
$finalStats = DB::connection('sqlsrv')
    ->table('grouping')
    ->select('landuse', DB::raw('COUNT(*) as count'))
    ->groupBy('landuse')
    ->orderBy('count', 'desc')
    ->get();

$total = 0;
echo "📊 Final Land Use Distribution:\n";
foreach ($finalStats as $stat) {
    echo "  {$stat->landuse}: " . number_format($stat->count) . "\n";
    $total += $stat->count;
}
echo "\nTotal: " . number_format($total) . " records\n\n";

// Verify no CON patterns remain with wrong land use
$constructionRemaining = DB::connection('sqlsrv')
    ->table('grouping')
    ->where('landuse', 'CONSTRUCTION')
    ->count();

echo "🔍 Verification Checks:\n";
echo "  Remaining CONSTRUCTION records: " . number_format($constructionRemaining) . "\n";

if ($constructionRemaining == 0) {
    echo "✅ SUCCESS: All CON prefix corrections completed!\n";
} else {
    echo "⚠️  WARNING: Some CONSTRUCTION records still remain\n";
    
    // Show samples of remaining CONSTRUCTION records
    $samples = DB::connection('sqlsrv')
        ->table('grouping')
        ->where('landuse', 'CONSTRUCTION')
        ->select('awaiting_fileno')
        ->limit(10)
        ->get();
    
    echo "    Sample remaining CONSTRUCTION records:\n";
    foreach ($samples as $sample) {
        echo "    - {$sample->awaiting_fileno}\n";
    }
}

// Show sample corrected records
echo "\n📝 Sample Corrected Records:\n";

$commercialSamples = DB::connection('sqlsrv')
    ->table('grouping')
    ->where('awaiting_fileno', 'LIKE', 'CON-COM%')
    ->where('landuse', 'COMMERCIAL')
    ->select('awaiting_fileno', 'landuse')
    ->limit(3)
    ->get();

echo "  CON-COM* → COMMERCIAL:\n";
foreach ($commercialSamples as $sample) {
    echo "    {$sample->awaiting_fileno} → {$sample->landuse}\n";
}

$residentialSamples = DB::connection('sqlsrv')
    ->table('grouping')
    ->where('awaiting_fileno', 'LIKE', 'CON-RES%')
    ->where('landuse', 'RESIDENTIAL')
    ->select('awaiting_fileno', 'landuse')
    ->limit(3)
    ->get();

echo "  CON-RES* → RESIDENTIAL:\n";
foreach ($residentialSamples as $sample) {
    echo "    {$sample->awaiting_fileno} → {$sample->landuse}\n";
}

echo "\n🎯 Land Use Correction Verification Complete!\n";
?>