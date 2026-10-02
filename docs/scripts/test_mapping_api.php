<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    // Test the actual API endpoint logic
    $controller = new \App\Http\Controllers\Api\GroupingController();
    $request = new \Illuminate\Http\Request();
    $request->merge(['limit' => 5]);
    
    $response = $controller->preview($request);
    $data = json_decode($response->getContent(), true);
    
    echo "API Response Structure:\n";
    echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
    echo "Data count: " . count($data['data'] ?? []) . "\n\n";
    
    if (!empty($data['data'])) {
        foreach ($data['data'] as $index => $row) {
            echo "Row " . ($index + 1) . ":\n";
            echo "  awaiting_fileno: " . ($row['awaiting_fileno'] ?? 'NULL') . "\n";
            echo "  mls_fileno: " . ($row['mls_fileno'] ?? 'NULL') . "\n";
            echo "  mapping: " . var_export($row['mapping'] ?? 'NULL', true) . " (type: " . gettype($row['mapping'] ?? null) . ")\n";
            
            // Test the JavaScript logic in PHP
            $mappingRaw = $row['mapping'] ?? 0;
            $mappingNumber = is_numeric($mappingRaw) ? (float)$mappingRaw : 0;
            $mappingText = strtolower(trim((string)$mappingRaw));
            $isMatched = ($mappingNumber >= 1) || in_array($mappingText, ['true', 'yes', 'matched', 'match', 'y']);
            
            echo "  Parsed number: $mappingNumber\n";
            echo "  Parsed text: '$mappingText'\n";
            echo "  Should be matched: " . ($isMatched ? 'YES' : 'NO') . "\n";
            echo "  Badge should show: " . ($isMatched ? 'Matched' : 'Pending') . "\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}