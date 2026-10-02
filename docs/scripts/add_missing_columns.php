<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ADDING MISSING COLUMNS ===\n\n";

try {
    // Check and add tracking_id
    $trackingIdExists = DB::connection('sqlsrv')->select("
        SELECT COUNT(*) as count
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'mother_applications' AND COLUMN_NAME = 'tracking_id'
    ");
    
    if ($trackingIdExists[0]->count == 0) {
        DB::connection('sqlsrv')->statement("
            ALTER TABLE mother_applications
            ADD tracking_id NVARCHAR(255) NULL
        ");
        echo "✓ Added tracking_id column\n";
    } else {
        echo "ℹ tracking_id column already exists\n";
    }
    
    // Check and add primary_file_id
    $primaryFileIdExists = DB::connection('sqlsrv')->select("
        SELECT COUNT(*) as count
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'mother_applications' AND COLUMN_NAME = 'primary_file_id'
    ");
    
    if ($primaryFileIdExists[0]->count == 0) {
        DB::connection('sqlsrv')->statement("
            ALTER TABLE mother_applications
            ADD primary_file_id NVARCHAR(255) NULL
        ");
        echo "✓ Added primary_file_id column\n";
    } else {
        echo "ℹ primary_file_id column already exists\n";
    }
    
    echo "\n✓ Column additions complete\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
