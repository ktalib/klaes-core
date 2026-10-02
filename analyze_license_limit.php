<?php
/**
 * SQL Server License Limit Analysis
 * Shows archiving candidates WITHOUT deleting data
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
    
    echo "=== SQL Server License Limit Analysis ===\n";
    echo "Database: {$database}\n";
    echo "License Limit: 10,240 MB (10 GB)\n";
    echo "Current Size: 10,024 MB (FULL)\n";
    echo str_repeat("=", 60) . "\n\n";
    
    // Show tables by date range
    echo "ARCHIVING CANDIDATES (Show OLD data by table):\n";
    echo str_repeat("-", 60) . "\n\n";
    
    // grouping table analysis
    echo "1. TABLE: grouping (5,338.61 MB)\n";
    $sql = "SELECT 
                MIN(created_at) AS OldestRecord,
                MAX(created_at) AS NewestRecord,
                COUNT(*) AS TotalRows,
                DATEDIFF(YEAR, MIN(created_at), MAX(created_at)) AS AgeInYears
            FROM grouping";
    
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt) {
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            echo "   Oldest: " . $row['OldestRecord'] . "\n";
            echo "   Newest: " . $row['NewestRecord'] . "\n";
            echo "   Total Rows: " . number_format($row['TotalRows']) . "\n";
            echo "   Age Range: " . $row['AgeInYears'] . " years\n";
        }
        sqlsrv_free_stmt($stmt);
    }
    echo "\n";
    
    // blindings_scannings analysis
    echo "2. TABLE: blind_scannings (1,098.97 MB)\n";
    $sql = "SELECT 
                MIN(created_at) AS OldestRecord,
                MAX(created_at) AS NewestRecord,
                COUNT(*) AS TotalRows
            FROM blind_scannings";
    
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt) {
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            echo "   Oldest: " . $row['OldestRecord'] . "\n";
            echo "   Newest: " . $row['NewestRecord'] . "\n";
            echo "   Total Rows: " . number_format($row['TotalRows']) . "\n";
        }
        sqlsrv_free_stmt($stmt);
    }
    echo "\n";
    
    // pra table analysis
    echo "3. TABLE: pra (282.59 MB)\n";
    $sql = "SELECT 
                MIN(created_at) AS OldestRecord,
                MAX(created_at) AS NewestRecord,
                COUNT(*) AS TotalRows
            FROM pra";
    
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt) {
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            echo "   Oldest: " . $row['OldestRecord'] . "\n";
            echo "   Newest: " . $row['NewestRecord'] . "\n";
            echo "   Total Rows: " . number_format($row['TotalRows']) . "\n";
        }
        sqlsrv_free_stmt($stmt);
    }
    echo "\n";
    
    echo str_repeat("=", 60) . "\n";
    echo "AVAILABLE OPTIONS:\n\n";
    
    echo "Option A: UPGRADE SQL SERVER LICENSE\n";
    echo "  • Remove 10 GB limit → unlimited database size\n";
    echo "  • Then add new data files as planned\n";
    echo "  • Requires: SQL Server Standard or Enterprise edition\n\n";
    
    echo "Option B: CREATE ARCHIVE TABLES\n";
    echo "  • Backup old records to separate archive database\n";
    echo "  • Delete old data from production (2+ years old)\n";
    echo "  • Free up space for new data\n";
    echo "  • Keep audit trail in archive\n\n";
    
    echo "Option C: REQUEST FEATURE\n";
    echo "  • Archive: blind_scannings (1.1 GB) → Archive DB\n";
    echo "  • Archive: grouping old records (2-3 GB) → Archive DB  \n";
    echo "  • Could free up 3-4 GB within same 10 GB limit\n\n";
    
    echo "RECOMMENDATION:\n";
    echo "  Create archive database on different SQL Server/storage\n";
    echo "  Move 2+ year old records to archive\n";
    echo "  This creates room for current operations\n";
    
    sqlsrv_close($conn);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
