<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== DEBUG DOCUMENT UPLOAD ISSUE ===\n\n";

echo "1. Checking the most recent applications to see document status...\n";

$recentRecords = DB::connection('sqlsrv')->select("
    SELECT TOP 10 id, applicant_type, first_name, surname, documents, survey_plan, created_at
    FROM mother_applications 
    ORDER BY created_at DESC
");

foreach ($recentRecords as $record) {
    echo "Record ID: {$record->id} - {$record->first_name} {$record->surname}\n";
    echo "  Created: {$record->created_at}\n";
    echo "  Documents: " . ($record->documents ? 'HAS DATA' : 'NULL') . "\n";
    echo "  Survey Plan: " . ($record->survey_plan ? 'HAS DATA' : 'NULL') . "\n";
    
    if ($record->documents) {
        $documents = json_decode($record->documents, true);
        if ($documents) {
            echo "  Document types: " . implode(', ', array_keys($documents)) . "\n";
        }
    }
    echo "  ---\n";
}

echo "\n2. Checking recent log entries for document uploads...\n";

$logFile = 'storage/logs/laravel.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    
    $documentLogs = [];
    foreach (array_reverse($lines) as $line) {
        if (stripos($line, 'Document uploaded') !== false || 
            stripos($line, 'documents_uploaded') !== false ||
            stripos($line, 'document_count') !== false ||
            stripos($line, 'application_letter') !== false ||
            stripos($line, 'building_plan') !== false) {
            $documentLogs[] = $line;
            if (count($documentLogs) >= 10) break;
        }
    }
    
    if (!empty($documentLogs)) {
        echo "Recent document-related log entries:\n";
        foreach (array_reverse($documentLogs) as $line) {
            echo "  " . trim($line) . "\n";
        }
    } else {
        echo "No recent document upload log entries found.\n";
        echo "This suggests documents are not being processed by the controller.\n";
    }
} else {
    echo "Log file not found.\n";
}

echo "\n3. Checking file field names that might be received...\n";

// Check if we can see the request data from recent logs
$requestLogs = [];
foreach (array_reverse($lines) as $line) {
    if (stripos($line, 'All Request Data:') !== false || 
        stripos($line, 'Files:') !== false) {
        $requestLogs[] = $line;
        if (count($requestLogs) >= 5) break;
    }
}

if (!empty($requestLogs)) {
    echo "Recent request data logs:\n";
    foreach (array_reverse($requestLogs) as $line) {
        echo "  " . trim($line) . "\n";
    }
} else {
    echo "No recent request data logs found.\n";
}

echo "\n4. Checking if files are being saved to the documents directory...\n";

$documentsDir = storage_path('app/public/documents');
if (is_dir($documentsDir)) {
    $recentFiles = [];
    $files = glob($documentsDir . '/*');
    
    // Get files modified in the last hour
    $oneHourAgo = time() - 3600;
    foreach ($files as $file) {
        if (filemtime($file) > $oneHourAgo) {
            $recentFiles[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'time' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
    }
    
    if (!empty($recentFiles)) {
        echo "Recent files (last hour) in documents directory:\n";
        foreach ($recentFiles as $file) {
            echo "  - {$file['name']} ({$file['size']} bytes, {$file['time']})\n";
        }
    } else {
        echo "No files uploaded to documents directory in the last hour.\n";
        echo "This suggests the file upload processing is not working.\n";
    }
} else {
    echo "Documents directory doesn't exist!\n";
}

echo "\n=== DEBUG COMPLETE ===\n";