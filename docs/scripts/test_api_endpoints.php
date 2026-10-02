<?php
/**
 * Test Grouping API Endpoints
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\Api\GroupingApiController;
use Illuminate\Http\Request;

echo "🧪 Testing Grouping API Endpoints\n";
echo str_repeat("=", 50) . "\n\n";

$controller = new GroupingApiController();

// Test 1: Get Totals and Statistics
echo "1. Testing /api/grouping/totals\n";
echo str_repeat("-", 30) . "\n";
try {
    $response = $controller->totals();
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "✅ Status: SUCCESS\n";
        echo "📊 Total Records: " . number_format($data['data']['total_records']) . "\n";
        echo "🏷️  Land Use Types: " . $data['data']['summary']['unique_land_uses'] . "\n";
        echo "📈 Most Common: " . $data['data']['summary']['most_common'] . "\n";
        
        echo "\nLand Use Breakdown:\n";
        foreach ($data['data']['land_use_breakdown'] as $landUse) {
            echo "  • {$landUse['landuse']}: " . number_format($landUse['count']) . " ({$landUse['percentage']}%)\n";
        }
    } else {
        echo "❌ Status: FAILED\n";
        echo "Error: " . $data['message'] . "\n";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";

// Test 2: List Records with Pagination
echo "2. Testing /api/grouping (paginated list)\n";
echo str_repeat("-", 30) . "\n";
try {
    $request = new Request([
        'page' => 1,
        'per_page' => 5,
        'landuse' => 'COMMERCIAL',
        'sort_by' => 'awaiting_fileno',
        'sort_order' => 'asc'
    ]);
    
    $response = $controller->index($request);
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "✅ Status: SUCCESS\n";
        echo "📄 Page: {$data['pagination']['current_page']} of {$data['pagination']['last_page']}\n";
        echo "📊 Total: " . number_format($data['pagination']['total']) . " records\n";
        echo "🔍 Showing: {$data['pagination']['from']} to {$data['pagination']['to']}\n";
        
        echo "\nSample Records:\n";
        foreach ($data['data'] as $record) {
            echo "  • ID: {$record['id']} | {$record['awaiting_fileno']} | {$record['landuse']}\n";
        }
    } else {
        echo "❌ Status: FAILED\n";
        echo "Error: " . $data['message'] . "\n";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";

// Test 3: Search Records
echo "3. Testing /api/grouping/search\n";
echo str_repeat("-", 30) . "\n";
try {
    $request = new Request([
        'query' => 'CON-COM',
        'per_page' => 5
    ]);
    
    $response = $controller->search($request);
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "✅ Status: SUCCESS\n";
        echo "🔍 Search Query: '{$data['search_query']}'\n";
        echo "📊 Results Found: " . number_format($data['results_found']) . "\n";
        
        echo "\nSample Results:\n";
        foreach ($data['data'] as $record) {
            echo "  • {$record['awaiting_fileno']} | {$record['landuse']} | Year: {$record['year']}\n";
        }
    } else {
        echo "❌ Status: FAILED\n";
        echo "Error: " . $data['message'] . "\n";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";

// Test 4: Get by Land Use
echo "4. Testing /api/grouping/land-use/{landuse}\n";
echo str_repeat("-", 30) . "\n";
try {
    $request = new Request(['per_page' => 3]);
    $response = $controller->byLandUse($request, 'RESIDENTIAL');
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "✅ Status: SUCCESS\n";
        echo "🏠 Land Use: {$data['land_use']}\n";
        echo "📊 Total: " . number_format($data['pagination']['total']) . " records\n";
        
        echo "\nSample RESIDENTIAL Records:\n";
        foreach ($data['data'] as $record) {
            echo "  • {$record['awaiting_fileno']} | Year: {$record['year']} | Batch: {$record['batch_no']}\n";
        }
    } else {
        echo "❌ Status: FAILED\n";
        echo "Error: " . $data['message'] . "\n";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";

// Test 5: Get Utility Data
echo "5. Testing Utility Endpoints\n";
echo str_repeat("-", 30) . "\n";

// Land Use Types
echo "📊 Land Use Types:\n";
try {
    $response = $controller->landUseTypes();
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        foreach ($data['data'] as $landUse) {
            echo "  • {$landUse}\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n📅 Available Years:\n";
try {
    $response = $controller->availableYears();
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        foreach ($data['data'] as $year) {
            echo "  • {$year}\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🎉 API Testing Complete!\n";
echo "📋 View comprehensive test interface at: /test_grouping_api.html\n";
echo "📖 Read full documentation in: GROUPING_API_DOCUMENTATION.md\n";
?>