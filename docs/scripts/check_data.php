<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Check raw data
$sample = DB::connection('sqlsrv')
    ->table('grouping')
    ->select('pseudo_fileno', 'year', 'landuse')
    ->limit(3)
    ->get();

echo "Sample records:" . PHP_EOL;
foreach ($sample as $record) {
    echo "FileNo: {$record->pseudo_fileno}, Year: {$record->year}, LandUse: {$record->landuse}" . PHP_EOL;
}

// Check distinct years
$years = DB::connection('sqlsrv')
    ->table('grouping')
    ->select('year')
    ->distinct()
    ->orderBy('year')
    ->limit(10)
    ->get();

echo PHP_EOL . "Distinct years:" . PHP_EOL;
foreach ($years as $year) {
    echo $year->year . PHP_EOL;
}

// Check year counts
$yearCounts = DB::connection('sqlsrv')
    ->table('grouping')
    ->select('year', DB::raw('COUNT(*) as count'))
    ->groupBy('year')
    ->orderBy('year')
    ->limit(10)
    ->get();

echo PHP_EOL . "Year counts:" . PHP_EOL;
foreach ($yearCounts as $yearCount) {
    echo "{$yearCount->year}: " . number_format($yearCount->count) . PHP_EOL;
}
?>