<?php

// Check subapplications table structure and data
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Subapplications Table Analysis ===" . PHP_EOL;

try {
    // Check total count
    $totalCount = DB::connection('sqlsrv')->table('subapplications')->count();
    echo "Total subapplications records: {$totalCount}" . PHP_EOL . PHP_EOL;
    
    if ($totalCount > 0) {
        // Get sample records to see structure
        $sampleRecords = DB::connection('sqlsrv')->table('subapplications')
            ->select('id', 'shared_areas')
            ->take(10)
            ->get();
            
        echo "Sample records:" . PHP_EOL;
        foreach ($sampleRecords as $record) {
            echo "ID: {$record->id}" . PHP_EOL;
            echo "Shared Areas: '" . ($record->shared_areas ?? 'NULL') . "'" . PHP_EOL;
            echo "Length: " . strlen($record->shared_areas ?? '') . PHP_EOL;
            echo "---" . PHP_EOL;
        }
        
        // Check for non-null shared_areas
        $withSharedAreas = DB::connection('sqlsrv')->table('subapplications')
            ->whereNotNull('shared_areas')
            ->count();
        echo PHP_EOL . "Records with non-null shared_areas: {$withSharedAreas}" . PHP_EOL;
        
        // Check for non-empty shared_areas
        $withNonEmptySharedAreas = DB::connection('sqlsrv')->table('subapplications')
            ->whereNotNull('shared_areas')
            ->where('shared_areas', '!=', '')
            ->count();
        echo "Records with non-empty shared_areas: {$withNonEmptySharedAreas}" . PHP_EOL;
        
        // Get actual non-empty records if any
        if ($withNonEmptySharedAreas > 0) {
            $nonEmptyRecords = DB::connection('sqlsrv')->table('subapplications')
                ->select('id', 'shared_areas')
                ->whereNotNull('shared_areas')
                ->where('shared_areas', '!=', '')
                ->take(5)
                ->get();
                
            echo PHP_EOL . "Non-empty shared_areas records:" . PHP_EOL;
            foreach ($nonEmptyRecords as $record) {
                echo "ID: {$record->id} | Shared Areas: '{$record->shared_areas}'" . PHP_EOL;
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}