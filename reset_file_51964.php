<?php
// Reset RES-1985-938 (file_indexing_id=51964) from page typing back to scan stage
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PageTyping;
use App\Models\Scanning;

$fileIndexingId = 51964;

// Count before
$ptCount = PageTyping::on('sqlsrv')->where('file_indexing_id', $fileIndexingId)->count();
$scanCount = Scanning::on('sqlsrv')->where('file_indexing_id', $fileIndexingId)->count();
$scanStatuses = Scanning::on('sqlsrv')->where('file_indexing_id', $fileIndexingId)->pluck('status')->toArray();

echo "=== BEFORE ===\n";
echo "PageTypings: $ptCount\n";
echo "Scannings: $scanCount\n";
echo "Scanning statuses: " . implode(', ', $scanStatuses) . "\n\n";

// Delete all pagetypings
PageTyping::on('sqlsrv')->where('file_indexing_id', $fileIndexingId)->delete();

// Reset scanning statuses back to pending
Scanning::on('sqlsrv')->where('file_indexing_id', $fileIndexingId)->update(['status' => 'pending']);

// Verify
$ptAfter = PageTyping::on('sqlsrv')->where('file_indexing_id', $fileIndexingId)->count();
$scanStatusAfter = Scanning::on('sqlsrv')->where('file_indexing_id', $fileIndexingId)->pluck('status')->toArray();

echo "=== AFTER ===\n";
echo "PageTypings: $ptAfter\n";
echo "Scanning statuses: " . implode(', ', $scanStatusAfter) . "\n";
echo "\nDone - RES-1985-938 reset to Scanned stage.\n";
