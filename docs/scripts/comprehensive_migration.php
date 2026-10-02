<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Comprehensive Migration of Existing Applications ===\n\n";

try {
    // First, clear all existing st_file_numbers records to start fresh
    echo "Clearing existing st_file_numbers records...\n";
    DB::connection('sqlsrv')->table('st_file_numbers')->delete();
    echo "✅ Cleared all records\n\n";
    
    // 1. Migrate mother_applications (PRIMARY applications)
    echo "=== 1. Migrating mother_applications (PRIMARY) ===\n";
    $motherApps = DB::connection('sqlsrv')->select('SELECT * FROM mother_applications ORDER BY id');
    
    foreach ($motherApps as $app) {
        echo "Processing mother_application ID: {$app->id}\n";
        
        // Generate tracking ID
        $trackingId = 'TRK-' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8)) . '-' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 5));
        
        // Map land use to code
        $landUseCode = match(strtoupper(trim($app->land_use ?? 'RESIDENTIAL'))) {
            'COMMERCIAL', 'COMMERCIAL USE' => 'COM',
            'INDUSTRIAL', 'INDUSTRIAL USE', 'INDUSTRY' => 'IND',
            'RESIDENTIAL', 'RESIDENTIAL USE' => 'RES',
            'MIXED', 'MIXED USE' => 'MIXED',
            default => 'RES'
        };
        
        // Extract serial number and year from np_fileno
        $serialNo = 1;
        $year = date('Y');
        if ($app->np_fileno) {
            $parts = explode('-', $app->np_fileno);
            if (count($parts) >= 4) {
                $serialNo = (int)$parts[3];
                $year = (int)$parts[2];
            }
        }
        
        $insertData = [
            'np_fileno' => $app->np_fileno,
            'fileno' => $app->fileno ?? $app->np_fileno, // Use fileno if exists, otherwise np_fileno
            'mls_fileno' => $app->np_fileno, // MLS same as np_fileno for PRIMARY
            'land_use' => ucfirst(strtolower($app->land_use ?? 'Residential')),
            'land_use_code' => $landUseCode,
            'serial_no' => $serialNo,
            'unit_sequence' => null, // NULL for PRIMARY
            'year' => $year,
            'file_no_type' => 'PRIMARY',
            'parent_id' => null,
            'mother_application_id' => $app->id,
            'subapplication_id' => null,
            'status' => 'USED', // Existing applications are USED
            'reserved_at' => $app->created_at ?? now(),
            'expires_at' => null,
            'used_at' => $app->created_at ?? now(),
            'tra' => $trackingId,
            'applicant_type' => ucfirst($app->applicant_type ?? 'Individual'),
            'applicant_title' => $app->applicant_title,
            'first_name' => $app->first_name,
            'surname' => $app->surname,
            'corporate_name' => $app->corporate_name,
            'rc_number' => $app->rc_number,
            'multiple_owners_names' => $app->multiple_owners_names ? json_encode(explode(',', $app->multiple_owners_names)) : null,
            'created_by' => $app->created_by,
            'created_at' => $app->created_at ?? now(),
            'updated_at' => now()
        ];
        
        $insertedId = DB::connection('sqlsrv')->table('st_file_numbers')->insertGetId($insertData);
        echo "✅ Inserted PRIMARY record ID: {$insertedId} | {$app->np_fileno} | TRA: {$trackingId}\n";
    }
    
    echo "\n=== 2. Migrating subapplications (SUA) ===\n";
    $suaApps = DB::connection('sqlsrv')->select("SELECT * FROM subapplications WHERE unit_type = 'SUA' AND is_sua_unit = 1 ORDER BY id");
    
    foreach ($suaApps as $sua) {
        echo "Processing SUA subapplication ID: {$sua->id}\n";
        
        // Generate tracking ID
        $trackingId = 'TRK-' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8)) . '-' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 5));
        
        // Extract land use from np_fileno (e.g., ST-COM-2025-1 -> COM -> Commercial)
        $landUseCode = 'COM'; // Default
        $landUse = 'Commercial';
        
        if ($sua->np_fileno) {
            $parts = explode('-', $sua->np_fileno);
            if (count($parts) >= 2) {
                $landUseCode = $parts[1];
                $landUse = match($landUseCode) {
                    'COM' => 'Commercial',
                    'RES' => 'Residential',
                    'IND' => 'Industrial',
                    'MIXED' => 'Mixed',
                    default => 'Commercial'
                };
            }
        }
        
        // Extract serial number and year from np_fileno
        $serialNo = 1;
        $year = date('Y');
        if ($sua->np_fileno) {
            $parts = explode('-', $sua->np_fileno);
            if (count($parts) >= 4) {
                $serialNo = (int)$parts[3];
                $year = (int)$parts[2];
            }
        }
        
        // Extract unit sequence from fileno (e.g., ST-COM-2025-1-001 -> 001 -> 1)
        $unitSequence = 1;
        if ($sua->fileno) {
            $parts = explode('-', $sua->fileno);
            if (count($parts) >= 5) {
                $unitSequence = (int)$parts[4];
            }
        }
        
        $insertData = [
            'np_fileno' => $sua->np_fileno,
            'fileno' => $sua->fileno,
            'mls_fileno' => $sua->mls_fileno ?? $sua->np_fileno,
            'land_use' => $landUse,
            'land_use_code' => $landUseCode,
            'serial_no' => $serialNo,
            'unit_sequence' => $unitSequence,
            'year' => $year,
            'file_no_type' => 'SUA',
            'parent_id' => null, // SUA has no parent - it's standalone
            'mother_application_id' => null, // SUA doesn't belong to mother_applications
            'subapplication_id' => $sua->id,
            'status' => 'USED', // Existing applications are USED
            'reserved_at' => $sua->created_at ?? now(),
            'expires_at' => null,
            'used_at' => $sua->created_at ?? now(),
            'tra' => $trackingId,
            'applicant_type' => 'Individual', // Default for SUA
            'applicant_title' => null,
            'first_name' => null,
            'surname' => null,
            'corporate_name' => null,
            'rc_number' => null,
            'multiple_owners_names' => null,
            'created_by' => $sua->created_by ?? null,
            'created_at' => $sua->created_at ?? now(),
            'updated_at' => now()
        ];
        
        $insertedId = DB::connection('sqlsrv')->table('st_file_numbers')->insertGetId($insertData);
        echo "✅ Inserted SUA record ID: {$insertedId} | {$sua->np_fileno} | {$sua->fileno} | TRA: {$trackingId}\n";
    }
    
    echo "\n=== 3. Migration Summary ===\n";
    
    // Show final results
    $allRecords = DB::connection('sqlsrv')->table('st_file_numbers')
        ->orderBy('file_no_type')
        ->orderBy('land_use_code')
        ->orderBy('serial_no')
        ->get();
    
    $primaryCount = 0;
    $suaCount = 0;
    $puaCount = 0;
    
    echo "\nMigrated records:\n";
    foreach ($allRecords as $record) {
        switch ($record->file_no_type) {
            case 'PRIMARY':
                $primaryCount++;
                break;
            case 'SUA':
                $suaCount++;
                break;
            case 'PUA':
                $puaCount++;
                break;
        }
        
        $parentInfo = $record->parent_id ? " (Parent: {$record->parent_id})" : "";
        $motherInfo = $record->mother_application_id ? " (Mother: {$record->mother_application_id})" : "";
        $subInfo = $record->subapplication_id ? " (Sub: {$record->subapplication_id})" : "";
        
        echo "ID: {$record->id} | {$record->file_no_type} | {$record->np_fileno} | {$record->fileno} | {$record->status}{$parentInfo}{$motherInfo}{$subInfo}\n";
    }
    
    echo "\n=== Statistics ===\n";
    echo "PRIMARY applications: {$primaryCount}\n";
    echo "SUA applications: {$suaCount}\n";
    echo "PUA applications: {$puaCount}\n";
    echo "Total migrated: " . ($primaryCount + $suaCount + $puaCount) . "\n";
    
    echo "\n=== Next File Numbers Preview ===\n";
    
    // Check what the next file numbers would be
    $nextRes = DB::connection('sqlsrv')->table('st_file_numbers')
        ->where('land_use_code', 'RES')
        ->max('serial_no') + 1;
    
    $nextCom = DB::connection('sqlsrv')->table('st_file_numbers')
        ->where('land_use_code', 'COM')
        ->max('serial_no') + 1;
    
    $nextInd = DB::connection('sqlsrv')->table('st_file_numbers')
        ->where('land_use_code', 'IND')
        ->max('serial_no') + 1;
    
    echo "Next Residential: ST-RES-2025-{$nextRes}\n";
    echo "Next Commercial: ST-COM-2025-{$nextCom}\n";
    echo "Next Industrial: ST-IND-2025-{$nextInd}\n";
    
    echo "\n🎉 Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}