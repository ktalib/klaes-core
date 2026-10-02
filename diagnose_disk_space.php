<?php
/**
 * SQL Server Disk Space Diagnostic Script
 * Checks PRIMARY filegroup capacity and provides remediation steps
 */

// Database configuration from .env
$server = '10.50.1.1';
$database = 'klas';
$username = 'klaesDb';
$password = 'KlasdbStrongPassword123';
$port = 1433;

// Connection string for SQL Server
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
    
    echo "=== SQL Server Disk Space Diagnostic Report ===\n";
    echo "Database: {$database}\n";
    echo "Server: {$server}\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n";
    echo str_repeat("=", 50) . "\n\n";
    
    // 1. Check Filegroup Status
    echo "1. FILEGROUP STATUS:\n";
    echo str_repeat("-", 50) . "\n";
    
    $sql1 = "SELECT name AS FileGroupName, type_desc AS FileGroupType 
             FROM sys.filegroups";
    $stmt1 = sqlsrv_query($conn, $sql1);
    
    if ($stmt1 === false) {
        throw new Exception("Query 1 failed: " . print_r(sqlsrv_errors(), true));
    }
    
    while ($row = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC)) {
        echo "  Filegroup: {$row['FileGroupName']} ({$row['FileGroupType']})\n";
    }
    sqlsrv_free_stmt($stmt1);
    echo "\n";
    
    // 2. Check Data File Sizes and Space Usage
    echo "2. DATA FILE SPACE USAGE:\n";
    echo str_repeat("-", 50) . "\n";
    
    $sql2 = "SELECT 
                df.name AS FileName,
                df.physical_name AS FilePath,
                CAST(df.size * 8.0 / 1024 AS NUMERIC(10,2)) AS SizeMB,
                CAST(FILEPROPERTY(df.name, 'SpaceUsed') * 8.0 / 1024 AS NUMERIC(10,2)) AS UsedMB,
                CAST((df.size - FILEPROPERTY(df.name, 'SpaceUsed')) * 8.0 / 1024 AS NUMERIC(10,2)) AS FreeMB,
                CAST(((df.size - FILEPROPERTY(df.name, 'SpaceUsed')) * 100.0 / df.size) AS NUMERIC(5,2)) AS FreePercent,
                is_percent_growth,
                growth
             FROM sys.database_files df
             WHERE df.type = 0
             ORDER BY df.name";
    
    $stmt2 = sqlsrv_query($conn, $sql2);
    
    if ($stmt2 === false) {
        throw new Exception("Query 2 failed: " . print_r(sqlsrv_errors(), true));
    }
    
    $totalSizeMB = 0;
    $totalUsedMB = 0;
    $totalFreeMB = 0;
    $criticalFlag = false;
    
    while ($row = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
        $sizeMB = (float)$row['SizeMB'];
        $usedMB = (float)$row['UsedMB'];
        $freeMB = (float)$row['FreeMB'];
        $freePercent = (float)$row['FreePercent'];
        
        $totalSizeMB += $sizeMB;
        $totalUsedMB += $usedMB;
        $totalFreeMB += $freeMB;
        
        echo "  File: {$row['FileName']}\n";
        echo "    Path: {$row['FilePath']}\n";
        echo "    Total Size: {$sizeMB} MB\n";
        echo "    Used Space: {$usedMB} MB\n";
        echo "    Free Space: {$freeMB} MB ({$freePercent}%)\n";
        
        // Check autogrowth
        $growthText = ($row['is_percent_growth'] == 1) 
            ? $row['growth'] . '%' 
            : $row['growth'] . ' pages (8KB)';
        echo "    Autogrowth: Yes ({$growthText})\n";
        
        // Warning if free space is low
        if ($freePercent < 10) {
            echo "    ⚠️  WARNING: Low free space!\n";
            $criticalFlag = true;
        }
        echo "\n";
    }
    sqlsrv_free_stmt($stmt2);
    
    echo "  SUMMARY:\n";
    echo "    Total Database Size: {$totalSizeMB} MB\n";
    echo "    Total Used Space: {$totalUsedMB} MB\n";
    echo "    Total Free Space: {$totalFreeMB} MB\n";
    echo "\n";

    // --- 5. Six-month projection ---
    // Supports: --history=path/to.csv  (date,usedMB) or --monthly-percent=X
    function compute_linear_slope_from_history($csvPath) {
        $lines = @file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return null;
        $points = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = str_getcsv($line);
            if (count($parts) < 2) continue;
            $date = strtotime(trim($parts[0]));
            $value = floatval(trim($parts[1]));
            if ($date === false) continue;
            $points[] = ['t' => $date, 'v' => $value];
        }
        if (count($points) < 2) return null;
        usort($points, function($a,$b){return $a['t'] - $b['t'];});
        $t0 = $points[0]['t'];
        // convert time to months since t0
        $xs = [];
        $ys = [];
        foreach ($points as $p) {
            $months = ($p['t'] - $t0) / (30*24*60*60);
            $xs[] = $months;
            $ys[] = $p['v'];
        }
        $n = count($xs);
        $sumx = array_sum($xs);
        $sumy = array_sum($ys);
        $sumxy = 0; $sumxx = 0;
        for ($i=0;$i<$n;$i++) { $sumxy += $xs[$i]*$ys[$i]; $sumxx += $xs[$i]*$xs[$i]; }
        $den = ($n*$sumxx - $sumx*$sumx);
        if (abs($den) < 1e-9) return null;
        $slope = ($n*$sumxy - $sumx*$sumy)/$den; // MB per month
        return $slope;
    }

    function print_6_month_projection($totalSizeMB, $totalUsedMB, $totalFreeMB, $slopeMBperMonth = null, $monthlyPercent = null) {
        echo "6-MONTH STORAGE PROJECTION:\n";
        echo str_repeat("-",50) . "\n";
        echo sprintf("Current Used: %.2f MB | Total Size: %.2f MB | Current Free: %.2f MB\n", $totalUsedMB, $totalSizeMB, $totalFreeMB);
        echo "\n";
        echo sprintf("%-8s %-18s %-18s %-12s\n", 'Month', 'Projected Used (MB)', 'Projected Free (MB)', 'Free %');
        for ($m=1;$m<=6;$m++) {
            if ($slopeMBperMonth !== null) {
                $projUsed = $totalUsedMB + $slopeMBperMonth * $m;
            } else {
                $pct = ($monthlyPercent !== null) ? $monthlyPercent : 2.0; // default 2% monthly
                $projUsed = $totalUsedMB * pow(1 + $pct/100.0, $m);
            }
            // assume total size fixed (autogrowth not modeled), free reduces as used grows
            $projFree = max(0.0, $totalSizeMB - $projUsed);
            $freePct = ($totalSizeMB > 0) ? ($projFree * 100.0 / $totalSizeMB) : 0.0;
            echo sprintf("%-8d %-18.2f %-18.2f %-12.2f\n", $m, $projUsed, $projFree, $freePct);
        }
        echo "\n";
        if ($slopeMBperMonth === null) {
            $usedAfter6 = $totalUsedMB * pow(1 + ($monthlyPercent ?? 2.0)/100.0, 6);
            if ($usedAfter6 > $totalSizeMB) {
                echo "NOTE: At the given monthly percent growth, used space will exceed total size within 6 months unless autogrowth/size increases.\n";
            }
        }
    }

    // parse CLI args when run from CLI
    $historyPath = null;
    $monthlyPercent = null;
    if (php_sapi_name() === 'cli') {
        global $argv;
        foreach ($argv as $a) {
            if (strpos($a, '--history=') === 0) {
                $historyPath = substr($a, strlen('--history='));
            }
            if (strpos($a, '--monthly-percent=') === 0) {
                $monthlyPercent = floatval(substr($a, strlen('--monthly-percent=')));
            }
        }
    }

    $slope = null;
    if ($historyPath && file_exists($historyPath)) {
        $slope = compute_linear_slope_from_history($historyPath);
        if ($slope === null) {
            echo "Warning: could not compute slope from history file, falling back to percent method.\n";
        }
    }

    print_6_month_projection($totalSizeMB, $totalUsedMB, $totalFreeMB, $slope, $monthlyPercent);
    
    // 3. Check Largest Tables
    echo "3. LARGEST TABLES (Top 10):\n";
    echo str_repeat("-", 50) . "\n";
    
    $sql3 = "SELECT TOP 10
                OBJECT_NAME(ps.object_id) AS TableName,
                SUM(ps.row_count) AS [RowCount],
                CAST(SUM(ps.reserved_page_count) * 8.0 / 1024 AS NUMERIC(10,2)) AS ReservedMB
             FROM sys.dm_db_partition_stats ps
             WHERE ps.object_id > 100
             GROUP BY ps.object_id
             ORDER BY SUM(ps.reserved_page_count) DESC";
    
    $stmt3 = sqlsrv_query($conn, $sql3);
    
    if ($stmt3 === false) {
        throw new Exception("Query 3 failed: " . print_r(sqlsrv_errors(), true));
    }
    
    while ($row = sqlsrv_fetch_array($stmt3, SQLSRV_FETCH_ASSOC)) {
        echo "  {$row['TableName']}: {$row['ReservedMB']} MB ({$row['RowCount']} rows)\n";
    }
    sqlsrv_free_stmt($stmt3);
    echo "\n";
    
    // 4. Problems and Recommendations
    echo "4. RECOMMENDATIONS:\n";
    echo str_repeat("-", 50) . "\n";
    
    if ($criticalFlag) {
        echo "  🔴 CRITICAL: Your PRIMARY filegroup is critically low on free space!\n\n";
    }
    
    echo "  IMMEDIATE ACTIONS:\n";
    echo "  1. Enable autogrowth on existing data files (if not already enabled)\n";
    echo "  2. Add additional data files to the PRIMARY filegroup\n";
    echo "  3. Consider archiving old data from high-volume tables\n\n";
    
    echo "  SQL COMMANDS TO EXECUTE:\n\n";
    
    echo "  -- Enable autogrowth (100MB increments):\n";
    echo "  ALTER DATABASE klas MODIFY FILE (\n";
    echo "      NAME = 'klas',\n";
    echo "      FILEGROWTH = 100MB\n";
    echo "  );\n\n";
    
    echo "  -- Add new data file to PRIMARY filegroup:\n";
    echo "  ALTER DATABASE klas ADD FILE (\n";
    echo "      NAME = 'klas_data_2',\n";
    echo "      FILENAME = 'D:\\SQLData\\klas_data_2.ndf',\n";
    echo "      SIZE = 500MB,\n";
    echo "      FILEGROWTH = 100MB\n";
    echo "  ) TO FILEGROUP [PRIMARY];\n\n";
    
    sqlsrv_close($conn);
    
    echo str_repeat("=", 50) . "\n";
    echo "Diagnostic complete.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
