<?php

echo "<h2>KLAES Page Typing Diagnostic Report</h2>";
echo "<p>Generated: " . date('Y-m-d H:i:s') . "</p>";

try {
    require_once 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    use Illuminate\Support\Facades\DB;
    use App\Http\Controllers\EdmsController;
    
    echo "<h3>Environment Information</h3>";
    echo "APP_ENV: " . config('app.env') . "<br>";
    echo "APP_URL: " . config('app.url') . "<br>";
    echo "APP_DEBUG: " . (config('app.debug') ? 'true' : 'false') . "<br>";
    
    echo "<h3>Database Connection Test</h3>";
    
    // Test SQL Server connection
    try {
        $sqlservConnection = DB::connection('sqlsrv');
        $testQuery = $sqlservConnection->select('SELECT 1 as test');
        echo "✓ SQL Server connection: SUCCESS<br>";
        echo "Connection: " . config('database.connections.sqlsrv.host') . "<br>";
        echo "Database: " . config('database.connections.sqlsrv.database') . "<br>";
    } catch (Exception $e) {
        echo "✗ SQL Server connection: FAILED<br>";
        echo "Error: " . $e->getMessage() . "<br>";
    }
    
    echo "<h3>Table Existence Check</h3>";
    
    $tables = ['CoverType', 'PageType', 'PageSubType'];
    $tableStatus = [];
    
    foreach ($tables as $table) {
        try {
            $count = DB::connection('sqlsrv')->table($table)->count();
            echo "✓ Table '{$table}': EXISTS with {$count} records<br>";
            $tableStatus[$table] = ['exists' => true, 'count' => $count];
        } catch (Exception $e) {
            echo "✗ Table '{$table}': NOT FOUND<br>";
            echo "Error: " . $e->getMessage() . "<br>";
            $tableStatus[$table] = ['exists' => false, 'error' => $e->getMessage()];
        }
    }
    
    echo "<h3>EDMS Controller Test</h3>";
    
    try {
        $controller = new EdmsController();
        $response = $controller->getPageTypingData();
        $content = $response->getContent();
        $data = json_decode($content, true);
        
        if ($data && $data['success']) {
            echo "✓ EdmsController::getPageTypingData(): SUCCESS<br>";
            echo "Cover Types: " . count($data['coverTypes']) . "<br>";
            echo "Page Types: " . count($data['pageTypes']) . "<br>";
            echo "Page Sub Types: " . count($data['pageSubTypes']) . " groups<br>";
            
            // Show if data is from database or fallback
            if ($tableStatus['CoverType']['exists'] && count($data['coverTypes']) > 0) {
                $firstCover = $data['coverTypes'][0];
                echo "Data source: " . (isset($firstCover['id']) ? "DATABASE" : "FALLBACK") . "<br>";
            }
        } else {
            echo "✗ EdmsController::getPageTypingData(): FAILED<br>";
            echo "Response: " . ($data['success'] ?? 'No success field') . "<br>";
        }
    } catch (Exception $e) {
        echo "✗ EdmsController test: ERROR<br>";
        echo "Error: " . $e->getMessage() . "<br>";
    }
    
    echo "<h3>Route Information</h3>";
    echo "pagetyping.get-data route: " . route('pagetyping.get-data') . "<br>";
    
    echo "<h3>Recommendations</h3>";
    
    if (!$tableStatus['CoverType']['exists'] || !$tableStatus['PageType']['exists'] || !$tableStatus['PageSubType']['exists']) {
        echo "<div style='background: #ffdddd; padding: 10px; border: 1px solid #ff9999; margin: 10px 0;'>";
        echo "<strong>⚠️ TABLES MISSING</strong><br>";
        echo "The page typing tables don't exist in the database. You need to:<br>";
        echo "1. Run the table creation script: create_pagetyping_tables.sql<br>";
        echo "2. Or use the web interface: /create-pagetyping-tables<br>";
        echo "</div>";
    } else {
        echo "<div style='background: #ddffdd; padding: 10px; border: 1px solid #99ff99; margin: 10px 0;'>";
        echo "<strong>✅ INTEGRATION WORKING</strong><br>";
        echo "The page typing system is successfully reading from the database.<br>";
        echo "If you see static data, it might be cache-related or you're looking at an old page.<br>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<h3>Critical Error</h3>";
    echo "<div style='background: #ffdddd; padding: 10px; border: 1px solid #ff0000;'>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
    echo "</div>";
}

?>