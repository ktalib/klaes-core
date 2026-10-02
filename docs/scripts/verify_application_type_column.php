<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "ST File Numbers Table - application_type Column Details\n";
echo "========================================================\n\n";

$column = DB::connection('sqlsrv')->select("
    SELECT 
        COLUMN_NAME,
        DATA_TYPE,
        CHARACTER_MAXIMUM_LENGTH,
        IS_NULLABLE,
        COLUMN_DEFAULT
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'st_file_numbers' 
    AND COLUMN_NAME = 'application_type'
");

if (count($column) > 0) {
    $col = $column[0];
    echo "✅ application_type column EXISTS in st_file_numbers\n\n";
    echo "Column Details:\n";
    echo "---------------\n";
    echo "Name:        " . $col->COLUMN_NAME . "\n";
    echo "Type:        " . $col->DATA_TYPE . "\n";
    echo "Max Length:  " . $col->CHARACTER_MAXIMUM_LENGTH . "\n";
    echo "Nullable:    " . $col->IS_NULLABLE . "\n";
    echo "Default:     " . ($col->COLUMN_DEFAULT ?? 'NULL') . "\n";
    
    echo "\n✅ CONFIRMED: st_file_numbers table has application_type column!\n";
} else {
    echo "❌ application_type column NOT FOUND in st_file_numbers table\n";
    echo "⚠️  Migration may have failed!\n";
}

echo "\n\n";
echo "All Application Type Columns Across Tables\n";
echo "===========================================\n\n";

$allColumns = DB::connection('sqlsrv')->select("
    SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        DATA_TYPE,
        CHARACTER_MAXIMUM_LENGTH
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE COLUMN_NAME = 'application_type'
    ORDER BY TABLE_NAME
");

foreach ($allColumns as $col) {
    echo "✓ {$col->TABLE_NAME} → {$col->DATA_TYPE}({$col->CHARACTER_MAXIMUM_LENGTH})\n";
}

echo "\n";
echo "Total tables with application_type: " . count($allColumns) . "\n";
