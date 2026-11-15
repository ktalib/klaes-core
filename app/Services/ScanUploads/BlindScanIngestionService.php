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

    /**
     * Index cards saved under the holder's name instead of the file number (CR 141102026 item 2, 2026-11-15).
     * Drop folder for them under BLIND_SCAN: loose cards named by the title, or one folder per title.
     */
    public const TITLE_CARD_FOLDER = 'Index_Cards_By_Title';
    /** Marks a card found by name in the manifest's relative_path; the rest is its path under BLIND_SCAN. */
    public const TITLE_CARD_PREFIX = '@title/';

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
    public function discover(string $rawFileNumber, ?string $registry = null, array $titles = []): array
    {
        $fileNumber = $this->normalizeFileNumber($rawFileNumber);
        $titleCards = $this->titleCards($titles, $fileNumber);
        try {
            [$blindFolder, $blindRelative] = $this->resolveBlindFolder($fileNumber, $registry, $titleCards->isNotEmpty());
            $files = $this->collectFiles($blindFolder, $blindRelative, $fileNumber);
        } catch (FileNotFoundException $e) {
            // No folder under the file number: the cards saved under its name may still be there.
            if ($titleCards->isEmpty()) {
                throw $e;
            }
            [$blindRelative, $files] = [null, collect()];
        }
        $files = $files->concat($titleCards)->values();
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
    public function transfer(string $rawFileNumber, array $requestedFiles, ?int $fileIndexingId = null, ?string $registry = null, ?string $fileType = null, array $titles = []): array
    {
        $fileNumber = $this->normalizeFileNumber($rawFileNumber);
        $wantsTitleCards = collect($requestedFiles)->contains(fn ($f) => str_starts_with((string) (is_array($f) ? ($f['relative_path'] ?? '') : $f), self::TITLE_CARD_PREFIX));
        try {
            [$blindFolder, $blindRelative] = $this->resolveBlindFolder($fileNumber, $registry, $wantsTitleCards);
        } catch (FileNotFoundException $e) {
            if (! $wantsTitleCards) {
                throw $e;
            }
            [$blindFolder, $blindRelative] = [null, null];
        }

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

        $discovered = ($blindFolder !== null ? $this->collectFiles($blindFolder, $blindRelative, $fileNumber, $registrySubFolder) : collect())
            ->concat($wantsTitleCards ? $this->titleCards($titles, $fileNumber, $registrySubFolder) : [])
            ->keyBy('relative_path');
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
            // Continue after the pages already on the file. Starting at 0 every
            // time gave a second batch the same positions as the first, and Page
            // Typing then interleaved the two, undoing the operator's arrangement.
            $displayOrder = 0;
            if ($fileIndexingId) {
                $highest = DB::connection('sqlsrv')->table('scannings')
                    ->where('file_indexing_id', $fileIndexingId)
                    ->max('display_order');
                $displayOrder = $highest === null ? 0 : ((int) $highest + 1);
            }

            foreach ($targetFiles as $manifest) {
                // A card found by name sits outside the file's own folder: it is moved from where it was found.
                $source = ! empty($manifest['title_card'])
                    ? $manifest['original_path']
                    : $blindFolder . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $manifest['relative_path']);
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

            if ($blindFolder !== null) {
                $this->cleanupIfEmpty($blindFolder);
            }
            // A title's own folder in the drop folder goes once its last card is taken; the drop folder stays.
            foreach ($targetFiles->where('title_card', true)->pluck('original_path')->map(fn ($p) => dirname($p))->unique() as $dir) {
                if (strcasecmp(basename(dirname($dir)), self::TITLE_CARD_FOLDER) === 0) {
                    $this->cleanupIfEmpty($dir);
                }
            }

            return $createdScans;
        });

        return [
            'file_number' => $fileNumber,
            'files' => $movedFiles,
        ];
    }

    /** A name as it is compared: capitals, letters and digits only, single spaces ("Alh. Ado  Bayero" = "ALH ADO BAYERO"). */
    public static function titleKey(?string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9]+/', ' ', strtoupper((string) $value))));
    }

    /** The name a card was saved under: no extension, no "_1" page or " (2)" copy suffix. */
    public static function cardNameKey(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        do {
            $before = $base;
            $base = preg_replace('/(\s*\(\d+\)|_\d{1,3})$/', '', trim($base));
        } while ($base !== $before);

        return self::titleKey($base);
    }

    /**
     * Index cards saved under the file's name (File Title / holder) rather than its number. Looked for in
     *  - the Index_Cards_By_Title drop folder: loose cards named by the title, or a folder named by it;
     *  - a folder named by the title in any registry's blind-scan folder;
     *  - the Blind Scan table: rows whose "file number" is the title (a folder uploaded under the name).
     * Each card is offered as a Property Index Card for the file's INDEX_CARDS folder.
     *
     * @param  array<int,string>  $titles  the file's names, as recorded
     */
    public function titleCards(array $titles, string $fileNumber, ?string $registrySubFolder = null): Collection
    {
        $keys = [];
        foreach ($titles as $t) {
            $k = self::titleKey($t);
            // Too short or no letters (e.g. "A", "123"): would match unrelated cards.
            if (strlen($k) >= 5 && preg_match('/[A-Z]{3,}/', $k)) {
                $keys[$k] = trim((string) $t);
            }
        }
        if (! $keys) {
            return collect();
        }
        $root = file_storage_path('app/public/' . self::BLIND_SCAN_ROOT);
        $found = [];   // absolute path => matched title
        $take = function (string $path, string $title) use (&$found) {
            if (str_starts_with(basename($path), '.trashed') || str_contains(strtolower(str_replace('\\', '/', $path)), '_pdf_originals/')) {
                return;
            }
            $found[$path] = $found[$path] ?? $title;
        };
        $takeFolder = function (string $dir, string $title) use ($take) {
            foreach ($this->filesystem->allFiles($dir) as $f) {
                $take($f->getPathname(), $title);
            }
        };

        // 1. The drop folder (loose cards, and one level of folders).
        $drop = $root . DIRECTORY_SEPARATOR . self::TITLE_CARD_FOLDER;
        if ($this->filesystem->isDirectory($drop)) {
            foreach ($this->titleDropIndex($drop) as [$path, $key, $isDir]) {
                if (isset($keys[$key]) && ($isDir ? $this->filesystem->isDirectory($path) : $this->filesystem->isFile($path))) {
                    $isDir ? $takeFolder($path, $keys[$key]) : $take($path, $keys[$key]);
                }
            }
        }

        // 2. A folder named by the title in a registry's blind-scan folder.
        foreach (glob($root . DIRECTORY_SEPARATOR . '*_Raw', GLOB_ONLYDIR) ?: [] as $rawDir) {
            foreach ($keys as $key => $title) {
                foreach (array_unique([$title, $key]) as $name) {
                    $dir = $rawDir . DIRECTORY_SEPARATOR . $name;
                    if (! str_contains($name, '..') && ! preg_match('#[\\\\/]#', $name) && $this->filesystem->isDirectory($dir)) {
                        $takeFolder($dir, $title);
                    }
                }
            }
        }

        // 3. The Blind Scan table: still-pending rows filed under the name.
        try {
            $rows = DB::connection('sqlsrv')->table('blind_scannings')->lock('WITH (NOLOCK)')
                ->whereIn('file_number', array_values(array_unique(array_merge(array_keys($keys), array_values($keys)))))
                ->where('status', '!=', BlindScanning::STATUS_CONVERTED)
                ->limit(500)->get(['file_number', 'document_path']);
            foreach ($rows as $r) {
                $rel = str_replace('\\', '/', (string) $r->document_path);
                if (! str_starts_with($rel, self::BLIND_SCAN_ROOT . '/') || str_contains($rel, '..')) {
                    continue;
                }
                $abs = file_storage_path('app/public/' . $rel);
                if ($this->filesystem->isFile($abs)) {
                    $take($abs, $keys[self::titleKey($r->file_number)] ?? $r->file_number);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('BlindScan: title-card table lookup failed', ['error' => $e->getMessage()]);
        }

        $targetBase = self::SCAN_UPLOAD_ROOT . '/' . ($registrySubFolder ? $registrySubFolder . '/' : '') . $fileNumber;
        $rootNorm = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $cards = collect();
        $seen = [];   // content hash => first card: identical "(2)" copies are flagged, not offered as new
        // Originals before their " (2)" copies, so the copy is the one flagged as the same card.
        uksort($found, fn ($a, $b) => [dirname($a), self::cardNameKey(basename($a)), preg_match('/\(\d+\)\.[^.]+$/', $a), strtolower(basename($a))]
            <=> [dirname($b), self::cardNameKey(basename($b)), preg_match('/\(\d+\)\.[^.]+$/', $b), strtolower(basename($b))]);
        foreach ($found as $path => $title) {
            $norm = str_replace('\\', '/', $path);
            if (stripos($norm, $rootNorm) !== 0) {
                continue;
            }
            $under = substr($norm, strlen($rootNorm));
            $size = @filesize($path) ?: 0;
            $hash = $size <= 50 * 1024 * 1024 ? (@md5_file($path) ?: null) : null;
            $dup = $hash !== null && isset($seen[$hash]) ? $seen[$hash] : null;
            if ($hash !== null && $dup === null) {
                $seen[$hash] = basename($path);
            }
            $target = \App\Http\Controllers\BlindScanningController::INDEX_CARD_FOLDER . '/' . $this->buildTargetFilename(basename($path));
            $cards->push([
                'name' => basename($path),
                'relative_path' => self::TITLE_CARD_PREFIX . $under,
                'size' => $size,
                'paper_size' => 'A4',
                'document_type' => \App\Http\Controllers\BlindScanningController::INDEX_CARD_DOCUMENT_TYPE,
                'target_relative_path' => $target,
                'original_path' => $path,
                'blind_storage_path' => self::BLIND_SCAN_ROOT . '/' . $under,
                'target_storage_path' => $targetBase . '/' . $target,
                'title_card' => true,
                'matched_title' => $title,
                'duplicate_of' => $dup,
            ]);
        }

        return $cards;
    }

    /**
     * [path, name key, is folder] for everything in the drop folder and one level down. Cached for 10 minutes
     * against the folder's modified time, so a large drop folder is not re-read for every file opened.
     */
    private function titleDropIndex(string $drop): array
    {
        $build = function () use ($drop) {
            $out = [];
            foreach (new \FilesystemIterator($drop, \FilesystemIterator::SKIP_DOTS) as $e) {
                if ($e->isDir()) {
                    $out[] = [$e->getPathname(), self::titleKey($e->getFilename()), true];
                    foreach (new \FilesystemIterator($e->getPathname(), \FilesystemIterator::SKIP_DOTS) as $f) {
                        if ($f->isFile()) {
                            $out[] = [$f->getPathname(), self::cardNameKey($f->getFilename()), false];
                        }
                    }
                } elseif ($e->isFile()) {
                    $out[] = [$e->getPathname(), self::cardNameKey($e->getFilename()), false];
                }
            }

            return $out;
        };

        return \Illuminate\Support\Facades\Cache::remember('blind_scan.title_cards.' . md5($drop) . '.' . @filemtime($drop), 600, $build);
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
            // Scanned property index cards keep their own folder in Scan Upload, as A3 pages keep theirs (user, 2026-11-11).
            $indexCard = \App\Http\Controllers\BlindScanningController::isIndexCardPath($relativePath);
            $targetRelativePath = ($indexCard ? \App\Http\Controllers\BlindScanningController::INDEX_CARD_FOLDER : $paperSize) . '/' . $targetFilename;

            $files->push([
                'name' => $file->getFilename(),
                'relative_path' => $relativePath,
                'size' => $file->getSize(),
                'paper_size' => $this->detectPaperSizeFromPath($relativePath),
                'document_type' => $indexCard
                    ? \App\Http\Controllers\BlindScanningController::INDEX_CARD_DOCUMENT_TYPE
                    : $this->detectDocumentTypeFromName($file->getFilename()),
                'target_relative_path' => $targetRelativePath,
                'original_path' => $file->getPathname(),
                'blind_storage_path' => self::BLIND_SCAN_ROOT . '/' . $blindRelative . '/' . $relativePath,
                'target_storage_path' => $targetBase . '/' . $targetRelativePath,
            ]);
        }

        return $files;
    }

    /**
     * @param  bool  $directOnly  only the file's folder by name in each registry folder; no deep search. Used when
     *                            cards saved under the file's name were found: the deep search takes minutes.
     */
    private function resolveBlindFolder(string $fileNumber, ?string $registry = null, bool $directOnly = false): array
    {
        $startTime = microtime(true);
        Log::info('BlindScan: Resolving folder start', ['file_number' => $fileNumber, 'registry' => $registry]);

        $normalizedFileNumber = $this->normalizeFileNumber($fileNumber);
        $needle = preg_replace('/[^a-z0-9]/', '', strtolower($normalizedFileNumber)) ?? '';

        // 1. Try Direct/Known Locations (Fastest). The selected registry's
        // folder first, so a file number present under two registries resolves
        // to the one the operator is working in; then every registry folder on
        // disk (DCIV_Registry_Raw, ...), not only Lands and Cadastral.
        $commonRegistries = [];
        if ($registry !== null && trim($registry) !== '') {
            $commonRegistries[] = app(\App\Services\Edms\EdmsDocumentPathResolver::class)->blindScanRegistryFolder($registry);
        }
        foreach (glob(file_storage_path('app/public/' . self::BLIND_SCAN_ROOT) . DIRECTORY_SEPARATOR . '*_Raw', GLOB_ONLYDIR) ?: [] as $rawDir) {
            $commonRegistries[] = basename($rawDir);
        }
        $commonRegistries = array_values(array_unique(array_merge(
            $commonRegistries,
            ['Lands_Registry_Raw', 'Cadastral_Registry_Raw', '', 'Lands_Registry', 'Cadastral_Registry']
        )));
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

        if ($directOnly) {
            throw new FileNotFoundException("Blind scan folder not found for {$fileNumber}.");
        }

        // 2. Try Exact Match in Registries/Subdirectories (Iterative search)
        $root = file_storage_path('app/public/' . self::BLIND_SCAN_ROOT);
        if ($this->filesystem->isDirectory($root)) {
            $topDirs = $this->filesystem->directories($root);
            foreach ($topDirs as $topDir) {
                $dirName = basename($topDir);
                $cleanName = preg_replace('/[^a-z0-9]/', '', strtolower($dirName)) ?? '';

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
                    $cleanSubName = preg_replace('/[^a-z0-9]/', '', strtolower($subDirName)) ?? '';

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
                            $cleanDeepName = preg_replace('/[^a-z0-9]/', '', strtolower($deepDirName)) ?? '';

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
                        if (preg_replace('/[^a-z0-9]/', '', strtolower($segment)) ?? '' === $needle) {
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
        // Compare with forward slashes and without case: the base and the
        // destination can differ in slash style (F:\\storage vs F:\storage).
        $path = str_replace('\\', '/', $absolutePath);
        $base = rtrim(str_replace('\\', '/', file_storage_path('app/public')), '/') . '/';

        if (stripos($path, $base) === 0) {
            $relative = substr($path, strlen($base));
        } elseif (($at = stripos($path, '/app/public/')) !== false) {
            // Different base than expected: everything after app/public/ is
            // still the disk-relative path. Never store an absolute path.
            $relative = substr($path, $at + strlen('/app/public/'));
        } else {
            $relative = $path;
        }

        return trim(preg_replace('#/{2,}#', '/', $relative), '/');
    }

    private function markBlindScanRecord(array $manifest, string $fileNumber, ?string $blindRelative, ?int $fileIndexingId, int $scanId): void
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
