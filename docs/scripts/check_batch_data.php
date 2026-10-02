<?php
// Quick database check script
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check current batch data
$results = DB::connection('sqlsrv')
    ->table('file_indexings')
    ->select(
        'batch_no',
        'shelf_location', 
        'shelf_label_id',
        DB::raw('COUNT(*) as count'),
        DB::raw('MAX(serial_no) as max_serial')
    )
    ->where(function($query) {
        $query->where('is_deleted', 0)->orWhereNull('is_deleted');
    })
    ->whereNotNull('batch_no')
    ->groupBy('batch_no', 'shelf_location', 'shelf_label_id')
    ->orderBy('batch_no', 'desc')
    ->limit(10)
    ->get();

echo "Current batch data from file_indexings table:\n";
echo "================================================\n";

foreach ($results as $result) {
    echo "Batch: {$result->batch_no}, Count: {$result->count}, Max Serial: {$result->max_serial}, Shelf: {$result->shelf_location}, Shelf ID: {$result->shelf_label_id}\n";
}

echo "\n";

// Check the specific batch 35 you mentioned
$batch35 = DB::connection('sqlsrv')
    ->table('file_indexings')
    ->where('batch_no', 35)
    ->where(function($query) {
        $query->where('is_deleted', 0)->orWhereNull('is_deleted');
    })
    ->select('shelf_location', 'shelf_label_id', 'serial_no', 'file_number')
    ->get();

echo "Batch 35 details:\n";
echo "=================\n";
echo "Total records: " . $batch35->count() . "\n";

if ($batch35->count() > 0) {
    $first = $batch35->first();
    echo "Shelf Location: {$first->shelf_location}\n";
    echo "Shelf Label ID: {$first->shelf_label_id}\n";
    
    echo "\nAll records in batch 35:\n";
    foreach ($batch35 as $record) {
        echo "Serial: {$record->serial_no}, File: {$record->file_number}\n";
    }
}
?>