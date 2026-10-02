<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CHECKING DOCUMENT COLUMNS ===\n\n";

$columns = DB::connection('sqlsrv')->select("
    SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'mother_applications' 
    AND (COLUMN_NAME LIKE '%document%' OR COLUMN_NAME = 'survey_plan') 
    ORDER BY COLUMN_NAME
");

echo "Document-related columns in mother_applications:\n";
foreach ($columns as $column) {
    echo "✅ {$column->COLUMN_NAME} ({$column->DATA_TYPE}";
    if ($column->CHARACTER_MAXIMUM_LENGTH && $column->CHARACTER_MAXIMUM_LENGTH != -1) {
        echo ", max length: {$column->CHARACTER_MAXIMUM_LENGTH}";
    } elseif ($column->CHARACTER_MAXIMUM_LENGTH == -1) {
        echo ", max length: unlimited";
    }
    echo ", nullable: {$column->IS_NULLABLE})\n";
}

echo "\nNow checking recent records with documents...\n";

$recentRecords = DB::connection('sqlsrv')->select("
    SELECT TOP 5 id, applicant_type, first_name, surname, documents, survey_plan, created_at
    FROM mother_applications 
    WHERE documents IS NOT NULL OR survey_plan IS NOT NULL
    ORDER BY created_at DESC
");

foreach ($recentRecords as $record) {
    echo "Record ID: {$record->id}\n";
    echo "  Name: {$record->first_name} {$record->surname}\n";
    echo "  Survey Plan: " . ($record->survey_plan ?? 'NULL') . "\n";
    echo "  Created: {$record->created_at}\n";
    
    if ($record->documents) {
        echo "  Documents JSON:\n";
        $documents = json_decode($record->documents, true);
        if ($documents) {
            foreach ($documents as $docType => $docData) {
                if (is_array($docData) && isset($docData['path'])) {
                    echo "    - {$docType}: {$docData['path']} ({$docData['original_name']})\n";
                } else {
                    echo "    - {$docType}: " . json_encode($docData) . "\n";
                }
            }
        } else {
            echo "    - (Invalid JSON or empty)\n";
        }
    } else {
        echo "  Documents: NULL\n";
    }
    echo "  ---\n";
}