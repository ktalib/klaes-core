<?php

namespace App\Services\Edms;

use App\Models\PageTyping;
use App\Models\Scanning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PassportPageTypingService
{
    public function __construct(private EdmsDocumentPathResolver $paths)
    {
    }

    /** Register a new passport scan and its Image / Passport classification together. */
    public function register(array $attributes): Scanning
    {
        if (($attributes['document_type'] ?? null) !== 'Passport Photograph') {
            throw new RuntimeException('Automatic passport typing requires a passport photograph.');
        }

        $createdCopies = [];

        try {
            return DB::connection('sqlsrv')->transaction(function () use ($attributes, &$createdCopies) {
                $db = DB::connection('sqlsrv');
                // Serialize passport uploads for this file before allocating the next page.
                $indexing = $db->table('file_indexings')
                    ->where('id', $attributes['file_indexing_id'])->lockForUpdate()->first();

                if (!$indexing || trim((string) $indexing->file_number) === '') {
                    throw new RuntimeException('The passport has no indexed file number.');
                }

                $type = $db->table('PageType')->where('PageType', 'Image')->value('id');
                $subtype = $db->table('PageSubType')->where('PageTypeId', $type)
                    ->where('PageSubType', 'Passport')->value('id');
                $cover = $db->table('CoverType')->where('Name', 'Front Cover')->value('Id');

                if (!$type || !$subtype || !$cover) {
                    throw new RuntimeException('Image / Passport or Front Cover classification is missing.');
                }

                $lastOrder = $db->table('scannings')->where('file_indexing_id', $indexing->id)->max('display_order');
                $position = max(
                    1,
                    (int) $db->table('pagetypings')->where('file_indexing_id', $indexing->id)->max('page_number') + 1,
                    $lastOrder === null ? 1 : (int) $lastOrder + 2
                );
                $registry = $this->paths->registryName($attributes['registry'] ?? $indexing->registry ?? null);
                $fileType = EdmsFileType::normalize($attributes['edms_file_type'] ?? $indexing->edms_file_type ?? null);
                $paper = $attributes['paper_size'] ?? 'A4';
                $source = $attributes['document_path'];
                $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg');
                $pageCode = 'FC-I-P-0a';

                // Do not reuse a filename left by an earlier failed database write.
                do {
                    $definitionCode = $position . '-' . $pageCode;
                    $filename = $definitionCode . '.' . $extension;
                    $typedPath = $this->paths->pageTypingPath($registry, $indexing->file_number, $paper, $filename, $fileType);
                    $archivePath = $this->paths->archivePath($registry, $indexing->file_number, $paper, $filename, $fileType);
                    $occupied = Storage::disk('public')->exists($typedPath) || Storage::disk('public')->exists($archivePath);
                    if ($occupied) {
                        $position++;
                    }
                } while ($occupied);

                foreach ([$typedPath, $archivePath] as $destination) {
                    $createdCopies[] = $destination;
                    if (!$this->paths->copyWithin($source, $destination)) {
                        throw new RuntimeException('Could not create the passport page-typing and archive copies.');
                    }
                }

                $scan = Scanning::on('sqlsrv')->create(array_merge($attributes, [
                    'status' => 'completed',
                    'display_order' => $position - 1,
                    'definition' => $position,
                    'definition_code' => $definitionCode,
                    'edms_file_type' => $fileType,
                ]));

                $page = PageTyping::on('sqlsrv')->create([
                    'file_indexing_id' => $indexing->id,
                    'scanning_id' => $scan->id,
                    'cover_type_id' => $cover,
                    'page_type' => (string) $type,
                    'page_subtype' => (string) $subtype,
                    'serial_number' => '0',
                    'serial_suffix' => 'a',
                    'page_code' => $pageCode,
                    'definition' => $position,
                    'definition_code' => $definitionCode,
                    'page_number' => $position,
                    'file_path' => $typedPath,
                    'typed_by' => $attributes['uploaded_by'] ?? 0,
                    'source' => PageTyping::SOURCE_IMAGE_COPY,
                    'registry' => $registry,
                    'edms_file_type' => $fileType,
                    'qc_status' => PageTyping::QC_STATUS_PENDING,
                    'qc_overridden' => false,
                    'has_qc_issues' => false,
                    'is_booklet_page' => false,
                    'is_bcfc_page' => false,
                ]);

                $scan->setRelation('pagetypings', collect([$page]));

                return $scan;
            });
        } catch (Throwable $e) {
            // Only these newly allocated copies are removed; retain the scan original.
            foreach ($createdCopies as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }
    }
}
