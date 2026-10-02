<?php
require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $connection = DB::connection('sqlsrv');
    
    echo "=== CLEANING UP MIGRATION RECORDS ===\n";
    
    // Remove all grouping-related migration records
    $deleted = $connection->table('migrations')
        ->where('migration', 'like', '%grouping%')
        ->delete();
    
    echo "Deleted {$deleted} grouping-related migration records.\n";
    
    // Show remaining migration status
    echo "\nCurrent migration status:\n";
    $migrations = $connection->table('migrations')
        ->orderBy('batch', 'desc')
        ->orderBy('migration', 'desc')
        ->limit(5)
        ->get();
    
    foreach ($migrations as $migration) {
        echo "  {$migration->migration} (batch: {$migration->batch})\n";
    }
    
    echo "\n✅ Ready for fresh start! You can now run the migration again.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>