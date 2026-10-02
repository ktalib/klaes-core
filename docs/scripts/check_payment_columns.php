<?php
require 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Http\Kernel')->bootstrap();

try {
    $columns = DB::connection('sqlsrv')->select("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'mother_applications'
        ORDER BY COLUMN_NAME
    ");
    
    echo "Payment-related columns in mother_applications table:\n";
    echo "=================================================\n";
    
    $paymentColumns = [];
    foreach($columns as $col) {
        $columnName = $col->COLUMN_NAME;
        if(stripos($columnName, 'payment') !== false || 
           stripos($columnName, 'receipt') !== false || 
           stripos($columnName, 'fee') !== false) {
            $paymentColumns[] = $columnName;
        }
    }
    
    if(empty($paymentColumns)) {
        echo "No individual payment tracking columns found.\n";
        echo "Current system uses single payment_date and receipt_number columns.\n";
    } else {
        foreach($paymentColumns as $col) {
            echo "- $col\n";
        }
    }
    
    echo "\nChecking existing payment columns:\n";
    echo "--------------------------------\n";
    
    $basicPaymentColumns = ['payment_date', 'receipt_number', 'Payment_Status'];
    foreach($basicPaymentColumns as $col) {
        $exists = false;
        foreach($columns as $dbCol) {
            if(strtolower($dbCol->COLUMN_NAME) === strtolower($col)) {
                echo "✓ $col exists\n";
                $exists = true;
                break;
            }
        }
        if(!$exists) {
            echo "✗ $col does not exist\n";
        }
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}