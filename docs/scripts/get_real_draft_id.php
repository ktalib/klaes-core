<?php

require_once 'bootstrap/app.php';

use Illuminate\Support\Facades\DB;

try {
    echo "Getting real draft ID from database...\n\n";
    
    $draft = DB::connection('sqlsrv')
        ->table('mother_application_draft')
        ->select('draft_id', 'np_file_no', 'created_at')
        ->orderBy('created_at', 'desc')
        ->first();
        
    if ($draft) {
        echo "Most recent draft:\n";
        echo "Draft ID: {$draft->draft_id}\n";
        echo "File Number: " . ($draft->np_file_no ?: 'NONE') . "\n";
        echo "Created: {$draft->created_at}\n\n";
        
        // Check if this draft has a reservation
        $reservation = DB::connection('sqlsrv')
            ->table('file_number_reservations')
            ->where('draft_id', $draft->draft_id)
            ->first();
            
        if ($reservation) {
            echo "✅ Has reservation: {$reservation->file_number}\n";
        } else {
            echo "❌ No reservation found\n";
        }
    } else {
        echo "No drafts found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}