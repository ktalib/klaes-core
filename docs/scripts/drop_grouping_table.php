<?php
require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $connection = DB::connection('sqlsrv');
    
    echo "=== DROPPING GROUPING TABLE ===\n";
    echo "Database: " . config('database.connections.sqlsrv.database') . "\n";
    echo "Host: " . config('database.connections.sqlsrv.host') . "\n\n";
    
    // Check if table exists first
    $tableExists = $connection->select("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'grouping'");
    
    if ($tableExists[0]->count > 0) {
        echo "Table 'grouping' exists. Dropping...\n";
        $connection->statement("DROP TABLE grouping");
        echo "✅ Table 'grouping' dropped successfully!\n";
    } else {
        echo "ℹ️  Table 'grouping' does not exist.\n";
    }
    
    // Verify it's gone
    $tableExists = $connection->select("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'grouping'");
    
    if ($tableExists[0]->count == 0) {
        echo "✅ Verified: Table 'grouping' has been removed from SQL Server.\n";
    } else {
        echo "❌ Error: Table 'grouping' still exists!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>