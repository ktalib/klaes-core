<?php

namespace App\Services\Edms;

use App\Models\FileIndexing;
use App\Models\PageTyping;
use App\Models\ScanReassignmentLog;
use App\Models\Scanning;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\FilearchiveController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EdmsDocumentRelocationService
 *
 * Moves a file's documents to a new place in the EDMS trees:
 *
 *   EDMS/SCAN_UPLOAD/{registry}/{type?}/{file_number}/...      (the original)
 *   EDMS/PAGETYPING/{registry}/{type?}/{file_number}/...       (typed copies)
 *   EDMS/ARCHIVE_Doc_WARE/{registry}/{type?}/{file_number}/... (Doc-WARE archive)
 *
 * Both the registry and the EDMS file type are folder segments, so changing
 * either on the indexing record without moving the documents strands every one
 * of them. This keeps the two in step: the physical files move, and
 * file_indexings / scannings / pagetypings are all re-pointed in one
 * transaction.
 *
 * Files are MOVED here, not copied — unlike page typing, which copies from
 * SCAN_UPLOAD into the derived trees. A relocation is a correction or a
 * classification: the documents did not belong where they were.
 *
 * The two entry points that wrap this — EdmsRegistryTransferService (change the
 * registry, keep the type) and EdmsFileTypeTransferService (change the type,
 * keep the registry) — exist so each interface can present one decision at a
 * time. The move underneath is the same one.
 */
class EdmsDocumentRelocationService
{
    /** @var EdmsDocumentPathResolver */
    private $paths;

    /** @var Filesystem */
    private $filesystem;

    public function __construct(EdmsDocumentPathResolver $paths, Filesystem $filesystem)
    {
        $this->paths = $paths;
        $this->filesystem = $filesystem;
    }

    /**
     * Work out exactly what a move would do, touching nothing.
     *
     * @param  string|null  $targetRegistry  null keeps the file's current registry
     * @param  string|null  $targetFileType  the EDMS file-type key; null means the
     *                                       registry root (unclassified)
     * @param  bool  $fileTypeIsChanging  false keeps the file's current type — the
     *                                    only way to tell "keep it" from "clear it",
     *                                    since null is a legitimate destination
     * @return array{
     *   file_indexing_id: int,
     *   file_number: string,
     *   from_registry: string, to_registry: string,
     *   from_slug: string, to_slug: string,
     *   from_file_type: ?string, to_file_type: ?string,
     *   from_file_type_label: string, to_file_type_label: string,
     *   scannings: array, pagetypings: array, counts: array, blockers: string[]
     * }
     */
    public function preview(FileIndexing $file, ?string $targetRegistry, ?string $targetFileType, bool $fileTypeIsChanging): array
    {
        $fromRegistry = $this->paths->registryName($file->registry);
        $toRegistry = $targetRegistry === null || $targetRegistry === ''
            ? $fromRegistry
            : $this->paths->registryName($targetRegistry);

        $fromFileType = EdmsFileType::normalize($file->edms_file_type);
        $toFileType = $fileTypeIsChanging ? EdmsFileType::normalize($targetFileType) : $fromFileType;

        $fromPrefix = $this->paths->registryTypePrefix($fromRegistry, $fromFileType);
        $toPrefix = $this->paths->registryTypePrefix($toRegistry, $toFileType);

        $blockers = [];
        if ($fromPrefix === $toPrefix) {
            $blockers[] = sprintf(
                'File %s is already in %s.',
                $file->file_number,
                $toFileType === null ? $toRegistry : $toRegistry . ' / ' . EdmsFileType::label($toFileType)
            );
        }

        $scannings = [];
        $missing = 0;
        foreach ($this->scanningsFor($file) as $scan) {
            $context = $this->paths->contextFromScanning($scan, $file);
            $resolved = $this->paths->resolveRelative($scan->document_path, $context);

            $target = $resolved
                ? $this->retarget($resolved, $toRegistry, $toFileType, $file->file_number, $scan->paper_size)
                : null;

            if (!$resolved) {
                $missing++;
            }

            $scannings[] = [
                'id' => $scan->id,
                'original_filename' => $scan->original_filename,
                'stored_path' => $scan->document_path,
                'resolved_path' => $resolved,
                'target_path' => $target,
                'on_disk' => (bool) $resolved,
                'conflict' => $target ? $this->conflicts($resolved, $target) : false,
            ];
        }

        $pagetypings = [];
        foreach ($this->pageTypingsFor($file) as $pt) {
            $paperSize = optional($pt->scanning)->paper_size;

            $resolved = $this->paths->resolveRelative($pt->file_path, [
                'file_number' => $file->file_number,
                'registry' => $pt->registry ?? $file->registry,
                'paper_size' => $paperSize,
                'file_type' => $pt->edms_file_type ?? $file->edms_file_type,
            ]);

            $target = $resolved
                ? $this->retarget($resolved, $toRegistry, $toFileType, $file->file_number, $paperSize)
                : null;

            if (!$resolved) {
                $missing++;
            }

            $pagetypings[] = [
                'id' => $pt->id,
                'page_number' => $pt->page_number,
                'definition_code' => $pt->definition_code,
                'stored_path' => $pt->file_path,
                'resolved_path' => $resolved,
                'target_path' => $target,
                'on_disk' => (bool) $resolved,
                'conflict' => $target ? $this->conflicts($resolved, $target) : false,
            ];
        }

        $conflicts = collect($scannings)->where('conflict', true)->count()
            + collect($pagetypings)->where('conflict', true)->count();

        if ($conflicts > 0) {
            $blockers[] = "{$conflicts} document(s) already exist at the destination. "
                . 'Resolve the duplicates before moving.';
        }

        return [
            'file_indexing_id' => $file->id,
            'file_number' => $file->file_number,
            'from_registry' => $fromRegistry,
            'to_registry' => $toRegistry,
            'from_slug' => $fromPrefix,
            'to_slug' => $toPrefix,
            'from_file_type' => $fromFileType,
            'to_file_type' => $toFileType,
            'from_file_type_label' => EdmsFileType::label($fromFileType) ?? 'Unclassified',
            'to_file_type_label' => EdmsFileType::label($toFileType) ?? 'Unclassified',
            'scannings' => $scannings,
            'pagetypings' => $pagetypings,
            'counts' => [
                'scannings' => count($scannings),
                'pagetypings' => count($pagetypings),
                'missing_on_disk' => $missing,
                'conflicts' => $conflicts,
            ],
            'blockers' => $blockers,
        ];
    }

