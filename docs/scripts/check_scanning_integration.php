<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== RECENT SCANNING TABLE ENTRIES ===\n\n";

try {
    $recentScans = DB::connection('sqlsrv')->select("
        SELECT TOP 10 
            s.id,
            s.file_indexing_id,
            s.document_path,
            s.original_filename,
            s.document_type,
            s.status,
            s.created_at,
            fi.file_number,
            fi.file_title
        FROM scannings s
        LEFT JOIN file_indexings fi ON s.file_indexing_id = fi.id
        ORDER BY s.created_at DESC
    ");
    
    if (empty($recentScans)) {
        echo "❌ NO SCANNING RECORDS FOUND\n";
    } else {
        echo "✅ Found " . count($recentScans) . " recent scanning records:\n\n";
        foreach ($recentScans as $scan) {
            echo "ID: {$scan->id}\n";
            echo "File Indexing ID: {$scan->file_indexing_id}\n";
            echo "File Number: {$scan->file_number}\n";
            echo "Document Path: {$scan->document_path}\n";
            echo "Original Filename: {$scan->original_filename}\n";
            echo "Document Type: {$scan->document_type}\n";
            echo "Status: {$scan->status}\n";
            echo "Created: {$scan->created_at}\n";
            echo "---\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error checking scanning table: " . $e->getMessage() . "\n";
}

echo "\n=== RECENT FILE INDEXING ENTRIES ===\n\n";

try {
    $recentIndexes = DB::connection('sqlsrv')->select("
        SELECT TOP 10 
            fi.id,
            fi.main_application_id,
            fi.file_number,
            fi.file_title,
            fi.created_at,
            COUNT(s.id) as scanning_count
        FROM file_indexings fi
        LEFT JOIN scannings s ON fi.id = s.file_indexing_id
        GROUP BY fi.id, fi.main_application_id, fi.file_number, fi.file_title, fi.created_at
        ORDER BY fi.created_at DESC
    ");
    
    foreach ($recentIndexes as $index) {
        echo "File Indexing ID: {$index->id}\n";
        echo "Application ID: {$index->main_application_id}\n";
        echo "File Number: {$index->file_number}\n";
        echo "File Title: {$index->file_title}\n";
        echo "Scanning Records Count: {$index->scanning_count}\n";
        echo "Created: {$index->created_at}\n";
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error checking file indexing: " . $e->getMessage() . "\n";
}

echo "\n=== CHECKING EDMS FOLDER STRUCTURE ===\n\n";

// Check if EDMS directories exist
$publicPath = storage_path('app/public');
$edmsBasePath = $publicPath . '/EDMS/SCAN_UPLOAD';

echo "Public Storage Path: {$publicPath}\n";
echo "EDMS Base Path: {$edmsBasePath}\n\n";

if (is_dir($edmsBasePath)) {
    echo "✅ EDMS/SCAN_UPLOAD directory exists\n";
    
    // List subdirectories
    $directories = glob($edmsBasePath . '/*', GLOB_ONLYDIR);
    if (empty($directories)) {
        echo "❌ No file number subdirectories found\n";
    } else {
        echo "✅ Found " . count($directories) . " file number directories:\n";
        foreach ($directories as $dir) {
            $dirName = basename($dir);
            $fileCount = count(glob($dir . '/*'));
            echo "  - {$dirName} ({$fileCount} files)\n";
        }
    }
} else {
    echo "❌ EDMS/SCAN_UPLOAD directory does not exist\n";
    echo "Expected path: {$edmsBasePath}\n";
}

echo "\n=== CHECKING RECENT APPLICATION SUBMISSIONS ===\n\n";

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
        ORDER BY ma.created_at DESC
    ");
    
    foreach ($recentApps as $app) {
        echo "Application ID: {$app->id}\n";
        echo "NP File No: {$app->np_fileno}\n";
        echo "File No: {$app->fileno}\n";
        echo "Owner: {$app->owner_fullname}\n";
        echo "Documents JSON: " . ($app->documents ? 'YES' : 'NO') . "\n";
        echo "Created: {$app->created_at}\n";
        
        // Check if this application has file indexing
        $hasFileIndexing = DB::connection('sqlsrv')->table('file_indexings')
            ->where('main_application_id', $app->id)
            ->exists();
        echo "Has File Indexing: " . ($hasFileIndexing ? 'YES' : 'NO') . "\n";
        
        if ($hasFileIndexing) {
            $scanningCount = DB::connection('sqlsrv')->table('scannings s')
                ->join('file_indexings fi', 's.file_indexing_id', '=', 'fi.id')
                ->where('fi.main_application_id', $app->id)
                ->count();
            echo "Scanning Records: {$scanningCount}\n";
        }
        
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error checking applications: " . $e->getMessage() . "\n";
}

?>