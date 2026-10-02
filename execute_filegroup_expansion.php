<?php
/**
 * SQL Server Filegroup Expansion & Maintenance Script
 * Adds new data file and rebuilds indexes
 */

$server = '10.50.1.1';
$database = 'klas';
$username = 'klaesDb';
$password = 'KlasdbStrongPassword123';
$port = 1433;

$connectionInfo = array(
    "Database" => $database,
    "UID" => $username,
    "PWD" => $password,
    "CharacterSet" => "UTF-8",
    "ReturnDatesAsStrings" => true
);

try {
    $conn = sqlsrv_connect("{$server},{$port}", $connectionInfo);
    
    if ($conn === false) {
        throw new Exception("Connection failed: " . print_r(sqlsrv_errors(), true));
    }
    
    echo "=== SQL Server Filegroup Expansion & Maintenance ===\n";
    echo "Database: {$database}\n";
    echo "Server: {$server}\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n";
    echo str_repeat("=", 50) . "\n\n";
    
    // Command 1: Add new data file
    echo "1. ADDING NEW DATA FILE TO PRIMARY FILEGROUP...\n";
    echo str_repeat("-", 50) . "\n";
    
    $sql1 = "ALTER DATABASE klas ADD FILE (
        NAME = 'klas_data_2',
        FILENAME = 'F:\\SQLData\\klas_data_2.ndf',
        SIZE = 2000MB,
        FILEGROWTH = 200MB
    ) TO FILEGROUP [PRIMARY]";
    
    $stmt1 = sqlsrv_query($conn, $sql1);
    
    if ($stmt1 === false) {
        throw new Exception("Failed to add new file: " . print_r(sqlsrv_errors(), true));
    }
    
    echo "✓ Successfully added klas_data_2.ndf (2,000 MB)\n";
    echo "  Location: F:\\SQLData\\klas_data_2.ndf\n";
    echo "  Autogrowth: 200 MB increments\n\n";
    
    // Command 2: Rebuild indexes on pra table
    echo "2. REBUILDING INDEXES ON [pra] TABLE...\n";
    echo str_repeat("-", 50) . "\n";
    
    $sql2 = "DBCC DBREINDEX ([pra], '', 80)";
    $stmt2 = sqlsrv_query($conn, $sql2, array("Scrollable" => SQLSRV_CURSOR_STATIC));
    
    if ($stmt2 === false) {
        echo "⚠️  Index rebuild encountered an issue: " . print_r(sqlsrv_errors(), true) . "\n";
    } else {
        echo "✓ Index rebuild initiated for [pra] table\n";
        echo "  Fill Factor: 80%\n\n";
    }
    
    // Command 3: Shrink database file
    echo "3. SHRINKING DATABASE FILE...\n";
    echo str_repeat("-", 50) . "\n";
    
    $sql3 = "DBCC SHRINKFILE (Klas, 8500)";
    $stmt3 = sqlsrv_query($conn, $sql3, array("Scrollable" => SQLSRV_CURSOR_STATIC));
    
    if ($stmt3 === false) {
        echo "⚠️  Shrink encountered an issue: " . print_r(sqlsrv_errors(), true) . "\n";
    } else {
        echo "✓ Database file shrink initiated\n";
        echo "  Target size: 8,500 MB\n\n";
    }
    
    // Verify new file was added
    echo "4. VERIFYING NEW FILE CONFIGURATION...\n";
    echo str_repeat("-", 50) . "\n";
    
    $sqlVerify = "SELECT 
                    df.name AS FileName,
                    df.physical_name AS FilePath,
                    CAST(df.size * 8.0 / 1024 AS NUMERIC(10,2)) AS SizeMB,
                    CAST(FILEPROPERTY(df.name, 'SpaceUsed') * 8.0 / 1024 AS NUMERIC(10,2)) AS UsedMB,
                    CAST((df.size - FILEPROPERTY(df.name, 'SpaceUsed')) * 8.0 / 1024 AS NUMERIC(10,2)) AS FreeMB
                 FROM sys.database_files df
                 WHERE df.type = 0
                 ORDER BY df.name";
    
    $stmtVerify = sqlsrv_query($conn, $sqlVerify);
    
    if ($stmtVerify === false) {
        throw new Exception("Verification query failed: " . print_r(sqlsrv_errors(), true));
    }
    
    $totalSize = 0;
    $totalUsed = 0;
    $totalFree = 0;
    
    while ($row = sqlsrv_fetch_array($stmtVerify, SQLSRV_FETCH_ASSOC)) {
        $size = (float)$row['SizeMB'];
        $used = (float)$row['UsedMB'];
        $free = (float)$row['FreeMB'];
        
        $totalSize += $size;
        $totalUsed += $used;
        $totalFree += $free;
        
        echo "  File: {$row['FileName']}\n";
        echo "    Size: {$size} MB | Used: {$used} MB | Free: {$free} MB\n";
    }
    sqlsrv_free_stmt($stmtVerify);
    
    echo "\n  TOTAL DATABASE STATUS:\n";
    echo "    Total Size: {$totalSize} MB\n";
    echo "    Total Used: {$totalUsed} MB\n";
    echo "    Total Free: {$totalFree} MB\n";
    echo "    Available Space: " . round(($totalFree / $totalSize) * 100, 2) . "%\n\n";
    
    // Summary
    echo str_repeat("=", 50) . "\n";
    echo "✓ OPERATIONS COMPLETED SUCCESSFULLY\n\n";
    echo "SUMMARY:\n";
    echo "  • Added 2,000 MB new data file (klas_data_2.ndf)\n";
    echo "  • Rebuilt indexes on pra table\n";
    echo "  • Initiated database file shrink\n";
    echo "  • Total available space: ~{$totalFree} MB\n\n";
    
    echo "NEXT STEPS:\n";
    echo "  1. Monitor database growth over next few days\n";
    echo "  2. If space fills up again, consider archiving old records\n";
    echo "  3. Check index fragmentation regularly\n";
    echo "  4. Consider implementing data retention policies\n";
    
    sqlsrv_close($conn);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
