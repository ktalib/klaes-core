<?php

namespace App\Services\Edms;

use App\Models\FileIndexing;
use App\Models\PageTyping;
use App\Models\Scanning;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * EdmsFileDeletionService
 *
 * Doc-WARE "Delete File" (Change Request 22102026 item 28, Super Admin only).
 *
 * Removes a file's DOCUMENTS from the EDMS: its scans (SCAN_UPLOAD), typed pages
 * (PAGETYPING) and Doc-WARE archive copies, plus — on the folder-listed archives
 * (KANGIS / SLTR / DCIV / Cadastral) — every file in the card's folder. The
 * file_indexings row is left alone: indexing belongs to other modules, and a
 * file with no scans and no typed pages simply drops out of Doc-WARE.
 *
 * Nothing is unlinked. The files on disk have no backup, so every document is
 * MOVED into a trash folder outside the public disk —
 *
 *   {storage}/app/edms-deleted/{Ymd_His}_{id}_{file number}/files/{original relative path}
 *
 * — next to a manifest.json holding the full scannings / pagetypings rows that
 * were deleted, who did it and why. Restoring is a matter of moving the files
 * back and re-inserting the rows from the manifest.
 */
class EdmsFileDeletionService
{
    public const TRASH_ROOT = 'app/edms-deleted';

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
     * Work out exactly what a delete would remove, touching nothing.
     *
     * @param  string[]  $extraFolders  public-disk folders whose every file goes too
     *                                  (the folder a folder-listed archive card is)
     */
    public function preview(?FileIndexing $file, array $extraFolders = [], ?string $fallbackFileNumber = null): array
    {
        $scannings = $file ? $this->scanningsFor($file) : collect();
        $pageTypings = $file ? $this->pageTypingsFor($file) : collect();

        $paths = [];
        $missing = 0;

        foreach ($scannings as $scan) {
            $resolved = $this->paths->resolveRelative($scan->document_path, $this->paths->contextFromScanning($scan, $file));
            $resolved ? $paths[$resolved] = true : $missing++;
        }

        foreach ($pageTypings as $pt) {
            $paperSize = optional($pt->scanning)->paper_size;
            $registry = $pt->registry ?? $file->registry;
            $fileType = $pt->edms_file_type ?? $file->edms_file_type;

            $resolved = $this->paths->resolveRelative($pt->file_path, [
                'file_number' => $file->file_number,
                'registry' => $registry,
                'paper_size' => $paperSize,
                'file_type' => $fileType,
            ]);

            if (!$resolved) {
                $missing++;
                continue;
            }

            $paths[$resolved] = true;

            // The Doc-WARE archive copy sits beside the typed page under ARCHIVE_Doc_WARE.
            $archive = $this->paths->archivePath(
                $this->paths->registryName($registry),
                $file->file_number,
                $paperSize,
                basename(str_replace('\\', '/', $resolved)),
                EdmsFileType::normalize($fileType)
            );
            if ($this->filesystem->exists($this->paths->absolute($archive))) {
                $paths[$archive] = true;
            }
        }

        $disk = Storage::disk('public');
        foreach ($extraFolders as $folder) {
            $folder = trim(str_replace('\\', '/', (string) $folder), '/');
            if ($folder === '' || !$this->isInsideEdms($folder) || !$disk->exists($folder)) {
                continue;
            }
            foreach ($disk->allFiles($folder) as $path) {
                $paths[str_replace('\\', '/', $path)] = true;
            }
        }

        $blockers = [];

        // A Cadastral document still in force points at one of these scans; deleting
        // it would leave the Cadastral file with a document that opens nothing.
        if ($scannings->isNotEmpty() && Schema::connection('sqlsrv')->hasTable('cadastral_documents')) {
            $linked = DB::connection('sqlsrv')->table('cadastral_documents')
                ->whereIn('scanning_id', $scannings->pluck('id'))
                ->where(function ($q) {
                    $q->whereNull('is_superseded')->orWhere('is_superseded', 0);
                })
                ->count();
            if ($linked > 0) {
                $blockers[] = "{$linked} Cadastral document(s) are filed against these scans. Supersede them in the Cadastral module first.";
            }
        }

        if ($scannings->isEmpty() && $pageTypings->isEmpty() && empty($paths)) {
            $blockers[] = 'There are no documents to delete for this file.';
        }

        return [
            'file_indexing_id' => $file ? $file->id : null,
            'file_number' => $file ? $file->file_number : $fallbackFileNumber,
            'file_title' => $file ? $file->file_title : null,
            'scanning_ids' => $scannings->pluck('id')->all(),
            'pagetyping_ids' => $pageTypings->pluck('id')->all(),
            'paths' => array_keys($paths),
            'counts' => [
                'scannings' => $scannings->count(),
                'pagetypings' => $pageTypings->count(),
                'files_on_disk' => count($paths),
                'missing_on_disk' => $missing,
            ],
            'blockers' => $blockers,
        ];
    }

    /**
     * Delete the documents. Files go to the trash folder first; if the DB delete
     * then fails, they are moved back so disk and records stay in step.
     *
     * @throws \InvalidArgumentException when the delete is blocked
     */
    public function delete(?FileIndexing $file, string $reason, int $actorId, array $extraFolders = [], ?string $fallbackFileNumber = null): array
    {
        $preview = $this->preview($file, $extraFolders, $fallbackFileNumber);
        if (!empty($preview['blockers'])) {
            throw new \InvalidArgumentException(implode(' ', $preview['blockers']));
        }

        $label = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($preview['file_number'] ?: 'unknown'));
        $trashDir = file_storage_path(self::TRASH_ROOT . '/' . now()->format('Ymd_His') . '_' . ($file ? $file->id : 'folder') . '_' . $label);
        $this->filesystem->ensureDirectoryExists($trashDir, 0775, true);

