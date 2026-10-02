<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== BUYER_LIST TABLE STRUCTURE ===\n\n";

try {
    $columns = DB::connection('sqlsrv')->select("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'buyer_list' 
        ORDER BY ORDINAL_POSITION
    ");
    
    foreach ($columns as $col) {
        echo "{$col->COLUMN_NAME} ({$col->DATA_TYPE}) - Nullable: {$col->IS_NULLABLE}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== FILE_INDEXINGS TABLE STRUCTURE ===\n\n";

try {
    $columns = DB::connection('sqlsrv')->select("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'file_indexings' 
        ORDER BY ORDINAL_POSITION
    ");
    
    foreach ($columns as $col) {
        echo "{$col->COLUMN_NAME} ({$col->DATA_TYPE}) - Nullable: {$col->IS_NULLABLE}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== COMPLETE_DATA TABLE STRUCTURE ===\n\n";

try {
    $columns = DB::connection('sqlsrv')->select("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'complete_data' 
        ORDER BY ORDINAL_POSITION
    ");
    
    foreach ($columns as $col) {
        echo "{$col->COLUMN_NAME} ({$col->DATA_TYPE}) - Nullable: {$col->IS_NULLABLE}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
