<?php
try {
    $pdo = new PDO('sqlsrv:server=localhost;database=klas', 'klas', 'YourStrongPassword123!');
    
    echo "=== INVESTIGATING DATA STRUCTURE ===\n\n";
    
    // Check for duplicates in file numbers
    echo "Checking for duplicate awaiting_fileno patterns...\n";
    $stmt = $pdo->query("
        SELECT awaiting_fileno, COUNT(*) as count 
        FROM grouping 
        WHERE number IN (1, 100, 101) 
        GROUP BY awaiting_fileno 
        HAVING COUNT(*) > 1
        ORDER BY awaiting_fileno
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Duplicate: {$row['awaiting_fileno']} appears {$row['count']} times\n";
    }
    
    echo "\nChecking unique awaiting_fileno patterns for number=1...\n";
    $stmt = $pdo->query("
        SELECT DISTINCT awaiting_fileno, landuse, year, number 
        FROM grouping 
        WHERE number = 1 
        ORDER BY awaiting_fileno
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "File: {$row['awaiting_fileno']}, Land Use: {$row['landuse']}, Year: {$row['year']}\n";
    }
    
    echo "\nChecking the correct RESIDENTIAL 1981 range...\n";
    $stmt = $pdo->query("
        SELECT TOP 10 awaiting_fileno, landuse, year, number,
               CEILING(CAST(number AS FLOAT) / 100.0) as group_number,
               ((number - 1) % 100) + 1 as position_in_group
        FROM grouping 
        WHERE landuse = 'RESIDENTIAL' 
            AND year = 1981
            AND awaiting_fileno LIKE 'RES-1981-%'
        ORDER BY number
    ");
    
    echo "\nCorrect RESIDENTIAL-1981 files:\n";
    echo "File Number\t\tLand Use\tYear\tNumber\tGroup\tPosition\n";
    echo "-------------\t\t--------\t----\t------\t-----\t--------\n";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%s\t%s\t%d\t%d\t%d\t%d\n", 
            $row['awaiting_fileno'], 
            $row['landuse'],
            $row['year'],
            $row['number'], 
            $row['group_number'], 
            $row['position_in_group']
        );
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>