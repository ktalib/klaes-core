<?php
// Database column check for tracking_id
// File: check_tracking_id_column.php

try {
    // Load Laravel framework
    require_once 'bootstrap/app.php';
    
    // Get SQL Server connection
    $pdo = DB::connection('sqlsrv')->getPdo();
    
    echo "<h2>Database Schema Check - tracking_id Column</h2>\n";
    
    // Check if tracking_id column exists in file_indexings table
    $sql = "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = 'file_indexings' 
            AND COLUMN_NAME = 'tracking_id'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "<p>✅ <strong>tracking_id column exists!</strong></p>\n";
        echo "<ul>\n";
        echo "<li>Column Name: {$result['COLUMN_NAME']}</li>\n";
        echo "<li>Data Type: {$result['DATA_TYPE']}</li>\n";
        echo "<li>Nullable: {$result['IS_NULLABLE']}</li>\n";
        echo "<li>Default: " . ($result['COLUMN_DEFAULT'] ?: 'None') . "</li>\n";
        echo "</ul>\n";
    } else {
        echo "<p>❌ <strong>tracking_id column does NOT exist!</strong></p>\n";
        echo "<p>The column needs to be added to the file_indexings table.</p>\n";
        
        // Show the SQL command to add it
        echo "<h3>SQL to add the column:</h3>\n";
        echo "<code>ALTER TABLE [klas].[dbo].[file_indexings] ADD tracking_id NVARCHAR(255) NULL;</code>\n";
    }
    
    // Also check the full structure of file_indexings table
    echo "<hr>\n";
    echo "<h3>Full file_indexings table structure (first 20 columns):</h3>\n";
    
    $sql2 = "SELECT TOP 20 COLUMN_NAME, DATA_TYPE, IS_NULLABLE 
             FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_NAME = 'file_indexings' 
             ORDER BY ORDINAL_POSITION";
    
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute();
    $columns = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Column Name</th><th>Data Type</th><th>Nullable</th></tr>\n";
    
    foreach ($columns as $col) {
        echo "<tr>\n";
        echo "<td>{$col['COLUMN_NAME']}</td>\n";
        echo "<td>{$col['DATA_TYPE']}</td>\n";
        echo "<td>{$col['IS_NULLABLE']}</td>\n";
        echo "</tr>\n";
    }
    
    echo "</table>\n";
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Error:</strong> " . $e->getMessage() . "</p>\n";
}
?>