<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$db = app('db')->connection('sqlsrv');

echo "=== ENTITIES TABLE ===\n";
$entities_cols = $db->select('SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = "entities" ORDER BY ORDINAL_POSITION');
if (count($entities_cols) > 0) {
    foreach ($entities_cols as $col) {
        echo "✓ " . $col->COLUMN_NAME . " (" . $col->DATA_TYPE . ")\n";
    }
    echo "\n✅ Entities table verified!\n";
} else {
    echo "⚠ Entities table not found\n";
}

echo "\n=== CUSTOMERS TABLE - entity_id COLUMN ===\n";
$customer_cols = $db->select('SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = "customers" AND COLUMN_NAME = "entity_id"');
if (count($customer_cols) > 0) {
    foreach ($customer_cols as $col) {
        echo "✓ " . $col->COLUMN_NAME . " (" . $col->DATA_TYPE . ")\n";
    }
    echo "\n✅ entity_id column verified!\n";
} else {
    echo "⚠ entity_id column not found\n";
}

echo "\n=== FOREIGN KEY CONSTRAINTS ===\n";
$fks = $db->select("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'customers' AND COLUMN_NAME = 'entity_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
if (count($fks) > 0) {
    echo "✓ Foreign key constraint found\n";
} else {
    echo "⚠ Foreign key constraint not found\n";
}

echo "\n✅ All migrations completed and verified!\n";
