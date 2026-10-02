<?php
/**
 * Test Column Rename - Verify awaiting_fileno column exists
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Grouping;

echo "🔍 Testing Column Rename: pseudo_fileno → awaiting_fileno\n";
echo "======================================================\n\n";

try {
    // Test 1: Check if the column exists in the schema
    echo "1. Testing database schema...\n";
    $columns = DB::connection('sqlsrv')->select("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'grouping' 
        AND COLUMN_NAME IN ('pseudo_fileno', 'awaiting_fileno')
        ORDER BY COLUMN_NAME
    ");
    
    foreach ($columns as $column) {
        echo "   Column: {$column->COLUMN_NAME} ({$column->DATA_TYPE}";
        if ($column->CHARACTER_MAXIMUM_LENGTH) {
            echo ", max length: {$column->CHARACTER_MAXIMUM_LENGTH}";
        }
        echo ")\n";
    }
    
    // Test 2: Try to query using the new column name
    echo "\n2. Testing Eloquent model query...\n";
    $sampleRecords = Grouping::select('awaiting_fileno', 'landuse')
        ->limit(5)
        ->get();
    
    echo "   Sample records:\n";
    foreach ($sampleRecords as $record) {
        echo "   - {$record->awaiting_fileno} → {$record->landuse}\n";
    }
    
    // Test 3: Count total records
    echo "\n3. Testing record count...\n";
    $totalCount = Grouping::count();
    echo "   Total records in table: " . number_format($totalCount) . "\n";
    
    // Test 4: Test filtering by awaiting_fileno
    echo "\n4. Testing awaiting_fileno filtering...\n";
    $conComCount = Grouping::where('awaiting_fileno', 'LIKE', 'CON-COM%')->count();
    $conResCount = Grouping::where('awaiting_fileno', 'LIKE', 'CON-RES%')->count();
    
    echo "   CON-COM* records: " . number_format($conComCount) . "\n";
    echo "   CON-RES* records: " . number_format($conResCount) . "\n";
    
    echo "\n✅ Column rename verification completed successfully!\n";
    echo "✅ awaiting_fileno column is working correctly\n";
    
} catch (Exception $e) {
    echo "\n❌ Error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>