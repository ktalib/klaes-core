<?php
// Simple database check without full Laravel bootstrap
$serverName = "(local)\SQLEXPRESS";
$connectionOptions = array(
    "Database" => "klaes_gis_edms",
    "Uid" => "",
    "PWD" => ""
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die("Connection failed: " . print_r(sqlsrv_errors(), true));
}

echo "Checking payment-related columns in mother_applications table:\n";
echo "===========================================================\n";

$sql = "SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'mother_applications'
        ORDER BY COLUMN_NAME";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    die("Query failed: " . print_r(sqlsrv_errors(), true));
}

$paymentColumns = [];
$allColumns = [];

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $columnName = $row['COLUMN_NAME'];
    $allColumns[] = $columnName;
    
    if (stripos($columnName, 'payment') !== false || 
        stripos($columnName, 'receipt') !== false || 
        stripos($columnName, 'fee') !== false) {
        $paymentColumns[] = $columnName;
    }
}

if (empty($paymentColumns)) {
    echo "No individual payment tracking columns found.\n";
    echo "Current system uses basic payment tracking.\n\n";
} else {
    echo "Payment-related columns found:\n";
    foreach ($paymentColumns as $col) {
        echo "- $col\n";
    }
    echo "\n";
}

echo "Checking for basic payment columns:\n";
echo "-----------------------------------\n";

$basicColumns = ['payment_date', 'receipt_number', 'Payment_Status', 'application_fee', 'processing_fee', 'site_plan_fee'];

foreach ($basicColumns as $col) {
    $found = false;
    foreach ($allColumns as $dbCol) {
        if (strtolower($dbCol) === strtolower($col)) {
            echo "✓ $col exists\n";
            $found = true;
            break;
        }
    }
    if (!$found) {
        echo "✗ $col does not exist\n";
    }
}

sqlsrv_close($conn);
?>