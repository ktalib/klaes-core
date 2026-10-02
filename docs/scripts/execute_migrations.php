<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$db = app('db')->connection('sqlsrv');
$schema = $db->getSchemaBuilder();

echo "=== CREATING ENTITIES TABLE ===\n";

try {
    // Create entities table if it doesn't exist
    if (!$schema->hasTable('entities')) {
        $schema->create('entities', function ($table) {
            $table->id();
            $table->string('entity_type', 50);
            $table->string('entity_name', 255);
            $table->string('passport_photo', 255)->nullable();
            $table->string('company_logo', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->index('entity_type');
            $table->index('entity_name');
            $table->index(['entity_type', 'entity_name']);
            $table->index('created_at');
        });
        echo "✓ Entities table created\n";
    } else {
        echo "✓ Entities table already exists\n";
    }
    
    echo "\n=== ADDING entity_id TO CUSTOMERS TABLE ===\n";
    
    // Add entity_id column to customers if it doesn't exist
    if (!$schema->hasColumn('customers', 'entity_id')) {
        $schema->table('customers', function ($table) {
            $table->unsignedBigInteger('entity_id')->nullable()->after('id');
            $table->index('entity_id');
        });
        echo "✓ entity_id column added to customers\n";
    } else {
        echo "✓ entity_id column already exists on customers\n";
    }
    
    echo "\n✅ Migration execution complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}
