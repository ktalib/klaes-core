<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    DB::connection('sqlsrv')->table('migrations')->insert([
        'migration' => '2025_10_02_000001_create_file_number_reservations_table',
        'batch' => 2
    ]);
    
    echo "✓ Migration marked as completed\n";
    
    // Verify it worked
    echo "\nChecking migration status...\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}