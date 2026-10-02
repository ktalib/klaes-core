<?php

namespace App\Services;

use App\Models\RegistryFileDocument;
use App\Models\RegistryFileFolder;
use App\Models\RegistrySource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Registry digital source — scans the on-disk registry folders and imports the
 * file folders + scanned documents into the database, and resolves a file
 * number (optionally scoped to a selected registry) to its digital copies.
 *
 * Disk layout:  {base_path}/{folder}/{file_number}/{category}/{image…}
 * e.g.          …/SLTR_Registry/SLTR-220944/A4/scan-001.jpg
 *
 * The existing FileIndexing-based digital library (Land registries) is
 * untouched — this service only covers registries stored as raw folders.
 */
class RegistrySourceService
{
    /**
     * Seed/refresh the registry_sources lookup rows from config. Idempotent:
     * matches on the stable `code` and updates name/folder in place.
     */
    public function syncLookups(): int
    {
        $count = 0;
        foreach ((array) config('registry_sources.registries', []) as $def) {
            if (empty($def['code']) || empty($def['folder'])) {
                continue;
            }
            RegistrySource::updateOrCreate(
                ['code' => $def['code']],
                [
                    'name'      => $def['name'] ?? $def['code'],
                    'folder'    => $def['folder'],
                    'is_active' => $def['is_active'] ?? true,
                ]
            );
            $count++;
        }
        return $count;
    }

    /**
     * Scan the registry folders and import any new file folders / documents.
     * Safe to re-run: existing rows are matched and updated, never duplicated.
     *
     * @param  string|null $onlyCode  Limit the scan to a single registry code.
     * @return array{registries:int,folders:int,documents:int,skipped:array,details:array}
     */
    public function sync(?string $onlyCode = null): array
    {
        // Ensure lookup rows exist before scanning.
        $this->syncLookups();

        $base = rtrim((string) config('registry_sources.base_path'), '/\\');
        $allowed = array_map('strtolower', (array) config('registry_sources.allowed_extensions', []));

        $stats = ['registries' => 0, 'folders' => 0, 'documents' => 0, 'skipped' => [], 'details' => []];

        $sources = RegistrySource::active()
            ->when($onlyCode, fn ($q) => $q->where('code', $onlyCode))
            ->get();

        foreach ($sources as $source) {
            $registryDir = $base . DIRECTORY_SEPARATOR . $source->folder;

            if (! is_dir($registryDir)) {
                $stats['skipped'][] = "{$source->code} ({$source->folder}) — directory not found";
                continue;
            }

            $stats['registries']++;
            $registryFolders = 0;
            $registryDocs    = 0;

            foreach ($this->subDirectories($registryDir) as $folderPath) {
                $fileNumber = basename($folderPath);

                $relativeFolder = $this->relativePath($folderPath, $base);

                $folder = RegistryFileFolder::updateOrCreate(
                    ['registry_source_id' => $source->id, 'file_number' => $fileNumber],
                    ['relative_path' => $relativeFolder, 'last_synced_at' => now()]
                );

                // Walk every file beneath the folder (any depth — e.g. /A4/scan.jpg).
                $docCount = 0;
                foreach ($this->filesRecursive($folderPath) as $filePath) {
                    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    if ($allowed && ! in_array($ext, $allowed, true)) {
                        continue;
                    }

                    $relativeDoc = $this->relativePath($filePath, $base);
                    // category = first sub-folder under the file folder (e.g. "A4").
                    $category = $this->categoryFor($folderPath, $filePath);

                    $doc = RegistryFileDocument::updateOrCreate(
                        ['registry_file_folder_id' => $folder->id, 'relative_path' => $relativeDoc],
                        [
                            'category'  => $category,
                            'filename'  => basename($filePath),
                            'extension' => $ext,
                            'file_size' => @filesize($filePath) ?: null,
                        ]
                    );
                    if ($doc->wasRecentlyCreated) {
                        $registryDocs++;
                    }
                    $docCount++;
                }

                $folder->update(['document_count' => $docCount]);
                if ($folder->wasRecentlyCreated) {
                    $registryFolders++;
                }
                $stats['folders']++;
                $stats['documents'] += $docCount;
            }

            $source->update(['last_synced_at' => now()]);
            $stats['details'][] = "{$source->code}: {$registryFolders} new folder(s), {$registryDocs} new document(s)";
        }

        return $stats;
    }

