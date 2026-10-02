<?php
require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $connection = DB::connection('sqlsrv');
    
    echo "=== MARKING REDUNDANT MIGRATION AS COMPLETE ===\n";
    
    // Get the current maximum batch number
    $maxBatch = $connection->table('migrations')->max('batch') ?: 0;
    $newBatch = $maxBatch + 1;
    
    // Insert the problematic migration as already completed
    $connection->table('migrations')->insert([
        'migration' => '2025_10_25_035308_add_year_landuse_to_grouping_table',
        'batch' => $newBatch
    ]);
    
    echo "✅ Marked redundant migration as complete (batch: {$newBatch})\n";
    
    // Now run remaining migrations
    echo "\n=== RUNNING REMAINING MIGRATIONS ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>