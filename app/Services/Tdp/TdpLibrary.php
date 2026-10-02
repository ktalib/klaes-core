<?php

namespace App\Services\Tdp;

use App\Models\Lga;
use App\Services\AuditService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * GIS → Title Deed Plan Management.
 *
 * Reads (and, when uploads are enabled, writes) the Title Deed Plan store held
 * on the GIS server:
 *
 *     C:\Kano State\TDP\[LGA]\<file number>.pdf
 *
 * Modelled on App\Services\DigitalFileAccessService (config/dfr.php): a
 * configured root, an allowed-extension list, and matching that tolerates the
 * way a file number is spelled. Two differences matter:
 *
 *  1. Nothing here throws because the folder is missing. isConfigured() /
 *     isReachable() / status() answer that question so the screen can say so
 *     plainly — the folder does not exist on a development machine.
 *  2. Every path that reaches the filesystem goes through resolve(), which
 *     refuses "..", absolute paths and anything whose real path escapes the
 *     configured root.
 *
 * Matching: a stored number like "ST/KN/7655/KMC" must find
 * ST-KN-7655-KMC.pdf, ST_KN_7655_KMC.pdf or st kn 7655 kmc.pdf. Both sides
 * are pushed through normalizeNumber() and then stripped to their letters and
 * digits, so separators and case stop mattering.
 */
class TdpLibrary
{
    /** Cache of one scan per (lga|query) within a request. */
    private array $scanCache = [];

    // ── Configuration and reachability ───────────────────────────────────────

    /** The configured root, trimmed of trailing separators. '' when unset. */
    public function root(): string
    {
        return rtrim(trim((string) config('tdp.root', '')), "\\/ \t\n\r\0\x0B");
    }

    public function isConfigured(): bool
    {
        return $this->root() !== '';
    }

