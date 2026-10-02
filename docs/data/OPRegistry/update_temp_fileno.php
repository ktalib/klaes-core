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
    
    echo "Connected to database successfully.\n\n";
    
    // Step 1: Get the maximum temp file number across all tables
    $maxTempFileNo = 0;
    $prefix = 'TEMP-';
    
    $tablesToCheck = ['pra', 'pic', 'instrument_capture', 'deed_registrations'];
    
    echo "Checking for existing temp file numbers...\n";
    foreach ($tablesToCheck as $table) {
        // Check if table exists
        $checkTableSql = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?";
        $stmt = $conn->prepare($checkTableSql);
        $stmt->execute([$table]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['cnt'] == 0) {
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
                    echo "  Found max in $table.$col: {$row[$col]} (number: $currentMax)\n";
                }
            }
        }
    }
    
    $startingTempFileNo = $maxTempFileNo + 1;
    echo "\nStarting temp file number: $startingTempFileNo\n";
    echo "Formatted: " . ($prefix . str_pad((string)$startingTempFileNo, 5, '0', STR_PAD_LEFT)) . "\n\n";
    
    // Step 2: Find all OP records without temp_fileno
    echo "Finding Occupancy Permit records without temp_fileno...\n";
    $sql = "SELECT id, registration_number, party_2_name, plot_number, district 
            FROM instrument_capture 
            WHERE instrument_type = 'Occupancy Permit' 
            AND (temp_fileno IS NULL OR temp_fileno = '')
            ORDER BY id ASC";
    
    $stmt = $conn->query($sql);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($records) == 0) {
        echo "No records found that need temp file numbers.\n";
        exit(0);
    }
    
    echo "Found " . count($records) . " records to update.\n\n";
    
    // Step 3: Begin transaction and update records
    echo "Starting update process...\n";
    $conn->beginTransaction();
    
    $currentTempFileNo = $startingTempFileNo;
    $updatedCount = 0;
    
    foreach ($records as $record) {
        $tempFileNo = $prefix . str_pad((string)$currentTempFileNo, 5, '0', STR_PAD_LEFT);
        
        // Update instrument_capture
        $updateInstrumentSql = "UPDATE instrument_capture 
                               SET temp_fileno = ?, updated_at = GETDATE() 
                               WHERE id = ?";
        $stmt = $conn->prepare($updateInstrumentSql);
        $stmt->execute([$tempFileNo, $record['id']]);
        
        // Update deed_registrations (match by registration_number and instrument_type)
        $updateDeedSql = "UPDATE deed_registrations 
                         SET fileno = ?, updated_at = GETDATE() 
                         WHERE registration_number = ? 
                         AND instrument_type = 'Occupancy Permit'
                         AND (fileno IS NULL OR fileno = '')";
        $stmt = $conn->prepare($updateDeedSql);
        $stmt->execute([$tempFileNo, $record['registration_number']]);
        
        $updatedCount++;
        $currentTempFileNo++;
        
        // Show progress every 50 records
        if ($updatedCount % 50 == 0) {
            echo "  Updated $updatedCount records...\n";
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    $endingTempFileNo = $currentTempFileNo - 1;
    
    echo "\n✓ Successfully updated $updatedCount records!\n";
    echo "\nTemp file numbers assigned:\n";
    echo "  From: " . ($prefix . str_pad((string)$startingTempFileNo, 5, '0', STR_PAD_LEFT)) . "\n";
    echo "  To:   " . ($prefix . str_pad((string)$endingTempFileNo, 5, '0', STR_PAD_LEFT)) . "\n";
    
    // Verification
    echo "\nVerifying updates...\n";
    $verifySql = "SELECT COUNT(*) as cnt FROM instrument_capture 
                  WHERE instrument_type = 'Occupancy Permit' 
                  AND (temp_fileno IS NULL OR temp_fileno = '')";
    $stmt = $conn->query($verifySql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['cnt'] == 0) {
        echo "✓ All Occupancy Permit records now have temp file numbers.\n";
    } else {
        echo "⚠ Warning: {$result['cnt']} records still missing temp file numbers.\n";
    }
    
} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
        echo "\n✗ Transaction rolled back due to error.\n";
    }
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nPlease update the database credentials in the script.\n";
    exit(1);
}