    /**
     * Move a file's documents (and its records) to the destination.
     *
     * Note that `registry` is always rewritten to the resolved display name, even
     * on a file-type-only move where the caller passed no registry. The folder the
     * documents land in is built from that resolved name, so leaving the column
     * saying something else ('', a numeric land-use id) would put the record and
     * the disk out of step. Empty already resolved to Lands Registry everywhere,
     * so this changes where nothing is read, only what the row admits to.
     *
     * @param  bool  $dryRun  when true, nothing is written — the preview is returned as-is
     *
     * @throws \InvalidArgumentException when the move is blocked
     */
    public function relocate(
        FileIndexing $file,
        ?string $targetRegistry,
        ?string $targetFileType,
        bool $fileTypeIsChanging,
        ?string $reason = null,
        bool $dryRun = false,
        ?int $actorId = null
    ): array {
        $preview = $this->preview($file, $targetRegistry, $targetFileType, $fileTypeIsChanging);

        if (!empty($preview['blockers'])) {
            throw new \InvalidArgumentException(implode(' ', $preview['blockers']));
        }

        if ($dryRun) {
            return $preview + ['dry_run' => true, 'moved' => ['files' => 0, 'scannings' => 0, 'pagetypings' => 0]];
        }

        $actorId = $this->resolveActorId($file, $actorId);

        // The filesystem is not transactional: if the DB rolls back after files
        // have moved, the records point at paths that no longer hold anything.
        // Every move and creation is journalled so it can be undone by hand.
        $journal = ['moves' => [], 'created' => []];

        try {
            return DB::connection('sqlsrv')->transaction(function () use ($file, $preview, $reason, $actorId, &$journal) {
                return $this->apply($file, $preview, $reason, $actorId, $journal);
            });
        } catch (\Throwable $e) {
            $this->undo($journal);

            throw $e;
        }
    }

