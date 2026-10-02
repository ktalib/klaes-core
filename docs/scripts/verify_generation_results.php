<?php
require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $connection = DB::connection('sqlsrv');
    
    echo "🔍 VERIFYING DIRECT GENERATION RESULTS\n";
    echo "=" . str_repeat("=", 50) . "\n\n";
    
    // Total count
    $totalCount = $connection->selectOne("SELECT COUNT(*) as count FROM grouping")->count;
    echo "📊 TOTAL RECORDS: " . number_format($totalCount) . "\n\n";
    
    // Land use breakdown
    echo "🏷️  LAND USE DISTRIBUTION:\n";
    $landUseStats = $connection->select("
        SELECT landuse, COUNT(*) as count, 
               CAST(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM grouping) AS DECIMAL(5,2)) as percentage
        FROM grouping 
        GROUP BY landuse 
        ORDER BY COUNT(*) DESC
    ");
    
    foreach ($landUseStats as $stat) {
        echo "  {$stat->landuse}: " . number_format($stat->count) . " ({$stat->percentage}%)\n";
    }
    
    // Prefix breakdown
    echo "\n🔤 PREFIX DISTRIBUTION:\n";
    $prefixStats = $connection->select("
        SELECT 
            CASE 
                WHEN awaiting_fileno LIKE 'CON-%-RC-%' THEN LEFT(awaiting_fileno, LEN(awaiting_fileno) - LEN(SUBSTRING(awaiting_fileno, LEN(awaiting_fileno) - CHARINDEX('-', REVERSE(awaiting_fileno)) + 2, LEN(awaiting_fileno))))
                WHEN awaiting_fileno LIKE 'CON-%' AND awaiting_fileno NOT LIKE 'CON-%-RC-%' THEN LEFT(awaiting_fileno, LEN(awaiting_fileno) - LEN(SUBSTRING(awaiting_fileno, LEN(awaiting_fileno) - CHARINDEX('-', REVERSE(awaiting_fileno)) + 2, LEN(awaiting_fileno))))
                WHEN awaiting_fileno LIKE '%-RC-%' THEN LEFT(awaiting_fileno, LEN(awaiting_fileno) - LEN(SUBSTRING(awaiting_fileno, LEN(awaiting_fileno) - CHARINDEX('-', REVERSE(awaiting_fileno)) + 2, LEN(awaiting_fileno))))
                ELSE LEFT(awaiting_fileno, CHARINDEX('-', awaiting_fileno + '-') - 1)
            END as prefix,
            COUNT(*) as count
        FROM grouping 
        GROUP BY 
            CASE 
                WHEN awaiting_fileno LIKE 'CON-%-RC-%' THEN LEFT(awaiting_fileno, LEN(awaiting_fileno) - LEN(SUBSTRING(awaiting_fileno, LEN(awaiting_fileno) - CHARINDEX('-', REVERSE(awaiting_fileno)) + 2, LEN(awaiting_fileno))))
                WHEN awaiting_fileno LIKE 'CON-%' AND awaiting_fileno NOT LIKE 'CON-%-RC-%' THEN LEFT(awaiting_fileno, LEN(awaiting_fileno) - LEN(SUBSTRING(awaiting_fileno, LEN(awaiting_fileno) - CHARINDEX('-', REVERSE(awaiting_fileno)) + 2, LEN(awaiting_fileno))))
                WHEN awaiting_fileno LIKE '%-RC-%' THEN LEFT(awaiting_fileno, LEN(awaiting_fileno) - LEN(SUBSTRING(awaiting_fileno, LEN(awaiting_fileno) - CHARINDEX('-', REVERSE(awaiting_fileno)) + 2, LEN(awaiting_fileno))))
                ELSE LEFT(awaiting_fileno, CHARINDEX('-', awaiting_fileno + '-') - 1)
            END
        ORDER BY COUNT(*) DESC
    ");
    
    foreach ($prefixStats as $stat) {
        echo "  {$stat->prefix}: " . number_format($stat->count) . " records\n";
    }
    
    // Year range check
    echo "\n📅 YEAR RANGE:\n";
    $yearRange = $connection->selectOne("SELECT MIN(year) as min_year, MAX(year) as max_year FROM grouping");
    echo "  Years: {$yearRange->min_year} - {$yearRange->max_year}\n";
    
    // Sample records
    echo "\n📋 SAMPLE RECORDS:\n";
    $samples = $connection->select("SELECT TOP 5 awaiting_fileno, number, year, landuse FROM grouping ORDER BY awaiting_fileno");
    foreach ($samples as $sample) {
        echo "  {$sample->awaiting_fileno} | Number: {$sample->number} | Year: {$sample->year} | {$sample->landuse}\n";
    }
    
    // Expected vs actual
    echo "\n✅ VALIDATION:\n";
    $expectedTotal = 12 * 45 * 5000; // 12 sheets × 45 years × 5000 numbers
    echo "  Expected total: " . number_format($expectedTotal) . "\n";
    echo "  Actual total:   " . number_format($totalCount) . "\n";
    echo "  Match: " . ($totalCount == $expectedTotal ? "✅ YES" : "❌ NO") . "\n";
    
    if ($totalCount == $expectedTotal) {
        echo "\n🎉 SUCCESS: All 2.7M file numbers generated correctly!\n";
        echo "🚀 Database is ready for production use.\n";
    } else {
        echo "\n⚠️  WARNING: Record count mismatch detected.\n";
        echo "🔍 Review generation process for potential issues.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>