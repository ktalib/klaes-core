<?php

// Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

// Get database connection
$db = app('db')->connection('sqlsrv');

// Migrations to record
$migrations = [
    '2025_11_11_000001_create_entities_table',
    '2025_11_11_000002_add_entity_id_to_customers_table'
];

foreach ($migrations as $migration) {
    $exists = $db->table('migrations')->where('migration', $migration)->exists();
    
    if (!$exists) {
        $db->table('migrations')->insert([
            'migration' => $migration,
            'batch' => 999
        ]);
        echo "✓ Recorded: $migration\n";
    } else {
        echo "✓ Already recorded: $migration\n";
    }
}

echo "\n✅ All migrations recorded!\n";