        $db = DB::connection('sqlsrv');

        // The manifest is written before anything moves, so even a crash part-way
        // leaves a record of what was meant to go and where it went.
        $manifest = [
            'deleted_at' => now()->toDateTimeString(),
            'deleted_by' => $actorId,
            'reason' => $reason,
            'file_indexing' => $file ? $file->getAttributes() : null,
            'scannings' => $preview['scanning_ids']
                ? $db->table('scannings')->whereIn('id', $preview['scanning_ids'])->get()->all() : [],
            'pagetypings' => $preview['pagetyping_ids']
                ? $db->table('pagetypings')->whereIn('id', $preview['pagetyping_ids'])->get()->all() : [],
            'scan_reassignment_logs' => $preview['scanning_ids']
                ? $db->table('scan_reassignment_logs')->whereIn('scanning_id', $preview['scanning_ids'])->get()->all() : [],
            'files' => $preview['paths'],
        ];
        $this->filesystem->put($trashDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $moved = [];
        $sourceFolders = [];

        try {
            foreach ($preview['paths'] as $relative) {
                $source = $this->paths->absolute($relative);
                if (!$this->filesystem->exists($source)) {
                    continue; // a scan and its typed page can share one image
                }
                $target = $trashDir . '/files/' . $relative;
                $this->filesystem->ensureDirectoryExists(dirname($target), 0775, true);
                if (!$this->filesystem->move($source, $target)) {
                    throw new \RuntimeException("Unable to move {$relative} to the trash folder.");
                }
                $moved[] = [$source, $target];
                $sourceFolders[dirname($source)] = true;
            }

            $db->transaction(function () use ($db, $preview) {
                $scanIds = $preview['scanning_ids'];

                if ($preview['pagetyping_ids']) {
                    foreach (array_chunk($preview['pagetyping_ids'], 1000) as $chunk) {
                        $db->table('pagetypings')->whereIn('id', $chunk)->delete();
                    }
                }

                if ($scanIds) {
                    foreach (array_chunk($scanIds, 1000) as $chunk) {
                        // parent_scan_id is a NO ACTION self-reference: detach children
                        // (ours or another file's) before their parents go.
                        $db->table('scannings')->whereIn('parent_scan_id', $chunk)->update(['parent_scan_id' => null]);
                    }
                    foreach (array_chunk($scanIds, 1000) as $chunk) {
                        $db->table('scannings')->whereIn('id', $chunk)->delete();
                    }
                }
            });
        } catch (\Throwable $e) {
            foreach (array_reverse($moved) as [$source, $target]) {
                try {
                    $this->filesystem->ensureDirectoryExists(dirname($source), 0775, true);
                    $this->filesystem->move($target, $source);
                } catch (\Throwable $undo) {
                    Log::error('Could not restore a file after a failed Doc-WARE delete', [
                        'from' => $target, 'to' => $source, 'error' => $undo->getMessage(),
                    ]);
                }
            }
            $this->filesystem->put($trashDir . '/FAILED.txt', 'Delete failed and was rolled back: ' . $e->getMessage());

            throw $e;
        }

        foreach (array_keys($sourceFolders) as $folder) {
            $this->cleanupIfEmpty($folder);
        }

        Log::channel('daily')->warning('Doc-WARE file documents deleted', [
            'file_indexing_id' => $preview['file_indexing_id'],
            'file_number' => $preview['file_number'],
            'scannings' => $preview['counts']['scannings'],
            'pagetypings' => $preview['counts']['pagetypings'],
            'files_moved' => count($moved),
            'trash' => $trashDir,
            'reason' => $reason,
            'deleted_by' => $actorId,
        ]);

        return $preview + ['files_moved' => count($moved), 'trash' => $trashDir];
    }

    private function scanningsFor(FileIndexing $file)
    {
        return Scanning::on('sqlsrv')->where('file_indexing_id', $file->id)->get();
    }

    private function pageTypingsFor(FileIndexing $file)
    {
        return PageTyping::on('sqlsrv')->with('scanning')->where('file_indexing_id', $file->id)->get();
    }

    /** Only paths under EDMS/ may be swept by folder — never a disk root or a sibling tree. */
    private function isInsideEdms(string $folder): bool
    {
        if (str_contains($folder, '..')) {
            return false;
        }

        $segments = explode('/', $folder);

        // EDMS/{tree}/{registry}/{file...}: anything shallower is a tree or registry folder.
        return count($segments) >= 4 && strcasecmp($segments[0], 'EDMS') === 0;
    }

    /** Remove the folders a delete emptied, stopping at the EDMS tree / registry level. */
    private function cleanupIfEmpty(string $folder): void
    {
        $edmsRoot = realpath($this->paths->absolute('EDMS'));
        $real = realpath($folder);

        if (!$edmsRoot || !$real || !$this->filesystem->isDirectory($real) || !str_starts_with($real, $edmsRoot)) {
            return;
        }

        $relative = trim(str_replace('\\', '/', substr($real, strlen($edmsRoot))), '/');
        // {tree}/{registry} and above stay, as do the master file-type folders.
        if (substr_count($relative, '/') < 2) {
            return;
        }
        foreach (EdmsFileType::folderSkeleton() as $typeFolder) {
            if (str_ends_with($relative, '/' . trim($typeFolder, '/'))) {
                return;
            }
        }

        if (empty($this->filesystem->allFiles($real)) && empty($this->filesystem->directories($real))) {
            $this->filesystem->deleteDirectory($real);
            $this->cleanupIfEmpty(dirname($real));
        }
    }
}
