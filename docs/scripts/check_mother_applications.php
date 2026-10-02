<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Checking mother_applications table ===\n\n";

try {
    // Get the most recent applications
    $apps = DB::connection('sqlsrv')->select('SELECT TOP 5 * FROM mother_applications ORDER BY id DESC');
    
    if (empty($apps)) {
        echo "No applications found in mother_applications table.\n";
    } else {
        echo "Found " . count($apps) . " applications:\n\n";
        
        foreach ($apps as $app) {
            echo "ID: {$app->id}\n";
            echo "File No: " . ($app->file_no ?? 'NULL') . "\n";
            echo "NP File No: " . ($app->np_fileno ?? 'NULL') . "\n";
            echo "Applicant Type: " . ($app->applicant_type ?? 'NULL') . "\n";
            echo "First Name: " . ($app->first_name ?? 'NULL') . "\n";
            echo "Surname: " . ($app->surname ?? 'NULL') . "\n";
            echo "Corporate Name: " . ($app->corporate_name ?? 'NULL') . "\n";
            echo "Land Use: " . ($app->land_use ?? 'NULL') . "\n";
            echo "Status: " . ($app->status ?? 'NULL') . "\n";
            echo "Created At: " . ($app->created_at ?? 'NULL') . "\n";
            echo "---\n";
        }
    }
    
    // Check table structure
    echo "\n=== Table Structure ===\n";
    $columns = DB::connection('sqlsrv')->select("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'mother_applications' 
        ORDER BY ORDINAL_POSITION
    ");
    
    foreach ($columns as $col) {
        echo "{$col->COLUMN_NAME} ({$col->DATA_TYPE}) - Nullable: {$col->IS_NULLABLE}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}