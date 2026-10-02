<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== CHECKING DOCUMENT UPLOADS IN RECENT APPLICATIONS ===\n\n";

try {
    $recentApps = DB::connection('sqlsrv')->select("
        SELECT TOP 5 
            ma.id,
            ma.np_fileno,
            ma.fileno,
            ma.owner_fullname,
            ma.documents,
            ma.created_at
        FROM mother_applications ma
        WHERE ma.created_at >= '2025-10-12'
        ORDER BY ma.created_at DESC
    ");
    
    foreach ($recentApps as $app) {
        echo "=== Application ID: {$app->id} ===\n";
        echo "NP File No: {$app->np_fileno}\n";
        echo "File No: {$app->fileno}\n";
        echo "Owner: {$app->owner_fullname}\n";
        echo "Created: {$app->created_at}\n";
        
        // Decode and display documents JSON
        if ($app->documents) {
            echo "Documents JSON:\n";
            $documents = json_decode($app->documents, true);
            if ($documents) {
                foreach ($documents as $docType => $docData) {
                    echo "  - {$docType}: ";
                    if (is_array($docData)) {
                        if (isset($docData['path'])) {
                            echo "Path: {$docData['path']}, Original: {$docData['original_name']}\n";
                        } else {
                            echo "Multiple files: " . count($docData) . " files\n";
                        }
                    } else {
                        echo "Simple value\n";
                    }
                }
            } else {
                echo "  Invalid JSON format\n";
            }
        } else {
            echo "❌ NO DOCUMENTS JSON\n";
        }
        
        // Check file indexing
        $fileIndexing = DB::connection('sqlsrv')->table('file_indexings')
            ->where('main_application_id', $app->id)
            ->first();
            
        if ($fileIndexing) {
            echo "✅ File Indexing ID: {$fileIndexing->id}\n";
            
            // Check scanning records
            $scanningRecords = DB::connection('sqlsrv')->table('scannings')
                ->where('file_indexing_id', $fileIndexing->id)
                ->get();
                
            if ($scanningRecords->count() > 0) {
                echo "✅ Scanning Records: {$scanningRecords->count()}\n";
                foreach ($scanningRecords as $scan) {
                    echo "    - {$scan->document_type}: {$scan->original_filename} -> {$scan->document_path}\n";
                }
            } else {
                echo "❌ NO SCANNING RECORDS\n";
            }
        } else {
            echo "❌ NO FILE INDEXING\n";
        }
        
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== CHECKING PROCESSEDDOCUMENTSFOREDMS METHOD CALLS ===\n\n";

// Check if the method is being called by looking at recent logs
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    
    // Look for EDMS processing logs from recent submissions
    if (strpos($logContent, 'Processing documents for EDMS') !== false) {
        echo "✅ Found EDMS processing logs\n";
        
        // Extract recent EDMS processing entries
        $lines = explode("\n", $logContent);
        $recentLines = array_slice($lines, -500); // Last 500 lines
        
        $edmsLines = array_filter($recentLines, function($line) {
            return strpos($line, 'Processing documents for EDMS') !== false ||
                   strpos($line, 'Document processed successfully for EDMS') !== false ||
                   strpos($line, 'EDMS document processing completed') !== false;
        });
        
        if (!empty($edmsLines)) {
            echo "Recent EDMS processing logs:\n";
            foreach ($edmsLines as $line) {
                echo "  " . trim($line) . "\n";
            }
        } else {
            echo "❌ No recent EDMS processing logs found\n";
        }
    } else {
        echo "❌ No EDMS processing logs found in laravel.log\n";
    }
} else {
    echo "❌ Laravel log file not found\n";
}

?>