<?php

namespace App\Services\Edms;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Puts a parcel-update upload where the rest of KLAES can see it.
 *
 * Change of Purpose, Subdivision, Merger, Separation and Duplex Parcel Update all
 * accepted documents and wrote them to private little folders — site_plans/,
 * parcel_documents/merger/ and so on — with no scannings or pagetypings row. The
 * document existed, but the EDMS workflow, the archive and the Virtual Folder
 * System all reported the file as having zero pages. 28 uploads were stranded that
 * way before this service existed.
 *
 * Every parcel module now ingests through here, so there is ONE definition of
 * where a parcel document lives and how it is classified. MigrateParcelUploadsToEdms
 * uses the same methods for the historical backfill, which is what stops the
 * migrated pages and the newly-uploaded ones drifting into two conventions.
 *
 * FALLBACK, AND WHY IT IS NOT AN ERROR
 * The EDMS tree is keyed by registry, and registry comes from the indexing record.
 * A file number nobody has indexed therefore has nowhere correct to go. Rather than
 * refuse the upload — which would block a clerk mid-application over a data gap
 * they cannot fix — the document is stored in the legacy folder and NO EDMS rows
 * are written. It is recoverable later: MigrateParcelUploadsToEdms picks up exactly
 * these rows on a re-run once the file is indexed.
 */
class ParcelDocumentIngestService
{
    private const CONNECTION = 'sqlsrv';

    /** Uploads arrive as phone photos and desk scans; nothing records a real sheet size. */
    public const PAPER_SIZE = 'A4';

    /**
     * Upload field => [PageType.id, PageSubType.id|null, label for scannings.document_type].
     *
     * A site plan is filed under Survey / Survey Plan: the drawing is a survey
     * product, and PageSubType 25 is the only "Survey Plan" under a type that means
     * the drawing itself rather than a covering letter about one. PageSubType has no
     * "Site Plan" entry at all — worth adding, at which point only this line changes.
     *
     * Where no subtype fits, null is stored rather than a near-miss. A wrong subtype
     * is harder to notice, and harder to undo, than a missing one.
     */
    public const DOCUMENT_MAP = [
        'site_plan' => [9, 25, 'Site Plan'],
        'rec_page_site_plan' => [9, 25, 'Site Plan (Recommendation)'],
        'application_letter' => [2, null, 'Application Letter'],
        'rec_page_application' => [2, null, 'Application (Recommendation)'],
        'rec_page_planning' => [12, null, 'Town Planning (Recommendation)'],
        'ownership_document' => [5, null, 'Ownership Document'],
        'means_of_id' => [10, null, 'Means of Identification'],
        'tax_clearance' => [7, 21, 'Tax Clearance'],
    ];

    /** Used when a field is not in the map — Miscellaneous, and visible as such. */
    private const FALLBACK_DOCUMENT = [10, null, 'Parcel Update Document'];

    /** Page positions handed out in this process, keyed by file_indexings.id. */
    private array $positions = [];

    public function __construct(private EdmsDocumentPathResolver $paths)
    {
    }

    /**
     * Store an uploaded parcel document and register it in EDMS.
     *
     * @param  string  $legacyFolder  where this module used to write; used only when
     *                                the file number is not indexed
     * @return string the path to persist in the parcel table's own column
     */
    public function ingest(
        UploadedFile $file,
        ?string $fileNumber,
        string $field,
        string $legacyFolder,
        string $filename,
        ?int $userId = null
    ): string {
        $indexing = $this->indexingFor($fileNumber);

        // No indexing record means no registry, and no registry means no correct
        // folder. Keep the old behaviour so the upload still succeeds.
        if (!$indexing) {
            return $file->storeAs($legacyFolder, $filename, 'public');
        }

        $scanPath = $this->paths->scanUploadPath(
            $indexing->registry,
            $indexing->file_number,
            self::PAPER_SIZE,
            $filename
        );

        $file->storeAs(dirname($scanPath), $filename, 'public');

        $this->register($indexing, $scanPath, $field, $filename, $file->getSize(), $legacyFolder, $userId);

        return $scanPath;
    }

