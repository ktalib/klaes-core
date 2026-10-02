<?php

namespace App\Services\Cadastral;

use App\Services\Edms\EdmsDocumentPathResolver;
use App\Services\Edms\EdmsFileType;
use App\Services\EdmsScanUploadFolderService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The Cadastral module's one document upload: files a document into the FILE'S
 * EDMS folder as a scan, and remembers which cadastral record it was filed for.
 *
 * WHERE THE BYTES GO. docs/EDMS_WORKFLOW_AND_FILE_MOVEMENT.md, Stage 1(a): the
 * same place POST /scan-uploads/upload writes,
 *
 *   EDMS/SCAN_UPLOAD/{Registry}/{Type?}/{FILE NUMBER}/{PAPER}/{file}
 *
 * with a `scannings` row whose document_path names that original. The row is
 * left `pending` and untyped, so the page turns up in Scan Upload and in the
 * Page Typing queue like any other scanned page, and Page Typing later COPIES
 * it into PAGETYPING. Nothing here writes PAGETYPING, ARCHIVE_Doc_WARE or the
 * separate EDMS/UPLOAD tree.
 *
 * Registry is always Cadastral Registry (Cadastral_Registry on disk), not the
 * file's home registry — the convention the existing cadastral scans follow.
 * The other segments come from the file_indexings row: the file's own edms_file_type when
 * it has one (NULL keeps the legacy layout and is never coerced), the file
 * number with Windows-illegal characters folded to "-", and paper A4 — nothing
 * an officer uploads records a real sheet size.
 *
 * NO INDEXING RECORD, NO UPLOAD. A file nobody has indexed has no registry, so
 * no correct folder; inventing one would strand the scan. The upload is refused
 * with a message instead (ParcelDocumentIngestService falls back to a private
 * folder — this module has no legacy folder to fall back to, and should not
 * start one).
 *
 * WHAT IS STORED HERE. cadastral_documents is a pointer — owner, kind,
 * scanning_id — never a second copy and never a path. Rows are never deleted:
 * a replaced manual chart is flagged is_superseded and both scans stay in EDMS.
 *
 * VIEWING goes through the existing scan-uploads.download route, which streams
 * scannings.document_path. That route is not mapped in module_permissions, so
 * it is open to any signed-in user — the same as every other EDMS page in KLAES.
 *
 * WORKS BEFORE ITS MIGRATION: installed() is false until cadastral_documents
 * exists, the forms say so, and nothing is uploaded.
 *
 * DISK WRITES DO NOT ROLL BACK. Every file written in this request is kept in
 * $written; a caller whose transaction fails calls discardWritten() so a
 * rolled-back event does not leave an orphan scan on disk.
 */
class CadastralDocuments
{
    private const CONN  = 'sqlsrv';
    private const TABLE = 'cadastral_documents';

    /** The EDMS registry every cadastral document is filed under. */
    private const REGISTRY = 'Cadastral Registry';

    public const PAPER_SIZE = 'A4';

    /** Upload cap, in kilobytes (Laravel's max: unit). */
    public const MAX_KB = 10240;

