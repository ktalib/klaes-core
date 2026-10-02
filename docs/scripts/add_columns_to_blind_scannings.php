<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "🔧 Adding additional columns to blind_scannings table...\n\n";
    
    // Read the SQL file
    $sql = file_get_contents('add_blind_scannings_columns.sql');
    
    // Execute the SQL
    DB::connection('sqlsrv')->unprepared($sql);
    
    echo "✅ Columns added successfully!\n\n";
    
    // Verify the new columns exist
    $columns = DB::connection('sqlsrv')->select("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'blind_scannings'
        AND COLUMN_NAME IN ('file_number', 'local_pc_path', 'a4_count', 'a3_count', 'total_pages')
        ORDER BY COLUMN_NAME
    ");
    
    echo "📋 New columns added:\n";
    foreach ($columns as $column) {
        echo "  - {$column->COLUMN_NAME} ({$column->DATA_TYPE}) " . 
             ($column->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
    
    // Update the existing record with some sample data
    echo "\n🔄 Updating existing records with sample data...\n";
    
    $updated = DB::connection('sqlsrv')->table('blind_scannings')->update([
        'file_number' => 'TEST_001',
        'local_pc_path' => 'C:\\Scans\\TEST_001\\test_document.pdf',
        'a4_count' => 5,
        'a3_count' => 2,
        'total_pages' => 7
    ]);
    
    echo "✅ Updated {$updated} records with sample data\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}