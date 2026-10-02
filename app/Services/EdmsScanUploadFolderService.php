<?php

namespace App\Services;

use App\Models\FileIndexing;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The file's scan folder under storage/app/public/EDMS/SCAN_UPLOAD.
 *
 * Layout, which the scanning and page-typing modules already rely on:
 *
 *      EDMS/SCAN_UPLOAD/{Registry_Slug}/{FILE NUMBER}/
 *
 * Created as early as possible so the folder is waiting before anyone scans into
 * it — at commissioning (MLS and ST) and again at indexing for files that were
 * never commissioned through KLAES. Previously it only appeared when the first
 * page was uploaded (ScanningController), or from a copy of this logic inside
 * MlsFileNoController that hardcoded Lands_Registry.
 *
 * The registry slug MUST match what the readers compute, or scans get written to
 * one folder and looked for in another — PageTypingController::getRegistrySlug()
 * is the map this mirrors, and the paper-size subfolder (A4/A3) is deliberately
 * NOT created here: the uploader makes it, and an empty A4 folder would imply a
 * scan session that never happened.
 *
 * ── Counterpart folios ───────────────────────────────────────────────────────
 *
 * A land file does not live in one registry. The same file number also has a
 * physical folder in the Cadastral registry and in the Physical Planning
 * registry, and until now nothing on disk said so — a scan belonging to the
 * cadastral copy had nowhere to go. ensureWithFolios() therefore creates the
 * file-number folder in those two registries alongside the home one, so all
 * three folios exist from the moment the file is indexed or commissioned.
 *
 * LAND FILES ONLY. A DCIV, Secret, Deeds, ST, SLTR or KANGIS file has no
 * cadastral or planning counterpart on paper, so it gets its own folder and
 * nothing else — see FOLIO_HOME_REGISTRY.
 *
 * Deliberately NAKED (no edms_file_type segment). The classification — Regular,
 * Subdivision Mother, Regrant New — is a fact about the file in its HOME
 * registry; stamping it into all three would put the same fact on disk in three
 * places, and EdmsFileTypeTransferService would then have to move three folders
 * every time an operator re-types a file. The naked layout is a first-class
 * supported state (EdmsFileType: a NULL type contributes no segment), it is what
 * EdmsDocumentPathResolver::candidates() already probes, and it is what the
 * Physical Planning folders on disk already look like — shadow:import reads bare
 * file-number folder names straight out of that registry.
 */
class EdmsScanUploadFolderService
{
    public const BASE_PATH = 'EDMS/SCAN_UPLOAD';

    /** Registry name => folder slug. Mirrors PageTypingController::getRegistrySlug(). */
    private const REGISTRY_SLUGS = [
        'Lands Registry'     => 'Lands_Registry',
        'Cadastral Registry' => 'Cadastral_Registry',
        'DCIV Registry'      => 'DCIV_Registry',
        'Secret Registry'    => 'Secret_Registry',
        'KANGIS Registry'    => 'KANGIS_Registry',
        'SLTR Registry'      => 'SLTR_Registry',
        'ST Registry'        => 'ST_Registry',
        'Deeds Registry'     => 'Deeds_Registry',
        // Not a home registry for any file KLAES issues, but every file gets a
        // counterpart folio here — see FOLIO_REGISTRIES. The character-scrub in
        // registrySlug() already produced this exact slug; it is spelled out so
        // the folio target cannot drift if that fallback is ever narrowed.
        'Physical Planning Registry' => 'Physical_Planning_Registry',
    ];

    /**
     * The registries a LAND file gets a counterpart folio in, on top of its own.
     */
    public const FOLIO_REGISTRIES = [
        'Cadastral Registry',
        'Physical Planning Registry',
    ];

    /**
     * The only home registry whose files get counterpart folios.
     *
     * A land file is the one that genuinely sits in three places: the Lands
     * registry holds the file, and the Cadastral and Physical Planning registries
     * each keep their own folder for the same parcel. Nothing corresponding
     * exists for a DCIV, Secret, Deeds, ST, SLTR or KANGIS file — giving those an
     * empty Cadastral folio would put folders on disk for a paper file that is
     * not there, which is worse than having none: an operator sent to fetch it
     * finds nothing and cannot tell a missing file from a folder that never
     * should have existed.
     */
    public const FOLIO_HOME_REGISTRY = 'Lands Registry';

    /**
     * Create the file's scan folder, and its counterpart folios, if not already there.
     *
     * Best-effort: the file is already indexed by the time this runs, so a storage
     * failure is logged and reported, never thrown.
     *
     * @return array{created:bool, existed:bool, path:?string, registry:?string, reason:string, folios:array<string,array>}
     */
    public function ensureForIndexing(FileIndexing $record): array
    {
        return $this->ensureWithFolios(
            (string) ($record->file_number ?: $record->temp_file_no ?: ''),
            $this->registryNameFor($record),
            ['file_indexing_id' => $record->id]
        );
    }

    /**
     * The home scan folder, plus a folio in each of FOLIO_REGISTRIES for a land file.
     *
     * A file in any other registry gets its home folder and nothing else, so
     * `folios` comes back empty and the caller renders no folio lines.
     *
     * Returns the home outcome unchanged, with the folios hung off it under a
     * `folios` key — every existing caller reads `path`/`reason` off the top
     * level and keeps working without knowing folios exist.
     *
     * @param  array<string,mixed>  $logContext extra fields for the log lines
     * @return array{created:bool, existed:bool, path:?string, registry:?string, reason:string, folios:array<string,array>}
     */
    public function ensureWithFolios(string $fileNumber, ?string $registryName = null, array $logContext = []): array
    {
        $home = $this->ensure($fileNumber, $registryName, $logContext);
        $home['folios'] = $this->ensureFolios($fileNumber, $registryName, $logContext);

        return $home;
    }

