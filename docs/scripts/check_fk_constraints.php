<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = Illuminate\Http\Request::capture());

use Illuminate\Support\Facades\DB;

echo "=== Checking Foreign Key Constraints on memos table ===\n";

try {
    $constraints = DB::connection('sqlsrv')->select("
        SELECT 
            fk.name AS constraint_name, 
            tp.name AS parent_table, 
            cp.name AS parent_column, 
            tr.name AS referenced_table, 
            cr.name AS referenced_column
        FROM sys.foreign_keys fk 
        INNER JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id 
        INNER JOIN sys.tables tp ON fkc.parent_object_id = tp.object_id 
        INNER JOIN sys.columns cp ON fkc.parent_object_id = cp.object_id AND fkc.parent_column_id = cp.column_id 
        INNER JOIN sys.tables tr ON fk.referenced_object_id = tr.object_id 
        INNER JOIN sys.columns cr ON fk.referenced_object_id = cr.object_id AND fkc.referenced_column_id = cr.column_id 
        WHERE tp.name = 'memos'
    ");

    if (empty($constraints)) {
        echo "No foreign key constraints found on memos table.\n";
    } else {
        foreach ($constraints as $constraint) {
            echo "FK: {$constraint->constraint_name}\n";
            echo "   {$constraint->parent_table}.{$constraint->parent_column} -> {$constraint->referenced_table}.{$constraint->referenced_column}\n\n";
        }
    }

    // Also check if application_id column allows NULL
    echo "=== Checking application_id column properties ===\n";
    $columnInfo = DB::connection('sqlsrv')->select("
        SELECT 
            COLUMN_NAME, 
            IS_NULLABLE, 
            DATA_TYPE, 
            COLUMN_DEFAULT
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'memos' AND COLUMN_NAME = 'application_id'
    ");

    if (!empty($columnInfo)) {
        $col = $columnInfo[0];
        echo "Column: {$col->COLUMN_NAME}\n";
        echo "Nullable: {$col->IS_NULLABLE}\n";
        echo "Data Type: {$col->DATA_TYPE}\n";
        echo "Default: " . ($col->COLUMN_DEFAULT ?? 'NULL') . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>