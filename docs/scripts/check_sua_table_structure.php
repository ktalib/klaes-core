<?php

require_once 'vendor/autoload.php';

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== SUA File Numbers Table Structure ===\n\n";

try {
    // Get table structure
    $columns = DB::connection('sqlsrv')
        ->select("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE 
                 FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_NAME = 'sua_file_numbers' 
                 ORDER BY ORDINAL_POSITION");
    
    echo "Table columns:\n";
    foreach ($columns as $column) {
        echo "  - {$column->COLUMN_NAME} ({$column->DATA_TYPE}) " . 
             ($column->IS_NULLABLE == 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
    
    echo "\nSample data:\n";
    $sampleData = DB::connection('sqlsrv')
        ->table('sua_file_numbers')
        ->limit(3)
        ->get();
    
    foreach ($sampleData as $row) {
        echo "Row data:\n";
        foreach ($row as $key => $value) {
            echo "  {$key}: {$value}\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}