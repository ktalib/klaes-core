<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = Illuminate\Http\Request::capture());

use Illuminate\Support\Facades\DB;

echo "=== Checking Subapplications Data ===\n";

// Check for subapplications with main_application_id
$apps = DB::connection('sqlsrv')
    ->table('subapplications')
    ->whereNotNull('main_application_id')
    ->where('main_application_id', '!=', '')
    ->select('id', 'main_application_id', 'fileno', 'unit_number')
    ->limit(5)
    ->get();

if ($apps->isEmpty()) {
    echo "No subapplications with main_application_id found.\n";
    echo "This might be a data structure issue.\n\n";
    
    // Check all subapplications structure
    echo "Sample subapplication records:\n";
    $sampleApps = DB::connection('sqlsrv')
        ->table('subapplications')
        ->select('id', 'main_application_id', 'fileno', 'unit_number')
        ->limit(3)
        ->get();
    
    foreach ($sampleApps as $app) {
        echo "ID: {$app->id}, Main_App_ID: '" . ($app->main_application_id ?? 'NULL') . "', File: {$app->fileno}, Unit: {$app->unit_number}\n";
    }
} else {
    echo "Found subapplications with main_application_id:\n";
    foreach ($apps as $app) {
        echo "Unit: {$app->id}, Main: {$app->main_application_id}, File: {$app->fileno}, Unit: {$app->unit_number}\n";
    }
}

echo "\n=== Checking Mother Applications ===\n";
$motherApps = DB::connection('sqlsrv')
    ->table('mother_applications')
    ->select('id', 'fileno', 'land_use')
    ->limit(3)
    ->get();

if ($motherApps->count() > 0) {
    echo "Found mother applications:\n";
    foreach ($motherApps as $app) {
        echo "ID: {$app->id}, File: {$app->fileno}, Land Use: {$app->land_use}\n";
    }
} else {
    echo "No mother applications found.\n";
}
?>