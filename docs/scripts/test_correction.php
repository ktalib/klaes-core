<?php
/**
 * Test Land Use Correction Script
 * Just test the queries first
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 Testing land use correction queries...\n";
echo "========================================\n\n";

try {
    // Test connection
    echo "Testing database connection...\n";
    $connectionTest = DB::connection('sqlsrv')->select('SELECT 1 as test');
    echo "✅ Connection successful\n\n";
    
    // Count records by pattern
    echo "📊 Counting records by pattern:\n";
    
    $conComCount = DB::connection('sqlsrv')
        ->table('grouping')
        ->where('pseudo_fileno', 'LIKE', 'CON-COM%')
        ->count();
    echo "  CON-COM*: " . number_format($conComCount) . "\n";
    
    $conResCount = DB::connection('sqlsrv')
        ->table('grouping')
        ->where('pseudo_fileno', 'LIKE', 'CON-RES%')
        ->count();
    echo "  CON-RES*: " . number_format($conResCount) . "\n";
    
    $conAgCount = DB::connection('sqlsrv')
        ->table('grouping')
        ->where('pseudo_fileno', 'LIKE', 'CON-AG%')
        ->count();
    echo "  CON-AG*: " . number_format($conAgCount) . "\n";
    
    // Show sample records
    echo "\n📝 Sample CON-COM records:\n";
    $samples = DB::connection('sqlsrv')
        ->table('grouping')
        ->where('pseudo_fileno', 'LIKE', 'CON-COM%')
        ->select('pseudo_fileno', 'landuse')
        ->limit(5)
        ->get();
    
    foreach ($samples as $sample) {
        echo "  {$sample->pseudo_fileno} → {$sample->landuse}\n";
    }
    
    echo "\n📝 Sample CON-RES records:\n";
    $samples = DB::connection('sqlsrv')
        ->table('grouping')
        ->where('pseudo_fileno', 'LIKE', 'CON-RES%')
        ->select('pseudo_fileno', 'landuse')
        ->limit(5)
        ->get();
    
    foreach ($samples as $sample) {
        echo "  {$sample->pseudo_fileno} → {$sample->landuse}\n";
    }
    
    echo "\n✅ All tests completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>