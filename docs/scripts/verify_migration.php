<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Verifying st_file_numbers migration ===\n\n";

try {
    // Query the new st_file_numbers record
    $stRecord = DB::connection('sqlsrv')->table('st_file_numbers')
        ->where('mother_application_id', 2)
        ->first();
    
    if ($stRecord) {
        echo "✅ Found st_file_numbers record:\n";
        echo "ID: {$stRecord->id}\n";
        echo "NP FileNo: {$stRecord->np_fileno}\n";
        echo "FileNo: {$stRecord->fileno}\n";
        echo "MLS FileNo: {$stRecord->mls_fileno}\n";
        echo "Land Use: {$stRecord->land_use} ({$stRecord->land_use_code})\n";
        echo "Serial No: {$stRecord->serial_no}\n";
        echo "Year: {$stRecord->year}\n";
        echo "Type: {$stRecord->file_no_type}\n";
        echo "Status: {$stRecord->status}\n";
        echo "Tracking ID: {$stRecord->tra}\n";
        echo "Mother Application ID: {$stRecord->mother_application_id}\n";
        echo "Applicant Type: {$stRecord->applicant_type}\n";
        echo "Corporate Name: {$stRecord->corporate_name}\n";
        echo "RC Number: {$stRecord->rc_number}\n";
        echo "Created At: {$stRecord->created_at}\n";
        echo "Used At: {$stRecord->used_at}\n\n";
        
        // Check if we need to update mother_applications with the st_file_numbers ID
        $motherApp = DB::connection('sqlsrv')->table('mother_applications')->where('id', 2)->first();
        
        echo "=== Related mother_applications record ===\n";
        echo "ID: {$motherApp->id}\n";
        echo "NP FileNo: {$motherApp->np_fileno}\n";
        echo "FileNo: " . ($motherApp->fileno ?? 'NULL') . "\n";
        echo "Applicant Type: {$motherApp->applicant_type}\n";
        echo "Corporate Name: {$motherApp->corporate_name}\n";
        echo "Land Use: {$motherApp->land_use}\n\n";
        
        // Test the API to see if it works with existing data
        echo "=== Testing file number lookup ===\n";
        
        $detailsTest = DB::connection('sqlsrv')->table('st_file_numbers')
            ->where('fileno', $stRecord->fileno)
            ->orWhere('np_fileno', $stRecord->np_fileno)
            ->first();
            
        if ($detailsTest) {
            echo "✅ File number lookup works!\n";
            echo "Found by fileno: {$detailsTest->fileno}\n";
            echo "Found by np_fileno: {$detailsTest->np_fileno}\n";
        } else {
            echo "❌ File number lookup failed!\n";
        }
        
        // Check for any potential conflicts
        echo "\n=== Checking for conflicts ===\n";
        
        $duplicateNP = DB::connection('sqlsrv')->table('st_file_numbers')
            ->where('np_fileno', $stRecord->np_fileno)
            ->count();
            
        $duplicateFile = DB::connection('sqlsrv')->table('st_file_numbers')
            ->where('fileno', $stRecord->fileno)
            ->count();
        
        echo "Records with same NP FileNo: {$duplicateNP}\n";
        echo "Records with same FileNo: {$duplicateFile}\n";
        
        if ($duplicateNP > 1 || $duplicateFile > 1) {
            echo "⚠️  Warning: Duplicate file numbers detected!\n";
        } else {
            echo "✅ No conflicts detected.\n";
        }
        
    } else {
        echo "❌ No st_file_numbers record found for mother_application_id = 2\n";
    }
    
    // Show summary of all st_file_numbers records
    echo "\n=== All st_file_numbers records ===\n";
    $allRecords = DB::connection('sqlsrv')->table('st_file_numbers')
        ->orderBy('id', 'desc')
        ->get();
    
    foreach ($allRecords as $record) {
        echo "ID: {$record->id} | {$record->np_fileno} | {$record->file_no_type} | {$record->status} | TRA: {$record->tra}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}