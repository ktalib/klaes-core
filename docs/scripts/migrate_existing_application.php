<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Migrating existing mother_applications to st_file_numbers table ===\n\n";

try {
    // Get the existing application
    $existingApp = DB::connection('sqlsrv')->select('SELECT * FROM mother_applications WHERE id = 2')[0] ?? null;
    
    if (!$existingApp) {
        echo "No application found with ID 2.\n";
        exit;
    }
    
    echo "Found application:\n";
    echo "ID: {$existingApp->id}\n";
    echo "NP File No: {$existingApp->np_fileno}\n";
    echo "File No: " . ($existingApp->fileno ?? 'NULL') . "\n";
    echo "Applicant Type: {$existingApp->applicant_type}\n";
    echo "Corporate Name: {$existingApp->corporate_name}\n";
    echo "Land Use: {$existingApp->land_use}\n\n";
    
    // Generate tracking ID
    $trackingId = 'TRK-' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8)) . '-' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 5));
    
    // Map land use to code
    $landUseCode = match(strtoupper(trim($existingApp->land_use ?? 'RESIDENTIAL'))) {
        'COMMERCIAL', 'COMMERCIAL USE' => 'COM',
        'INDUSTRIAL', 'INDUSTRIAL USE', 'INDUSTRY' => 'IND',
        'RESIDENTIAL', 'RESIDENTIAL USE' => 'RES',
        'MIXED', 'MIXED USE' => 'MIXED',
        default => 'RES'
    };
    
    // Extract serial number from np_fileno (e.g., ST-RES-2025-1 -> 1)
    $serialNo = 1;
    if ($existingApp->np_fileno) {
        $parts = explode('-', $existingApp->np_fileno);
        if (count($parts) >= 4) {
            $serialNo = (int)$parts[3];
        }
    }
    
    // Extract year from np_fileno
    $year = date('Y');
    if ($existingApp->np_fileno) {
        $parts = explode('-', $existingApp->np_fileno);
        if (count($parts) >= 3) {
            $year = (int)$parts[2];
        }
    }
    
    // Determine file number type (assume PRIMARY for now)
    $fileNoType = 'PRIMARY';
    
    // Prepare data for insertion
    $insertData = [
        'np_fileno' => $existingApp->np_fileno,
        'fileno' => $existingApp->fileno ?? $existingApp->np_fileno, // Use np_fileno if fileno is null
        'mls_fileno' => $existingApp->np_fileno, // MLS same as primary for PRIMARY type
        'land_use' => ucfirst(strtolower($existingApp->land_use ?? 'Residential')),
        'land_use_code' => $landUseCode,
        'serial_no' => $serialNo,
        'unit_sequence' => null, // NULL for PRIMARY applications
        'year' => $year,
        'file_no_type' => $fileNoType,
        'parent_id' => null,
        'mother_application_id' => $existingApp->id,
        'subapplication_id' => null,
        'status' => 'USED', // Since this is an existing application
        'reserved_at' => $existingApp->created_at ?? now(),
        'expires_at' => null, // No expiry for used applications
        'used_at' => $existingApp->created_at ?? now(),
        'tra' => $trackingId,
        'applicant_type' => ucfirst($existingApp->applicant_type ?? 'Individual'),
        'applicant_title' => $existingApp->applicant_title,
        'first_name' => $existingApp->first_name,
        'surname' => $existingApp->surname,
        'corporate_name' => $existingApp->corporate_name,
        'rc_number' => $existingApp->rc_number,
        'multiple_owners_names' => $existingApp->multiple_owners_names ? json_encode(explode(',', $existingApp->multiple_owners_names)) : null,
        'created_by' => $existingApp->created_by,
        'created_at' => $existingApp->created_at ?? now(),
        'updated_at' => now()
    ];
    
    echo "Prepared data for insertion:\n";
    foreach ($insertData as $key => $value) {
        echo "  {$key}: " . ($value ?? 'NULL') . "\n";
    }
    echo "\n";
    
    // Check if record already exists
    $existingRecord = DB::connection('sqlsrv')->table('st_file_numbers')
        ->where('mother_application_id', $existingApp->id)
        ->first();
    
    if ($existingRecord) {
        echo "Record already exists in st_file_numbers table with ID: {$existingRecord->id}\n";
        echo "Skipping insertion.\n";
    } else {
        // Insert the record
        $insertedId = DB::connection('sqlsrv')->table('st_file_numbers')->insertGetId($insertData);
        
        echo "Successfully inserted record into st_file_numbers table!\n";
        echo "New record ID: {$insertedId}\n";
        echo "Tracking ID: {$trackingId}\n";
        
        // Verify the insertion
        $verifyRecord = DB::connection('sqlsrv')->table('st_file_numbers')->where('id', $insertedId)->first();
        
        echo "\nVerification:\n";
        echo "ID: {$verifyRecord->id}\n";
        echo "NP FileNo: {$verifyRecord->np_fileno}\n";
        echo "FileNo: {$verifyRecord->fileno}\n";
        echo "Type: {$verifyRecord->file_no_type}\n";
        echo "Status: {$verifyRecord->status}\n";
        echo "Tracking ID: {$verifyRecord->tra}\n";
        echo "Mother Application ID: {$verifyRecord->mother_application_id}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}