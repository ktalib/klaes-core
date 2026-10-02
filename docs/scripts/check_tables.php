<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$db = app('db')->connection('sqlsrv');

echo "=== CHECKING FOR ENTITIES TABLE ===\n";
try {
    $result = $db->select("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE TABLE' AND TABLE_NAME IN ('entities', 'customers')");
    foreach ($result as $table) {
        echo "✓ Found table: " . $table->TABLE_NAME . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== CHECKING CUSTOMERS.entity_id COLUMN ===\n";
try {
    if ($db->getSchemaBuilder()->hasColumn('customers', 'entity_id')) {
        echo "✓ entity_id column exists on customers table\n";
    } else {
        echo "✗ entity_id column NOT found on customers table\n";
    }
} catch (Exception $e) {
    echo "Error checking column: " . $e->getMessage() . "\n";
}

echo "\n✅ Check complete!\n";
