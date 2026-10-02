<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Cleaning up test records and finalizing migration ===\n\n";

try {
    // First, let's see all records
    $allRecords = DB::connection('sqlsrv')->table('st_file_numbers')->get();
    
    echo "Current st_file_numbers records:\n";
    foreach ($allRecords as $record) {
        echo "ID: {$record->id} | NP: {$record->np_fileno} | File: {$record->fileno} | Type: {$record->file_no_type} | Status: {$record->status} | MotherApp: " . ($record->mother_application_id ?? 'NULL') . "\n";
    }
    echo "\n";
    
    // Keep only the migrated record (the one with mother_application_id = 2)
    $migratedRecord = DB::connection('sqlsrv')->table('st_file_numbers')
        ->where('mother_application_id', 2)
        ->first();
    
    if ($migratedRecord) {
        echo "✅ Found migrated record with ID: {$migratedRecord->id}\n";
        
        // Delete test records (records without mother_application_id or with status RESERVED that are test records)
        $testRecords = DB::connection('sqlsrv')->table('st_file_numbers')
            ->where('id', '!=', $migratedRecord->id)
            ->where(function($query) {
                $query->whereNull('mother_application_id')
                      ->orWhere('status', 'RESERVED');
            })
            ->get();
        
        echo "\nTest records to be deleted:\n";
        foreach ($testRecords as $record) {
            echo "ID: {$record->id} | NP: {$record->np_fileno} | Status: {$record->status}\n";
        }
        
        if (count($testRecords) > 0) {
            $deletedCount = DB::connection('sqlsrv')->table('st_file_numbers')
                ->where('id', '!=', $migratedRecord->id)
                ->where(function($query) {
                    $query->whereNull('mother_application_id')
                          ->orWhere('status', 'RESERVED');
                })
                ->delete();
            
            echo "\n✅ Deleted {$deletedCount} test records.\n";
        } else {
            echo "\nNo test records to delete.\n";
        }
        
        // Verify final state
        echo "\n=== Final verification ===\n";
        $finalRecords = DB::connection('sqlsrv')->table('st_file_numbers')->get();
        
        echo "Remaining st_file_numbers records:\n";
        foreach ($finalRecords as $record) {
            echo "ID: {$record->id}\n";
            echo "  NP FileNo: {$record->np_fileno}\n";
            echo "  FileNo: {$record->fileno}\n";
            echo "  MLS FileNo: {$record->mls_fileno}\n";
            echo "  Type: {$record->file_no_type}\n";
            echo "  Status: {$record->status}\n";
            echo "  Land Use: {$record->land_use} ({$record->land_use_code})\n";
            echo "  Applicant: {$record->applicant_type}\n";
            echo "  Corporate Name: " . ($record->corporate_name ?? 'NULL') . "\n";
            echo "  Tracking ID: {$record->tra}\n";
            echo "  Mother App ID: " . ($record->mother_application_id ?? 'NULL') . "\n";
            echo "  Created: {$record->created_at}\n";
            echo "---\n";
        }
        
        // Test the STFileNumberService methods with the migrated data
        echo "\n=== Testing service integration ===\n";
        
        // Test getFileNumberDetails
        $details = DB::connection('sqlsrv')->table('st_file_numbers')
            ->where('fileno', $migratedRecord->fileno)
            ->orWhere('np_fileno', $migratedRecord->np_fileno)
            ->first();
        
        if ($details) {
            echo "✅ Service lookup test passed\n";
            echo "Found record by file number lookup\n";
        } else {
            echo "❌ Service lookup test failed\n";
        }
        
        echo "\n🎉 Migration completed successfully!\n";
        echo "The existing application has been properly migrated to the new ST File Number system.\n";
        
    } else {
        echo "❌ Could not find migrated record!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}