<?php
try {
    $pdo = new PDO('sqlsrv:server=localhost;database=klas', 'klas', 'YourStrongPassword123!');
    echo "=== GROUPING TABLE ANALYSIS ===\n";
    
    // Get total count
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM grouping');
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total Records: " . number_format($total['total']) . "\n\n";
    
    // Sample records to understand structure
    echo "=== SAMPLE RECORDS ===\n";
    $stmt = $pdo->query('SELECT TOP 5 awaiting_fileno, mls_fileno, mapping, [group], batch_no, year, landuse, number FROM grouping ORDER BY id');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "awaiting_fileno: {$row['awaiting_fileno']}\n";
        echo "mls_fileno: " . ($row['mls_fileno'] ?: 'NULL') . "\n";
        echo "mapping: {$row['mapping']}\n";
        echo "group: {$row['group']}\n";
        echo "batch_no: {$row['batch_no']}\n";
        echo "year: {$row['year']}, landuse: {$row['landuse']}, number: {$row['number']}\n";
        echo "---\n";
    }
    
    // Check mapping status
    echo "\n=== MAPPING STATUS ===\n";
    $stmt = $pdo->query('SELECT mapping, COUNT(*) as count FROM grouping GROUP BY mapping');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Mapping {$row['mapping']}: " . number_format($row['count']) . " files\n";
    }
    
    // Check land use distribution
    echo "\n=== LAND USE DISTRIBUTION ===\n";
    $stmt = $pdo->query('SELECT landuse, COUNT(*) as count FROM grouping GROUP BY landuse ORDER BY landuse');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['landuse']}: " . number_format($row['count']) . " files\n";
    }
    
    // Check group structure
    echo "\n=== GROUP STRUCTURE SAMPLE ===\n";
    $stmt = $pdo->query('SELECT TOP 10 [group], batch_no, COUNT(*) as files_in_group FROM grouping GROUP BY [group], batch_no ORDER BY [group]');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Group {$row['group']}, Batch {$row['batch_no']}: {$row['files_in_group']} files\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>