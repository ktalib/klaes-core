<?php
/**
 * Display Land Use Totals
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "📊 GROUPING TABLE - LAND USE DISTRIBUTION\n";
echo str_repeat("=", 50) . "\n";

try {
    // Get land use distribution
    $landUseStats = DB::connection('sqlsrv')
        ->table('grouping')
        ->select('landuse', DB::raw('COUNT(*) as count'))
        ->groupBy('landuse')
        ->orderBy('count', 'desc')
        ->get();
    
    $grandTotal = 0;
    
    echo "Land Use                 Count            Percentage\n";
    echo str_repeat("-", 50) . "\n";
    
    // First pass to calculate grand total
    foreach ($landUseStats as $stat) {
        $grandTotal += $stat->count;
    }
    
    // Second pass to display with percentages
    foreach ($landUseStats as $stat) {
        $percentage = round(($stat->count / $grandTotal) * 100, 1);
        $countFormatted = str_pad(number_format($stat->count), 12, ' ', STR_PAD_LEFT);
        $landUseFormatted = str_pad($stat->landuse, 20, ' ', STR_PAD_RIGHT);
        $percentageFormatted = str_pad($percentage . '%', 10, ' ', STR_PAD_LEFT);
        
        echo "{$landUseFormatted} {$countFormatted} {$percentageFormatted}\n";
    }
    
    echo str_repeat("-", 50) . "\n";
    echo str_pad('TOTAL:', 20, ' ', STR_PAD_RIGHT) . str_pad(number_format($grandTotal), 12, ' ', STR_PAD_LEFT) . str_pad('100.0%', 10, ' ', STR_PAD_LEFT) . "\n";
    echo str_repeat("=", 50) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>