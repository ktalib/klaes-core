<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Mark migrations as completed
$migrations = [
    '2025_11_11_000001_create_entities_table',
    '2025_11_11_000002_add_entity_id_to_customers_table'
];

foreach ($migrations as $migration) {
    // Check if already recorded
    $exists = DB::connection('sqlsrv')->table('migrations')
        ->where('migration', $migration)
        ->exists();
    
    if (!$exists) {
        DB::connection('sqlsrv')->table('migrations')->insert([
            'migration' => $migration,
            'batch' => 999
        ]);
        echo "✓ Recorded: $migration\n";
    } else {
        echo "✓ Already recorded: $migration\n";
    }
}
