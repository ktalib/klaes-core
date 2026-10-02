<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$db = app('db')->connection('sqlsrv');
$schema = $db->getSchemaBuilder();

echo "=== ADDING FOREIGN KEY CONSTRAINT ===\n";

try {
    // Check if foreign key already exists
    $fks = $db->select("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS 
        WHERE CONSTRAINT_NAME LIKE '%entity_id%'
    ");
    
    if (count($fks) > 0) {
        echo "✓ Foreign key already exists\n";
    } else {
        // Add foreign key
        $schema->table('customers', function ($table) {
            $table->foreign('entity_id')
                ->references('id')
                ->on('entities')
                ->onDelete('restrict')
                ->onUpdate('cascade');
        });
        echo "✓ Foreign key constraint added\n";
    }
    
    echo "\n✅ Foreign key setup complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
