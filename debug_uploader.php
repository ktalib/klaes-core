<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use App\Models\Scanning;
use App\Models\User;

// Get latest scans with uploaded_by
$scans = Scanning::on('sqlsrv')->whereNotNull('uploaded_by')->with('uploader')->limit(3)->get();

foreach ($scans as $scan) {
    echo "=== Scan ID: {$scan->id} ===\n";
    echo "uploaded_by: " . ($scan->uploaded_by ?? 'NULL') . "\n";
    echo "uploader relationship loaded: " . ($scan->uploader ? 'YES' : 'NO') . "\n";
    
    if ($scan->uploader) {
        echo "User ID: {$scan->uploader->id}\n";
        echo "first_name: " . ($scan->uploader->first_name ?? 'NULL') . "\n";
        echo "last_name: " . ($scan->uploader->last_name ?? 'NULL') . "\n";
        echo "name: " . ($scan->uploader->name ?? 'NULL') . "\n";
    } else {
        // Try to manually find the user
        $user = User::on('sqlsrv')->find($scan->uploaded_by);
        if ($user) {
            echo "User found by manual lookup\n";
            echo "first_name: " . ($user->first_name ?? 'NULL') . "\n";
            echo "last_name: " . ($user->last_name ?? 'NULL') . "\n";
        } else {
            echo "User NOT found for ID: {$scan->uploaded_by}\n";
        }
    }
    echo "\n";
}
