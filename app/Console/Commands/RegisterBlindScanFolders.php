<?php

namespace App\Console\Commands;

use App\Models\BlindScanning;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Record the images sitting in EDMS/BLIND_SCAN/{Registry}_Raw in blind_scannings.
 *
 * The Blind Scan page does this from the browser ("Sync Migration Log"), but it
 * walks every folder inside one 600-second request. With 140k+ folders it times
 * out long before reaching new ones, and empty folders never get a row, so
 * every run re-scans them first. This command does the same work with no time
 * limit:
 *
 *   - rows are shaped exactly as the page's sync writes them (MIGRATE_ ids,
 *     status pending, paper size from the A3/A4 folder, local_pc_path
 *     "{Registry}_Raw/{folder}");
 *   - only images without a row are added, matched on document_path, so a
 *     folder the page half-recorded is completed, never duplicated, and the
 *     command can be stopped and re-run at any point;
 *   - each folder is inserted in its own transaction, so a folder is never
 *     left half-recorded by this command;
 *   - PDFs are recorded as one row each and are NOT converted: conversion
 *     writes page images into the folder, which is a separate decision.
 *
 * Inserts only. It never updates or deletes a blind_scannings row and never
 * touches a file on disk.
 */
class RegisterBlindScanFolders extends Command
{
    protected $signature = 'blind-scan:register
                            {--registry=lands : lands, cadastral or all}
                            {--folder=* : Only these folder names (e.g. COM-2000-470)}
                            {--since= : Only folders created on or after this date, e.g. 2026-10-06}
                            {--limit=0 : Stop after this many folders that needed rows (0 = no limit)}
                            {--user=1 : uploaded_by for the new rows}
                            {--dry-run : Count what would be inserted without writing}';

    protected $description = 'Record blind-scan images on disk that have no blind_scannings row (insert-only, resumable)';

    private const ROOT = 'EDMS/BLIND_SCAN';

