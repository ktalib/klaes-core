<?php

// Fix subapplications relationships and inherit shared areas with JSON fix
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Fix Subapplications with JSON Repair ===" . PHP_EOL;
echo "Date: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

function parseSharedAreas($jsonString) {
    // First try standard JSON decode
    $decoded = json_decode($jsonString, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return array_values($decoded); // Return values only
    }
    
    // Handle malformed JSON like {"ACCSESS"} - treat the key as the value
    if (preg_match('/^\{\"([^"]+)\"\}$/', $jsonString, $matches)) {
        return [$matches[1]]; // Return the key as a single item array
    }
    
    // Handle multiple keys like {"ACCSESS","PARKING"} 
    if (preg_match_all('/\"([^"]+)\"/', $jsonString, $matches)) {
        return $matches[1];
    }
    
    return [];
}

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
    echo "Raw Shared Areas: {$motherApp->shared_areas}" . PHP_EOL;
    
    // Parse the shared areas with our custom function
    $sharedAreasArray = parseSharedAreas($motherApp->shared_areas);
    echo "Parsed Shared Areas: " . implode(', ', $sharedAreasArray) . PHP_EOL . PHP_EOL;
    
    if (empty($sharedAreasArray)) {
        echo "No shared areas found to inherit!" . PHP_EOL;
        exit;
    }
    
    // Get subapplications that need shared areas
    $subapplications = DB::connection('sqlsrv')->table('subapplications')
        ->whereNotExists(function($query) {
            $query->select(DB::raw(1))
                  ->from('shared_utilities')
                  ->whereRaw('shared_utilities.sub_application_id = subapplications.id');
        })
        ->get();
    
    echo "Found " . count($subapplications) . " subapplications needing shared areas:" . PHP_EOL;
    
    foreach ($subapplications as $sub) {
        echo "Sub ID: {$sub->id} | File No: {$sub->fileno}" . PHP_EOL;
    }
    
    if (count($subapplications) == 0) {
        echo "All subapplications already have shared utilities data!" . PHP_EOL;
        exit;
    }
    
    echo PHP_EOL . "Proceed with inheriting shared areas: " . implode(', ', $sharedAreasArray) . " ? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $input = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($input) !== 'y') {
        echo "Operation cancelled." . PHP_EOL;
        exit;
    }
    
    // Inherit shared areas for each subapplication
    $inherited = 0;
    
    foreach ($subapplications as $sub) {
        $order = 1;
        foreach ($sharedAreasArray as $area) {
            if (!empty(trim($area))) {
                DB::connection('sqlsrv')->table('shared_utilities')->insert([
                    'application_id' => null,
                    'sub_application_id' => $sub->id,
                    'utility_type' => trim($area),
                    'dimension' => null,
                    'order' => $order,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'created_by' => 'json_repair_migration',
                    'updated_by' => 'json_repair_migration'
                ]);
                $order++;
            }
        }
        
        echo "✓ Sub Application {$sub->id}: Inherited " . count($sharedAreasArray) . " shared areas" . PHP_EOL;
        $inherited++;
    }
    
    echo PHP_EOL . "=== Final Summary ===" . PHP_EOL;
    echo "Subapplications with inherited shared areas: {$inherited}" . PHP_EOL;
    
    // Show final state
    echo PHP_EOL . "=== Final Shared Utilities Table State ===" . PHP_EOL;
    $finalRecords = DB::connection('sqlsrv')->table('shared_utilities')
        ->orderBy('application_id')
        ->orderBy('sub_application_id')
        ->orderBy('order')
        ->get();
        
    $count = 0;
    foreach ($finalRecords as $record) {
        $count++;
        if ($record->application_id) {
            echo "{$count}. Primary App {$record->application_id}: {$record->utility_type}" . PHP_EOL;
        } else {
            echo "{$count}. Sub App {$record->sub_application_id}: {$record->utility_type}" . PHP_EOL;
        }
    }
    
    echo PHP_EOL . "Total shared utilities records: " . count($finalRecords) . PHP_EOL;
    echo "Migration completed successfully!" . PHP_EOL;
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . PHP_EOL;
    echo "Stack trace: " . $e->getTraceAsString() . PHP_EOL;
}