    /**
     * The body of a move. Runs inside the DB transaction; every filesystem change
     * it makes is recorded in $journal so a failure can be unwound.
     */
    private function apply(FileIndexing $file, array $preview, ?string $reason, int $actorId, array &$journal): array
    {
        $toRegistry = $preview['to_registry'];
        $toFileType = $preview['to_file_type'];

        $filesMoved = 0;
        $sourceFolders = [];

        // --- SCAN_UPLOAD originals -------------------------------------
        $scannings = $this->scanningsFor($file)->keyBy('id');
        foreach ($preview['scannings'] as $row) {
            $scan = $scannings->get($row['id']);
            if (!$scan) {
                continue;
            }

            if ($row['target_path']) {
                if ($this->moveFile($row['resolved_path'], $row['target_path'], $sourceFolders, $journal)) {
                    $filesMoved++;
                }
                $scan->document_path = $row['target_path'];
            }

            $scan->registry = $toRegistry;
            $scan->edms_file_type = $toFileType;
            $scan->save();

            $this->logMove($scan, $file, $preview, $row, $reason, $actorId);
        }

        // --- PAGETYPING + Doc-WARE archive copies -----------------------
        $pageTypings = $this->pageTypingsFor($file)->keyBy('id');
        foreach ($preview['pagetypings'] as $row) {
            $pt = $pageTypings->get($row['id']);
            if (!$pt) {
                continue;
            }

            if ($row['target_path']) {
                if ($this->moveFile($row['resolved_path'], $row['target_path'], $sourceFolders, $journal)) {
                    $filesMoved++;
                }
                $pt->file_path = $row['target_path'];

                // Keep the Doc-WARE archive in step: move the old archive copy
                // across, or mint one from the typed page if it never had one.
                $fileName = basename(str_replace('\\', '/', $row['target_path']));
                $paperSize = optional($pt->scanning)->paper_size;

                $newArchive = $this->paths->archivePath($toRegistry, $file->file_number, $paperSize, $fileName, $toFileType);
                $oldArchive = $this->paths->archivePath(
                    $preview['from_registry'],
                    $file->file_number,
                    $paperSize,
                    $fileName,
                    $preview['from_file_type']
                );

                if ($oldArchive !== $newArchive && $this->filesystem->exists($this->paths->absolute($oldArchive))) {
                    if ($this->moveFile($oldArchive, $newArchive, $sourceFolders, $journal)) {
                        $filesMoved++;
                    }
                } elseif (!$this->filesystem->exists($this->paths->absolute($newArchive))) {
                    if ($this->paths->copyWithin($row['target_path'], $newArchive)) {
                        $journal['created'][] = $newArchive;
                        $filesMoved++;
                    }
                }
            }

            $pt->registry = $toRegistry;
            $pt->edms_file_type = $toFileType;
            $pt->save();
        }

        // --- The indexing record itself ---------------------------------
        $file->registry = $toRegistry;
        $file->edms_file_type = $toFileType;
        $file->save();

        foreach (array_keys($sourceFolders) as $folder) {
            $this->cleanupIfEmpty($folder);
        }

        // The registry archive pages list the disk through a five-minute cached
        // folder index, so without this the file it just moved is missing from
        // the destination — and still listed in the source — until the cache
        // ages out. Both ends, since a move empties one and fills the other.
        $this->bustArchiveListingCache($preview['from_registry'], $preview['to_registry']);

        Log::channel('daily')->info('EDMS document relocation completed', [
            'file_indexing_id' => $file->id,
            'file_number' => $file->file_number,
            'from' => $preview['from_slug'],
            'to' => $preview['to_slug'],
            'files_moved' => $filesMoved,
            'scannings' => $preview['counts']['scannings'],
            'pagetypings' => $preview['counts']['pagetypings'],
            'reason' => $reason,
            'moved_by' => $actorId,
        ]);

        return $preview + [
            'dry_run' => false,
            'moved' => [
                'files' => $filesMoved,
                'scannings' => $preview['counts']['scannings'],
                'pagetypings' => $preview['counts']['pagetypings'],
            ],
        ];
    }

