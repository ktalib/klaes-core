<?php

// Migration script to copy shared_areas from mother_applications to shared_utilities for subapplications
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Subapplications Shared Areas Inheritance Migration ===" . PHP_EOL;
echo "Date: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

try {
    // Get all subapplications and their parent mother_applications
    $subapplications = DB::connection('sqlsrv')->select("
        SELECT s.id as sub_id, s.main_application_id, m.id as mother_id, m.shared_areas
        FROM subapplications s
        LEFT JOIN mother_applications m ON s.main_application_id = m.id
        WHERE m.shared_areas IS NOT NULL 
        AND m.shared_areas != ''
        AND LEN(m.shared_areas) > 2
    ");
    
    echo "Found " . count($subapplications) . " subapplications with parent shared_areas data:" . PHP_EOL . PHP_EOL;
    
    if (count($subapplications) == 0) {
        echo "No subapplications found with parent shared_areas data to inherit." . PHP_EOL;
        exit;
    }
    
    // Display the records that will be processed
    echo "Records to process:" . PHP_EOL;
    foreach ($subapplications as $record) {
        echo "Sub Application ID: {$record->sub_id}" . PHP_EOL;
        echo "Mother Application ID: {$record->mother_id}" . PHP_EOL;
        echo "Parent Shared Areas JSON: {$record->shared_areas}" . PHP_EOL;
        
        // Try to decode JSON
        $sharedAreasArray = json_decode($record->shared_areas, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($sharedAreasArray)) {
            echo "Areas to inherit: " . implode(', ', $sharedAreasArray) . PHP_EOL;
        } else {
            echo "JSON Decode Error or Invalid Format" . PHP_EOL;
        }
        echo "---" . PHP_EOL;
    }
    
    echo PHP_EOL . "Do you want to proceed with inheriting shared areas for subapplications? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $input = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($input) !== 'y') {
        echo "Migration cancelled." . PHP_EOL;
        exit;
    }
    
    // Proceed with migration
    $migrated = 0;
    $errors = 0;
    $skipped = 0;
    
    foreach ($subapplications as $record) {
        try {
            // Check if data already exists in shared_utilities for this subapplication
            $existingCount = DB::connection('sqlsrv')->table('shared_utilities')
                ->where('sub_application_id', $record->sub_id)
                ->count();
                
            if ($existingCount > 0) {
                echo "Skipping Sub Application ID {$record->sub_id}: Data already exists in shared_utilities" . PHP_EOL;
                $skipped++;
                continue;
            }
            
            // Decode the shared_areas JSON from mother application
            $sharedAreasArray = json_decode($record->shared_areas, true);
            
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($sharedAreasArray)) {
                echo "Skipping Sub Application ID {$record->sub_id}: Invalid JSON format in parent" . PHP_EOL;
                $errors++;
                continue;
            }
            
            // Insert each shared area as a separate record for the subapplication
            $order = 1;
            foreach ($sharedAreasArray as $area) {
                if (!empty(trim($area))) {
                    DB::connection('sqlsrv')->table('shared_utilities')->insert([
                        'application_id' => null, // No direct link to mother app for subapplication entries
                        'sub_application_id' => $record->sub_id,
                        'utility_type' => trim($area),
                        'dimension' => null, // No dimension data in original JSON
                        'order' => $order,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'created_by' => 'inheritance_migration',
                        'updated_by' => 'inheritance_migration'
                    ]);
                    $order++;
                }
            }
            
            echo "✓ Inherited for Sub Application ID {$record->sub_id}: " . implode(', ', $sharedAreasArray) . PHP_EOL;
            $migrated++;
            
        } catch (Exception $e) {
            echo "✗ Error processing Sub Application ID {$record->sub_id}: " . $e->getMessage() . PHP_EOL;
            $errors++;
        }
    }
    
    echo PHP_EOL . "=== Migration Summary ===" . PHP_EOL;
    echo "Total subapplications processed: " . count($subapplications) . PHP_EOL;
    echo "Successfully inherited: {$migrated}" . PHP_EOL;
    echo "Skipped (already exists): {$skipped}" . PHP_EOL;
    echo "Errors: {$errors}" . PHP_EOL;
    echo "Migration completed at: " . date('Y-m-d H:i:s') . PHP_EOL;
    
    // Show final state of shared_utilities table
    echo PHP_EOL . "=== Final Shared Utilities Table State ===" . PHP_EOL;
    $finalRecords = DB::connection('sqlsrv')->table('shared_utilities')
        ->orderBy('application_id')
        ->orderBy('sub_application_id')
        ->orderBy('order')
        ->get();
        
    foreach ($finalRecords as $record) {
        $appType = $record->application_id ? "Primary App {$record->application_id}" : "Sub App {$record->sub_application_id}";
        echo "{$appType}: {$record->utility_type}" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . PHP_EOL;
    echo "Stack trace: " . $e->getTraceAsString() . PHP_EOL;
}