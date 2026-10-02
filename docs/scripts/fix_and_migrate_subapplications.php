<?php

// Fix subapplications relationships and inherit shared areas
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Fix Subapplications Relationships & Inherit Shared Areas ===" . PHP_EOL;
echo "Date: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

try {
    // Get the mother application
    $motherApp = DB::connection('sqlsrv')->table('mother_applications')->first();
    if (!$motherApp) {
        echo "No mother application found!" . PHP_EOL;
        exit;
    }
    
    echo "Mother Application Found:" . PHP_EOL;
    echo "ID: {$motherApp->id}" . PHP_EOL;
    echo "File No: {$motherApp->fileno}" . PHP_EOL;
    echo "Shared Areas: {$motherApp->shared_areas}" . PHP_EOL . PHP_EOL;
    
    // Get all subapplications
    $subapplications = DB::connection('sqlsrv')->table('subapplications')->get();
    echo "Found " . count($subapplications) . " subapplications:" . PHP_EOL;
    
    foreach ($subapplications as $sub) {
        echo "Sub ID: {$sub->id} | File No: {$sub->fileno} | Current Main App ID: " . ($sub->main_application_id ?? 'NULL') . PHP_EOL;
    }
    
    echo PHP_EOL . "Do you want to:" . PHP_EOL;
    echo "1. Link all subapplications to the mother application (ID: {$motherApp->id})" . PHP_EOL;
    echo "2. Inherit shared areas from mother to subapplications" . PHP_EOL;
    echo "Proceed? (y/n): ";
    
    $handle = fopen("php://stdin", "r");
    $input = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($input) !== 'y') {
        echo "Operation cancelled." . PHP_EOL;
        exit;
    }
    
    // Step 1: Fix the relationships
    echo PHP_EOL . "=== Step 1: Fixing Relationships ===" . PHP_EOL;
    $relationshipsFixed = 0;
    
    foreach ($subapplications as $sub) {
        if (empty($sub->main_application_id)) {
            $updated = DB::connection('sqlsrv')->table('subapplications')
                ->where('id', $sub->id)
                ->update(['main_application_id' => $motherApp->id]);
                
            if ($updated) {
                echo "✓ Linked Sub Application {$sub->id} to Mother Application {$motherApp->id}" . PHP_EOL;
                $relationshipsFixed++;
            } else {
                echo "✗ Failed to link Sub Application {$sub->id}" . PHP_EOL;
            }
        } else {
            echo "- Sub Application {$sub->id} already linked to {$sub->main_application_id}" . PHP_EOL;
        }
    }
    
    echo "Relationships fixed: {$relationshipsFixed}" . PHP_EOL . PHP_EOL;
    
    // Step 2: Inherit shared areas
    echo "=== Step 2: Inheriting Shared Areas ===" . PHP_EOL;
    
    // Decode mother app shared areas
    $sharedAreasArray = json_decode($motherApp->shared_areas, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($sharedAreasArray)) {
        echo "✗ Cannot decode mother application shared areas JSON" . PHP_EOL;
        exit;
    }
    
    echo "Shared areas to inherit: " . implode(', ', $sharedAreasArray) . PHP_EOL . PHP_EOL;
    
    $inherited = 0;
    $skipped = 0;
    
    foreach ($subapplications as $sub) {
        // Check if data already exists in shared_utilities for this subapplication
        $existingCount = DB::connection('sqlsrv')->table('shared_utilities')
            ->where('sub_application_id', $sub->id)
            ->count();
            
        if ($existingCount > 0) {
            echo "- Sub Application {$sub->id}: Already has shared utilities data ({$existingCount} records)" . PHP_EOL;
            $skipped++;
            continue;
        }
        
        // Insert each shared area as a separate record for the subapplication
        $order = 1;
        foreach ($sharedAreasArray as $area) {
            if (!empty(trim($area))) {
                DB::connection('sqlsrv')->table('shared_utilities')->insert([
                    'application_id' => null, // No direct link to mother app for subapplication entries
                    'sub_application_id' => $sub->id,
                    'utility_type' => trim($area),
                    'dimension' => null, // No dimension data in original JSON
                    'order' => $order,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'created_by' => 'relationship_fix_migration',
                    'updated_by' => 'relationship_fix_migration'
                ]);
                $order++;
            }
        }
        
        echo "✓ Sub Application {$sub->id}: Inherited " . count($sharedAreasArray) . " shared areas" . PHP_EOL;
        $inherited++;
    }
    
    echo PHP_EOL . "=== Final Summary ===" . PHP_EOL;
    echo "Relationships fixed: {$relationshipsFixed}" . PHP_EOL;
    echo "Subapplications with inherited shared areas: {$inherited}" . PHP_EOL;
    echo "Subapplications skipped (already had data): {$skipped}" . PHP_EOL;
    
    // Show final state
    echo PHP_EOL . "=== Final Shared Utilities Table State ===" . PHP_EOL;
    $finalRecords = DB::connection('sqlsrv')->table('shared_utilities')
        ->orderBy('application_id')
        ->orderBy('sub_application_id')
        ->orderBy('order')
        ->get();
        
    foreach ($finalRecords as $record) {
        if ($record->application_id) {
            echo "Primary App {$record->application_id}: {$record->utility_type}" . PHP_EOL;
        } else {
            echo "Sub App {$record->sub_application_id}: {$record->utility_type}" . PHP_EOL;
        }
    }
    
    echo PHP_EOL . "Migration completed successfully!" . PHP_EOL;
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . PHP_EOL;
    echo "Stack trace: " . $e->getTraceAsString() . PHP_EOL;
}