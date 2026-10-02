<?php

// Database connection details
$serverName = "localhost";
$database = "klas";
$username = "sa";
$password = "password"; // Update with your actual password

try {
    // Connect to SQL Server
    $conn = new PDO("sqlsrv:Server=$serverName;Database=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully.\n";
    
    // Get the maximum temp file number across all tables
    $maxTempFileNo = 0;
    $prefix = 'TEMP-';
    
    $tablesToCheck = ['pra', 'pic', 'instrument_capture', 'deed_registrations'];
    
    foreach ($tablesToCheck as $table) {
        // Check if table exists
        $checkTableSql = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?";
        $stmt = $conn->prepare($checkTableSql);
        $stmt->execute([$table]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['cnt'] == 0) {
            echo "Table $table does not exist, skipping...\n";
            continue;
        }
        
        // Determine which column to check
        $columnsToCheck = [];
        if ($table === 'deed_registrations') {
            $columnsToCheck = ['fileno'];
        } else {
            $columnsToCheck = ['temp_fileno', 'mlsFNo'];
        }
        
        foreach ($columnsToCheck as $col) {
            // Check if column exists
            $checkColSql = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS 
                           WHERE TABLE_NAME = ? AND COLUMN_NAME = ?";
            $stmt = $conn->prepare($checkColSql);
            $stmt->execute([$table, $col]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['cnt'] == 0) {
                continue;
            }
            
            // Get max temp file number from this column
            $sql = "SELECT TOP 1 $col 
                    FROM $table 
                    WHERE $col LIKE ? 
                    ORDER BY TRY_CONVERT(INT, REPLACE(CAST($col AS VARCHAR), ?, '')) DESC, $col DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$prefix . '%', $prefix]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row && $row[$col]) {
                if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/i', $row[$col], $matches)) {
                    $currentMax = (int)$matches[1];
                    $maxTempFileNo = max($maxTempFileNo, $currentMax);
                    echo "Found max in $table.$col: {$row[$col]} (number: $currentMax)\n";
                }
            }
        }
    }
    
    echo "\nStarting temp file number: " . ($maxTempFileNo + 1) . "\n\n";
    
    // Now process CSV files
    $directory = 'C:/wamp64/www/klaes/docs/data/OPRegistry';
    $outputFile = 'C:/wamp64/www/klaes/docs/data/OPRegistry/insert_ops.sql';
    
    $files = glob($directory . '/op*.csv');
    
    $sqlStatements = [];
    $nowStr = date('Y-m-d H:i:s');
    $todayStr = date('Y-m-d');
    
    function clean($text) {
        if ($text === null || $text === '') return "";
        return str_replace("'", "''", trim($text));
    }
    
    $currentTempFileNo = $maxTempFileNo + 1;
    
    foreach ($files as $filePath) {
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 1000, ",");
            if (!$headers) continue;
            
            // Trim headers
            $headers = array_map('trim', $headers);
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Pad data if needed
                if (count($data) < count($headers)) {
                    $data = array_pad($data, count($headers), "");
                }
                
                $row = array_combine($headers, array_slice($data, 0, count($headers)));
                
                // Extract fields
                $grantee = clean($row['Allottee'] ?? '');
                $serialRaw = trim($row['SerialNo'] ?? '');
                $pageRaw = trim($row['PageNo'] ?? '');
                $volRaw = trim($row['VolNo'] ?? '');
                $tp_no = clean($row['TPNo'] ?? '');
                $op_serial = clean($row['OP SerialNo'] ?? '');
                $plot = clean($row['PlotNo'] ?? ($row['PlotNo '] ?? ''));
                $district = clean($row['District'] ?? '');
                
                // Skip empty rows
                if (empty($grantee) && empty($serialRaw) && empty($pageRaw) && empty($volRaw) && 
                    empty($tp_no) && empty($op_serial) && empty($plot) && empty($district)) {
                    continue; 
                }
                
                // Skip rows with missing critical numbering data
                if (empty($serialRaw) || empty($pageRaw) || empty($volRaw) || 
                    !is_numeric($serialRaw) || !is_numeric($pageRaw) || !is_numeric($volRaw)) {
                    continue;
                }
                
                $serial = (int)$serialRaw;
                $page = (int)$pageRaw;
                $vol = (int)$volRaw;
                
                $reg_no = "{$page}/{$serial}/{$vol}";
                
                // Generate temp file number
                $tempFileNo = $prefix . str_pad((string)$currentTempFileNo, 5, '0', STR_PAD_LEFT);
                $currentTempFileNo++;
                
                // Insert into instrument_capture
                $sqlStatements[] = "INSERT INTO [instrument_capture] (
                    [instrument_type], [registration_number], [volume_no], [page_no], [serial_no],
                    [party_1_name], [party_1_address], [party_2_name], [plot_number], [district],
                    [tp_no], [op_serial_number], [temp_fileno], [is_deed_registered], 
                    [created_at], [updated_at], [created_by]
                ) VALUES (
                    'Occupancy Permit', '$reg_no', $vol, $page, $serial,
                    'Kano State Kano', 'Ministry of Land and Physical Planning Kano State', 
                    '$grantee', '$plot', '$district',
                    '$tp_no', '$op_serial', '$tempFileNo', 1, 
                    '$nowStr', '$nowStr', 1
                );";
                
                // Insert into deed_registrations
                $sqlStatements[] = "INSERT INTO [deed_registrations] (
                    [fileno], [instrument_type], [registration_number], [volume_no], [page_no], [serial_no],
                    [grantor], [grantee], [plot_number], [district], [status],
                    [created_at], [updated_at], [created_by], [updated_by], [deeds_date], [instrument_date]
                ) VALUES (
                    '$tempFileNo', 'Occupancy Permit', '$reg_no', $vol, $page, $serial,
                    'Kano State Kano', '$grantee', '$plot', '$district', 'registered',
                    '$nowStr', '$nowStr', 1, 1, '$todayStr', '$todayStr'
                );";
            }
            fclose($handle);
        }
    }
    
    // Write SQL file
    file_put_contents($outputFile, "-- SQL Generated for OP Registry Import with Temp File Numbers\n-- Generated: $nowStr\nBEGIN TRANSACTION;\n" . implode("\n", $sqlStatements) . "\nCOMMIT;");
    
    echo "\nSQL file generated: $outputFile\n";
    echo "Total statements: " . count($sqlStatements) . "\n";
    echo "Records processed: " . (count($sqlStatements) / 2) . "\n";
    echo "Temp file numbers used: " . ($prefix . str_pad((string)($maxTempFileNo + 1), 5, '0', STR_PAD_LEFT)) . " to " . ($prefix . str_pad((string)($currentTempFileNo - 1), 5, '0', STR_PAD_LEFT)) . "\n";
    
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    echo "\nPlease update the database credentials in the script.\n";
    exit(1);
}
