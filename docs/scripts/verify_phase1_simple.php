<?php

// Direct SQL Server connection to verify schema - no Laravel bootstrap
$host = getenv('DB_HOST') ?: 'localhost';
$database = getenv('DB_DATABASE') ?: 'EDMAS';
$username = getenv('DB_USERNAME') ?: 'SA';
$password = getenv('DB_PASSWORD') ?: '';
$port = getenv('DB_PORT') ?: '1433';

echo "=== Phase 1 Migration Verification ===\n";
echo "Connecting to: $host:$port/$database\n\n";

try {
    // Connect using PDO
    $dsn = "sqlsrv:Server=$host,$port;Database=$database";
    
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to SQL Server\n\n";
    
    // Check user_activity_logs columns
    echo "Checking user_activity_logs table...\n";
    
    $stmt = $pdo->query("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'user_activity_logs'
        AND TABLE_SCHEMA = 'dbo'
    ");
    
    $columns = array_map(function($row) {
        return is_array($row) ? $row['COLUMN_NAME'] : $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    $required = ['duration_minutes', 'status', 'last_seen_at', 'related_file_number', 'test_control', 'indexed_at'];
    
    $missing = array_diff($required, $columns);
    
    if (empty($missing)) {
        echo "✅ All required columns found:\n";
        foreach ($required as $col) {
            echo "   ✓ $col\n";
        }
    } else {
        echo "❌ Missing columns:\n";
        foreach ($missing as $col) {
            echo "   ✗ $col\n";
        }
        echo "\nExisting columns: " . implode(', ', $columns) . "\n";
    }
    
    echo "\n";
    
    // Check user_activity_log_settings columns
    echo "Checking user_activity_log_settings table...\n";
    
    $stmt = $pdo->query("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'user_activity_log_settings'
        AND TABLE_SCHEMA = 'dbo'
    ");
    
    $settings_columns = array_map(function($row) {
        return is_array($row) ? $row['COLUMN_NAME'] : $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    $required_settings = ['timezone', 'notes'];
    
    $missing_settings = array_diff($required_settings, $settings_columns);
    
    if (empty($missing_settings)) {
        echo "✅ All required columns found:\n";
        foreach ($required_settings as $col) {
            echo "   ✓ $col\n";
        }
    } else {
        echo "❌ Missing columns:\n";
        foreach ($missing_settings as $col) {
            echo "   ✗ $col\n";
        }
        echo "\nExisting columns: " . implode(', ', $settings_columns) . "\n";
    }
    
    echo "\n";
    
    // Check indexes
    echo "Checking indexes...\n";
    
    $stmt = $pdo->query("
        SELECT name FROM sys.indexes 
        WHERE object_id = OBJECT_ID('user_activity_logs')
    ");
    
    $all_indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $index_names = array_map(function($row) {
        return is_array($row) ? $row['name'] : $row;
    }, $all_indexes);
    
    if (in_array('idx_activity_user_status_login', $index_names)) {
        echo "✅ Composite index found: idx_activity_user_status_login\n";
    } else {
        echo "⚠️  Composite index not found\n";
        echo "   Available indexes: " . implode(', ', $index_names) . "\n";
    }
    
    echo "\n=== Summary ===\n";
    if (empty($missing) && empty($missing_settings)) {
        echo "✅ PHASE 1 MIGRATION COMPLETE\n";
        echo "All database enhancements successfully applied!\n";
        exit(0);
    } else {
        echo "⚠️  Some columns are missing - migration needs to be executed\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
