<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    echo "Checking detailed file upload data for recent records...\n\n";
    
    // Get the most recent record with file data
    $recentRecord = DB::connection('sqlsrv')->select("
        SELECT TOP 1 id, applicant_type, first_name, surname, passport, id_document, 
               multiple_owners_passport, created_at, updated_at
        FROM mother_applications 
        WHERE passport IS NOT NULL OR id_document IS NOT NULL
        ORDER BY created_at DESC
    ");
    
    if (!empty($recentRecord)) {
        $record = $recentRecord[0];
        echo "=== Most Recent Record with File Data ===\n";
        echo "ID: {$record->id}\n";
        echo "Name: {$record->first_name} {$record->surname}\n";
        echo "Applicant Type: {$record->applicant_type}\n";
        echo "Passport Path: " . ($record->passport ?? 'NULL') . "\n";
        echo "ID Document Path: " . ($record->id_document ?? 'NULL') . "\n";
        echo "Multiple Owners Passport: " . ($record->multiple_owners_passport ?? 'NULL') . "\n";
        echo "Created: {$record->created_at}\n";
        echo "Updated: {$record->updated_at}\n";
        
        // Check if the files actually exist
        echo "\n=== File Existence Check ===\n";
        
        if ($record->passport) {
            $passportPath = "storage/app/public/" . $record->passport;
            echo "Passport file: " . ($record->passport) . "\n";
            echo "  Full path: {$passportPath}\n";
            echo "  Exists: " . (file_exists($passportPath) ? "✅ YES" : "❌ NO") . "\n";
            if (file_exists($passportPath)) {
                echo "  Size: " . number_format(filesize($passportPath)) . " bytes\n";
            }
        }
        
        if ($record->id_document) {
            $idDocPath = "storage/app/public/" . $record->id_document;
            echo "ID Document file: " . ($record->id_document) . "\n";
            echo "  Full path: {$idDocPath}\n";
            echo "  Exists: " . (file_exists($idDocPath) ? "✅ YES" : "❌ NO") . "\n";
            if (file_exists($idDocPath)) {
                echo "  Size: " . number_format(filesize($idDocPath)) . " bytes\n";
            }
        }
        
        // Check if both fields have the same value (potential bug)
        if ($record->passport && $record->id_document && $record->passport === $record->id_document) {
            echo "\n⚠️  WARNING: passport and id_document fields have the same value!\n";
            echo "This indicates a potential field mapping issue in the form submission.\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Checking form field names in recent submissions...\n\n";
    
    // Check logs for form submission details if available
    $logFile = 'storage/logs/laravel.log';
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        
        // Look for recent file upload entries
        $lines = explode("\n", $logContent);
        $relevantLines = [];
        
        foreach (array_reverse($lines) as $line) {
            if (stripos($line, 'passport') !== false || 
                stripos($line, 'id_document') !== false || 
                stripos($line, 'file received') !== false ||
                stripos($line, 'uploaded') !== false) {
                $relevantLines[] = $line;
                if (count($relevantLines) >= 10) break;
            }
        }
        
        if (!empty($relevantLines)) {
            echo "Recent log entries related to file uploads:\n";
            foreach (array_reverse($relevantLines) as $line) {
                echo "  " . trim($line) . "\n";
            }
        } else {
            echo "No recent file upload log entries found.\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}