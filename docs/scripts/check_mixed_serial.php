<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Checking actual MIXED serial value...\n\n";

$mixed = \DB::connection('sqlsrv')->table('land_use_serials')
    ->where('land_use_type', 'MIXED')
    ->where('year', 2025)
    ->first();

if ($mixed) {
    echo "Current Serial: {$mixed->current_serial}\n";
    echo "Next File Number Should Be: ST-MIXED-2025-" . ($mixed->current_serial + 1) . "\n";
    
    // Test what the controller is actually generating
    echo "\n🧪 Testing controller logic...\n";
    
$controller = app(\App\Http\Controllers\PrimaryApplicationController::class);
$request = \Illuminate\Http\Request::create('/primaryform', 'GET', ['landuse' => 'MIXED']);

ob_start();
try {
    $response = $controller->index($request);
    $content = $response->render();

    if (preg_match('/value="([^"]*ST-MIXED[^"]*)"/', $content, $matches)) {
        echo "Controller Generated: {$matches[1]}\n";
    } else {
        echo "Could not extract file number from controller output\n";
    }
} catch (Exception $e) {
    echo "Error testing controller: " . $e->getMessage() . "\n";
}
ob_end_clean();
    
} else {
    echo "No MIXED record found!\n";
}
