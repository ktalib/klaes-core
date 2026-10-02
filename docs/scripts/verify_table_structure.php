<?php
require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $connection = DB::connection('sqlsrv');
    
    echo "=== GROUPING TABLE STRUCTURE ===\n";
    
    // Get column information
    $columns = $connection->select("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'grouping'
        ORDER BY ORDINAL_POSITION
    ");
    
    echo "Columns in 'grouping' table:\n";
    foreach ($columns as $col) {
        $nullable = $col->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $col->COLUMN_DEFAULT ? " DEFAULT: {$col->COLUMN_DEFAULT}" : '';
        echo "  - {$col->COLUMN_NAME} ({$col->DATA_TYPE}) {$nullable}{$default}\n";
    }
    
    echo "\n✅ Table created successfully with " . count($columns) . " columns!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>