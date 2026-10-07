<?php

namespace App\Services;

use App\Models\FileIndexing;
use App\Models\Scanning;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Read and write the applicant's passport photograph for a commissioned file.
 *
 * The photograph is captured at commissioning (MlsFileNoController::storeCommissioningPassport)
 * and lands in TWO places, both of which matter:
 *
 *   1. the file's own EDMS scan folder (EDMS/SCAN_UPLOAD/Lands_Registry/{FILE NUMBER}),
 *      registered as a `scannings` row and automatically typed as Image / Passport;
 *   2. `oss_applications.passport_photo`, which is what the OSS screens read.
 *
 * This service exists because the EDIT paths need the same two writes plus a read-back, and
 * the commissioning copy is a private controller method bound to its own pre-resolved folder
 * handle. Two screens use it: File Number Generator → Edit, and the OSS File Commissioning
 * capture-edit page. Commissioning itself is deliberately left on its own copy: it runs
 * inside a flow that has already resolved the EDMS folder, and rerouting it here would put a
 * working registry write at risk for no gain.
 */
class FilePassportService
{
    /** Screen a photograph was filed from => the wording that goes on the scan row. */
    private const SOURCE_LABELS = [
        'file_number_edit'       => 'the file number edit form',
        'oss_commissioning_edit' => 'the OSS File Commissioning edit page',
    ];

    /**
     * Request-lifetime memo, shared by resolve() and prime().
     *
     * Keyed by the UPPER-CASED file number: the database collation is case-insensitive, so
     * "res-2026-1" and "RES-2026-1" are the same file and must not each cost a lookup.
     * A cached null means "asked, and this file has no passport" — distinct from absent.
     *
     * @var array<string, array{path:string, url:string, source:string}|null>
     */
    private static array $cache = [];

    /** Memo for the schema probes, which are otherwise repeated on every single call. */
    private static array $columnCache = [];

    /** Cache key for a file number. */
    private static function key(string $fileNumber): string
    {
        return strtoupper(trim($fileNumber));
    }

    /** hasColumn(), asked once per request instead of once per file. */
    private function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;

        if (!array_key_exists($key, self::$columnCache)) {
            try {
                self::$columnCache[$key] = Schema::connection('sqlsrv')->hasColumn($table, $column);
            } catch (\Throwable $e) {
                self::$columnCache[$key] = false;
            }
        }

