<?php
/**
 * Add CofO Number and Application ID columns to CofO table
 * Run this via: php artisan tinker and then copy-paste the relevant code
 */

// Connect to SQL Server and add the columns
try {
    echo "Checking CofO table structure...\n";
    
    // Get the database connection
    $connection = DB::connection('sqlsrv');
    
    // Check if the table exists
    $tables = $connection->select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");
    $cofoTableName = null;
    
    foreach ($tables as $table) {
        if (in_array(strtolower($table->TABLE_NAME), ['cofo', 'cofO'])) {
            $cofoTableName = $table->TABLE_NAME;
            break;
        }
    }
    
    if (!$cofoTableName) {
        throw new Exception("CofO table not found");
    }
    
    echo "Found CofO table: {$cofoTableName}\n";
    
    // Check existing columns
    $columns = $connection->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$cofoTableName}'");
    $existingColumns = array_map(function($col) { return strtolower($col->COLUMN_NAME); }, $columns);
    
    // Add cofo_no column if it doesn't exist
    if (!in_array('cofo_no', $existingColumns)) {
        $connection->statement("ALTER TABLE [{$cofoTableName}] ADD cofo_no NVARCHAR(255) NULL");
        echo "✅ Added cofo_no column\n";
    } else {
        echo "⚠️ cofo_no column already exists\n";
    }
    
    // Add application_id column if it doesn't exist
    if (!in_array('application_id', $existingColumns)) {
        $connection->statement("ALTER TABLE [{$cofoTableName}] ADD application_id INT NULL");
        echo "✅ Added application_id column\n";
    } else {
        echo "⚠️ application_id column already exists\n";
    }
    
    echo "✅ CofO table update completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>