<?php
try {
    $pdo = new PDO('sqlsrv:server=VMI2583396;Database=klas', 'klas', 'YourStrongPassword123!');
    
    echo "Recent File Indexing Records:\n";
    echo "============================\n";
    
    $stmt = $pdo->query('SELECT TOP 10 id, main_application_id, file_number, file_title, created_at FROM file_indexings ORDER BY created_at DESC');
    
    while ($row = $stmt->fetch()) {
        echo "ID: {$row['id']}\n";
        echo "Application ID: {$row['main_application_id']}\n";
        echo "File Number: {$row['file_number']}\n";
        echo "File Title: {$row['file_title']}\n";
        echo "Created: {$row['created_at']}\n";
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>