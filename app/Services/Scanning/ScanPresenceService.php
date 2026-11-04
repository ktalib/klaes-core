<?php

namespace App\Services\Scanning;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * "Has this file been scanned?" (user, 2026-11-01; config fileindexing.scan_check).
 *
 * Looks, in order, in:
 *   1. the Digital Archive: scannings rows of the file's indexing record(s);
 *   2. the Blind Scan folder: EDMS/BLIND_SCAN/<Registry>_Raw/<file no.>, found directly on
 *      disk and through blind_scannings rows (which also catch "<file no.> - Copy" / "(TEMP)");
 *   3. fallback, only when 1 and 2 find nothing: the raw scans on the records server
 *      (\\10.50.1.1\land_registry_raw\<file no.>\ or <file no.>.pdf).
 *
 * A folder only counts when it holds page files: empty folders were created to be counted.
 * Folders are never listed from their root (the records server holds >130,000); every look is
 * a direct path built from the file number.
 */
class ScanPresenceService
{
    public const SOURCE_LABELS = [
        'digital_archive' => 'Digital Archive',
        'scan_upload' => 'Scan Upload folder',
        'upload' => 'Upload folder',
        'blind_scan' => 'Blind Scan',
        'records_server' => 'Raw scans (records server)',
    ];

    private const BLIND_ROOT = 'app/public/EDMS/BLIND_SCAN';

    /**
     * EDMS folders looked through by NAME (user, 2026-11-02: SLTR and KANGIS scans also sit in
     * SCAN_UPLOAD\<Registry> and UPLOAD\<Registry>). Every registry folder under each root is
     * listed (a name listing, ~0.1 s for 147,000 folders) and matched with key(), so spacing and
     * punctuation differences ("KNML  2794", "SLTR -123", "SLRT-107644") still match.
     */
    private const FOLDER_ROOTS = [
        'scan_upload' => 'app/public/EDMS/SCAN_UPLOAD',
        'upload' => 'app/public/EDMS/UPLOAD',
        'blind_scan' => 'app/public/EDMS/BLIND_SCAN',
    ];

