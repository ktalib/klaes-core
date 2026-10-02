<?php

// Check the relationship between subapplications and mother_applications
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Subapplications Relationship Analysis ===" . PHP_EOL;

try {
    // Get all subapplications with their relationship data
    $subapplications = DB::connection('sqlsrv')->table('subapplications')
        ->select('id', 'main_application_id', 'fileno')
        ->get();
        
    echo "All subapplications:" . PHP_EOL;
    foreach ($subapplications as $sub) {
        echo "Sub ID: {$sub->id} | Main App ID: {$sub->main_application_id} | File No: {$sub->fileno}" . PHP_EOL;
    }
    
    echo PHP_EOL . "Mother applications:" . PHP_EOL;
    $motherApps = DB::connection('sqlsrv')->table('mother_applications')
        ->select('id', 'fileno', 'shared_areas')
        ->get();
        
    foreach ($motherApps as $mother) {
        echo "Mother ID: {$mother->id} | File No: {$mother->fileno} | Shared Areas: {$mother->shared_areas}" . PHP_EOL;
    }
    
    // Try a direct join to see the relationship
    echo PHP_EOL . "Direct join results:" . PHP_EOL;
    $joinResults = DB::connection('sqlsrv')->select("
        SELECT s.id as sub_id, s.main_application_id, s.fileno as sub_fileno, 
               m.id as mother_id, m.fileno as mother_fileno, m.shared_areas
        FROM subapplications s
        LEFT JOIN mother_applications m ON s.main_application_id = m.id
    ");
    
    foreach ($joinResults as $result) {
        echo "Sub ID: {$result->sub_id} | Sub File: {$result->sub_fileno} | ";
        echo "Mother ID: " . ($result->mother_id ?? 'NULL') . " | Mother File: " . ($result->mother_fileno ?? 'NULL');
        echo " | Shared Areas: " . ($result->shared_areas ?? 'NULL') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}