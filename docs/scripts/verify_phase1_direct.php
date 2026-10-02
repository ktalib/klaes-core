<?php

// Direct SQL Server connection to verify schema
require 'vendor/autoload.php';

// Get config
$config = require 'config/database.php';
$sqlsrv_config = $config['connections']['sqlsrv'];

// Connect using PDO
try {
    $dsn = sprintf(
        "sqlsrv:Server=%s;Database=%s",
        $sqlsrv_config['host'],
        $sqlsrv_config['database']
    );
    
    $pdo = new PDO(
        $dsn,
        $sqlsrv_config['username'],
        $sqlsrv_config['password']
    );
    
    echo "=== Phase 1 Migration Verification ===\n\n";
    
    // Check user_activity_logs columns
    echo "Checking user_activity_logs table...\n";
    
    $stmt = $pdo->query("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'user_activity_logs'
    ");
    
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $required = ['duration_minutes', 'status', 'last_seen_at', 'related_file_number', 'test_control', 'indexed_at'];
    
    $missing = array_diff($required, $columns);
    
    if (empty($missing)) {
        echo "✅ All required columns found:\n";
        foreach ($required as $col) {
            echo "   - $col\n";
        }
    } else {
        echo "❌ Missing columns:\n";
        foreach ($missing as $col) {
            echo "   - $col\n";
        }
    }
    
    echo "\n";
    
    // Check user_activity_log_settings columns
    echo "Checking user_activity_log_settings table...\n";
    
    $stmt = $pdo->query("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'user_activity_log_settings'
    ");
    
    $settings_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $required_settings = ['timezone', 'notes'];
    
    $missing_settings = array_diff($required_settings, $settings_columns);
    
    if (empty($missing_settings)) {
        echo "✅ All required columns found:\n";
        foreach ($required_settings as $col) {
            echo "   - $col\n";
        }
    } else {
        echo "❌ Missing columns:\n";
        foreach ($missing_settings as $col) {
            echo "   - $col\n";
        }
    }
    
    echo "\n";
    
    // Check indexes
    echo "Checking indexes...\n";
    
    $stmt = $pdo->query("
        SELECT name FROM sys.indexes 
        WHERE object_id = OBJECT_ID('user_activity_logs')
        AND name = 'idx_activity_user_status_login'
    ");
    
    $indexes = $stmt->fetchAll();
    
    if (!empty($indexes)) {
        echo "✅ Composite index found: idx_activity_user_status_login\n";
    } else {
        echo "⚠️  Composite index not found\n";
    }
    
    echo "\n=== Summary ===\n";
    if (empty($missing) && empty($missing_settings)) {
        echo "✅ PHASE 1 MIGRATION COMPLETE\n";
        echo "All database enhancements successfully applied!\n";
    } else {
        echo "⚠️  Some columns are missing - migration may need to be run\n";
    }

} catch (Exception $e) {
    echo "❌ Connection Error: " . $e->getMessage() . "\n";
    exit(1);
}