    /**
     * Put every file back where it was after a failed move, so the disk matches
     * the rolled-back records.
     */
    private function undo(array $journal): void
    {
        $emptied = [];

        foreach (array_reverse($journal['moves']) as [$from, $to]) {
            try {
                $target = $this->paths->absolute($from);
                $source = $this->paths->absolute($to);

                if ($this->filesystem->exists($source)) {
                    $this->filesystem->ensureDirectoryExists(dirname($target), 0775, true);
                    $this->filesystem->move($source, $target);
                    $emptied[dirname($source)] = true;
                }
            } catch (\Throwable $e) {
                Log::error('Could not undo an EDMS relocation move', [
                    'from' => $from,
                    'to' => $to,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($journal['created'] as $created) {
            try {
                $absolute = $this->paths->absolute($created);
                if ($this->filesystem->exists($absolute)) {
                    $this->filesystem->delete($absolute);
                    $emptied[dirname($absolute)] = true;
                }
            } catch (\Throwable $e) {
                Log::error('Could not remove a file created during a failed EDMS relocation', [
                    'path' => $created,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Drop the destination folders the aborted move created.
        foreach (array_keys($emptied) as $folder) {
            $this->cleanupIfEmpty($folder);
        }

        if ($journal['moves'] || $journal['created']) {
            Log::warning('EDMS relocation failed — filesystem changes were rolled back', [
                'moves_undone' => count($journal['moves']),
                'files_removed' => count($journal['created']),
            ]);
        }
    }

    /**
     * Who to record as the actor. Falls back to the file's creator when running
     * outside a request (console), since the audit column is NOT NULL.
     */
    private function resolveActorId(FileIndexing $file, ?int $actorId): int
    {
        $resolved = $actorId ?: Auth::id() ?: $file->created_by;

        if (!$resolved) {
            $resolved = DB::connection('sqlsrv')->table('users')->min('id');
        }

        if (!$resolved) {
            throw new \RuntimeException('Cannot record the move: no user could be resolved as the actor.');
        }

        return (int) $resolved;
    }

    /**
     * Rebuild a path under the destination registry and file type, going through
     * the canonical layout so a legacy path (missing the registry or paper-size
     * segment) comes out standardized rather than half-fixed.
     */
    private function retarget(string $relativePath, string $toRegistry, ?string $toFileType, string $fileNumber, $paperSize): string
    {
        $normalized = str_replace('\\', '/', $relativePath);
        $fileName = basename($normalized);

        if (str_starts_with($normalized, EdmsDocumentPathResolver::PAGETYPING_ROOT . '/')) {
            return $this->paths->pageTypingPath($toRegistry, $fileNumber, $paperSize, $fileName, $toFileType);
        }

        if (str_starts_with($normalized, EdmsDocumentPathResolver::ARCHIVE_ROOT . '/')) {
            return $this->paths->archivePath($toRegistry, $fileNumber, $paperSize, $fileName, $toFileType);
        }

        // SCAN_UPLOAD, BLIND_SCAN and anything legacy land in SCAN_UPLOAD, which
        // is where an original belongs.
        return $this->paths->scanUploadPath($toRegistry, $fileNumber, $paperSize, $fileName, $toFileType);
    }

    /**
     * Is something already sitting at the destination?
     *
     * A path that retargets onto itself is not a conflict — that is a document
     * already standardized, which the move simply leaves alone.
     */
    private function conflicts(?string $resolved, string $target): bool
    {
        if ($resolved === $target) {
            return false;
        }

        return $this->filesystem->exists($this->paths->absolute($target));
    }

    /**
     * Move one file on the public disk, remembering its folder for cleanup.
     */
    /**
     * Drop the cached folder listing for the registries a move touched.
     *
     * FilearchiveController keys its cache by the archive page's module name
     * ("dciv", "kangis", …) while a move deals in registry names ("DCIV
     * Registry"), so the registry is matched back to its module. A registry
     * with no archive page of its own simply matches nothing.
     */
    private function bustArchiveListingCache(?string ...$registries): void
    {
        $modules = ['kangis', 'sltr', 'dciv', 'cadastral'];

        foreach ($registries as $registry) {
            $slug = strtolower((string) $registry);
            if ($slug === '') {
                continue;
            }

            foreach ($modules as $module) {
                if (str_contains($slug, $module)) {
                    FilearchiveController::bustFolderIndexCache($module);
                }
            }
        }
    }

    private function moveFile(string $sourceRelative, string $targetRelative, array &$sourceFolders, array &$journal): bool
    {
        if ($sourceRelative === $targetRelative) {
            return false;
        }

        $source = $this->paths->absolute($sourceRelative);
        $target = $this->paths->absolute($targetRelative);

        // Already moved — several records can share one file (a scan and its
        // typed page often point at the same image on legacy data).
        if (!$this->filesystem->exists($source)) {
            return false;
        }

        $this->filesystem->ensureDirectoryExists(dirname($target), 0775, true);

        if (!$this->filesystem->move($source, $target)) {
            throw new \RuntimeException("Unable to move {$sourceRelative} to {$targetRelative}.");
        }

        $journal['moves'][] = [$sourceRelative, $targetRelative];
        $sourceFolders[dirname($source)] = true;

        return true;
    }

    /**
     * @return \Illuminate\Support\Collection<Scanning>
     */
    private function scanningsFor(FileIndexing $file)
    {
        return Scanning::on('sqlsrv')->where('file_indexing_id', $file->id)->get();
    }

    /**
     * @return \Illuminate\Support\Collection<PageTyping>
     */
    private function pageTypingsFor(FileIndexing $file)
    {
        return PageTyping::on('sqlsrv')->with('scanning')->where('file_indexing_id', $file->id)->get();
    }

    /**
     * Reuse the scan reassignment audit trail: the file number is unchanged, the
     * folder (and therefore the path) is what moved.
     */
    private function logMove(Scanning $scan, FileIndexing $file, array $preview, array $row, ?string $reason, int $actorId): void
    {
        if (!$row['resolved_path'] && !$row['target_path']) {
            return;
        }

        ScanReassignmentLog::on('sqlsrv')->create([
            'scanning_id' => $scan->id,
            'from_file_number' => $file->file_number,
            'to_file_number' => $file->file_number,
            'from_file_indexing_id' => $file->id,
            'to_file_indexing_id' => $file->id,
            'from_path' => (string) ($row['resolved_path'] ?? $row['stored_path']),
            'to_path' => (string) $row['target_path'],
            'reason' => trim(sprintf(
                'EDMS relocation: %s -> %s. %s',
                $preview['from_slug'],
                $preview['to_slug'],
                (string) $reason
            )),
            'reassigned_by' => $actorId,
        ]);
    }

    /**
     * Delete a folder once its last document has left. Never touches a tree root,
     * a registry folder, or one of the master file-type folders — those are the
     * skeleton edms:create-file-type-folders lays down and they are meant to sit
     * there empty.
     */
    private function cleanupIfEmpty(string $folder): void
    {
        if (!$this->filesystem->isDirectory($folder)) {
            return;
        }

        $realFolder = realpath($folder);
        if (!$realFolder) {
            return;
        }

        $roots = [
            EdmsDocumentPathResolver::SCAN_UPLOAD_ROOT,
            EdmsDocumentPathResolver::PAGETYPING_ROOT,
            EdmsDocumentPathResolver::ARCHIVE_ROOT,
            EdmsDocumentPathResolver::BLIND_SCAN_ROOT,
        ];

        foreach ($roots as $root) {
            $realRoot = realpath($this->paths->absolute($root));
            if (!$realRoot) {
                continue;
            }

            // The tree root itself, and the registry folders directly below it.
            if ($realFolder === $realRoot || dirname($realFolder) === $realRoot) {
                return;
            }

            // The master file-type folders under a registry.
            foreach (EdmsFileType::folderSkeleton() as $typeFolder) {
                $suffix = DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $typeFolder);
                if (str_ends_with($realFolder, $suffix) && str_starts_with($realFolder, $realRoot)) {
                    return;
                }
            }
        }

        if (empty($this->filesystem->allFiles($folder))) {
            $this->filesystem->deleteDirectory($folder);
            Log::info('Cleaned up empty folder after an EDMS relocation', ['folder' => $folder]);

            // The folder above may now be empty too.
            $this->cleanupIfEmpty(dirname($folder));
        }
    }
}