    /** Sniffed MIME type => stored extension. The client's own extension is ignored. */
    public const ALLOWED = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
    ];

    public const KIND_SUPPORTING   = 'supporting';
    public const KIND_MANUAL_CHART = 'manual_chart';

    public const OWNER_STATUS_EVENT = 'cadastral_file_status_event';
    public const OWNER_REPORT       = 'cadastral_report';

    /** scannings.document_type, so Scan Upload and Page Typing say where a page came from. */
    private const DOCUMENT_TYPES = [
        self::KIND_SUPPORTING   => 'Cadastral – File Status Document',
        self::KIND_MANUAL_CHART => 'Cadastral – Manual Chart',
    ];

    /** hasTable costs a round trip; one answer per request (per process). */
    private static ?bool $installed = null;

    /** Public-disk paths written in this request, for discardWritten(). */
    private array $written = [];

    public function __construct(
        private EdmsDocumentPathResolver $paths,
        private EdmsScanUploadFolderService $folders,
    ) {}

    public static function installed(): bool
    {
        return self::$installed ??= Schema::connection(self::CONN)->hasTable(self::TABLE);
    }

    /** Forget the cached answer — for a script that creates the table mid-run. */
    public static function refresh(): void
    {
        self::$installed = null;
    }

    /**
     * Validation rules for one upload field.
     *
     * `mimes` and `mimetypes` both read the file's CONTENT (finfo), not its
     * name, so a renamed executable fails either way. The cap is enforced here,
     * on the server, whatever the form says.
     */
    public static function fileRule(): array
    {
        return [
            'file',
            'mimes:pdf,jpg,jpeg,png',
            'mimetypes:' . implode(',', array_keys(self::ALLOWED)),
            'max:' . self::MAX_KB,
        ];
    }

    public static function messages(string $field): array
    {
        return [
            $field . '.mimes'     => 'Only PDF, JPG and PNG documents can be uploaded.',
            $field . '.mimetypes' => 'Only PDF, JPG and PNG documents can be uploaded — the file content did not match.',
            $field . '.max'       => 'A document can be at most ' . (self::MAX_KB / 1024) . ' MB.',
        ];
    }

    /* ----------------------------- the file ----------------------------- */

    /**
     * The file_indexings row a cadastral file's documents belong under.
     *
     * By id when the record already carries one (a receipt picked it from the
     * source index), provided that row still holds this number; otherwise by
     * the number string across every number column — a file can carry its
     * number in any of them, so never one column positionally. file_number_id
     * is not used: it is set on a few hundred rows of 170k.
     *
     * Several rows sharing the number are resolved to the one whose
     * file_number IS the number; if that still leaves more than one, null —
     * guessing would file a document into someone else's folder.
     */
    public function indexingFor(?int $fileIndexingId, ?string $fileNumber): ?object
    {
        $columns = ['file_number', 'st_fillno', 'kangis_file_no', 'new_kangis_file_no', 'mls_file_no'];
        $select  = array_merge(['id', 'registry', 'edms_file_type'], $columns);

        $spellings = array_values(array_unique(array_filter([
            trim((string) $fileNumber), FileNumberFormat::normalise($fileNumber),
        ])));

        $live = fn ($q) => $q->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0));

        if ($fileIndexingId) {
            $row = DB::connection(self::CONN)->table('file_indexings')
                ->select($select)->where('id', $fileIndexingId)->tap($live)->first();

            if ($row && ($spellings === [] || $this->holdsNumber($row, $columns, $spellings))) {
                return $row;
            }
        }

        if ($spellings === []) {
            return null;
        }

        $rows = collect(DB::connection(self::CONN)->table('file_indexings')
            ->select($select)
            ->tap($live)
            ->where(function ($w) use ($columns, $spellings) {
                foreach ($columns as $col) {
                    $w->orWhereIn($col, $spellings);
                }
            })
            ->limit(10)
            ->get());

        if ($rows->count() > 1) {
            $rows = $rows->filter(fn ($r) => in_array(trim((string) $r->file_number), $spellings, true))->values();
        }

        return $rows->count() === 1 ? $rows->first() : null;
    }

    private function holdsNumber(object $row, array $columns, array $spellings): bool
    {
        foreach ($columns as $col) {
            $value = trim((string) ($row->{$col} ?? ''));
            if ($value !== '' && (in_array($value, $spellings, true)
                || in_array(FileNumberFormat::normalise($value), $spellings, true))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The indexing row, or a validation error naming the field.
     *
     * Called BEFORE the caller's transaction, so a file that cannot take a
     * document is refused before anything else about the request is written.
     */
    public function requireIndexing(?int $fileIndexingId, ?string $fileNumber, string $field): object
    {
        $row = $this->indexingFor($fileIndexingId, $fileNumber);

        if (! $row) {
            throw ValidationException::withMessages([
                $field => "{$fileNumber} has no single file-index record, so it has no EDMS folder to file a document into. "
                    . 'Index the file first (or resolve the duplicate index entries); nothing was saved.',
            ]);
        }

        return $row;
    }

    /**
     * File one upload into EDMS and point a cadastral record at it.
     *
     * Disk first, then the scannings row and the pointer. If either insert
     * fails the file just written is removed before the exception goes on. Run
     * it inside the caller's transaction so the event and its documents land
     * together, and call discardWritten() if that transaction fails.
     *
     * @return array{document_id: int, scanning_id: int, path: string}
     */
    public function file(
        UploadedFile $upload,
        object $indexing,
        string $ownerType,
        int $ownerId,
        string $kind,
        ?string $note = null,
    ): array {
        if (! self::installed()) {
            throw new \RuntimeException('cadastral_documents is not installed yet.');
        }

        $mime      = (string) $upload->getMimeType();   // sniffed, not the client's claim
        $extension = self::ALLOWED[$mime] ?? null;

        if ($extension === null) {
            throw ValidationException::withMessages(['document' => 'Only PDF, JPG and PNG documents can be uploaded.']);
        }

        $conn         = DB::connection(self::CONN);
        // Always the Cadastral Registry tree, whatever the file's home registry:
        // these are cadastral documents, and Scan Upload already files them this
        // way — a scannings row on the file's own indexing record with
        // registry 'Cadastral Registry', under SCAN_UPLOAD/Cadastral_Registry/.
        $registryName = $this->paths->registryName(self::REGISTRY);
        $fileType     = EdmsFileType::normalize($indexing->edms_file_type);
        $folderNumber = $this->folders->folderName((string) $indexing->file_number);

        // Appended after the file's existing pages, in Scan Upload's own terms:
        // display_order is 0-based, definition is display_order + 1.
        $displayOrder   = ((int) $conn->table('scannings')->where('file_indexing_id', $indexing->id)->max('display_order')) + 1;
        $definition     = $displayOrder + 1;
        $definitionCode = mb_substr($definition . '-' . $indexing->file_number, 0, 100);

        $directory = $this->paths->scanUploadFolder($registryName, $folderNumber, self::PAPER_SIZE, $fileType);
        // ScanUploadsController::generateFilename's shape, with a cad- marker.
        $filename  = 'cad-' . Str::slug($definitionCode, '-') . '_' . now()->timestamp . '_' . Str::random(6) . '.' . $extension;

        // Folders this write is about to create, deepest first. Cleanup removes
        // only these — a file-number folder EdmsScanUploadFolderService made at
        // indexing is meant to sit empty and is never touched.
        $disk    = Storage::disk('public');
        $created = array_values(array_filter([$directory, dirname($directory)], fn ($d) => ! $disk->exists($d)));

        $path = $upload->storeAs($directory, $filename, 'public');

        if (! $path) {
            throw new \RuntimeException('The document could not be written to the EDMS folder.');
        }

        $this->written[] = ['path' => $path, 'created' => $created];

        try {
            $userId       = Auth::id();
            $originalName = mb_substr((string) $upload->getClientOriginalName(), 0, 255) ?: $filename;

            $scanningId = $conn->table('scannings')->insertGetId([
                'file_indexing_id'  => $indexing->id,
                'document_path'     => $path,
                'uploaded_by'       => $userId,
                'status'            => 'pending',
                'original_filename' => $originalName,
                'paper_size'        => self::PAPER_SIZE,
                'document_type'     => self::DOCUMENT_TYPES[$kind] ?? 'Cadastral Document',
                'notes'             => $note ? mb_substr($note, 0, 1000) : null,
                'display_order'     => $displayOrder,
                'file_size'         => $upload->getSize(),
                'is_pdf_converted'  => 0,
                'registry'          => $registryName,
                'edms_file_type'    => $fileType,
                'definition'        => $definition,
                'definition_code'   => $definitionCode,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            // Single-valued kinds: the new one supersedes, the old one is kept.
            $previous = $kind === self::KIND_MANUAL_CHART
                ? $conn->table(self::TABLE)
                    ->where('owner_type', $ownerType)->where('owner_id', $ownerId)
                    ->where('kind', $kind)->where('is_superseded', 0)
                    ->pluck('id')->all()
                : [];

            $documentId = $conn->table(self::TABLE)->insertGetId([
                'owner_type'       => $ownerType,
                'owner_id'         => $ownerId,
                'kind'             => $kind,
                'scanning_id'      => $scanningId,
                'file_indexing_id' => $indexing->id,
                'file_number'      => mb_substr((string) $indexing->file_number, 0, 100),
                'original_name'    => $originalName,
                'mime'             => $mime,
                'size'             => $upload->getSize(),
                'uploaded_by'      => $userId,
                'is_superseded'    => 0,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            if ($previous !== []) {
                $conn->table(self::TABLE)->whereIn('id', $previous)->update([
                    'is_superseded'    => 1,
                    'superseded_at'    => now(),
                    'superseded_by_id' => $documentId,
                    'updated_at'       => now(),
                ]);
            }

            // What Scan Upload does after a page lands: the file has new pages.
            $conn->table('file_indexings')->where('id', $indexing->id)->update(['is_updated' => 1]);
        } catch (\Throwable $e) {
            $this->discardWritten();
            throw $e;
        }

        return ['document_id' => (int) $documentId, 'scanning_id' => (int) $scanningId, 'path' => $path];
    }

    /**
     * Remove every file this request wrote, and the folders that write created
     * (the PAPER folder, and the file-number folder if it did not exist) once
     * they are empty again — never a tree root, registry or master folder, and
     * never a folder that was there before. Called when the caller's
     * transaction failed.
     */
    public function discardWritten(): void
    {
        $disk = Storage::disk('public');

        // Every file first: several uploads can share the folder the first one
        // created, and that folder is only empty once all of them are gone.
        foreach ($this->written as $entry) {
            try {
                $disk->delete($entry['path']);
            } catch (\Throwable $e) {
                Log::warning('Cadastral document cleanup failed: ' . $e->getMessage(), ['path' => $entry['path']]);
            }
        }

        // Then the folders, deepest first (each entry lists PAPER before FILE NUMBER).
        foreach ($this->written as $entry) {
            foreach ($entry['created'] as $dir) {
                try {
                    if (str_starts_with($dir, EdmsDocumentPathResolver::SCAN_UPLOAD_ROOT . '/')
                        && $disk->exists($dir) && $disk->allFiles($dir) === [] && $disk->allDirectories($dir) === []) {
                        $disk->deleteDirectory($dir);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Cadastral document folder cleanup failed: ' . $e->getMessage(), ['dir' => $dir]);
                }
            }
        }

        $this->written = [];
    }

    /** Paths written in this request (the harness checks and removes them). */
    public function written(): array
    {
        return array_column($this->written, 'path');
    }

    /* ----------------------------- reading ----------------------------- */

    /**
     * The documents filed for one or more records, newest first, with the
     * scan's live path and whether it is still on disk.
     *
     * @param  int|int[]  $ownerIds
     * @return Collection<int, object>
     */
    public function for(string $ownerType, int|array $ownerIds, ?string $kind = null): Collection
    {
        $ids = array_values(array_filter(array_map('intval', (array) $ownerIds)));

        if (! self::installed() || $ids === []) {
            return collect();
        }

        return collect(DB::connection(self::CONN)->table(self::TABLE . ' as d')
            ->leftJoin('scannings as s', 's.id', '=', 'd.scanning_id')
            ->where('d.owner_type', $ownerType)
            ->whereIn('d.owner_id', $ids)
            ->when($kind, fn ($q) => $q->where('d.kind', $kind))
            ->orderByDesc('d.id')
            ->get([
                'd.id', 'd.owner_id', 'd.kind', 'd.scanning_id', 'd.file_number', 'd.original_name',
                'd.mime', 'd.size', 'd.uploaded_by', 'd.is_superseded', 'd.superseded_at', 'd.created_at',
                's.document_path', 's.status as scan_status',
            ]))
            ->map(function ($doc) {
                $doc->url = $doc->document_path ? route('scan-uploads.download', $doc->scanning_id) : null;

                return $doc;
            });
    }
}
