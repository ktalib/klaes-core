<?php
require 'bootstrap/app.php';

$app = $app ?? require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Checking ST file numbers in fileNumber table...\n\n";

// Get all records where SOURCE = 'ST Dept'
$stRecords = DB::connection('sqlsrv')
    ->table('fileNumber')
    ->where('SOURCE', 'ST Dept')
    ->select('id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'SOURCE', 'is_deleted')
    ->get();

echo "Found " . count($stRecords) . " ST Dept records:\n";
echo str_repeat("=", 80) . "\n";

foreach ($stRecords as $record) {
    echo "ID: {$record->id}\n";
    echo "MLS File No: " . ($record->mlsfNo ?? 'NULL') . "\n";
    echo "KANGIS File No: " . ($record->kangisFileNo ?? 'NULL') . "\n";
    echo "New KANGIS File No: " . ($record->NewKANGISFileNo ?? 'NULL') . "\n";
    echo "ST File No: " . ($record->st_file_no ?? 'NULL') . "\n";
    echo "SOURCE: " . ($record->SOURCE ?? 'NULL') . "\n";
    echo "Is Deleted: " . ($record->is_deleted ?? 'NULL') . "\n";
    echo str_repeat("-", 40) . "\n";
}

// Also check if there are records with st_file_no but different SOURCE
echo "\nChecking records with st_file_no but different SOURCE:\n";
echo str_repeat("=", 80) . "\n";

$otherStRecords = DB::connection('sqlsrv')
    ->table('fileNumber')
    ->whereNotNull('st_file_no')
    ->where('SOURCE', '!=', 'ST Dept')
    ->select('id', 'mlsfNo', 'st_file_no', 'SOURCE', 'is_deleted')
    ->get();

foreach ($otherStRecords as $record) {
    echo "ID: {$record->id}, ST File No: {$record->st_file_no}, SOURCE: {$record->SOURCE}, Is Deleted: " . ($record->is_deleted ?? 'NULL') . "\n";
}

// Check the specific ST file numbers mentioned
echo "\nChecking for specific ST file numbers mentioned:\n";
echo str_repeat("=", 80) . "\n";

$stNumbers = [
    'ST-RES-2025-1',
    'ST-COM-2025-1-001',
    'ST-COM-2025-2-001', 
    'ST-COM-2025-3-001',
    'ST-COM-2025-4',
    'ST-MIXED-2025-1'
];

foreach ($stNumbers as $stNumber) {
    $found = DB::connection('sqlsrv')
        ->table('fileNumber')
        ->where(function($query) use ($stNumber) {
            $query->where('st_file_no', 'LIKE', "%{$stNumber}%")
                  ->orWhere('mlsfNo', 'LIKE', "%{$stNumber}%")
                  ->orWhere('kangisFileNo', 'LIKE', "%{$stNumber}%")
                  ->orWhere('NewKANGISFileNo', 'LIKE', "%{$stNumber}%");
        })
        ->select('id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'SOURCE', 'is_deleted')
        ->get();
    
    echo "Searching for: {$stNumber}\n";
    if ($found->count() > 0) {
        foreach ($found as $record) {
            echo "  Found in ID: {$record->id}, SOURCE: {$record->SOURCE}, is_deleted: " . ($record->is_deleted ?? 'NULL') . "\n";
            echo "    mlsfNo: " . ($record->mlsfNo ?? 'NULL') . "\n";
            echo "    st_file_no: " . ($record->st_file_no ?? 'NULL') . "\n";
        }
    } else {
        echo "  NOT FOUND\n";
    }
    echo "\n";
}