        return self::$columnCache[$key];
    }

    /**
     * Resolve many file numbers in two queries and memoise them.
     *
     * The file tracker list decorates every row on the page, and that list has already been
     * timed out once by exactly this shape of per-row lookup — see
     * CreateFileTrackerController::index(), which primes the movement history, the
     * counterpart locations, the indexing timestamps and the holder photos the same way.
     * Call this with the page's file numbers before the loop; resolve() then answers from
     * memory.
     *
     * Both queries compare the column directly. The collation is case-insensitive, so
     * wrapping either side in UPPER() would only make the predicate non-sargable and cost
     * the index seek — the exact regression that made this list unusable before.
     */
    public function prime(iterable $fileNumbers): void
    {
        $numbers = collect($fileNumbers)
            ->map(fn ($n) => trim((string) $n))
            ->filter(fn ($n) => $n !== '' && !array_key_exists(self::key($n), self::$cache))
            ->unique(fn ($n) => self::key($n))
            ->values();

        if ($numbers->isEmpty()) {
            return;
        }

        // Everything asked for starts as "no passport"; the two passes below fill in hits.
        foreach ($numbers as $number) {
            self::$cache[self::key($number)] = null;
        }

        $wanted = $numbers->all();

        // Pass 1 — oss_applications, the authoritative column (see resolve()).
        if ($this->hasColumn('oss_applications', 'passport_photo')) {
            try {
                $query = DB::connection('sqlsrv')->table('oss_applications')
                    ->whereIn('file_no', $wanted)
                    ->whereNotNull('passport_photo')
                    ->where('passport_photo', '<>', '');

                if ($this->hasColumn('oss_applications', 'is_deleted')) {
                    $query->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    });
                }

                // Ascending id so the LAST row written wins, matching orderByDesc()+first().
                foreach ($query->orderBy('id')->get(['file_no', 'passport_photo']) as $row) {
                    $path = trim((string) $row->passport_photo);
                    if ($path !== '') {
                        self::$cache[self::key((string) $row->file_no)] = $this->describe($path, 'oss_applications');
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Could not batch-read passports from oss_applications', [
                    'files' => count($wanted),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Pass 2 — the scan folder, for the files pass 1 did not answer.
        $stillMissing = array_values(array_filter(
            $wanted,
            fn ($n) => self::$cache[self::key($n)] === null
        ));

        if ($stillMissing === []) {
            return;
        }

        try {
            $scans = DB::connection('sqlsrv')->table('scannings')
                ->join('file_indexings', 'file_indexings.id', '=', 'scannings.file_indexing_id')
                ->whereIn('file_indexings.file_number', $stillMissing)
                ->where('scannings.document_type', 'Passport Photograph')
                ->whereNotNull('scannings.document_path')
                ->orderBy('scannings.id')
                ->get(['file_indexings.file_number', 'scannings.document_path']);

            foreach ($scans as $scan) {
                $path = trim((string) $scan->document_path);
                if ($path !== '') {
                    self::$cache[self::key((string) $scan->file_number)] = $this->describe($path, 'scannings');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Could not batch-read passports from scannings', [
                'files' => count($stillMissing),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Forget everything memoised. Tests only — a request never needs this. */
    public static function flushCache(): void
    {
        self::$cache = [];
        self::$columnCache = [];
    }

    /**
     * Locate the passport currently on record for a file number.
     *
     * oss_applications is asked first — it is the column the rest of the system reads and
     * the one a re-upload overwrites, so it is authoritative for "the current photo". The
     * scan row is the fallback, and covers files commissioned when no OSS row existed.
     *
     * @return array{path:string, url:string, source:string}|null  null when the file has no passport
     */
    public function resolve(?string $fileNumber): ?array
    {
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '') {
            return null;
        }

        // Answered by prime() on the list screens; falls through to the two queries below
        // for a single-record screen that never primed.
        if (array_key_exists(self::key($fileNumber), self::$cache)) {
            return self::$cache[self::key($fileNumber)];
        }

        try {
            if (Schema::connection('sqlsrv')->hasColumn('oss_applications', 'passport_photo')) {
                $query = DB::connection('sqlsrv')->table('oss_applications')
                    ->where('file_no', $fileNumber)
                    ->whereNotNull('passport_photo')
                    ->where('passport_photo', '<>', '');

                if (Schema::connection('sqlsrv')->hasColumn('oss_applications', 'is_deleted')) {
                    $query->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    });
                }

                $row = $query->orderByDesc('id')->first(['passport_photo']);
                if ($row && trim((string) $row->passport_photo) !== '') {
                    return self::$cache[self::key($fileNumber)]
                        = $this->describe((string) $row->passport_photo, 'oss_applications');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Could not read passport from oss_applications', [
                'file_number' => $fileNumber,
                'error'       => $e->getMessage(),
            ]);
        }

        try {
            $scan = DB::connection('sqlsrv')->table('scannings')
                ->join('file_indexings', 'file_indexings.id', '=', 'scannings.file_indexing_id')
                ->where('file_indexings.file_number', $fileNumber)
                ->where('scannings.document_type', 'Passport Photograph')
                ->whereNotNull('scannings.document_path')
                ->orderByDesc('scannings.id')
                ->first(['scannings.document_path']);

            if ($scan && trim((string) $scan->document_path) !== '') {
                return self::$cache[self::key($fileNumber)]
                    = $this->describe((string) $scan->document_path, 'scannings');
            }
        } catch (\Throwable $e) {
            Log::warning('Could not read passport from scannings', [
                'file_number' => $fileNumber,
                'error'       => $e->getMessage(),
            ]);
        }

        return self::$cache[self::key($fileNumber)] = null;
    }

    /**
     * File a replacement/first passport photograph against an existing file number.
     *
     * Mirrors storeCommissioningPassport(): image into the file's EDMS scan folder, a
     * `scannings` row so EDMS can see it, then oss_applications.passport_photo repointed
     * at the new path.
     *
     * The previous scan row is deliberately left in place. These are registry documents —
     * superseding one is a new document, not an erasure of the old.
     *
     * Best-effort by design: the caller's record update has its own success criteria, and a
     * storage hiccup here must not turn a saved edit into a failed one.
     *
     * $source names the screen the photograph came from. It reaches the folder log, the
     * scan row's notes and the info line, so a registry document can be traced back to the
     * form that filed it — more than one screen now uploads through here.
     *
     * @return array{stored:bool, path:?string, url:?string, scanning_id:?int, reason:string}
     */
    public function store(UploadedFile $file, string $fileNumber, string $source = 'file_number_edit'): array
    {
        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            return $this->failed('no_file_number');
        }

        try {
            $folders = app(EdmsScanUploadFolderService::class);
            $folder  = $folders->ensureWithFolios($fileNumber, 'Lands Registry', ['source' => $source]);

            $directory = $folder['path'] ?? null;
            if (!$directory) {
                $directory = EdmsScanUploadFolderService::BASE_PATH
                    . '/' . $folders->registrySlug('Lands Registry')
                    . '/' . $folders->folderName($fileNumber);
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $filename  = 'passport_' . now()->format('Ymd_His') . '_' . Str::random(6) . '.' . $extension;
            $storedPath = $file->storeAs($directory, $filename, 'public');

            if (!$storedPath) {
                throw new \RuntimeException('Storage returned no path for the passport image.');
            }

            $scanningId = $this->registerScan($file, $fileNumber, $storedPath, $filename, $source);
            $this->pointOssApplicationAt($fileNumber, $storedPath);

            Log::info('Passport photograph filed', [
                'file_number' => $fileNumber,
                'path'        => $storedPath,
                'scanning_id' => $scanningId,
                'source'      => $source,
            ]);

            $described = $this->describe($storedPath, 'scannings');

            return [
                'stored'      => true,
                'path'        => $storedPath,
                'url'         => $described['url'],
                'scanning_id' => $scanningId,
                'reason'      => 'stored',
            ];
        } catch (\Throwable $e) {
            Log::warning('Could not file passport photograph', [
                'file_number' => $fileNumber,
                'source'      => $source,
                'error'       => $e->getMessage(),
            ]);

            return $this->failed('error');
        }
    }

    /**
     * Remove a file's passport photograph from OSS, EDMS scan rows and public storage.
     *
     * This is only reached through an explicit edit-form instruction. Clearing both
     * references is important: otherwise the scan fallback would make the photo appear
     * again after its OSS pointer was cleared.
     */
    public function remove(string $fileNumber): bool
    {
        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            return false;
        }

        $paths = collect();
        $changed = false;

        try {
            if ($this->hasColumn('oss_applications', 'passport_photo')) {
                $query = DB::connection('sqlsrv')->table('oss_applications')->where('file_no', $fileNumber);
                if ($this->hasColumn('oss_applications', 'is_deleted')) {
                    $query->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    });
                }

                $rows = $query->get(['id', 'passport_photo']);
                $paths = $paths->merge($rows->pluck('passport_photo'));
                if ($rows->isNotEmpty()) {
                    $changes = ['passport_photo' => null];
                    if ($this->hasColumn('oss_applications', 'updated_at')) {
                        $changes['updated_at'] = now();
                    }
                    DB::connection('sqlsrv')->table('oss_applications')->whereIn('id', $rows->pluck('id'))->update($changes);
                    $changed = true;
                }
            }

            $scans = DB::connection('sqlsrv')->table('scannings')
                ->join('file_indexings', 'file_indexings.id', '=', 'scannings.file_indexing_id')
                ->where('file_indexings.file_number', $fileNumber)
                ->where('scannings.document_type', 'Passport Photograph')
                ->get(['scannings.id', 'scannings.document_path']);

            if ($scans->isNotEmpty()) {
                $paths = $paths->merge($scans->pluck('document_path'));
                $scanIds = $scans->pluck('id');
                $typedPaths = DB::connection('sqlsrv')->table('pagetypings')
                    ->whereIn('scanning_id', $scanIds)->pluck('file_path');
                $paths = $paths->merge($typedPaths);
                foreach ($typedPaths as $typedPath) {
                    if (str_starts_with((string) $typedPath, 'EDMS/PAGETYPING/')) {
                        $paths->push(str_replace('EDMS/PAGETYPING/', 'EDMS/ARCHIVE_Doc_WARE/', $typedPath));
                    }
                }
                DB::connection('sqlsrv')->transaction(function () use ($scanIds) {
                    DB::connection('sqlsrv')->table('pagetypings')->whereIn('scanning_id', $scanIds)->delete();
                    DB::connection('sqlsrv')->table('scannings')->whereIn('id', $scanIds)->delete();
                });
                $changed = true;
            }

            foreach ($paths->filter()->unique() as $path) {
                $this->deleteStoredPassport((string) $path);
            }

            self::$cache[self::key($fileNumber)] = null;
            Log::info('Passport photograph removed', ['file_number' => $fileNumber, 'changed' => $changed]);
            return $changed;
        } catch (\Throwable $e) {
            Log::warning('Could not remove passport photograph', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /** Delete only a relative public-disk path; never act on an external path. */
    private function deleteStoredPassport(string $path): void
    {
        $clean = str_replace('\\', '/', trim($path));
        if (str_starts_with($clean, 'public/')) {
            $clean = substr($clean, 7);
        }
        if ($clean === '' || str_starts_with($clean, '/') || str_contains($clean, '..')) {
            return;
        }

        try {
            Storage::disk('public')->delete($clean);
        } catch (\Throwable $e) {
            Log::warning('Could not delete passport image from storage', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register the stored image as a scan document so it shows up in EDMS.
     * Returns null when the file carries no file_indexings row to hang the scan off
     * (temporary files are deliberately not indexed) — the image still lives on disk.
     */
    private function registerScan(UploadedFile $file, string $fileNumber, string $storedPath, string $filename, string $source): ?int
    {
        $indexing = FileIndexing::on('sqlsrv')->where('file_number', $fileNumber)->first();

        if (!$indexing) {
            Log::info('Passport stored without a scan row - file has no file_indexings record', [
                'file_number' => $fileNumber,
                'path'        => $storedPath,
            ]);

            return null;
        }

        $scanning = app(\App\Services\Edms\PassportPageTypingService::class)->register([
            'file_indexing_id'  => $indexing->id,
            'document_path'     => $storedPath,
            'uploaded_by'       => Auth::id(),
            'original_filename' => $file->getClientOriginalName() ?: $filename,
            'paper_size'        => 'A4',
            'document_type'     => 'Passport Photograph',
            'notes'             => 'Applicant passport updated from ' . (self::SOURCE_LABELS[$source] ?? $source) . '.',
            'file_size'         => $file->getSize(),
            'registry'          => 'Lands Registry',
            'is_pdf_converted'  => false,
        ]);

        try {
            $indexing->update(['is_updated' => 1]);
        } catch (\Throwable $e) {
            Log::warning('Could not flag file_indexings.is_updated after passport upload', [
                'file_number' => $fileNumber,
                'error'       => $e->getMessage(),
            ]);
        }

        return (int) $scanning->id;
    }

    /**
     * Repoint the file's OSS application at the new image. No OSS row simply means the
     * photo lives in EDMS only, which is the same outcome commissioning produces.
     */
    private function pointOssApplicationAt(string $fileNumber, string $storedPath): void
    {
        try {
            if (!Schema::connection('sqlsrv')->hasColumn('oss_applications', 'passport_photo')) {
                return;
            }

            $query = DB::connection('sqlsrv')->table('oss_applications')->where('file_no', $fileNumber);

            if (Schema::connection('sqlsrv')->hasColumn('oss_applications', 'is_deleted')) {
                $query->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                });
            }

            $existing = $query->orderByDesc('id')->first(['id']);
            if (!$existing) {
                return;
            }

            $changes = ['passport_photo' => $storedPath];
            if (Schema::connection('sqlsrv')->hasColumn('oss_applications', 'updated_at')) {
                $changes['updated_at'] = now();
            }

            DB::connection('sqlsrv')->table('oss_applications')->where('id', $existing->id)->update($changes);
        } catch (\Throwable $e) {
            Log::warning('Could not point oss_applications.passport_photo at the new image', [
                'file_number' => $fileNumber,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Stored paths are relative to the `public` disk, but legacy rows carry a "public/"
     * prefix. Strip it so both shapes resolve to the same /storage URL (matches
     * LandsOneStopShop\ApplicationController::ossPassportUrl()).
     *
     * @return array{path:string, url:string, source:string}
     */
    private function describe(string $path, string $source): array
    {
        $clean = trim($path);
        if (str_starts_with($clean, 'public/')) {
            $clean = substr($clean, 7);
        }

        return [
            'path'   => $path,
            'url'    => asset('storage/' . ltrim($clean, '/')),
            'source' => $source,
        ];
    }

    /** @return array{stored:bool, path:?string, url:?string, scanning_id:?int, reason:string} */
    private function failed(string $reason): array
    {
        return ['stored' => false, 'path' => null, 'url' => null, 'scanning_id' => null, 'reason' => $reason];
    }
}
