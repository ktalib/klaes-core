<?php

// Use Artisan to bootstrap Laravel properly
$kernel = new class extends \Illuminate\Foundation\Console\Kernel {
    protected $commands = [];
};

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== LATEST SCANNINGS WITH uploaded_by ===\n\n";

$scans = DB::connection('sqlsrv')->table('scannings')
    ->whereNotNull('uploaded_by')
    ->orderByDesc('id')
    ->select('id', 'uploaded_by', 'created_at')
    ->limit(5)
    ->get();

foreach ($scans as $scan) {
    echo "Scan ID: {$scan->id}\n";
    echo "  uploaded_by: {$scan->uploaded_by}\n";
    echo "  created_at: {$scan->created_at}\n";
    
    $user = DB::connection('sqlsrv')->table('users')
        ->where('id', $scan->uploaded_by)
        ->select('id', 'first_name', 'last_name', 'name')
        ->first();
    
    if ($user) {
        echo "  ✓ User found:\n";
        echo "    first_name: " . ($user->first_name ?? 'NULL') . "\n";
        echo "    last_name: " . ($user->last_name ?? 'NULL') . "\n";
        echo "    name: " . ($user->name ?? 'NULL') . "\n";
    } else {
        echo "  ✗ User NOT found\n";
    }
    echo "\n";
}

echo "=== STATISTICS ===\n";
$totalScans = DB::connection('sqlsrv')->table('scannings')->count();
$withUploader = DB::connection('sqlsrv')->table('scannings')->whereNotNull('uploaded_by')->count();
$nullUploader = DB::connection('sqlsrv')->table('scannings')->whereNull('uploaded_by')->count();

echo "Total scannings: {$totalScans}\n";
echo "With uploaded_by: {$withUploader}\n";
echo "NULL uploaded_by: {$nullUploader}\n";

echo "\n=== SAMPLE USERS ===\n";
$users = DB::connection('sqlsrv')->table('users')
    ->select('id', 'first_name', 'last_name', 'name')
    ->limit(5)
    ->get();

foreach ($users as $user) {
    echo "ID {$user->id}: {$user->first_name} {$user->last_name} (name: {$user->name})\n";
}