    /** Spellings of a file number to look for (folders may use either). */
    public static function candidates(string $fileNumber): array
    {
        $base = preg_replace('/\s+/', ' ', trim($fileNumber));
        $base = trim((string) preg_replace('/\(\s*T\s*\)\s*$/i', '', $base));
        if ($base === '') {
            return [];
        }

        $set = [$base, strtoupper($base), str_replace('/', '-', $base), str_replace(' ', '-', $base)];
        // "KN 6077" <-> "KN-6077" <-> "KN  6077": letters, a separator, digits.
        if (preg_match('/^([A-Za-z]+)[ -](\d.*)$/', $base, $m)) {
            $set[] = $m[1] . ' ' . $m[2];
            $set[] = $m[1] . '-' . $m[2];
            $set[] = $m[1] . '  ' . $m[2];
        }
        // Some SLTR folders are spelled SLRT.
        foreach ($set as $c) {
            if (stripos($c, 'SLTR') === 0) {
                $set[] = 'SLRT' . substr($c, 4);
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', $set), fn ($c) => $c !== '' && !str_contains($c, '..'))));
    }

    /**
     * A spelling-proof key for a file number or folder name: letter runs and number runs
     * (leading zeros dropped) joined by "-"; copy markers such as " - Copy", "(1)" or "(TEMP)"
     * are ignored, and SLRT reads as SLTR. "KNML. 02794" and "KNML 2794" both give KNML-2794.
     */
    public static function key(string $name): string
    {
        $name = preg_replace('/\s*-\s*copy(\s*\(\d+\))?\s*$/i', '', $name);
        $name = preg_replace('/\s*\((\d+|temp|t|copy)\)\s*$/i', '', (string) $name);
        // "KN3547" (KANGIS) and "KN 3547" / "KN-3547" (land file) are different files:
        // a KN joined straight to its digits keeps its own key.
        if (preg_match('/^\s*KN(\d+)\s*$/i', (string) $name, $kn)) {
            return 'KN' . (ltrim($kn[1], '0') === '' ? '0' : ltrim($kn[1], '0'));
        }
        preg_match_all('/[A-Za-z]+|\d+/', (string) $name, $m);
        $parts = array_map(function ($p) {
            return ctype_digit($p) ? (ltrim($p, '0') === '' ? '0' : ltrim($p, '0')) : strtoupper($p);
        }, $m[0]);
        if (($parts[0] ?? null) === 'SLRT') {
            $parts[0] = 'SLTR';
        }

        return implode('-', $parts);
    }

    public function check(string $fileNumber, bool $fresh = false): array
    {
        $candidates = self::candidates($fileNumber);
        if (!$candidates) {
            return $this->result($fileNumber, []);
        }

        $key = 'scan-presence:' . md5(strtoupper(implode('|', $candidates)));
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, (int) config('fileindexing.scan_check.cache_seconds', 60), function () use ($fileNumber, $candidates) {
            $sources = $this->digitalArchive($candidates);
            $sources = array_merge($sources, $this->blindScan($candidates));

            $found = array_filter($sources, fn ($s) => $s['pages'] > 0);
            $recordsChecked = false;
            $recordsUnavailable = false;
            if (!$found) {
                [$records, $recordsChecked, $recordsUnavailable] = $this->recordsServer($candidates);
                $sources = array_merge($sources, $records);
            }

            return $this->result($fileNumber, $sources, $recordsChecked, $recordsUnavailable);
        });
    }

    private function result(string $fileNumber, array $sources, bool $recordsChecked = false, bool $recordsUnavailable = false): array
    {
        $withPages = array_values(array_filter($sources, fn ($s) => $s['pages'] > 0));
        $empty = array_values(array_filter($sources, fn ($s) => $s['pages'] === 0));
        $scanned = (bool) $withPages;
        $pages = array_sum(array_column($withPages, 'pages'));

        if ($scanned) {
            $where = implode(', ', array_unique(array_map(fn ($s) => $s['label'], $withPages)));
            $message = "Scanned: {$pages} page(s) found in {$where}.";
        } elseif ($empty) {
            $message = 'Not scanned: a folder exists for this file but it has no pages in it.';
        } else {
            $message = 'Not scanned: no pages were found for this file in the Digital Archive, the Blind Scan folder'
                . ($recordsChecked ? ' or the raw scans on the records server' : '') . '.';
        }

        return [
            'file_number' => trim($fileNumber),
            'scanned' => $scanned,
            'pages' => $pages,
            'sources' => $withPages,
            'empty_folders' => $empty,
            'records_server_checked' => $recordsChecked,
            'records_server_unavailable' => $recordsUnavailable,
            'message' => $message . ($recordsUnavailable ? ' (The records server could not be reached.)' : ''),
            'checked_at' => now()->toDateTimeString(),
        ];
    }

    /** Pages already filed to EDMS for the file's indexing record(s). */
    private function digitalArchive(array $candidates): array
    {
        try {
            $rows = DB::connection('sqlsrv')->table('file_indexings as fi')
                ->join('scannings as s', 's.file_indexing_id', '=', 'fi.id')
                ->whereIn('fi.file_number', $candidates)
                ->whereRaw('ISNULL(fi.is_deleted, 0) = 0')
                ->groupBy('fi.id', 'fi.file_number')
                ->select('fi.id', 'fi.file_number', DB::raw('COUNT(s.id) AS pages'))
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Scan check: digital archive lookup failed', ['error' => $e->getMessage()]);

            return [];
        }

        return $rows->map(fn ($r) => [
            'source' => 'digital_archive',
            'label' => self::SOURCE_LABELS['digital_archive'],
            'folder' => $r->file_number,
            'pages' => (int) $r->pages,
            'file_indexing_id' => (int) $r->id,
            'view_url' => route('edms.fileindexing', ['fileIndexingId' => $r->id]),
            'token' => null,
        ])->all();
    }

    /**
     * EDMS folders named after the file: SCAN_UPLOAD and UPLOAD (per registry, plus file-number
     * folders at the SCAN_UPLOAD root) and BLIND_SCAN (per registry), matched by key(); plus
     * Blind Scan folders recorded in blind_scannings (local_pc_path).
     */
    private function blindScan(array $candidates): array
    {
        $keys = array_values(array_unique(array_filter(array_map([self::class, 'key'], $candidates))));
        if (!$keys) {
            return [];
        }
        // Only names holding the file's last number are keyed (cheap pre-filter).
        $needles = [];
        foreach ($keys as $k) {
            if (preg_match('/(\d+)$/', $k, $m)) {
                $needles[] = $m[1];
            }
        }

        $found = []; // "rootKey|relative" => [rootKey, relative]
        foreach (self::FOLDER_ROOTS as $rootKey => $rootRel) {
            $root = file_storage_path($rootRel);
            if (!is_dir($root)) {
                continue;
            }
            $top = @scandir($root) ?: [];
            $scopes = [''];
            foreach ($top as $name) {
                if ($name === '.' || $name === '..' || $name[0] === '.' || $name[0] === '_') {
                    continue;
                }
                if (preg_match('/registry|_raw$/i', $name) && is_dir($root . '/' . $name)) {
                    $scopes[] = $name;
                }
            }
            foreach ($scopes as $scope) {
                $names = $scope === '' ? $top : (@scandir($root . '/' . $scope) ?: []);
                foreach ($names as $name) {
                    if ($name === '.' || $name === '..' || $name[0] === '.' || ($scope === '' && in_array($name, $scopes, true))) {
                        continue;
                    }
                    if ($needles && !$this->containsAny($name, $needles)) {
                        continue;
                    }
                    $base = preg_replace('/\.(pdf|jpe?g|png|tiff?)$/i', '', $name);
                    if (in_array(self::key((string) $base), $keys, true)) {
                        $relative = ($scope === '' ? '' : $scope . '/') . $name;
                        $found[strtoupper($rootKey . '|' . $relative)] = [$rootKey, $relative];
                    }
                }
            }
        }

        try {
            $query = DB::connection('sqlsrv')->table('blind_scannings')
                ->whereIn('file_number', $candidates);
            foreach ($candidates as $c) {
                // "<file no.> - Copy", "<file no.> (TEMP)", "<file no.> (1)"
                $query->orWhere('file_number', 'like', str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $c) . ' %');
            }
            foreach ($query->distinct()->pluck('local_pc_path') as $path) {
                $path = trim(str_replace('\\', '/', (string) $path), '/');
                if ($path !== '' && !str_contains($path, '..')) {
                    $found[strtoupper('blind_scan|' . $path)] ??= ['blind_scan', $path];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Scan check: blind_scannings lookup failed', ['error' => $e->getMessage()]);
        }

        $sources = [];
        foreach ($found as [$rootKey, $relative]) {
            $path = file_storage_path(self::FOLDER_ROOTS[$rootKey]) . '/' . $relative;
            if (@is_dir($path)) {
                $sources[] = $this->folderSource($rootKey, $relative, $this->countPages($path));
            } elseif (@is_file($path) && $this->isPageFile($path)) {
                $sources[] = $this->folderSource($rootKey, $relative, 1);
            }
        }

        return $sources;
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if (str_contains($haystack, $n)) {
                return true;
            }
        }

        return false;
    }

    /** Fallback: the raw scans on the records server. Returns [sources, checked, unavailable]. */
    private function recordsServer(array $candidates): array
    {
        $root = $this->recordsRoot();
        if ($root === null) {
            return [[], false, false];
        }
        if (!@is_dir($root)) {
            return [[], true, true];
        }

        $sources = [];
        foreach ($candidates as $c) {
            $dir = $root . '/' . $c;
            if (@is_dir($dir)) {
                $sources[strtoupper($c)] = $this->folderSource('records_server', $c, $this->countPages($dir));
            } elseif (@is_file($dir . '.pdf')) {
                $sources[strtoupper($c . '.pdf')] = $this->folderSource('records_server', $c . '.pdf', 1);
            }
        }

        return [array_values($sources), true, false];
    }

    private function folderSource(string $source, string $relative, int $pages): array
    {
        return [
            'source' => $source,
            'label' => self::SOURCE_LABELS[$source],
            'folder' => $relative,
            'pages' => $pages,
            'file_indexing_id' => null,
            'view_url' => null,
            // Opaque handle for the page browser: never the path itself.
            'token' => Crypt::encryptString(json_encode(['s' => $source, 'r' => $relative])),
        ];
    }

    public function recordsRoot(): ?string
    {
        $root = trim((string) config('fileindexing.scan_check.records_server_folder'));

        return $root === '' ? null : rtrim(str_replace('\\', '/', $root), '/');
    }

    public function blindRoot(): string
    {
        return file_storage_path(self::BLIND_ROOT);
    }

    private function isPageFile(string $name): bool
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return in_array($ext, config('fileindexing.scan_check.page_extensions', []), true);
    }

