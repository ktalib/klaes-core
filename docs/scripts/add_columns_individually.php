<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "🔧 Adding columns to blind_scannings table one by one...\n\n";
    
    $columns = [
        'file_number' => "ALTER TABLE blind_scannings ADD file_number nvarchar(255) NULL",
        'local_pc_path' => "ALTER TABLE blind_scannings ADD local_pc_path nvarchar(max) NULL", 
        'a4_count' => "ALTER TABLE blind_scannings ADD a4_count int NOT NULL DEFAULT 0",
        'a3_count' => "ALTER TABLE blind_scannings ADD a3_count int NOT NULL DEFAULT 0",
        'total_pages' => "ALTER TABLE blind_scannings ADD total_pages int NOT NULL DEFAULT 0"
    ];
    
    foreach ($columns as $columnName => $sql) {
        try {
            // Check if column exists first
            $exists = DB::connection('sqlsrv')->select("
                SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_NAME = 'blind_scannings' AND COLUMN_NAME = '{$columnName}'
            ");
            
            if (!$exists) {
                DB::connection('sqlsrv')->statement($sql);
                echo "✅ Added column: {$columnName}\n";
            } else {
                echo "⚠️  Column already exists: {$columnName}\n";
            }
        } catch (Exception $e) {
            echo "❌ Failed to add {$columnName}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n📋 Current table structure:\n";
    $allColumns = DB::connection('sqlsrv')->select("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'blind_scannings'
        ORDER BY ORDINAL_POSITION
    ");
    
    foreach ($allColumns as $column) {
        echo "  - {$column->COLUMN_NAME} ({$column->DATA_TYPE})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}