    /**
     * Resolve a file number to its registry digital copies. When a registry is
     * supplied (name or code from the dropdown) the lookup is scoped to it;
     * otherwise it searches every active registry source.
     *
     * @return array{available:bool,count:int,registry:?string,file_number:?string,files:array}
     */
    public function digitalFilesFor(string $fileNumber, ?string $registry = null): array
    {
        $fileNumber = trim($fileNumber);
        $empty = ['available' => false, 'count' => 0, 'registry' => null, 'file_number' => $fileNumber, 'files' => []];

        if ($fileNumber === '') {
            return $empty;
        }

        $sourceIds = null;
        if ($registry !== null && trim($registry) !== '') {
            $source = $this->resolveSource($registry);
            if (! $source) {
                return $empty;                 // selected registry isn't a folder-based source
            }
            $sourceIds = [$source->id];
        }

        $folder = RegistryFileFolder::query()
            ->when($sourceIds, fn ($q) => $q->whereIn('registry_source_id', $sourceIds))
            ->whereRaw('LOWER(file_number) = ?', [mb_strtolower($fileNumber)])
            ->with(['documents' => fn ($q) => $q->orderBy('category')->orderBy('filename'), 'source'])
            ->first();

        if (! $folder || $folder->documents->isEmpty()) {
            return array_merge($empty, ['registry' => optional($folder?->source)->name]);
        }

        $imageExts = array_map('strtolower', (array) config('registry_sources.image_extensions', []));

        $files = $folder->documents->map(function (RegistryFileDocument $doc) use ($imageExts) {
            return [
                'name'     => $doc->filename,
                'category' => $doc->category,
                'ext'      => $doc->extension,
                'is_image' => in_array(strtolower((string) $doc->extension), $imageExts, true),
                'url'      => $this->urlFor($doc->relative_path),
            ];
        })->values();

        return [
            'available'   => true,
            'count'       => $files->count(),
            'registry'    => optional($folder->source)->name,
            'file_number' => $folder->file_number,
            'files'       => $files->all(),
        ];
    }

    /**
     * Match a user-selected registry (the dropdown passes a name like
     * "SLTR Registry" or a code) to a folder-based RegistrySource. Falls back
     * to the config aliases (contains, case-insensitive).
     */
    public function resolveSource(string $registry): ?RegistrySource
    {
        $needle = mb_strtolower(trim($registry));
        if ($needle === '') {
            return null;
        }

        $sources = RegistrySource::active()->get();

        // 1) Exact code or name match.
        foreach ($sources as $s) {
            if (mb_strtolower($s->code) === $needle || mb_strtolower($s->name) === $needle) {
                return $s;
            }
        }

        // 2) Config alias / contains match (e.g. "Registry 1 - Cadastral" → CAD).
        foreach ((array) config('registry_sources.registries', []) as $def) {
            $aliases = array_merge([$def['code'] ?? '', $def['name'] ?? ''], (array) ($def['aliases'] ?? []));
            foreach (array_filter($aliases) as $alias) {
                $a = mb_strtolower($alias);
                if ($a !== '' && (Str::contains($needle, $a) || Str::contains($a, $needle))) {
                    return $sources->firstWhere('code', $def['code'] ?? null);
                }
            }
        }

        return null;
    }

    /** Build a public URL from a path stored relative to the public disk root. */
    public function urlFor(string $relativePath): string
    {
        $p = ltrim(str_replace('\\', '/', $relativePath), '/');
        // Encode each segment (filenames may contain spaces / special chars) while
        // keeping the slashes as path separators.
        $p = implode('/', array_map('rawurlencode', explode('/', $p)));
        // Collapse accidental double slashes without touching the scheme's "://".
        return preg_replace('#(?<!:)//+#', '/', Storage::disk('public')->url($p));
    }

    // ── Path helpers ────────────────────────────────────────────────────────

    /**
     * Path relative to the public disk root, prefixed with the configured
     * public prefix (e.g. EDMS/UPLOAD/SLTR_Registry/SLTR-220944/A4/x.jpg).
     */
    private function relativePath(string $absolute, string $base): string
    {
        $prefix    = trim((string) config('registry_sources.public_prefix', 'EDMS/UPLOAD'), '/\\');
        $relative  = ltrim(str_replace('\\', '/', substr($absolute, strlen($base))), '/');
        return ($prefix !== '' ? $prefix . '/' : '') . $relative;
    }

    /** Immediate sub-directories of a directory. */
    private function subDirectories(string $dir): array
    {
        $out = [];
        foreach ((array) @scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $out[] = $path;
            }
        }
        return $out;
    }

    /** All files beneath a directory (recursive). */
    private function filesRecursive(string $dir): array
    {
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $out[] = $file->getPathname();
            }
        }
        return $out;
    }

    /** First sub-folder of the file folder that contains the document (e.g. "A4"). */
    private function categoryFor(string $folderPath, string $filePath): ?string
    {
        $rel = ltrim(str_replace('\\', '/', substr($filePath, strlen($folderPath))), '/');
        $parts = explode('/', $rel);
        return count($parts) > 1 ? $parts[0] : null;
    }
}
