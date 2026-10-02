<?php

// Migration script to copy shared_areas from subapplications to shared_utilities table
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Subapplications Shared Areas Migration Script ===" . PHP_EOL;
echo "Date: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

try {
    // First, let's check what data we have in subapplications
    $records = DB::connection('sqlsrv')->select("
        SELECT id, shared_areas 
        FROM subapplications 
        WHERE shared_areas IS NOT NULL 
        AND shared_areas != ''
        AND shared_areas != 'null'
        AND shared_areas != '[]'
        AND LEN(shared_areas) > 2
    ");
    
    echo "Found " . count($records) . " subapplications with shared_areas data:" . PHP_EOL . PHP_EOL;
    
    if (count($records) == 0) {
        echo "No records found with shared_areas data to migrate." . PHP_EOL;
        exit;
    }
    
    // Display first few records to understand the data structure
    echo "Sample records:" . PHP_EOL;
    foreach (array_slice($records, 0, 5) as $record) {
        echo "Sub Application ID: {$record->id}" . PHP_EOL;
        echo "Shared Areas JSON: {$record->shared_areas}" . PHP_EOL;
        
        // Try to decode JSON
        $sharedAreasArray = json_decode($record->shared_areas, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($sharedAreasArray)) {
            echo "Decoded Areas: " . implode(', ', $sharedAreasArray) . PHP_EOL;
        } else {
            echo "JSON Decode Error or Invalid Format" . PHP_EOL;
        }
        echo "---" . PHP_EOL;
    }
    
    echo PHP_EOL . "Do you want to proceed with migration? (y/n): ";
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
    
    foreach ($records as $record) {
        try {
            // Decode the shared_areas JSON
            $sharedAreasArray = json_decode($record->shared_areas, true);
            
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($sharedAreasArray)) {
                echo "Skipping Sub Application ID {$record->id}: Invalid JSON format" . PHP_EOL;
                $errors++;
                continue;
            }
            
            // Check if data already exists in shared_utilities
            $existingCount = DB::connection('sqlsrv')->table('shared_utilities')
                ->where('sub_application_id', $record->id)
                ->count();
                
            if ($existingCount > 0) {
                echo "Skipping Sub Application ID {$record->id}: Data already exists in shared_utilities" . PHP_EOL;
                continue;
            }
            
            // Insert each shared area as a separate record
            $order = 1;
            foreach ($sharedAreasArray as $area) {
                if (!empty(trim($area))) {
                    DB::connection('sqlsrv')->table('shared_utilities')->insert([
                        'application_id' => null, // No primary application ID for subapplications
                        'sub_application_id' => $record->id,
                        'utility_type' => trim($area),
                        'dimension' => null, // No dimension data in original JSON
                        'order' => $order,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'created_by' => 'migration_script',
                        'updated_by' => 'migration_script'
                    ]);
                    $order++;
                }
            }
            
            echo "✓ Migrated Sub Application ID {$record->id}: " . implode(', ', $sharedAreasArray) . PHP_EOL;
            $migrated++;
            
        } catch (Exception $e) {
            echo "✗ Error migrating Sub Application ID {$record->id}: " . $e->getMessage() . PHP_EOL;
            $errors++;
        }
    }
    
    echo PHP_EOL . "=== Migration Summary ===" . PHP_EOL;
    echo "Total records processed: " . count($records) . PHP_EOL;
    echo "Successfully migrated: {$migrated}" . PHP_EOL;
    echo "Errors: {$errors}" . PHP_EOL;
    echo "Migration completed at: " . date('Y-m-d H:i:s') . PHP_EOL;
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . PHP_EOL;
    echo "Stack trace: " . $e->getTraceAsString() . PHP_EOL;
}