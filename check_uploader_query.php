<?php
// Query to inspect scannings and their uploaders
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';

use Illuminate\Support\Facades\DB;

echo "=== SCANNINGS WITH uploaded_by ===\n\n";

// Get scannings with their uploaded_by IDs
$scannings = DB::connection('sqlsrv')->table('scannings')
    ->select('id', 'file_indexing_id', 'uploaded_by', 'created_at')
    ->whereNotNull('uploaded_by')
    ->orderByDesc('created_at')
    ->limit(10)
    ->get();

foreach ($scannings as $scanning) {
    echo "Scan ID: {$scanning->id}\n";
    echo "  file_indexing_id: {$scanning->file_indexing_id}\n";
    echo "  uploaded_by: {$scanning->uploaded_by}\n";
    echo "  created_at: {$scanning->created_at}\n";
    
    // Try to find the user
    $user = DB::connection('sqlsrv')->table('users')
        ->where('id', $scanning->uploaded_by)
        ->select('id', 'first_name', 'last_name', 'name')
        ->first();
    
    if ($user) {
        echo "  ✓ User found:\n";
        echo "    ID: {$user->id}\n";
        echo "    first_name: " . ($user->first_name ?? 'NULL') . "\n";
        echo "    last_name: " . ($user->last_name ?? 'NULL') . "\n";
        echo "    name: " . ($user->name ?? 'NULL') . "\n";
    } else {
        echo "  ✗ User NOT found (ID {$scanning->uploaded_by})\n";
    }
    echo "\n";
}

echo "\n=== SUMMARY ===\n";
$totalScans = DB::connection('sqlsrv')->table('scannings')->count();
$scansWithUploader = DB::connection('sqlsrv')->table('scannings')->whereNotNull('uploaded_by')->count();
$scansWithoutUploader = DB::connection('sqlsrv')->table('scannings')->whereNull('uploaded_by')->count();

echo "Total scannings: {$totalScans}\n";
echo "With uploaded_by: {$scansWithUploader}\n";
echo "Without uploaded_by (NULL): {$scansWithoutUploader}\n";
