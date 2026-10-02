<?php

namespace App\Services\Edms;

use App\Models\FileIndexing;

/**
 * EdmsRegistryTransferService
 *
 * Moves a file's documents from one registry to another across every EDMS tree:
 *
 *   EDMS/SCAN_UPLOAD/{registry}/{type?}/{file_number}/...      (the original)
 *   EDMS/PAGETYPING/{registry}/{type?}/{file_number}/...       (typed copies)
 *   EDMS/ARCHIVE_Doc_WARE/{registry}/{type?}/{file_number}/... (Doc-WARE archive)
 *
 * A registry lives in the folder path, so changing a file's registry without
 * moving its documents strands every one of them.
 *
 * The move itself lives in EdmsDocumentRelocationService, which handles both
 * folder segments; this class fixes the file type and varies only the registry,
 * so the Page Typing and Doc-WARE interfaces present one decision at a time.
 * EdmsFileTypeTransferService is its counterpart.
 */
class EdmsRegistryTransferService
{
    /** @var EdmsDocumentPathResolver */
    private $paths;

    /** @var EdmsDocumentRelocationService */
    private $relocations;

    public function __construct(EdmsDocumentPathResolver $paths, EdmsDocumentRelocationService $relocations)
    {
        $this->paths = $paths;
        $this->relocations = $relocations;
    }

    /**
     * Registries a file can be moved to, as [display_name => folder_slug].
     */
    public function availableRegistries(): array
    {
        return $this->paths->registryMap();
    }

    /**
     * Work out exactly what a transfer would do, touching nothing.
     */
    public function preview(FileIndexing $file, string $targetRegistry): array
    {
        return $this->relocations->preview($file, $targetRegistry, null, false);
    }

    /**
     * Move a file's documents (and its records) to another registry.
     *
     * @param  bool  $dryRun  when true, nothing is written — the preview is returned as-is
     *
     * @throws \InvalidArgumentException when the transfer is blocked
     */
    public function transfer(FileIndexing $file, string $targetRegistry, ?string $reason = null, bool $dryRun = false, ?int $actorId = null): array
    {
        return $this->relocations->relocate($file, $targetRegistry, null, false, $reason, $dryRun, $actorId);
    }

    /**
     * Move by file number instead of model — the entry point for the console command.
     *
     * @throws \InvalidArgumentException when the file number is unknown
     */
    public function transferByFileNumber(string $fileNumber, string $targetRegistry, ?string $reason = null, bool $dryRun = false, ?int $actorId = null): array
    {
        $file = FileIndexing::on('sqlsrv')->where('file_number', trim($fileNumber))->first();

        if (!$file) {
            throw new \InvalidArgumentException("No indexing record found for file number '{$fileNumber}'.");
        }

        return $this->transfer($file, $targetRegistry, $reason, $dryRun, $actorId);
    }
}
