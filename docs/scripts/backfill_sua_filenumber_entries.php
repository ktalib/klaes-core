<?php

require_once 'vendor/autoload.php';

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== SUA FileNumber Backfill Script ===\n\n";

try {
    // Test database connection
    echo "1. Testing SQL Server connection...\n";
    $connection = DB::connection('sqlsrv');
    $connection->getPdo();
    echo "   ✓ Connection successful\n\n";
    
    // Find SUA applications without fileNumber entries
    echo "2. Finding SUA applications without fileNumber entries...\n";
    
    $suaApplications = DB::connection('sqlsrv')
        ->table('subapplications')
        ->leftJoin('fileNumbers', function($join) {
            $join->on('subapplications.id', '=', 'fileNumbers.sub_application_id')
                 ->where('fileNumbers.SOURCE', '=', 'ST');
        })
        ->where('subapplications.is_sua_unit', 1)
        ->whereNull('fileNumbers.id')
        ->select([
            'subapplications.id',
            'subapplications.fileno',
            'subapplications.np_fileno',
            'subapplications.mls_fileno',
            'subapplications.applicant_type',
            'subapplications.first_name',
            'subapplications.middle_name',
            'subapplications.surname',
            'subapplications.corporate_name',
            'subapplications.multiple_owners_names',
            'subapplications.property_location',
            'subapplications.created_at'
        ])
        ->orderBy('subapplications.created_at', 'desc')
        ->get();
    
    echo "   Found " . $suaApplications->count() . " SUA applications without fileNumber entries\n\n";
    
    if ($suaApplications->count() > 0) {
        echo "3. Backfilling fileNumber entries...\n";
        
        $backfilled = 0;
        $errors = 0;
        
        foreach ($suaApplications as $sua) {
            try {
                // Generate FileName based on applicant type
                $fileName = '';
                switch ($sua->applicant_type) {
                    case 'individual':
                        $firstName = $sua->first_name ?? '';
                        $middleName = $sua->middle_name ?? '';
                        $surname = $sua->surname ?? '';
                        $fileName = trim($firstName . ' ' . $middleName . ' ' . $surname);
                        if (empty($fileName)) {
                            $fileName = 'Individual Applicant';
                        }
                        break;
                        
                    case 'corporate':
                        $fileName = $sua->corporate_name ?? 'Corporate Applicant';
                        break;
                        
                    case 'multiple':
                        if (!empty($sua->multiple_owners_names)) {
                            // Try to decode as JSON first
                            $decoded = json_decode($sua->multiple_owners_names, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $ownerNames = $decoded;
                            } else {
                                // Fall back to comma-separated string
                                $ownerNames = array_map('trim', explode(',', $sua->multiple_owners_names));
                            }
                            
                            if (!empty($ownerNames)) {
                                $firstOwner = $ownerNames[0] ?? 'Multiple Owners';
                                $totalCount = count($ownerNames);
                                if ($totalCount > 1) {
                                    $fileName = $firstOwner . ' & ' . ($totalCount - 1) . ' Others';
                                } else {
                                    $fileName = $firstOwner;
                                }
                            } else {
                                $fileName = 'Multiple Owners';
                            }
                        } else {
                            $fileName = 'Multiple Owners';
                        }
                        break;
                        
                    default:
                        $fileName = 'Unknown Applicant';
                        break;
                }
                
                // Insert fileNumber entry
                DB::connection('sqlsrv')->table('fileNumbers')->insert([
                    'sub_application_id' => $sua->id,
                    'mlsfNo' => $sua->mls_fileno,
                    'st_file_no' => $sua->fileno,
                    'FileName' => $fileName,
                    'created_at' => $sua->created_at ?? now(),
                    'updated_at' => now(),
                    'location' => $sua->property_location,
                    'type' => 'ST',
                    'is_deleted' => 0,
                    'SOURCE' => 'ST'
                ]);
                
                echo "   ✓ Backfilled SUA ID: {$sua->id}, FileNo: {$sua->fileno}, FileName: {$fileName}\n";
                $backfilled++;
                
            } catch (Exception $e) {
                echo "   ✗ Error backfilling SUA ID: {$sua->id} - " . $e->getMessage() . "\n";
                $errors++;
            }
        }
        
        echo "\n   Summary:\n";
        echo "   - Successfully backfilled: {$backfilled} entries\n";
        echo "   - Errors: {$errors} entries\n\n";
        
        // Verify backfill results
        echo "4. Verifying backfill results...\n";
        
        $verificationQuery = DB::connection('sqlsrv')
            ->table('subapplications')
            ->join('fileNumbers', function($join) {
                $join->on('subapplications.id', '=', 'fileNumbers.sub_application_id')
                     ->where('fileNumbers.SOURCE', '=', 'ST');
            })
            ->where('subapplications.is_sua_unit', 1)
            ->count();
        
        echo "   Total SUA applications with fileNumber entries: {$verificationQuery}\n";
        
        // Show sample of backfilled entries
        echo "\n5. Sample of backfilled entries:\n";
        
        $sampleEntries = DB::connection('sqlsrv')
            ->table('fileNumbers')
            ->where('SOURCE', 'ST')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        foreach ($sampleEntries as $entry) {
            echo "   - ID: {$entry->id}, SUA ID: {$entry->sub_application_id}, FileName: {$entry->FileName}\n";
            echo "     st_file_no: {$entry->st_file_no}, mlsfNo: {$entry->mlsfNo}\n";
            echo "     Location: " . substr($entry->location ?? '', 0, 50) . "...\n\n";
        }
        
    } else {
        echo "3. No backfill needed - all SUA applications already have fileNumber entries\n\n";
    }
    
    echo "=== Backfill completed successfully ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}