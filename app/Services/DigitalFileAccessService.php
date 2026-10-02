<?php

namespace App\Services;

use App\Models\DigitalFileAccess;
use App\Models\DigitalFileRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DigitalFileAccessService
{
    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Full orchestration: find → copy → record.
     * Throws \RuntimeException if source files cannot be found.
     */
    public function grantAccess(DigitalFileRequest $request): DigitalFileAccess
    {
        $userId  = $request->requester_user_id;
        $fileNo  = $request->file_no;

        // 1. Find files in raw/network folder
        $sourcePaths = $this->findFilesInRawFolder($fileNo);

        // 2. Create user-specific temp folder
        $relFolder = $this->createTempFolder($userId, $fileNo);

        // 3. Copy files across
        $copiedNames = $this->copyFilesToTemp($sourcePaths, $relFolder);

        // 4. Calculate expiry (working days)
        $expiresAt = $this->calculateExpiry(config('dfr.access_days', 5));

        // 5. Persist the access record
        $access = DigitalFileAccess::create([
            'digital_request_id' => $request->id,
            'requester_user_id'  => $userId,
            'file_no'            => $fileNo,
            'file_title'         => $request->file_title,
            'temp_folder_path'   => $relFolder,
            'files_copied'       => $copiedNames,
            'expires_at'         => $expiresAt,
            'granted_at'         => now(),
            'access_status'      => DigitalFileAccess::STATUS_ACTIVE,
        ]);

        Log::info("DFR digital access granted", [
            'access_id'    => $access->id,
            'user_id'      => $userId,
            'file_no'      => $fileNo,
            'files_copied' => count($copiedNames),
            'expires_at'   => $expiresAt->toDateTimeString(),
        ]);

        return $access;
    }

    /**
     * Search the configured raw folder for files belonging to the given file number.
     *
     * Strategy:
     *  1. Look for a subdirectory matching the file number (case-insensitive)
     *  2. If not found, look for files whose name contains the file number
     *
     * @throws \RuntimeException
     */
    public function findFilesInRawFolder(string $fileNo): array
    {
        $rawRoot = rtrim(config('dfr.raw_folder', ''), DIRECTORY_SEPARATOR);

        if (empty($rawRoot)) {
            throw new \RuntimeException(
                'DFR raw folder path is not configured. Set DFR_RAW_FOLDER_PATH in .env'
            );
        }

        if (!is_dir($rawRoot)) {
            throw new \RuntimeException(
                "DFR raw folder does not exist or is not accessible: {$rawRoot}"
            );
        }

        $allowed = config('dfr.allowed_extensions', ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff']);

        // --- Strategy 1: look for a folder named exactly (or case-insensitively) like file_no ---
        $files = $this->searchInSubfolder($rawRoot, $fileNo, $allowed);

        if (!empty($files)) {
            return $files;
        }

        // --- Strategy 2: flat scan of root for files whose name contains file_no ---
        $files = $this->searchFlatInRoot($rawRoot, $fileNo, $allowed);

        if (empty($files)) {
            throw new \RuntimeException(
                "No digital files found for file number [{$fileNo}] in raw folder: {$rawRoot}"
            );
        }

        return $files;
    }

    /**
     * Create the temporary folder for a given user + file number.
     * Returns the relative path (relative to storage/app/).
     */
    public function createTempFolder(int $userId, string $fileNo): string
    {
        // Sanitise file number for use in a path (replace slashes / spaces)
        $safeFileNo = preg_replace('/[^A-Za-z0-9_\-]/', '_', $fileNo);

        // Always use forward slashes for Storage paths (works on both Windows and Unix).
        // DIRECTORY_SEPARATOR is '\' on Windows which breaks Laravel's Storage abstraction.
        $relPath = config('dfr.temp_base', 'DFR') . '/' . $userId . '/' . $safeFileNo;

        $disk = config('dfr.temp_disk', 'local');

        if (! Storage::disk($disk)->exists($relPath)) {
            Storage::disk($disk)->makeDirectory($relPath);
        }

        // Also ensure the real OS directory exists (belt-and-suspenders for Windows)
        $absPath = storage_path('app' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath));
        if (! is_dir($absPath)) {
            mkdir($absPath, 0755, true);
        }

        // Return path with forward slashes so copyFilesToTemp can reconstruct the absolute path safely
        return $relPath;
    }

    /**
     * Copy source files into the temp folder.
     * Returns array of basenames successfully copied.
     */
    public function copyFilesToTemp(array $sourcePaths, string $relTempFolder): array
    {
        // Convert the stored forward-slash relative path to an OS-correct absolute path
        $destBase = storage_path('app' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relTempFolder));
        $copied   = [];

        // Ensure dest directory exists (defensive: createTempFolder should already handle this)
        if (! is_dir($destBase)) {
            mkdir($destBase, 0755, true);
        }

        foreach ($sourcePaths as $src) {
            if (!is_file($src)) {
                Log::warning("DFR copy: source file not found — {$src}");
                continue;
            }

            $basename = basename($src);
            $dest     = $destBase . DIRECTORY_SEPARATOR . $basename;

            if (copy($src, $dest)) {
                $copied[] = $basename;
                Log::info("DFR copy: {$basename} → {$relTempFolder}");
            } else {
                Log::error("DFR copy failed: {$src} → {$dest}");
            }
        }

        if (empty($copied)) {
            throw new \RuntimeException('No files could be copied to the temporary folder.');
        }

        return $copied;
    }

    /**
     * Calculate expiry N working days (Mon–Fri) from now.
     */
    public function calculateExpiry(int $workingDays = 5): Carbon
    {
        $date  = Carbon::now();
        $added = 0;

        while ($added < $workingDays) {
            $date->addDay();
            if ($date->isWeekday()) {
                $added++;
            }
        }

        return $date->endOfDay(); // expires at end-of-day on the Nth working day
    }

    /**
     * Batch cleanup: mark expired accesses and delete their temp folders.
     * Returns the number of accesses cleaned up.
     */
    public function cleanupExpired(): int
    {
        $expired = DigitalFileAccess::expired()->get();
        $count   = 0;

        foreach ($expired as $access) {
            $this->deleteTempFolder($access);
            $access->update(['access_status' => DigitalFileAccess::STATUS_EXPIRED]);
            $count++;

            Log::info("DFR cleanup: expired access #{$access->id} for file {$access->file_no} (user {$access->requester_user_id})");
        }

        return $count;
    }

    /**
     * Revoke a single access immediately and remove its temp folder.
     */
    public function revokeAccess(DigitalFileAccess $access): void
    {
        $this->deleteTempFolder($access);
        $access->update(['access_status' => DigitalFileAccess::STATUS_REVOKED]);

        Log::info("DFR revoke: access #{$access->id} revoked for file {$access->file_no}");
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Normalise a file number / name for matching: lower-cased and stripped of
     * every non-alphanumeric character. This makes "RES/2024/1990",
     * "RES-2024-1990" and "res 2024 1990" all compare equal.
     */
    private function normalizeKey(string $s): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $s));
    }

    /**
     * Look for a subdirectory (at any depth) whose name matches $fileNo
     * (separator-insensitive) and collect allowed files recursively inside it.
     * Matches from multiple folders are merged.
     */
    private function searchInSubfolder(string $root, string $fileNo, array $allowed): array
    {
        $key     = $this->normalizeKey($fileNo);
        $results = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $item) {
            if ($item->isDir() && $this->normalizeKey($item->getFilename()) === $key) {
                $results = array_merge($results, $this->collectFiles($item->getPathname(), $allowed));
            }
        }

        return array_values(array_unique($results));
    }

    /**
     * Recursively scan $root for files whose name contains $fileNo
     * (separator-insensitive).
     */
    private function searchFlatInRoot(string $root, string $fileNo, array $allowed): array
    {
        $key     = $this->normalizeKey($fileNo);
        $results = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $item) {
            if (!$item->isFile()) continue;
            $ext = strtolower($item->getExtension());
            if (!in_array($ext, $allowed)) continue;
            if (str_contains($this->normalizeKey($item->getFilename()), $key)) {
                $results[] = $item->getPathname();
            }
        }

        sort($results); // stable, predictable page order
        return $results;
    }

    /**
     * Collect all allowed files from a directory (recursively).
     */
    private function collectFiles(string $dir, array $allowed): array
    {
        $files = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $item) {
            if (!$item->isFile()) continue;
            $ext = strtolower($item->getExtension());
            if (in_array($ext, $allowed)) {
                $files[] = $item->getPathname();
            }
        }

        sort($files);
        return $files;
    }

    /**
     * Delete the temp folder for a given access record.
     */
    private function deleteTempFolder(DigitalFileAccess $access): void
    {
        $disk    = config('dfr.temp_disk', 'local');
        $relPath = $access->temp_folder_path;

        if ($relPath && Storage::disk($disk)->exists($relPath)) {
            Storage::disk($disk)->deleteDirectory($relPath);
        }
    }
}
