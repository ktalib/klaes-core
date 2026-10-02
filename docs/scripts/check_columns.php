<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    echo "Checking for passport and id_document columns in mother_applications table...\n\n";
    
    $columns = DB::connection('sqlsrv')->select("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'mother_applications' 
        AND (COLUMN_NAME LIKE '%passport%' OR COLUMN_NAME LIKE '%id_document%') 
        ORDER BY COLUMN_NAME
    ");
    
    if (empty($columns)) {
        echo "❌ No passport or id_document columns found in mother_applications table!\n";
        echo "Checking all columns containing 'passport' or 'document'...\n\n";
        
        $allColumns = DB::connection('sqlsrv')->select("
            SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = 'mother_applications' 
            AND (COLUMN_NAME LIKE '%passport%' OR COLUMN_NAME LIKE '%document%') 
            ORDER BY COLUMN_NAME
        ");
        
        if (empty($allColumns)) {
            echo "❌ No passport or document related columns found at all!\n";
        } else {
            foreach ($allColumns as $column) {
                echo "✅ Found: {$column->COLUMN_NAME} ({$column->DATA_TYPE}";
                if ($column->CHARACTER_MAXIMUM_LENGTH) {
                    echo ", max length: {$column->CHARACTER_MAXIMUM_LENGTH}";
                }
                echo ", nullable: {$column->IS_NULLABLE})\n";
            }
        }
    } else {
        foreach ($columns as $column) {
            echo "✅ Found: {$column->COLUMN_NAME} ({$column->DATA_TYPE}";
            if ($column->CHARACTER_MAXIMUM_LENGTH) {
                echo ", max length: {$column->CHARACTER_MAXIMUM_LENGTH}";
            }
            echo ", nullable: {$column->IS_NULLABLE})\n";
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "Now checking recent insertions with passport/id_document data...\n\n";
    
    $recentData = DB::connection('sqlsrv')->select("
        SELECT TOP 5 id, applicant_type, passport, id_document, created_at
        FROM mother_applications 
        WHERE passport IS NOT NULL OR id_document IS NOT NULL
        ORDER BY created_at DESC
    ");
    
    if (empty($recentData)) {
        echo "❌ No records found with passport or id_document data!\n";
    } else {
        foreach ($recentData as $record) {
            echo "Record ID: {$record->id}\n";
            echo "  Applicant Type: {$record->applicant_type}\n";
            echo "  Passport: " . ($record->passport ?? 'NULL') . "\n";
            echo "  ID Document: " . ($record->id_document ?? 'NULL') . "\n";
            echo "  Created: {$record->created_at}\n";
            echo "  ---\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}