    /**
     * Create the counterpart folio in each registry the file also physically sits in.
     *
     * Keyed by registry display name so the caller can name the registry in the
     * post-action prompt without re-deriving it from the slug.
     *
     * @param  array<string,mixed>  $logContext
     * @return array<string,array{created:bool, existed:bool, path:?string, registry:?string, reason:string}>
     */
    public function ensureFolios(string $fileNumber, ?string $registryName = null, array $logContext = []): array
    {
        $homeSlug = $this->registrySlug($registryName);

        // Land files only. Every other registry gets its own folder and nothing
        // else — see FOLIO_HOME_REGISTRY. Compared on the slug so the "Land
        // Registry" / "Lands Registry" / land-use-id spellings normalizeRegistry()
        // already folds together all pass.
        if ($homeSlug !== $this->registrySlug(self::FOLIO_HOME_REGISTRY)) {
            return [];
        }

        $folios = [];

        foreach (self::FOLIO_REGISTRIES as $folioRegistry) {
            $folios[$folioRegistry] = $this->ensure(
                $fileNumber,
                $folioRegistry,
                array_merge($logContext, ['folio_of' => $homeSlug])
            );
        }

        return $folios;
    }

    /**
     * Create the scan folder for a bare file number + registry.
     *
     * Used by the commissioning flows (MLS and ST), where the file has no
     * file_indexings row yet — the folder is made the moment the number is issued,
     * so scanning can begin before the file is ever indexed.
     *
     * @param  array<string,mixed>  $logContext extra fields for the log lines
     * @return array{created:bool, existed:bool, path:?string, registry:?string, reason:string}
     */
    public function ensure(string $fileNumber, ?string $registryName = null, array $logContext = []): array
    {
        $fileNumber = trim($fileNumber);

        if ($fileNumber === '') {
            return $this->outcome(false, false, null, null, 'no_file_number');
        }

        $folderName = $this->folderName($fileNumber);
        if ($folderName === '') {
            return $this->outcome(false, false, null, null, 'unusable_file_number');
        }

        $slug = $this->registrySlug($registryName);
        $path = self::BASE_PATH . '/' . $slug . '/' . $folderName;

        try {
            if (Storage::disk('public')->exists($path)) {
                return $this->outcome(false, true, $path, $slug, 'already_exists');
            }

            Storage::disk('public')->makeDirectory($path);

            Log::info('EdmsScanUploadFolderService - created scan folder', array_merge($logContext, [
                'file_number' => $fileNumber,
                'path'        => $path,
            ]));

            return $this->outcome(true, false, $path, $slug, 'created');
        } catch (\Throwable $e) {
            Log::warning('EdmsScanUploadFolderService - could not create scan folder', array_merge($logContext, [
                'file_number' => $fileNumber,
                'path'        => $path,
                'error'       => $e->getMessage(),
            ]));

            return $this->outcome(false, false, $path, $slug, 'error');
        }
    }

    /**
     * The registry this file's scans belong under.
     *
     * general_registry carries the readable name ("Lands Registry", "KANGIS
     * Registry"); the older `registry` column mostly holds land-use ids (1/2/3)
     * or short codes, so it is only a fallback — and normalizeRegistry() maps the
     * numeric ids onto Lands Registry the way the rest of the app does.
     */
    private function registryNameFor(FileIndexing $record): string
    {
        foreach ([$record->general_registry ?? null, $record->registry ?? null] as $candidate) {
            $value = $this->collapseSpaces((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return 'Lands Registry';
    }

    /**
     * Folder slug for a registry name. Unknown registries (SIT, Survey, Physical
     * Planning …) fall through to the same character-scrub the readers apply, so
     * a registry that is not in the map still resolves to one consistent folder.
     */
    public function registrySlug(?string $registry): string
    {
        $normalized = $this->normalizeRegistry($registry);

        return self::REGISTRY_SLUGS[$normalized]
            ?? preg_replace('/[^A-Za-z0-9_\-]/', '_', $normalized);
    }

    /** Mirrors PageTypingController::normalizeRegistry(). */
    private function normalizeRegistry(?string $registry): string
    {
        $registry = $this->collapseSpaces((string) $registry);

        if ($registry === '') {
            return 'Lands Registry';
        }

        // 1/2/3 are land-use ids on the legacy `registry` column, not registries.
        if (in_array($registry, ['1', '2', '3'], true)) {
            return 'Lands Registry';
        }

        // "Land Registry" / "Land  Registry" are typo variants of the real name;
        // without this they would each mint their own folder.
        if (preg_match('/^Lands?\s+Registry$/i', $registry)) {
            return 'Lands Registry';
        }

        return $registry;
    }

    /**
     * Filesystem-safe folder name for a file number, matching the sanitiser the
     * commissioning path already uses (MlsFileNoController). A normal land number
     * (RES-2024-10, AG-2026-7(T)) passes through untouched; only characters
     * Windows forbids in a path are folded to "-".
     */
    public function folderName(string $fileNumber): string
    {
        $safe = preg_replace('/[\\\\\/\:\*\?"<>\|]+/', '-', trim($fileNumber));

        return trim((string) $safe);
    }

    /** @return array{created:bool, existed:bool, path:?string, registry:?string, reason:string} */
    private function outcome(bool $created, bool $existed, ?string $path, ?string $registry, string $reason): array
    {
        return [
            'created'  => $created,
            'existed'  => $existed,
            'path'     => $path,
            'registry' => $registry,
            'reason'   => $reason,
        ];
    }

    private function collapseSpaces(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
