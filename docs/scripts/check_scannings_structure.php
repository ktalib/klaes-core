<?php
try {
    $pdo = new PDO('sqlsrv:server=VMI2583396;Database=klas', 'klas', 'YourStrongPassword123!');
    $stmt = $pdo->query('SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = \'scannings\' ORDER BY ORDINAL_POSITION');
    
    echo "Scannings table structure:\n";
    echo "-------------------------\n";
    
    while ($row = $stmt->fetch()) {
        echo $row['COLUMN_NAME'] . ' - ' . $row['DATA_TYPE'];
        if ($row['CHARACTER_MAXIMUM_LENGTH']) {
            echo '(' . $row['CHARACTER_MAXIMUM_LENGTH'] . ')';
        }
        echo ' - ' . ($row['IS_NULLABLE'] == 'YES' ? 'NULL' : 'NOT NULL') . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>