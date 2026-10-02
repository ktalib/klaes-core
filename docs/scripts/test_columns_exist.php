<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check st_file_numbers table columns
$columns = DB::connection('sqlsrv')->select("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'st_file_numbers' 
    AND COLUMN_NAME IN ('application_type', 'buyer_list_id', 'middle_name')
    ORDER BY COLUMN_NAME
");

echo "Columns in st_file_numbers table:\n";
echo "==================================\n";
foreach ($columns as $column) {
    echo "✓ " . $column->COLUMN_NAME . "\n";
}

if (count($columns) === 3) {
    echo "\n✅ All required columns exist!\n";
} else {
    echo "\n⚠️  Missing columns: " . (3 - count($columns)) . "\n";
}

// Check mother_applications table
$appColumns = DB::connection('sqlsrv')->select("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'mother_applications' 
    AND COLUMN_NAME = 'application_type'
");

echo "\n\nColumns in mother_applications table:\n";
echo "======================================\n";
if (count($appColumns) > 0) {
    echo "✓ application_type\n";
    echo "\n✅ Application type column exists in mother_applications!\n";
} else {
    echo "⚠️  application_type column not found\n";
}

// Check subapplications table
$subColumns = DB::connection('sqlsrv')->select("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'subapplications' 
    AND COLUMN_NAME = 'application_type'
");

echo "\n\nColumns in subapplications table:\n";
echo "==================================\n";
if (count($subColumns) > 0) {
    echo "✓ application_type\n";
    echo "\n✅ Application type column exists in subapplications!\n";
} else {
    echo "⚠️  application_type column not found\n";
}
