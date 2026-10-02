<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "=== CURRENT BATCH STATUS VERIFICATION ===\n";

try {
    // Test the getCurrentBatchStatus endpoint
    $client = new \GuzzleHttp\Client([
        'base_uri' => 'http://localhost:8000',
        'timeout' => 30.0,
    ]);

    $response = $client->get('/get-current-batch-status');
    $data = json_decode($response->getBody(), true);
    
    echo "Current Batch Status API Response:\n";
    echo "- Status: " . ($data['success'] ? 'Success' : 'Failed') . "\n";
    echo "- Current Batch: " . ($data['current_batch'] ?? 'N/A') . "\n";
    echo "- Files in Batch: " . ($data['files_in_current_batch'] ?? 'N/A') . "\n";
    echo "- Next Serial: " . ($data['next_serial_number'] ?? 'N/A') . "\n";
    echo "- Shelf Location: " . ($data['shelf_location'] ?? 'N/A') . "\n";
    
    echo "\nRaw Response:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}