<?php
/**
 * Add CofO Number and Application ID columns to CofO table
 * This script adds the necessary columns for storing CofO number and application ID
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Load Laravel configuration
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

try {
    echo "Starting CofO table update...\n";
    
    // Read the SQL script
    $sqlScript = file_get_contents(__DIR__ . '/database_scripts/add_cofo_no_and_application_id_columns.sql');
    
    if (!$sqlScript) {
        throw new Exception("Could not read SQL script file");
    }
    
    // Execute the SQL script
    DB::connection('sqlsrv')->getPdo()->exec($sqlScript);
    
    echo "✅ CofO table update completed successfully!\n";
    echo "Added columns:\n";
    echo "- cofo_no (NVARCHAR(255) NULL)\n";
    echo "- application_id (INT NULL)\n";
    echo "- Index on application_id for better performance\n";
    
} catch (Exception $e) {
    echo "❌ Error updating CofO table: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\nCofO table is now ready for CofO Number and Application ID storage!\n";
?>