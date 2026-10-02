<?php

namespace App\Services\ScanUploads;

use App\Models\BlindScanning;
use App\Models\Scanning;
use App\Services\Edms\EdmsFileType;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BlindScanIngestionService
{
    /**
     * Storage base paths.
     */
    private const BLIND_SCAN_ROOT = 'EDMS/BLIND_SCAN';
    private const SCAN_UPLOAD_ROOT = 'EDMS/SCAN_UPLOAD';

    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Discover files that are waiting under the blind scan staging folder.
     *
     * @param  string  $rawFileNumber
     * @return array
     */
    public function discover(string $rawFileNumber): array
    {
        $fileNumber = $this->normalizeFileNumber($rawFileNumber);
        [$blindFolder, $blindRelative] = $this->resolveBlindFolder($fileNumber);

        $files = $this->collectFiles($blindFolder, $blindRelative, $fileNumber);
        if ($files->isEmpty()) {
            return [
                'file_number' => $fileNumber,
                'blind_folder' => $blindRelative,
                'files' => [],
                'counts' => [
                    'total' => 0,
                    'by_paper_size' => [],
                    'by_document_type' => [],
                ],
            ];
        }

        return [
            'file_number' => $fileNumber,
            'blind_folder' => $blindRelative,
            'files' => $files->all(),
            'counts' => [
                'total' => $files->count(),
                'by_paper_size' => $files->groupBy('paper_size')->map(function ($g) {
                    return $g->count();
                })->toArray(),
                'by_document_type' => $files->groupBy('document_type')->map(function ($g) {
                    return $g->count();
                })->toArray(),
            ],
        ];
    }

    /**
     * Move discovered files into the scan uploads structure and create Scanning records.
     *
     * @param  string  $rawFileNumber
     * @param  array<int,array<string,mixed>>  $requestedFiles
     * @param  int|null  $fileIndexingId
     * @param  string|null  $fileType  EDMS master folder key; null keeps the files
     *                                 directly under the registry, as before
     * @return array
     */
    public function transfer(string $rawFileNumber, array $requestedFiles, ?int $fileIndexingId = null, ?string $registry = null, ?string $fileType = null): array
    {
        $fileNumber = $this->normalizeFileNumber($rawFileNumber);
        [$blindFolder, $blindRelative] = $this->resolveBlindFolder($fileNumber);

        $registryMap = [
            'Lands Registry' => 'Lands_Registry',
            'Cadastral Registry' => 'Cadastral_Registry',
            'DCIV Registry' => 'DCIV_Registry',
            'Secret Registry' => 'Secret_Registry',
            'KANGIS Registry' => 'KANGIS_Registry',
            'SLTR Registry' => 'SLTR_Registry',
            'ST Registry' => 'ST_Registry',
            'Deeds Registry' => 'Deeds_Registry',
        ];

        // Map numeric IDs or fall back to Lands Registry
        $normalizedRegistryName = $registry;
        if (in_array($registry, ['1', '2', '3'])) {
            $normalizedRegistryName = 'Lands Registry';
        }

        $registrySubFolder = $registryMap[$normalizedRegistryName] ?? 'Lands_Registry';

        // The EDMS master folder sits between the registry and the file number, so
        // ingested files land in the same folder a direct upload would have used.
        $fileType = EdmsFileType::normalize($fileType);
        $typeFolder = EdmsFileType::folder($fileType);
        if ($typeFolder !== null) {
            $registrySubFolder .= '/' . $typeFolder;
        }

        $discovered = $this->collectFiles($blindFolder, $blindRelative, $fileNumber, $registrySubFolder)->keyBy('relative_path');
        if ($discovered->isEmpty()) {
            return ['file_number' => $fileNumber, 'files' => []];
        }

        // Filter to requested files only; if none specified assume all.
        $targetFiles = empty($requestedFiles)
            ? $discovered
            : $this->filterRequestedFiles($requestedFiles, $discovered);

        if ($targetFiles->isEmpty()) {
            return ['file_number' => $fileNumber, 'files' => []];
        }

        $scanUploadDirectory = file_storage_path('app/public/' . self::SCAN_UPLOAD_ROOT . '/' . $registrySubFolder . '/' . $fileNumber);
        $this->filesystem->ensureDirectoryExists($scanUploadDirectory, 0775, true);

        $movedFiles = DB::connection('sqlsrv')->transaction(function () use ($targetFiles, $blindFolder, $scanUploadDirectory, $fileIndexingId, $fileNumber, $blindRelative, $registry, $fileType) {
            $createdScans = [];
            $displayOrder = 0;

            foreach ($targetFiles as $manifest) {
                $source = $blindFolder . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $manifest['relative_path']);
                $destination = $scanUploadDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $manifest['target_relative_path']);

                $this->filesystem->ensureDirectoryExists(dirname($destination), 0775, true);
                if (!$this->filesystem->move($source, $destination)) {
                    throw new FileNotFoundException("Unable to move {$manifest['relative_path']} to scan uploads.");
                }

                $scan = $this->createScanningRecord($fileIndexingId, $manifest, $destination, $displayOrder++, $registry, $fileType);
                $createdScans[] = $scan->fresh(['fileIndexing', 'uploader']);
                $this->markBlindScanRecord($manifest, $fileNumber, $blindRelative, $fileIndexingId, $scan->id);
                $this->logMove($fileNumber, $manifest, $scan->id, $destination);
            }

            $this->cleanupIfEmpty($blindFolder);

            return $createdScans;
        });

        return [
            'file_number' => $fileNumber,
            'files' => $movedFiles,
        ];
    }

    private function normalizeFileNumber(string $value): string
    {
        $normalized = str_replace(['\\'], '/', trim($value));
        $normalized = preg_replace('/\s+/', '', $normalized);
        $normalized = str_replace('..', '', $normalized);

        return preg_replace('/[^A-Za-z0-9\-\/]/', '', $normalized) ?? '';
    }

    private function buildBlindScanPath(string $fileNumber): string
    {
        return file_storage_path('app/public/' . self::BLIND_SCAN_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $fileNumber));
    }

    private function buildScanUploadPath(string $fileNumber): string
    {
        return file_storage_path('app/public/' . self::SCAN_UPLOAD_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $fileNumber));
    }

    /**
     * @param  string|null  $registrySubFolder  Registry folder the files will land in.
     *                                          Required for target_storage_path to match
     *                                          where transfer() actually writes them.
     */
    private function collectFiles(string $folder, string $blindRelative, string $fileNumber, ?string $registrySubFolder = null): Collection
    {
        $targetBase = self::SCAN_UPLOAD_ROOT . '/'
            . ($registrySubFolder ? $registrySubFolder . '/' : '')
            . $fileNumber;

        $files = collect();

        $iterator = $this->filesystem->allFiles($folder);
        foreach ($iterator as $file) {
            $relativePath = ltrim(str_replace('\\', '/', Str::after($file->getPathname(), $folder)), '/');
            if (str_contains(strtolower($relativePath), '_pdf_originals/')) {
                continue;
            }
            $paperSize = $this->detectPaperSizeFromPath($relativePath);
            $targetFilename = $this->buildTargetFilename($file->getFilename());
            $targetRelativePath = $paperSize . '/' . $targetFilename;

            $files->push([
                'name' => $file->getFilename(),
                'relative_path' => $relativePath,
                'size' => $file->getSize(),
                'paper_size' => $this->detectPaperSizeFromPath($relativePath),
                'document_type' => $this->detectDocumentTypeFromName($file->getFilename()),
                'target_relative_path' => $targetRelativePath,
                'original_path' => $file->getPathname(),
                'blind_storage_path' => self::BLIND_SCAN_ROOT . '/' . $blindRelative . '/' . $relativePath,
                'target_storage_path' => $targetBase . '/' . $targetRelativePath,
            ]);
        }

        return $files;
    }

    private function resolveBlindFolder(string $fileNumber): array
    {
        $startTime = microtime(true);
        Log::info('BlindScan: Resolving folder start', ['file_number' => $fileNumber]);

        $normalizedFileNumber = $this->normalizeFileNumber($fileNumber);
        $needle = strtolower(preg_replace('/[^a-z0-9]/', '', $normalizedFileNumber) ?? '');

        // 1. Try Direct/Known Locations (Fastest)
        $commonRegistries = ['', 'Lands_Registry_Raw', 'Cadastral_Registry_Raw', 'Lands_Registry', 'Cadastral_Registry'];
        foreach ($commonRegistries as $reg) {
            $relative = $reg ? $reg . '/' . $fileNumber : $fileNumber;
            $directPath = $this->buildBlindScanPath($relative);

            if ($this->filesystem->isDirectory($directPath)) {
                Log::info('BlindScan: Found via direct/registry path', [
                    'path' => $directPath,
                    'relative' => $relative,
                    'duration' => microtime(true) - $startTime
                ]);
                return [$directPath, $relative];
            }
        }

        // 2. Try Exact Match in Registries/Subdirectories (Iterative search)
        $root = file_storage_path('app/public/' . self::BLIND_SCAN_ROOT);
        if ($this->filesystem->isDirectory($root)) {
            $topDirs = $this->filesystem->directories($root);
            foreach ($topDirs as $topDir) {
                $dirName = basename($topDir);
                $cleanName = strtolower(preg_replace('/[^a-z0-9]/', '', $dirName) ?? '');

                // Level 1 check
                if ($cleanName === $needle) {
                    Log::info('BlindScan: Found at Level 1', [
                        'path' => $topDir,
                        'duration' => microtime(true) - $startTime
                    ]);
                    return [$topDir, $dirName];
                }

                // Level 2 check
                $subDirs = $this->filesystem->directories($topDir);
                foreach ($subDirs as $subDir) {
                    $subDirName = basename($subDir);
                    $cleanSubName = strtolower(preg_replace('/[^a-z0-9]/', '', $subDirName) ?? '');

                    if ($cleanSubName === $needle) {
                        $relativeResult = $dirName . '/' . $subDirName;
                        Log::info('BlindScan: Found at Level 2', [
                            'path' => $subDir,
                            'duration' => microtime(true) - $startTime
                        ]);
                        return [$subDir, $relativeResult];
                    }

                    // Level 3 check (Deep search - only for relevant registries to prevent massive crawls)
                    if (in_array($dirName, ['Lands_Registry_Raw', 'Cadastral_Registry_Raw'])) {
                        $deepDirs = $this->filesystem->directories($subDir);
                        foreach ($deepDirs as $deepDir) {
                            $deepDirName = basename($deepDir);
                            $cleanDeepName = strtolower(preg_replace('/[^a-z0-9]/', '', $deepDirName) ?? '');

                            if ($cleanDeepName === $needle) {
                                $relativeResult = $dirName . '/' . $subDirName . '/' . $deepDirName;
                                Log::info('BlindScan: Found at Level 3', [
                                    'path' => $deepDir,
                                    'duration' => microtime(true) - $startTime
                                ]);
                                return [$deepDir, $relativeResult];
                            }
                        }
                    }
                }
            }
        }

        // 3. Fallback: Try to resolve from database history
        try {
            $existingRecord = BlindScanning::on('sqlsrv')
                ->where('file_number', $fileNumber)
                ->orderByDesc('created_at')
                ->first();

            if ($existingRecord && $existingRecord->document_path) {
                $documentPath = str_replace(['\\', '/', '//'], '/', $existingRecord->document_path);
                if (str_starts_with($documentPath, self::BLIND_SCAN_ROOT . '/')) {
                    $relativeRemainder = Str::after($documentPath, self::BLIND_SCAN_ROOT . '/');
                    $segments = explode('/', $relativeRemainder);

                    $pathAccumulator = '';
                    foreach ($segments as $segment) {
                        $pathAccumulator = $pathAccumulator ? $pathAccumulator . '/' . $segment : $segment;
                        if (strtolower(preg_replace('/[^a-z0-9]/', '', $segment) ?? '') === $needle) {
                            $candidatePath = file_storage_path('app/public/' . self::BLIND_SCAN_ROOT . '/' . $pathAccumulator);
                            if ($this->filesystem->isDirectory($candidatePath)) {
                                Log::info('BlindScan: Resolved from history', [
                                    'path' => $candidatePath,
                                    'duration' => microtime(true) - $startTime
                                ]);
                                return [$candidatePath, $pathAccumulator];
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('BlindScan: Database fallback failed', ['error' => $e->getMessage()]);
        }

        Log::error('BlindScan: Folder not found after deep search', [
            'file_number' => $fileNumber,
            'blind_scan_root' => file_storage_path('app/public/' . self::BLIND_SCAN_ROOT),
            'blind_scan_root_exists' => $this->filesystem->isDirectory(file_storage_path('app/public/' . self::BLIND_SCAN_ROOT)),
            'duration' => microtime(true) - $startTime
        ]);

        $resolvedRoot = file_storage_path('app/public/' . self::BLIND_SCAN_ROOT);
        throw new FileNotFoundException("Blind scan folder not found for {$fileNumber}. Expected under {$resolvedRoot}");
    }

    private function filterRequestedFiles(array $requestedFiles, Collection $discovered): Collection
    {
        $requested = collect($requestedFiles)->map(function ($item) {
            if (is_string($item)) {
                return ['relative_path' => $item];
            }

            return $item;
        });

        return $requested
            ->map(function ($item) use ($discovered) {
                return (isset($discovered[$item['relative_path']]) && $discovered[$item['relative_path']] !== null) ? $discovered[$item['relative_path']] : null;
            })
            ->filter();
    }

    private function buildTargetFilename(string $originalName): string
    {
        $slug = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: 'pdf');

        return $slug . '-' . now()->timestamp . '-' . Str::random(6) . '.' . $extension;
    }

    private function detectPaperSizeFromPath(string $relativePath): string
    {
        $upper = strtoupper($relativePath);

        if (str_contains($upper, 'A3')) {
            return 'A3';
        }

        if (str_contains($upper, 'A5')) {
            return 'A5';
        }

        if (str_contains($upper, 'LEGAL')) {
            return 'Legal';
        }

        if (str_contains($upper, 'LETTER')) {
            return 'Letter';
        }

        return 'A4';
    }

    private function detectDocumentTypeFromName(string $filename): string
    {
        $name = strtolower($filename);

        return match (true) {
            str_contains($name, 'deed') => 'Deed',
            str_contains($name, 'survey') => 'Survey Plan',
            str_contains($name, 'certificate') => 'Certificate',
            str_contains($name, 'application') => 'Application',
            str_contains($name, 'receipt') => 'Receipt',
            default => 'Document',
        };
    }

    private function createScanningRecord(?int $fileIndexingId, array $manifest, string $destination, ?int $displayOrder = null, ?string $registry = null, ?string $fileType = null)
    {
        return Scanning::on('sqlsrv')->create([
            'file_indexing_id' => $fileIndexingId,
            'document_path' => $this->convertToPublicPath($destination),
            'uploaded_by' => Auth::id(),
            'status' => 'pending',
            'original_filename' => $manifest['name'],
            'paper_size' => $manifest['paper_size'],
            'document_type' => $manifest['document_type'],
            'notes' => null,
            'display_order' => ($displayOrder !== null) ? $displayOrder : 0,
            'file_size' => $manifest['size'],
            'is_pdf_converted' => false,
            'registry' => $registry,
            'edms_file_type' => $fileType,
            'parent_scan_id' => null,
        ]);
    }

    private function convertToPublicPath(string $absolutePath): string
    {
        $base = file_storage_path('app/public/');
        return str_replace('\\', '/', trim(Str::after($absolutePath, $base), '/'));
    }

    private function markBlindScanRecord(array $manifest, string $fileNumber, string $blindRelative, ?int $fileIndexingId, int $scanId): void
    {
        BlindScanning::on('sqlsrv')
            ->where('document_path', $manifest['blind_storage_path'])
            ->update([
                'status' => BlindScanning::STATUS_CONVERTED,
                'file_indexing_id' => $fileIndexingId,
                'converted_at' => now(),
                'document_path' => $manifest['target_storage_path'],
                'uploaded_by' => Auth::id(),
            ]);
    }

    private function logMove(string $fileNumber, array $manifest, int $scanId, string $destination): void
    {
        Log::channel('daily')->info('Blind scan file moved to scan uploads', [
            'file_number' => $fileNumber,
            'manifest' => $manifest,
            'scan_id' => $scanId,
            'destination' => $destination,
            'user_id' => Auth::id(),
        ]);
    }

    private function cleanupIfEmpty(string $blindFolder): void
    {
        $root = file_storage_path('app/public/' . self::BLIND_SCAN_ROOT);

        // Safety: ensure it exists and we're not deleting the root BLIND_SCAN directory
        if (!$this->filesystem->isDirectory($blindFolder) || realpath($blindFolder) === realpath($root)) {
            return;
        }

        // Check if ANY files exist in this folder or any subfolder
        $files = $this->filesystem->allFiles($blindFolder);

        if (empty($files)) {
            $this->filesystem->deleteDirectory($blindFolder);
            Log::info("BlindScan: Cleaned up empty source folder: {$blindFolder}");
        }
    }
}