    /**
     * Write the scannings + pagetypings pair for a document already on the disk.
     *
     * Shared with the historical migration, which copies the bytes itself and then
     * calls this. Returns the new scannings id, or null if the typed copy could not
     * be made — a pagetypings row pointing at a file that is not there renders as a
     * broken page, so it is better to have written nothing.
     */
    public function register(
        $indexing,
        string $scanPath,
        string $field,
        string $originalName,
        ?int $size,
        string $sourceLabel,
        ?int $userId = null
    ): ?int {
        [$pageType, $pageSubType, $docLabel] = self::DOCUMENT_MAP[$field] ?? self::FALLBACK_DOCUMENT;

        $conn = DB::connection(self::CONNECTION);
        $position = $this->nextPosition($indexing->id);
        $pageCode = $this->pageCode($pageType, $pageSubType, $position);
        $definitionCode = $position . '-' . $pageCode;
        $extension = pathinfo($scanPath, PATHINFO_EXTENSION) ?: 'jpg';

        $typedPath = $this->paths->pageTypingPath(
            $indexing->registry,
            $indexing->file_number,
            self::PAPER_SIZE,
            $definitionCode . '.' . $extension
        );

        // Page typing reads its own copy; the SCAN_UPLOAD original must stay put.
        if (!$this->paths->copyWithin($scanPath, $typedPath)) {
            return null;
        }

        $registryName = $this->paths->registryName($indexing->registry);
        $userId ??= 0;

        return $conn->transaction(function () use (
            $conn, $indexing, $scanPath, $typedPath, $originalName, $size, $registryName,
            $pageType, $pageSubType, $docLabel, $pageCode, $definitionCode, $position,
            $sourceLabel, $userId
        ) {
            $scanningId = $conn->table('scannings')->insertGetId([
                'file_indexing_id' => $indexing->id,
                'document_path' => $scanPath,
                'uploaded_by' => $userId,
                'status' => 'completed',
                'original_filename' => $originalName,
                'paper_size' => self::PAPER_SIZE,
                'document_type' => $docLabel,
                'notes' => 'Parcel update upload (' . $sourceLabel . ').',
                'display_order' => $position,
                'file_size' => $size,
                'is_pdf_converted' => 0,
                'registry' => $registryName,
                'definition' => $position,
                'definition_code' => mb_substr($position . '-' . $indexing->file_number, 0, 100),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $conn->table('pagetypings')->insert([
                'file_indexing_id' => $indexing->id,
                'page_type' => (string) $pageType,
                'page_subtype' => $pageSubType === null ? null : (string) $pageSubType,
                'serial_number' => '0',
                'page_code' => $pageCode,
                'file_path' => $typedPath,
                'typed_by' => $userId,
                'page_number' => $position,
                'scanning_id' => $scanningId,
                'qc_status' => 'pending',
                'qc_overridden' => 0,
                'has_qc_issues' => 0,
                'is_bcfc_page' => 0,
                'is_booklet_page' => 0,
                // These are loose documents, not pages lifted off a physical file's
                // back cover, so the front-cover position is the only sane default.
                'cover_type_id' => '1',
                'registry' => $registryName,
                'definition' => $position,
                'definition_code' => $definitionCode,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $scanningId;
        });
    }

    /** The indexing record a file number belongs to, or null if it has none. */
    public function indexingFor(?string $fileNumber)
    {
        $fileNumber = trim((string) $fileNumber);

        if ($fileNumber === '') {
            return null;
        }

        return DB::connection(self::CONNECTION)
            ->table('file_indexings')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper($fileNumber)])
            ->first(['id', 'file_number', 'registry']);
    }

    /**
     * Next free page position for a file.
     *
     * Cached per process so several uploads in one request append rather than all
     * claiming the same page number — the database has not been written yet when
     * the second one asks.
     */
    public function nextPosition(int $fileIndexingId): int
    {
        if (!isset($this->positions[$fileIndexingId])) {
            $this->positions[$fileIndexingId] = (int) DB::connection(self::CONNECTION)
                ->table('pagetypings')
                ->where('file_indexing_id', $fileIndexingId)
                ->max('page_number');
        }

        return ++$this->positions[$fileIndexingId];
    }

    /**
     * Page code in the register's own shape: {cover}-{type}-{subtype}-{position}.
     *
     * The type and subtype segments are the initials of each word capped at four
     * characters — the rule PageType::getCodeAttribute() and
     * PageSubType::getCodeAttribute() already implement. Reproduced here so an
     * ingested page is indistinguishable from a hand-typed one in the viewer.
     */
    public function pageCode(int $pageType, ?int $pageSubType, int $position): string
    {
        $conn = DB::connection(self::CONNECTION);

        $initials = static function (?string $name): string {
            if (!$name) {
                return '';
            }

            $code = '';

            foreach (explode(' ', $name) as $word) {
                $code .= strtoupper(substr($word, 0, 1));
            }

            return substr($code, 0, 4);
        };

        $typeName = $conn->table('PageType')->where('id', $pageType)->value('PageType');
        $subName = $pageSubType
            ? $conn->table('PageSubType')->where('id', $pageSubType)->value('PageSubType')
            : null;

        $segments = array_filter(['FC', $initials($typeName), $initials($subName)]);

        return implode('-', $segments) . '-' . $position;
    }
}
