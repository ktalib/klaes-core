<?php

use Illuminate\Support\Facades\DB;

// Add tracking IDs to existing file_indexings records that don't have them
try {
    $records = DB::connection('sqlsrv')
        ->table('file_indexings')
        ->whereNull('tracking_id')
        ->get();
    
    echo "Found " . count($records) . " records without tracking IDs\n";
    
    foreach ($records as $record) {
        $trackingId = generateTrackingId();
        
        DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('id', $record->id)
            ->update(['tracking_id' => $trackingId]);
        
        echo "Updated record {$record->id} with tracking ID: {$trackingId}\n";
    }
    
    echo "All records updated successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

function generateTrackingId() {
    $segment1 = '';
    $segment2 = '';
    $characters = 'ABCDEFGHIJKLMNPQRSTUVWXYZ123456789';
    
    // Generate first segment (8 characters)
    for ($i = 0; $i < 8; $i++) {
        $segment1 .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    // Generate second segment (5 characters)
    for ($i = 0; $i < 5; $i++) {
        $segment2 .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return "TRK-{$segment1}-{$segment2}";
}