    private const REGISTRIES = [
        'lands' => ['folder' => 'Lands_Registry_Raw', 'type' => 'lands'],
        'cadastral' => ['folder' => 'Cadastral_Registry_Raw', 'type' => 'cadastral'],
    ];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'tif', 'tiff', 'bmp', 'gif', 'webp', 'pdf'];

    /** SQL Server allows 2100 bound parameters per statement; 16 columns a row. */
    private const ROWS_PER_INSERT = 120;

    public function handle(): int
    {
        @set_time_limit(0);

        $registryOption = strtolower((string) $this->option('registry'));
        $registries = $registryOption === 'all'
            ? array_values(self::REGISTRIES)
            : array_filter([self::REGISTRIES[$registryOption] ?? null]);
        if (!$registries) {
            $this->error('--registry must be lands, cadastral or all.');
            return self::FAILURE;
        }

        $since = $this->option('since') ? strtotime((string) $this->option('since')) : null;
        if ($this->option('since') && !$since) {
            $this->error('--since is not a date.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $onlyFolders = array_map('strtolower', (array) $this->option('folder'));

        $totals = ['folders' => 0, 'empty' => 0, 'complete' => 0, 'filled' => 0, 'rows' => 0, 'pdf' => 0, 'skipped_files' => 0, 'failed' => 0];
        $failures = [];

        foreach ($registries as $registry) {
            $base = file_storage_path('app/public/' . self::ROOT . '/' . $registry['folder']);
            if (!is_dir($base)) {
                $this->warn("Missing folder: {$base}");
                continue;
            }

            $this->info(($dryRun ? '[dry run] ' : '') . "Scanning {$base}");

            // Folders that already have rows, with their row counts: lets a
            // folder that is clearly complete be skipped without a query.
            // One grouped query (a few seconds); chunk() would re-run it per page.
            $recorded = [];
            $grouped = DB::connection('sqlsrv')->select(
                'SELECT local_pc_path, COUNT(*) AS n FROM blind_scannings WHERE local_pc_path LIKE ? GROUP BY local_pc_path',
                [$registry['folder'] . '/%']
            );
            foreach ($grouped as $row) {
                $recorded[strtolower((string) $row->local_pc_path)] = (int) $row->n;
            }
            $this->line('  ' . count($recorded) . ' folders already have rows.');

            foreach (new \DirectoryIterator($base) as $entry) {
                if ($limit && $totals['filled'] >= $limit) {
                    break 2;
                }
                if ($entry->isDot() || !$entry->isDir()) {
                    continue;
                }

                $folderName = $entry->getFilename();
                if ($onlyFolders && !in_array(strtolower($folderName), $onlyFolders, true)) {
                    continue;
                }
                if ($since && @filectime($entry->getPathname()) < $since) {
                    continue;
                }

                $totals['folders']++;
                $relativeFolder = $registry['folder'] . '/' . $folderName;

                try {
                    $files = $this->imagesIn($entry->getPathname(), $totals);
                    if (!$files) {
                        $totals['empty']++;
                        continue;
                    }

                    $already = $recorded[strtolower($relativeFolder)] ?? 0;
                    $missing = $this->missingFiles($files, $relativeFolder, $already);
                    if (!$missing) {
                        $totals['complete']++;
                        continue;
                    }

                    $rows = $this->rowsFor($missing, $registry, $folderName, $relativeFolder);
                    $totals['pdf'] += count(array_filter($missing, fn ($f) => $f['extension'] === 'pdf'));

                    if (!$dryRun) {
                        DB::connection('sqlsrv')->transaction(function () use ($rows) {
                            foreach (array_chunk($rows, self::ROWS_PER_INSERT) as $batch) {
                                BlindScanning::insert($batch);
                            }
                        });
                    }

                    $totals['filled']++;
                    $totals['rows'] += count($rows);
                    if ($totals['filled'] % 250 === 0) {
                        $this->line("  {$totals['filled']} folders filled, {$totals['rows']} rows" . ($dryRun ? ' (dry run)' : '') . " - last: {$folderName}");
                    }
                } catch (\Throwable $e) {
                    $totals['failed']++;
                    $failures[] = [$relativeFolder, mb_substr($e->getMessage(), 0, 160)];
                    report($e);
                }
            }
        }

        $this->newLine();
        $this->table(['', 'count'], [
            ['Folders looked at', $totals['folders']],
            ['  empty (no images)', $totals['empty']],
            ['  already complete', $totals['complete']],
            ['  ' . ($dryRun ? 'would be filled' : 'filled'), $totals['filled']],
            ['  failed', $totals['failed']],
            [($dryRun ? 'Rows that would be inserted' : 'Rows inserted'), $totals['rows']],
            ['  of which PDFs (one row each, not converted)', $totals['pdf']],
            ['Non-image files ignored', $totals['skipped_files']],
        ]);

        if ($failures) {
            $this->warn('Failed folders (nothing was written for these):');
            $this->table(['folder', 'error'], array_slice($failures, 0, 50));
        }

        return $failures ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Every image under a file-number folder, relative path with forward slashes.
     */
    private function imagesIn(string $folder, array &$totals): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($folder) + 1));
            // Originals the page archives after converting a PDF are not pages.
            if (str_contains(strtolower('/' . $relative), '/_pdf_originals/')) {
                continue;
            }
            $extension = strtolower($file->getExtension());
            if (!in_array($extension, self::IMAGE_EXTENSIONS, true)) {
                $totals['skipped_files']++;
                continue;
            }
            $files[] = ['relative' => $relative, 'name' => $file->getFilename(), 'extension' => $extension];
        }

        return $files;
    }

    /**
     * The files with no row yet, matched on document_path (case-insensitive,
     * as SQL Server compares it).
     */
    private function missingFiles(array $files, string $relativeFolder, int $alreadyRecorded): array
    {
        if ($alreadyRecorded === 0) {
            return $files;
        }
        // At least as many rows as images: the folder is done. This avoids a
        // full-table lookup (the path columns are not indexed) for every one
        // of the ~28k folders the page already recorded.
        if ($alreadyRecorded >= count($files)) {
            return [];
        }

        $prefix = self::ROOT . '/' . $relativeFolder . '/';
        $existing = [];
        DB::connection('sqlsrv')->table('blind_scannings')
            ->where('local_pc_path', $relativeFolder)
            ->orWhereRaw('LEFT(document_path, ?) = ?', [strlen($prefix), $prefix])
            ->pluck('document_path')
            ->each(function ($path) use (&$existing) {
                $existing[strtolower(str_replace('\\', '/', (string) $path))] = true;
            });

        return array_values(array_filter($files, fn ($f) => !isset($existing[strtolower($prefix . $f['relative'])])));
    }

    private function rowsFor(array $files, array $registry, string $folderName, string $relativeFolder): array
    {
        $now = now();
        $fileNumber = $this->fileNumberFromFolder($folderName);
        $uploader = (int) $this->option('user') ?: 1;

        return array_map(function ($file) use ($now, $fileNumber, $uploader, $registry, $folderName, $relativeFolder) {
            $paperSize = $this->paperSize($file['relative']);
            $isA3 = $paperSize === 'A3';

            return [
                'temp_file_id' => 'MIGRATE_' . microtime(true) . '_' . uniqid() . '_' . str_replace([' ', '.'], ['_', ''], $file['name']),
                'file_number' => $fileNumber,
                'local_pc_path' => $relativeFolder,
                'original_filename' => $file['name'],
                'document_path' => self::ROOT . '/' . $registry['folder'] . '/' . $folderName . '/' . $file['relative'],
                'paper_size' => $paperSize,
                'document_type' => $this->documentType($file['name']),
                'a4_count' => $isA3 ? 0 : 1,
                'a3_count' => $isA3 ? 1 : 0,
                'total_pages' => 1,
                'notes' => "Migrated from folder: {$folderName}",
                'status' => BlindScanning::STATUS_PENDING,
                'uploaded_by' => $uploader,
                'created_at' => $now,
                'updated_at' => $now,
                'registry_type' => $registry['type'],
            ];
        }, $files);
    }

    /** Same rules as BlindScanningController::detectPaperSizeFromPath(). */
    private function paperSize(string $relative): string
    {
        if (str_contains($relative, '/A3/') || stripos($relative, 'A3/') === 0) {
            return 'A3';
        }

        return 'A4';
    }

    /** Same rules as BlindScanningController::detectDocumentType(). */
    private function documentType(string $filename): string
    {
        $name = strtolower($filename);
        foreach (['deed' => 'Deed', 'survey' => 'Survey Plan', 'certificate' => 'Certificate', 'application' => 'Application', 'receipt' => 'Receipt'] as $needle => $type) {
            if (str_contains($name, $needle)) {
                return $type;
            }
        }

        return 'Document';
    }

    /** Same rules as BlindScanningController::extractFileNumberFromFolderName(). */
    private function fileNumberFromFolder(string $folderName): string
    {
        $clean = preg_replace('/_\d{6}$/', '', $folderName);

        if (preg_match('/^([A-Za-z]+)[\-_\/](\d+(?:[\-_\/]\d+)*)/', $clean, $m)) {
            return $m[1] . '-' . str_replace(['/', '_'], '-', $m[2]);
        }
        if (preg_match('/(\d{6,})/', $clean, $m)) {
            return $m[1];
        }

        return $clean;
    }
}
