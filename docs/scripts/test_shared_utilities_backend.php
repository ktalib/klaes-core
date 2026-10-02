<?php
/**
 * Test script for Shared Utilities API and Database Operations
 * This script tests the shared_utilities table operations
 */

// Include Laravel bootstrap
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Testing Shared Utilities Database Operations ===\n\n";

try {
    // Test 1: Check if shared_utilities table exists
    echo "1. Testing table existence...\n";
    $tableExists = Schema::connection('sqlsrv')->hasTable('shared_utilities');
    echo "shared_utilities table exists: " . ($tableExists ? "YES" : "NO") . "\n\n";

    if (!$tableExists) {
        echo "ERROR: shared_utilities table does not exist!\n";
        echo "Please run the migrations first.\n";
        exit(1);
    }

    // Test 2: Check table structure
    echo "2. Checking table structure...\n";
    $columns = Schema::connection('sqlsrv')->getColumnListing('shared_utilities');
    echo "Columns: " . implode(', ', $columns) . "\n\n";

    // Test 3: Insert sample data
    echo "3. Inserting sample data...\n";
    
    $sampleData = [
        [
            'application_id' => 1,
            'sub_application_id' => null,
            'utility_type' => 'Access Road',
            'dimension' => '6.0',
            'count' => 1,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'application_id' => 1,
            'sub_application_id' => null,
            'utility_type' => 'Drainage',
            'dimension' => '3.5',
            'count' => 1,
            'order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'application_id' => 1,
            'sub_application_id' => 1,
            'utility_type' => 'Staircase',
            'dimension' => '2.0',
            'count' => 1,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'application_id' => 1,
            'sub_application_id' => 1,
            'utility_type' => 'Common Entrance',
            'dimension' => '4.5',
            'count' => 1,
            'order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    ];

    foreach ($sampleData as $data) {
        // Check if record already exists
        $existing = DB::connection('sqlsrv')
            ->table('shared_utilities')
            ->where('application_id', $data['application_id'])
            ->where('sub_application_id', $data['sub_application_id'])
            ->where('utility_type', $data['utility_type'])
            ->first();

        if (!$existing) {
            DB::connection('sqlsrv')->table('shared_utilities')->insert($data);
            echo "✓ Inserted: {$data['utility_type']} (App: {$data['application_id']}, Sub: " . ($data['sub_application_id'] ?? 'NULL') . ")\n";
        } else {
            echo "→ Already exists: {$data['utility_type']} (App: {$data['application_id']}, Sub: " . ($data['sub_application_id'] ?? 'NULL') . ")\n";
        }
    }

    echo "\n";

    // Test 4: Fetch data like the API does
    echo "4. Testing data retrieval (like API)...\n";

    // Test primary application
    echo "For Application ID 1 (primary):\n";
    $primaryData = DB::connection('sqlsrv')
        ->table('shared_utilities')
        ->where('application_id', 1)
        ->whereNull('sub_application_id')
        ->select([
            'id', 'application_id', 'sub_application_id', 'utility_type', 
            'dimension', 'count', 'order', 'created_at', 'updated_at'
        ])
        ->orderBy('order')
        ->get();

    foreach ($primaryData as $record) {
        echo "  - {$record->utility_type}: {$record->dimension}\n";
    }

    // Test sub-application
    echo "\nFor Application ID 1, Sub-Application ID 1 (unit):\n";
    $subData = DB::connection('sqlsrv')
        ->table('shared_utilities')
        ->where('application_id', 1)
        ->where('sub_application_id', 1)
        ->select([
            'id', 'application_id', 'sub_application_id', 'utility_type', 
            'dimension', 'count', 'order', 'created_at', 'updated_at'
        ])
        ->orderBy('order')
        ->get();

    foreach ($subData as $record) {
        echo "  - {$record->utility_type}: {$record->dimension}\n";
    }

    echo "\n";

    // Test 5: Test JSON conversion (like frontend expects)
    echo "5. Testing JSON conversion format...\n";
    
    $jsonFormat = $primaryData->map(function($record, $index) {
        return [
            'sn' => $index + 1,
            'description' => $record->utility_type,
            'dimension' => $record->dimension
        ];
    });

    echo "JSON format for measurement entries:\n";
    echo json_encode($jsonFormat, JSON_PRETTY_PRINT) . "\n\n";

    // Test 6: Test API endpoint directly
    echo "6. Testing API endpoint...\n";
    echo "To test the API endpoint, visit:\n";
    echo "http://your-domain/api/shared-utilities/1 (for primary application)\n";
    echo "http://your-domain/api/shared-utilities/1/1 (for sub-application)\n\n";

    echo "=== All Tests Completed Successfully! ===\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}