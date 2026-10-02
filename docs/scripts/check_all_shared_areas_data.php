<?php

// Check mother_applications table for shared areas data
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Mother Applications Table Analysis ===" . PHP_EOL;

try {
    // Check total count
    $totalCount = DB::connection('sqlsrv')->table('mother_applications')->count();
    echo "Total mother_applications records: {$totalCount}" . PHP_EOL . PHP_EOL;
    
    if ($totalCount > 0) {
        // Get all records to see structure
        $records = DB::connection('sqlsrv')->table('mother_applications')
            ->select('id', 'fileno', 'shared_areas')
            ->get();
            
        echo "All mother_applications records:" . PHP_EOL;
        foreach ($records as $record) {
            echo "ID: {$record->id} | File No: {$record->fileno}" . PHP_EOL;
            echo "Shared Areas: '" . ($record->shared_areas ?? 'NULL') . "'" . PHP_EOL;
            echo "Length: " . strlen($record->shared_areas ?? '') . PHP_EOL;
            echo "---" . PHP_EOL;
        }
        
        // Check for non-null shared_areas
        $withSharedAreas = DB::connection('sqlsrv')->table('mother_applications')
            ->whereNotNull('shared_areas')
            ->count();
        echo PHP_EOL . "Records with non-null shared_areas: {$withSharedAreas}" . PHP_EOL;
        
        // Check for non-empty shared_areas
        $withNonEmptySharedAreas = DB::connection('sqlsrv')->table('mother_applications')
            ->whereNotNull('shared_areas')
            ->where('shared_areas', '!=', '')
            ->count();
        echo "Records with non-empty shared_areas: {$withNonEmptySharedAreas}" . PHP_EOL;
        
        // Get actual non-empty records if any
        if ($withNonEmptySharedAreas > 0) {
            $nonEmptyRecords = DB::connection('sqlsrv')->table('mother_applications')
                ->select('id', 'fileno', 'shared_areas')
                ->whereNotNull('shared_areas')
                ->where('shared_areas', '!=', '')
                ->get();
                
            echo PHP_EOL . "Non-empty shared_areas records:" . PHP_EOL;
            foreach ($nonEmptyRecords as $record) {
                echo "ID: {$record->id} | File No: {$record->fileno} | Shared Areas: '{$record->shared_areas}'" . PHP_EOL;
            }
        }
    }
    
    // Also check the shared_utilities table to see current state
    echo PHP_EOL . "=== Shared Utilities Table Current State ===" . PHP_EOL;
    $sharedUtilitiesCount = DB::connection('sqlsrv')->table('shared_utilities')->count();
    echo "Current shared_utilities records: {$sharedUtilitiesCount}" . PHP_EOL;
    
    if ($sharedUtilitiesCount > 0) {
        $sharedUtilitiesRecords = DB::connection('sqlsrv')->table('shared_utilities')->get();
        foreach ($sharedUtilitiesRecords as $record) {
            echo "App ID: {$record->application_id} | Sub App ID: {$record->sub_application_id} | Utility: {$record->utility_type}" . PHP_EOL;
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}