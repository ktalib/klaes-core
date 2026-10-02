<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    // Read the SQL file
    $sql = file_get_contents('create_recommended_site_plans_table.sql');
    
    // Execute the SQL
    DB::connection('sqlsrv')->statement($sql);
    
    echo "✅ Table 'recommended_site_plans' created successfully!\n";
    
    // Verify the table was created
    $exists = DB::connection('sqlsrv')->select("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'recommended_site_plans'");
    
    if ($exists) {
        echo "✅ Table verification successful!\n";
        
        // Show table structure
        $columns = DB::connection('sqlsrv')->select("
            SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = 'recommended_site_plans'
            ORDER BY ORDINAL_POSITION
        ");
        
        echo "\n📋 Table Structure:\n";
        foreach ($columns as $column) {
            echo "  - {$column->COLUMN_NAME} ({$column->DATA_TYPE}) " . 
                 ($column->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
        }
    } else {
        echo "❌ Table verification failed!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error creating table: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}