    /** Configured and the folder actually answers. Never throws. */
    public function isReachable(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            return @is_dir($this->root());
        } catch (\Throwable $e) {
            Log::warning('TDP root check failed', ['root' => $this->root(), 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function uploadsEnabled(): bool
    {
        return (bool) config('tdp.uploads_enabled', true) && $this->isReachable();
    }

    /** @return string[] lower-case extensions the store accepts */
    public function allowedExtensions(): array
    {
        $allowed = (array) config('tdp.allowed_extensions', ['pdf']);

        return array_values(array_filter(array_map(fn ($e) => strtolower(trim((string) $e)), $allowed)));
    }

    /**
     * Everything a screen needs to explain itself.
     *
     * @return array{configured:bool,reachable:bool,root:string,state:string,message:string,writable:bool}
     */
    public function status(): array
    {
        $root = $this->root();
        $configured = $this->isConfigured();
        $reachable = $this->isReachable();

        if (!$configured) {
            $state = 'not_configured';
            $message = 'The Title Deed Plan folder is not configured. Set TDP_ROOT_PATH in the .env file on the GIS server (for example C:\Kano State\TDP).';
        } elseif (!$reachable) {
            $state = 'unreachable';
            $message = 'The Title Deed Plan folder is not reachable: ' . $root . '. KLAES must run on the GIS server, or TDP_ROOT_PATH must point at a share this server can read.';
        } else {
            $state = 'ready';
            $message = 'Plans are read straight from the GIS server; nothing is copied into KLAES.';
        }

        return [
            'configured' => $configured,
            'reachable' => $reachable,
            'root' => $root,
            'state' => $state,
            'message' => $message,
            'writable' => $reachable && @is_writable($root),
        ];
    }

    // ── LGA folders ──────────────────────────────────────────────────────────

    /**
     * The immediate subfolders of the root, each with how many plans it holds.
     *
     * @return array<int, array{name:string,file_count:int,modified:?int,matches_lga:bool}>
     */
    public function lgas(): array
    {
        if (!$this->isReachable()) {
            return [];
        }

        $known = $this->knownLgaKeys();
        $folders = [];

        foreach ($this->directoryEntries($this->root()) as $entry) {
            if (!$entry->isDir()) {
                continue;
            }

            $name = $entry->getFilename();

            $folders[] = [
                'name' => $name,
                'file_count' => $this->countFiles($entry->getPathname()),
                'modified' => @$entry->getMTime() ?: null,
                'matches_lga' => isset($known[$this->key($name)]),
            ];
        }

        usort($folders, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $folders;
    }

    /**
     * LGA folder names against the KLAES LGA list (App\Models\Lga, the 44 Kano
     * LGAs). Folders nobody can explain and LGAs with nowhere to file a plan
     * are both reported; neither is corrected automatically.
     *
     * @return array{matched:array,folders_without_lga:array,lgas_without_folder:array,lga_source:string,lga_count:int,folder_count:int,plan_count:int}
     */
    public function reconcile(): array
    {
        $lgas = $this->knownLgas();
        $known = [];
        foreach ($lgas as $name) {
            $known[$this->key($name)] = $name;
        }

        $folders = $this->lgas();
        $matched = [];
        $foldersWithoutLga = [];
        $seen = [];
        $plans = 0;

        foreach ($folders as $folder) {
            $key = $this->key($folder['name']);
            $plans += $folder['file_count'];

            if (isset($known[$key])) {
                $seen[$key] = true;
                $matched[] = [
                    'folder' => $folder['name'],
                    'lga' => $known[$key],
                    'file_count' => $folder['file_count'],
                    'exact' => $folder['name'] === $known[$key],
                ];
            } else {
                $foldersWithoutLga[] = [
                    'folder' => $folder['name'],
                    'file_count' => $folder['file_count'],
                    'closest' => $this->closestLga($folder['name'], $lgas),
                ];
            }
        }

        $lgasWithoutFolder = [];
        foreach ($known as $key => $name) {
            if (!isset($seen[$key])) {
                $lgasWithoutFolder[] = $name;
            }
        }

        return [
            'matched' => $matched,
            'folders_without_lga' => $foldersWithoutLga,
            'lgas_without_folder' => $lgasWithoutFolder,
            'lga_source' => 'lgas table (App\Models\Lga)',
            'lga_count' => count($lgas),
            'folder_count' => count($folders),
            'plan_count' => $plans,
        ];
    }

    // ── Search and resolution ────────────────────────────────────────────────

    /**
     * Plans in the store, filtered by LGA folder and/or file number.
     *
     * The query is matched on the normalised file number, so "LUAC:AB/7655/UM",
     * "luac-ab-7655-um" and "7655" all find LUAC-AB-7655-UM.pdf.
     *
     * @return array{items:array,total:int,page:int,per_page:int,last_page:int}
     */
    public function search(?string $lga = null, ?string $query = null, int $page = 1, ?int $perPage = null): array
    {
        $perPage = $perPage ?: max(1, (int) config('tdp.per_page', 25));
        $page = max(1, $page);

        $items = $this->scan($lga, $query);
        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        return [
            'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ];
    }

    /**
     * The plan for one file number, used by the CofO workflow's TDP stage.
     * Returns null when the store is unreachable or nothing matches — the
     * caller reports missing data rather than inventing a page.
     *
     * @return array|null the matching item (see itemFor())
     */
    public function findForFileNumber(string $fileNumber, ?string $lga = null): ?array
    {
        $key = $this->key($fileNumber);
        if ($key === '') {
            return null;
        }

        $matches = [];
        foreach ($this->scan($lga, null) as $item) {
            if ($item['key'] === $key) {
                return $item;                       // exact number wins outright
            }
            if (str_contains($item['key'], $key)) {
                $matches[] = $item;
            }
        }

        return $matches[0] ?? null;
    }

    /** Every plan found for a file number, exact matches first. */
    public function allForFileNumber(string $fileNumber, ?string $lga = null): array
    {
        $key = $this->key($fileNumber);
        if ($key === '') {
            return [];
        }

        $found = array_values(array_filter(
            $this->scan($lga, null),
            fn ($item) => $item['key'] === $key || str_contains($item['key'], $key)
        ));

        usort($found, fn ($a, $b) => ($b['key'] === $key ? 1 : 0) <=> ($a['key'] === $key ? 1 : 0));

        return $found;
    }

    /**
     * Turn a relative store path ("Umuahia North/LUAC-AB-7655-UM.pdf") into an
     * absolute path, or null.
     *
     * This is the only way a request-supplied path may reach the filesystem.
     * Refused: absolute paths, UNC paths, drive letters, "..", null bytes,
     * anything whose real path leaves the root, and disallowed extensions.
     */
    public function resolve(?string $relative): ?string
    {
        $relative = trim((string) $relative);

        if ($relative === '' || !$this->isReachable()) {
            return null;
        }

        if (str_contains($relative, "\0")) {
            return null;
        }

        // Absolute or UNC: "/x", "\x", "\\server\share", "C:\x".
        if (preg_match('#^([a-zA-Z]:|[\\\\/])#', $relative)) {
            return null;
        }

        $segments = preg_split('#[\\\\/]+#', $relative) ?: [];
        foreach ($segments as $segment) {
            if ($segment === '..' || $segment === '.' || trim($segment) === '') {
                return null;
            }
        }

        $candidate = $this->root() . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
        $real = @realpath($candidate);
        $realRoot = @realpath($this->root());

        if ($real === false || $realRoot === false || !is_file($real)) {
            return null;
        }

        if (!$this->isInside($real, $realRoot)) {
            return null;
        }

        if (!in_array(strtolower(pathinfo($real, PATHINFO_EXTENSION)), $this->allowedExtensions(), true)) {
            return null;
        }

        return $real;
    }

    /** The item describing one relative path, or null if it does not resolve. */
    public function item(?string $relative): ?array
    {
        $absolute = $this->resolve($relative);
        if ($absolute === null) {
            return null;
        }

        $lga = trim(str_replace($this->root(), '', dirname($absolute)), '\\/');

        return $this->itemFor(new \SplFileInfo($absolute), $lga);
    }

    // ── Writing ──────────────────────────────────────────────────────────────

    /**
     * Put a plan in an LGA folder, named after the file number.
     *
     * An existing plan is never destroyed: it is renamed to
     * "<name>.2026-09-15-143000.bak.<ext>" beside itself before the new file
     * lands. The write is logged to the audit trail.
     *
     * @return array{item:array,replaced:?string,backup:?string}
     * @throws TdpException
     */
    public function store(UploadedFile $file, string $lga, string $fileNumber): array
    {
        if (!$this->isReachable()) {
            throw new TdpException($this->status()['message']);
        }

        if (!config('tdp.uploads_enabled', true)) {
            throw new TdpException('Uploading Title Deed Plans is switched off (TDP_UPLOADS_ENABLED).');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');
        if (!in_array($extension, $this->allowedExtensions(), true)) {
            throw new TdpException('A Title Deed Plan must be one of: ' . implode(', ', $this->allowedExtensions()) . '.');
        }

        $maxKb = max(1, (int) config('tdp.max_upload_kb', 25600));
        if ($file->getSize() !== false && $file->getSize() > $maxKb * 1024) {
            throw new TdpException('The plan is larger than the ' . number_format($maxKb / 1024, 0) . ' MB limit.');
        }

        $folder = $this->folderFor($lga);
        $base = $this->fileNameFor($fileNumber);
        if ($base === '') {
            throw new TdpException('Enter the file number the plan belongs to.');
        }

        $target = $folder . DIRECTORY_SEPARATOR . $base . '.' . $extension;

        // Paranoia, not decoration: the folder and the base name are both
        // derived from request input.
        if (!$this->isInside($target, (string) realpath($this->root()))) {
            throw new TdpException('That destination is outside the Title Deed Plan folder.');
        }

        $replaced = null;
        $backup = null;

        // Replacing also covers the same number stored under another allowed
        // extension — a PDF landing on top of a TIF of the same plan.
        foreach ($this->existingVariants($folder, $base) as $existing) {
            $stamp = now()->format('Y-m-d-His');
            $suffix = (string) config('tdp.backup_suffix', 'bak');
            $existingExt = pathinfo($existing, PATHINFO_EXTENSION);
            $backupPath = $folder . DIRECTORY_SEPARATOR . $base . '.' . $stamp . '.' . $suffix . '.' . $existingExt;

            if (!@rename($existing, $backupPath)) {
                throw new TdpException('The existing plan could not be set aside, so nothing was overwritten: ' . basename($existing));
            }

            $replaced = basename($existing);
            $backup = basename($backupPath);
        }

        try {
            $file->move($folder, $base . '.' . $extension);
        } catch (\Throwable $e) {
            // Put the old plan back rather than leave the folder empty.
            if ($backup !== null) {
                @rename($folder . DIRECTORY_SEPARATOR . $backup, $folder . DIRECTORY_SEPARATOR . $replaced);
            }

            throw new TdpException('The plan could not be written to ' . $folder . ': ' . $e->getMessage());
        }

        $this->scanCache = [];

        $item = $this->itemFor(new \SplFileInfo($target), basename($folder));

        $this->audit($replaced === null ? 'CREATED' : 'UPDATED', $item, $replaced, $backup);

        return ['item' => $item, 'replaced' => $replaced, 'backup' => $backup];
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /**
     * Normalised matching key: the file number stripped to letters and digits,
     * lower-cased. "LUAC:AB/7655/UM", "LUAC-AB-7655-UM" and "luac_ab_7655_um"
     * all become "luacab7655um".
     */
    /**
     * The canonical spelling of a file number, with no ALIS dependency.
     *
     * ALAES ran this through LegacyFileNumberNormalizer, which does not exist
     * in KLAES. Everything the library actually needs is here: upper-case,
     * separators collapsed to a single "/", and the surrounding punctuation
     * trimmed. key() then strips to letters and digits anyway, so this only
     * has to be consistent — not clever.
     *
     * Returns null for a blank input, matching the contract the three call
     * sites were written against (they all fall back with ??).
     */
    public static function normalizeNumber(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        if ($value === '') {
            return null;
        }

        $value = (string) preg_replace('#[\\/:_\-\s]+#', '/', $value);

        return trim($value, '/. ') ?: null;
    }

    public function key(?string $value): string
    {
        $value = (string) $value;
        if (trim($value) === '') {
            return '';
        }

        $normalized = self::normalizeNumber(str_replace(['_', '-'], '/', $value)) ?? $value;

        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $normalized));
    }

    /** The canonical spelling of a file name's number: ST/KN/7655/KMC. */
    public function fileNumberFrom(string $baseName): string
    {
        return self::normalizeNumber(str_replace(['_', '-'], '/', $baseName)) ?? strtoupper($baseName);
    }

    /** The file name a plan is stored under: ST/KN/7655/KMC → ST-KN-7655-KMC. */
    public function fileNameFor(string $fileNumber): string
    {
        $normalized = self::normalizeNumber($fileNumber) ?? strtoupper(trim($fileNumber));
        $name = preg_replace('#[\\\\/:*?"<>|\s]+#', '-', $normalized);
        $name = trim((string) $name, '-. ');

        return $name;
    }

    /**
     * One flat list of the plans under $lga (or all folders), optionally
     * filtered by file number. Cached per request.
     */
    private function scan(?string $lga, ?string $query): array
    {
        if (!$this->isReachable()) {
            return [];
        }

        $lgaKey = $this->key((string) $lga);
        $cacheKey = $lgaKey . '|' . $this->key((string) $query);

        if (isset($this->scanCache[$cacheKey])) {
            return $this->scanCache[$cacheKey];
        }

        $queryKey = $this->key((string) $query);
        $allowed = $this->allowedExtensions();
        $limit = max(100, (int) config('tdp.scan_limit', 20000));
        $backup = '.' . strtolower((string) config('tdp.backup_suffix', 'bak')) . '.';

        $items = [];
        $count = 0;

        foreach ($this->directoryEntries($this->root()) as $entry) {
            if (!$entry->isDir()) {
                continue;
            }

            $folderName = $entry->getFilename();
            if ($lgaKey !== '' && $this->key($folderName) !== $lgaKey) {
                continue;
            }

            foreach ($this->directoryEntries($entry->getPathname()) as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                if (!in_array(strtolower($file->getExtension()), $allowed, true)) {
                    continue;
                }
                // Superseded copies stay on disk but out of the library.
                if (str_contains(strtolower($file->getFilename()), $backup)) {
                    continue;
                }

                $item = $this->itemFor($file, $folderName);

                if ($queryKey !== '' && !str_contains($item['key'], $queryKey)
                    && !str_contains(strtolower($item['name']), strtolower(trim((string) $query)))) {
                    continue;
                }

                $items[] = $item;

                if (++$count >= $limit) {
                    break 2;
                }
            }
        }

        usort($items, function ($a, $b) {
            return [$a['lga'], $a['name']] <=> [$b['lga'], $b['name']];
        });

        return $this->scanCache[$cacheKey] = $items;
    }

    /**
     * @return array{name:string,lga:string,file_number:string,key:string,extension:string,size:int,modified:?int,relative_path:string}
     */
    private function itemFor(\SplFileInfo $file, string $lga): array
    {
        $name = $file->getFilename();
        $base = pathinfo($name, PATHINFO_FILENAME);

        return [
            'name' => $name,
            'lga' => $lga,
            'file_number' => $this->fileNumberFrom($base),
            'key' => $this->key($base),
            'extension' => strtolower($file->getExtension()),
            'size' => (int) (@$file->getSize() ?: 0),
            'modified' => @$file->getMTime() ?: null,
            'relative_path' => $lga . '/' . $name,
        ];
    }

    /** Directory listing that answers with nothing instead of raising. */
    private function directoryEntries(string $path): array
    {
        try {
            $iterator = new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS);
            $entries = [];
            foreach ($iterator as $entry) {
                $entries[] = $entry;
            }

            return $entries;
        } catch (\Throwable $e) {
            Log::warning('TDP listing failed', ['path' => $path, 'error' => $e->getMessage()]);

            return [];
        }
    }

    private function countFiles(string $folder): int
    {
        $allowed = $this->allowedExtensions();
        $backup = '.' . strtolower((string) config('tdp.backup_suffix', 'bak')) . '.';
        $count = 0;

        foreach ($this->directoryEntries($folder) as $file) {
            if ($file->isFile()
                && in_array(strtolower($file->getExtension()), $allowed, true)
                && !str_contains(strtolower($file->getFilename()), $backup)) {
                $count++;
            }
        }

        return $count;
    }

    /** Existing plans for a base name, whatever allowed extension they carry. */
    private function existingVariants(string $folder, string $base): array
    {
        $found = [];
        foreach ($this->allowedExtensions() as $extension) {
            $path = $folder . DIRECTORY_SEPARATOR . $base . '.' . $extension;
            if (is_file($path)) {
                $found[] = $path;
            }
        }

        return $found;
    }

    /**
     * The absolute path of the LGA folder to write into, created when the name
     * is a real KLAES LGA. A name with a separator in it never gets this far.
     *
     * @throws TdpException
     */
    private function folderFor(string $lga): string
    {
        $lga = trim($lga);

        if ($lga === '' || preg_match('#[\\\\/:*?"<>|]#', $lga) || str_contains($lga, '..')) {
            throw new TdpException('Choose the LGA folder the plan belongs in.');
        }

        // An existing folder, matched the way the library matches everywhere else.
        $key = $this->key($lga);
        foreach ($this->directoryEntries($this->root()) as $entry) {
            if ($entry->isDir() && $this->key($entry->getFilename()) === $key) {
                return $entry->getPathname();
            }
        }

        // Otherwise only a name KLAES knows may create a folder on the GIS server.
        foreach ($this->knownLgas() as $name) {
            if ($this->key($name) === $key) {
                $path = $this->root() . DIRECTORY_SEPARATOR . $name;
                if (!@mkdir($path, 0775, true) && !is_dir($path)) {
                    throw new TdpException('The LGA folder could not be created: ' . $path);
                }

                return $path;
            }
        }

        throw new TdpException('"' . $lga . '" is neither a folder in the TDP store nor a Kano LGA known to KLAES.');
    }

    /** The 44 Kano LGAs. Read-only; the DB being down must not break the page. */
    public function knownLgas(): array
    {
        try {
            return Lga::query()->orderBy('name')->pluck('name')
                ->map(fn ($n) => trim((string) $n))->filter()->values()->all();
        } catch (\Throwable $e) {
            Log::warning('TDP could not read the LGA list', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function knownLgaKeys(): array
    {
        $keys = [];
        foreach ($this->knownLgas() as $name) {
            $keys[$this->key($name)] = $name;
        }

        return $keys;
    }

    /** A suggestion for an unmatched folder — "Aba Nrth" → "Aba North". */
    private function closestLga(string $folder, array $lgas): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($lgas as $name) {
            $distance = levenshtein(strtolower($folder), strtolower($name));
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $name;
            }
        }

        return $bestDistance <= max(3, (int) floor(strlen($folder) / 3)) ? $best : null;
    }

    /** Case-insensitive on Windows, separator-agnostic on both. */
    private function isInside(string $path, string $root): bool
    {
        if ($root === '') {
            return false;
        }

        $normalize = function (string $value): string {
            $value = str_replace('\\', '/', $value);
            $value = rtrim($value, '/');

            return DIRECTORY_SEPARATOR === '\\' ? strtolower($value) : $value;
        };

        $path = $normalize($path);
        $root = $normalize($root);

        return $path !== $root && str_starts_with($path, $root . '/');
    }

    /** Uploads and replacements go to the audit trail (audit_logs). */
    private function audit(string $action, array $item, ?string $replaced, ?string $backup): void
    {
        try {
            // audit_logs.resource_id is a bigint, so the plan is identified in
            // the value payload rather than there.
            app(AuditService::class)->logAction(
                $action,
                'title_deed_plan',
                null,
                $replaced ? ['file' => $replaced, 'kept_as' => $backup] : null,
                [
                    'path' => $item['relative_path'],
                    'lga' => $item['lga'],
                    'file' => $item['name'],
                    'file_number' => $item['file_number'],
                    'size' => $item['size'],
                    'root' => $this->root(),
                ],
                ($replaced ? 'Title Deed Plan replaced' : 'Title Deed Plan uploaded') . ': ' . $item['relative_path']
            );
        } catch (\Throwable $e) {
            // The plan is on disk; a log failure must not undo that.
            Log::warning('TDP audit log failed', [
                'path' => $item['relative_path'],
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
