<?php

// Quick verification script to check if migration was applied
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';

// Bootstrap the application
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

$connection = $app['db']->connection('sqlsrv');

echo "=== Phase 1 Migration Verification ===\n\n";

try {
    // Check user_activity_logs table columns
    echo "Checking user_activity_logs table...\n";
    $columns = $connection->getSchemaBuilder()->getColumnListing('user_activity_logs');
    
    $required_columns = [
        'duration_minutes',
        'status',
        'last_seen_at',
        'related_file_number',
        'test_control',
        'indexed_at'
    ];
    
    $missing = array_diff($required_columns, $columns);
    
    if (empty($missing)) {
        echo "✅ All required columns found in user_activity_logs\n";
        echo "   Columns: " . implode(', ', $required_columns) . "\n";
    } else {
        echo "❌ Missing columns: " . implode(', ', $missing) . "\n";
    }
    
    echo "\n";
    
    // Check user_activity_log_settings table columns
    echo "Checking user_activity_log_settings table...\n";
    $setting_columns = $connection->getSchemaBuilder()->getColumnListing('user_activity_log_settings');
    
    $required_settings = ['timezone', 'notes'];
    $missing_settings = array_diff($required_settings, $setting_columns);
    
    if (empty($missing_settings)) {
        echo "✅ All required columns found in user_activity_log_settings\n";
        echo "   Columns: " . implode(', ', $required_settings) . "\n";
    } else {
        echo "❌ Missing columns: " . implode(', ', $missing_settings) . "\n";
    }
    
    echo "\n";
    
    // Check indexes
    echo "Checking indexes...\n";
    $indexes = $connection->select("
        SELECT name 
        FROM sys.indexes 
        WHERE object_id = OBJECT_ID('user_activity_logs') 
        AND name LIKE '%idx_activity_user_status_login%'
    ");
    
    if (!empty($indexes)) {
        echo "✅ Composite index found: idx_activity_user_status_login\n";
    } else {
        echo "⚠️  Composite index not found\n";
    }
    
    // Check migration record
    echo "\n";
    echo "Checking migrations table...\n";
    $migration_record = $connection->table('migrations')
        ->where('migration', 'like', '%enhance_activity_logs%')
        ->first();
    
    if ($migration_record) {
        echo "✅ Migration recorded in migrations table\n";
        echo "   Migration: " . $migration_record->migration . "\n";
        echo "   Batch: " . $migration_record->batch . "\n";
    } else {
        echo "⚠️  Migration not recorded in migrations table\n";
    }
    
    echo "\n=== Phase 1 Status ===\n";
    
    if (empty($missing) && empty($missing_settings) && !empty($indexes)) {
        echo "✅ PHASE 1 MIGRATION COMPLETE\n";
        echo "All database enhancements successfully applied!\n";
    } else {
        echo "⚠️  PHASE 1 INCOMPLETE - Some columns or indexes missing\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    exit(1);
}
