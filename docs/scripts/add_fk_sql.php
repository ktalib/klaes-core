<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$db = app('db')->connection('sqlsrv');

echo "=== ADDING FOREIGN KEY CONSTRAINT ===\n";

try {
    // Check if foreign key already exists
    $fks = $db->select("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS 
        WHERE CONSTRAINT_NAME LIKE 'FK_Customers_Entities%' OR CONSTRAINT_NAME LIKE 'customers_entity_id%'
    ");
    
    if (count($fks) > 0) {
        echo "✓ Foreign key already exists: " . $fks[0]->CONSTRAINT_NAME . "\n";
    } else {
        // Add foreign key using raw SQL for SQL Server
        $db->statement("
            ALTER TABLE customers 
            ADD CONSTRAINT FK_Customers_Entities 
            FOREIGN KEY (entity_id) 
            REFERENCES entities(id) 
            ON UPDATE CASCADE
            ON DELETE NO ACTION
        ");
        echo "✓ Foreign key constraint added\n";
    }
    
    echo "\n✅ Foreign key setup complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
