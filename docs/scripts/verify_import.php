<?php
// Quick verification script for imported data
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Grouping;

echo "Database Verification\n";
echo "====================\n\n";

// Total count
$total = Grouping::count();
echo "Total records in grouping table: " . number_format($total) . "\n\n";

// Count by land use
$landUseStats = Grouping::select('landuse', DB::raw('COUNT(*) as count'))
    ->groupBy('landuse')
    ->orderBy('count', 'desc')
    ->get();

echo "Records by Land Use:\n";
foreach ($landUseStats as $stat) {
    echo "  {$stat->landuse}: " . number_format($stat->count) . "\n";
}

echo "\nRecords by Year:\n";
$yearStats = Grouping::select('year', DB::raw('COUNT(*) as count'))
    ->groupBy('year')
    ->orderBy('year')
    ->limit(10)
    ->get();

foreach ($yearStats as $stat) {
    echo "  {$stat->year}: " . number_format($stat->count) . "\n";
}

echo "\nSample records:\n";
$samples = Grouping::limit(5)->get();

foreach ($samples as $sample) {
    echo "  {$sample->pseudo_fileno} | {$sample->year} | {$sample->landuse} | {$sample->created_by}\n";
}

echo "\nTesting model scopes:\n";
$mappedCount = Grouping::mapped()->count();
$unmappedCount = Grouping::unmapped()->count();
$currentYearCount = Grouping::currentYear()->count();
$commercialCount = Grouping::byLanduse('COMMERCIAL')->count();

echo "  Mapped records: " . number_format($mappedCount) . "\n";
echo "  Unmapped records: " . number_format($unmappedCount) . "\n";
echo "  Current year (2025) records: " . number_format($currentYearCount) . "\n";
echo "  Commercial records: " . number_format($commercialCount) . "\n";

echo "\nImport verification complete!\n";
?>