<?php

namespace App\Services\Edms;

use App\Models\FileIndexing;

/**
 * EdmsFileTypeTransferService
 *
 * Files a document set into one of the EDMS master folders — Regular,
 * Merger/Children, Subdivision/Mother, Extension/Old, Temporary_File,
 * Change_of_Purpose/New and the rest — without changing its registry:
 *
 *   EDMS/SCAN_UPLOAD/Lands_Registry/COM-2026-191/...
 *   -> EDMS/SCAN_UPLOAD/Lands_Registry/Subdivision/Mother/COM-2026-191/...
 *
 * New scans pick their type up front, at upload. This is the counterpart for
 * everything already in the system: files uploaded, page-typed or sitting in
 * Doc-WARE before the master folders existed, which have to be moved into them.
 *
 * The move itself lives in EdmsDocumentRelocationService — the same one the
 * registry transfer uses. This class fixes the registry and varies only the
 * type.
 */
class EdmsFileTypeTransferService
{
    /** @var EdmsDocumentRelocationService */
    private $relocations;

    public function __construct(EdmsDocumentRelocationService $relocations)
    {
        $this->relocations = $relocations;
    }

    /**
     * The master folders a file can be filed into.
     *
     * @return array<int, array{key:string, label:string, folder:string, group:?string}>
     */
    public function availableFileTypes(): array
    {
        return EdmsFileType::options();
    }

    /**
     * Work out exactly what filing would do, touching nothing.
     *
     * @param  string|null  $targetFileType  null moves the file back out to the
     *                                       registry root (unclassified)
     */
    public function preview(FileIndexing $file, ?string $targetFileType): array
    {
        return $this->relocations->preview($file, null, $targetFileType, true);
    }

    /**
     * File a document set into a master folder.
     *
     * @param  bool  $dryRun  when true, nothing is written — the preview is returned as-is
     *
     * @throws \InvalidArgumentException when the move is blocked
     */
    public function transfer(FileIndexing $file, ?string $targetFileType, ?string $reason = null, bool $dryRun = false, ?int $actorId = null): array
    {
        return $this->relocations->relocate($file, null, $targetFileType, true, $reason, $dryRun, $actorId);
    }

    /**
     * Move by file number instead of model — the entry point for the console command.
     *
     * @throws \InvalidArgumentException when the file number is unknown
     */
    public function transferByFileNumber(string $fileNumber, ?string $targetFileType, ?string $reason = null, bool $dryRun = false, ?int $actorId = null): array
    {
        $file = FileIndexing::on('sqlsrv')->where('file_number', trim($fileNumber))->first();

        if (!$file) {
            throw new \InvalidArgumentException("No indexing record found for file number '{$fileNumber}'.");
        }

        return $this->transfer($file, $targetFileType, $reason, $dryRun, $actorId);
    }
}
