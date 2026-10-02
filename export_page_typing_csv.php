<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$exportDir = __DIR__ . '/storage/app/public/exports';
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0777, true);
}

$timestamp = date('Ymd_His');
$outputPath = $exportDir . '/page_typing_summary_' . $timestamp . '.csv';

// Load raw page typing rows to aggregate in PHP (avoids SQL type-conversion issues)
// We'll aggregate by file_indexing_id but only for files that have scannings (matches UI logic)

// Get all files that have scannings and at least one pagetyping
// Use chunking to avoid loading all records into memory
$summary = [];

$fileQuery = \App\Models\FileIndexing::on('sqlsrv')
    ->with(['scannings', 'pagetypings.typedBy'])
    ->whereHas('scannings')
    ->orderBy('id');

$fileQuery->chunkById(500, function ($files) use (&$summary) {
    foreach ($files as $file) {
        $typedPages = $file->pagetypings->count();
        $totalScans = $file->scannings->count();

        if ($totalScans === 0) continue;

        $pageCodes = $file->pagetypings->pluck('page_code')->filter()->unique()->values()->toArray();
        $typers = $file->pagetypings->map(function ($pt) {
            return trim(($pt->typedBy->first_name ?? '') . ' ' . ($pt->typedBy->last_name ?? ''));
        })->filter()->unique()->values()->toArray();

        $summary[$file->id] = [
            'id' => $file->id,
            'file_number' => $file->file_number,
            'file_title' => $file->file_title,
            'number_of_pages' => $typedPages,
            'registry' => $file->registry ?? null,
            'page_codes' => $pageCodes,
            'typers' => $typers,
            'created_at' => $file->created_at,
            'scans_count' => $totalScans,
        ];
    }
});

// Compute dashboard counts using same logic as controller for verification
$pendingCount = \App\Models\FileIndexing::on('sqlsrv')
    ->whereHas('scannings')
    ->whereDoesntHave('pagetypings')
    ->count();

$inProgressCount = 0;
$completedCount = 0;

\App\Models\FileIndexing::on('sqlsrv')
    ->whereHas('scannings')
    ->whereHas('pagetypings')
    ->with(['scannings', 'pagetypings'])
    ->chunkById(500, function ($files) use (&$inProgressCount, &$completedCount) {
        foreach ($files as $file) {
            $totalPages = $file->scannings->count();
            $typedPages = $file->pagetypings->count();
            if ($typedPages > 0 && $typedPages < $totalPages) {
                $inProgressCount++;
            }
            if ($totalPages > 0 && $typedPages >= $totalPages) {
                $completedCount++;
            }
        }
    });

$handle = fopen($outputPath, 'w');
if ($handle === false) {
    fwrite(STDERR, "Unable to create export file: {$outputPath}\n");
    exit(1);
}

fwrite($handle, "\xEF\xBB\xBF");
fputcsv($handle, [
    'id',
    'file_number',
    'file_title',
    'number_of_pages',
    'registry',
    'page_code',
    'typed_by_full_name',
    'created_at',
]);

$count = 0;
foreach ($summary as $s) {
    $pageCode = implode('; ', $s['page_codes']);
    $typers = implode('; ', $s['typers']);

    fputcsv($handle, [
        $s['id'],
        $s['file_number'],
        $s['file_title'],
        $s['number_of_pages'],
        $s['registry'],
        $pageCode,
        $typers,
        $s['created_at'],
    ]);
    $count++;
}

fclose($handle);
echo "Exported summary of " . $count . " files to {$outputPath}\n";

// Print dashboard counts for comparison with UI
echo "Dashboard counts (computed):\n";
echo "  Pending: " . $pendingCount . "\n";
echo "  In Progress: " . $inProgressCount . "\n";
echo "  Completed: " . $completedCount . "\n";

echo "Exported summary rows: " . $count . "\n";

// Provide a quick consistency note
$totalUi = $pendingCount + $inProgressCount + $completedCount;
echo "UI total (pending+in_progress+completed): " . $totalUi . "\n";
if ($totalUi !== $count) {
    echo "Note: totals differ — export rows (" . $count . ") vs UI total (" . $totalUi . ").\n";
} else {
    echo "Totals match.\n";
}