    private function countPages(string $dir): int
    {
        return count($this->pageFiles($dir, 100000));
    }

    /**
     * Page files under a folder (or the folder itself when it is a single PDF), as paths
     * relative to it, in natural order. Copies kept under _pdf_originals are skipped.
     */
    public function pageFiles(string $dir, int $limit = 1000): array
    {
        if (@is_file($dir)) {
            return $this->isPageFile($dir) ? [basename($dir)] : [];
        }
        if (!@is_dir($dir)) {
            return [];
        }

        $files = [];
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($dir))), '/');
                if (str_contains(strtolower($rel), '_pdf_originals/') || !$this->isPageFile($rel)) {
                    continue;
                }
                $files[] = $rel;
                if (count($files) >= $limit) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Scan check: could not read folder', ['dir' => $dir, 'error' => $e->getMessage()]);
        }
        natcasesort($files);

        return array_values($files);
    }

    /** Resolve a browse token to [source, absolute folder or file]; null when invalid. */
    public function resolveToken(string $token): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable $e) {
            return null;
        }
        $source = $data['s'] ?? null;
        $relative = (string) ($data['r'] ?? '');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }
        $root = match ($source) {
            'blind_scan', 'scan_upload', 'upload' => file_storage_path(self::FOLDER_ROOTS[$source]),
            'records_server' => $this->recordsRoot(),
            default => null,
        };
        if (!$root) {
            return null;
        }

        return [$source, rtrim(str_replace('\\', '/', $root), '/') . '/' . $relative, $relative];
    }
}
