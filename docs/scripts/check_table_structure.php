<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== MOTHER_APPLICATIONS TABLE STRUCTURE ===\n\n";

try {
    $columns = DB::connection('sqlsrv')->select("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'mother_applications' 
        ORDER BY ORDINAL_POSITION
    ");
    
    foreach ($columns as $col) {
        $length = $col->CHARACTER_MAXIMUM_LENGTH ? "({$col->CHARACTER_MAXIMUM_LENGTH})" : '';
        echo "{$col->COLUMN_NAME} {$col->DATA_TYPE}{$length} (nullable: {$col->IS_NULLABLE})\n";
    }
    
    echo "\n=== Sample Records ===\n";
    $records = DB::connection('sqlsrv')->table('mother_applications')
        ->limit(2)
        ->get();
    
    if ($records->count() > 0) {
        foreach ($records as $record) {
            echo "ID: {$record->id}, NP: " . ($record->np_fileno ?? 'NULL') . ", File: " . ($record->fileno ?? 'NULL') . ", Applicant: " . ($record->applicant_type ?? 'NULL') . "\n";
        }
    } else {
        echo "No records found.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}