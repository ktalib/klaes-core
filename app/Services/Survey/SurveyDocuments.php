<?php

namespace App\Services\Survey;

use App\Services\Cadastral\CadastralDocuments;
use App\Services\Edms\EdmsDocumentPathResolver;
use App\Services\Edms\EdmsFileType;
use App\Services\EdmsScanUploadFolderService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Survey's document upload (Sketch Plan, change list p.2, 2026-10-29): files the
 * upload into the FILE'S EDMS folder as a scan, so Page Typing sees it (module
 * uploads go through EDMS), and remembers which Survey record it was filed for.
 *
 * A Survey copy of App\Services\Cadastral\CadastralDocuments::file() (clone per
 * module): same bytes-then-rows order and cleanup, but under the Survey Registry
 * tree (EDMS/SCAN_UPLOAD/Survey_Registry/...) with Survey document types, and the
 * pointer in survey_documents. The file lookup and upload rules are Cadastral's,
 * reused as they are: they are about the file, not the module.
 *
 * Owner = the anchor of SurveyItsAnchors: survey (I to S), cadastral (job) or
 * private (Surveyor's Report (Private)). A new Sketch Plan supersedes the last;
 * both scans stay in EDMS.
 */
class SurveyDocuments
{
    private const CONN  = 'sqlsrv';
    private const TABLE = 'survey_documents';
    private const REGISTRY = 'Survey Registry';
    public const PAPER_SIZE = 'A4';

    public const KIND_SKETCH_PLAN = 'sketch_plan';

    private const DOCUMENT_TYPES = [
        self::KIND_SKETCH_PLAN => 'Survey – Sketch Plan',
    ];

    /** Kinds where a new upload supersedes the previous one. */
    private const SINGLE = [self::KIND_SKETCH_PLAN];

    private array $written = [];

    public function __construct(
        private EdmsDocumentPathResolver $paths,
        private EdmsScanUploadFolderService $folders,
        private CadastralDocuments $lookup,
    ) {}

    public static function fileRule(): array
    {
        return CadastralDocuments::fileRule();
    }

    public static function messages(string $field): array
    {
        return CadastralDocuments::messages($field);
    }

    /** The file's indexing row, or a validation error on $field (no index record, no EDMS folder). */
    public function requireIndexing(?string $fileNumber, string $field): object
    {
        return $this->lookup->requireIndexing(null, $fileNumber, $field);
    }

    /** @return array{document_id:int, scanning_id:int, path:string} */
    public function file(UploadedFile $upload, object $indexing, string $ownerType, int $ownerId, string $kind): array
    {
        $mime = (string) $upload->getMimeType();
        $extension = CadastralDocuments::ALLOWED[$mime] ?? null;
        if ($extension === null) {
            throw ValidationException::withMessages(['document' => 'Only PDF, JPG and PNG documents can be uploaded.']);
        }

        $conn         = DB::connection(self::CONN);
        $registryName = $this->paths->registryName(self::REGISTRY);
        $fileType     = EdmsFileType::normalize($indexing->edms_file_type);
        $folderNumber = $this->folders->folderName((string) $indexing->file_number);

        $displayOrder   = ((int) $conn->table('scannings')->where('file_indexing_id', $indexing->id)->max('display_order')) + 1;
        $definition     = $displayOrder + 1;
        $definitionCode = mb_substr($definition . '-' . $indexing->file_number, 0, 100);

        $directory = $this->paths->scanUploadFolder($registryName, $folderNumber, self::PAPER_SIZE, $fileType);
        $filename  = 'srv-' . Str::slug($definitionCode, '-') . '_' . now()->timestamp . '_' . Str::random(6) . '.' . $extension;

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
                'document_type'     => self::DOCUMENT_TYPES[$kind] ?? 'Survey Document',
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

            $previous = in_array($kind, self::SINGLE, true)
                ? $conn->table(self::TABLE)->where('owner_type', $ownerType)->where('owner_id', $ownerId)
                    ->where('kind', $kind)->where('is_superseded', 0)->pluck('id')->all()
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
                    'is_superseded' => 1, 'superseded_at' => now(), 'superseded_by_id' => $documentId, 'updated_at' => now(),
                ]);
            }

            $conn->table('file_indexings')->where('id', $indexing->id)->update(['is_updated' => 1]);
        } catch (\Throwable $e) {
            $this->discardWritten();
            throw $e;
        }

        return ['document_id' => (int) $documentId, 'scanning_id' => (int) $scanningId, 'path' => $path];
    }

    /** Remove what this request wrote (and the folders it created, once empty), e.g. after a rollback. */
    public function discardWritten(): void
    {
        $disk = Storage::disk('public');
        foreach ($this->written as $e) {
            try { $disk->delete($e['path']); } catch (\Throwable $x) { Log::warning('Survey document cleanup failed: ' . $x->getMessage()); }
        }
        foreach ($this->written as $e) {
            foreach ($e['created'] as $dir) {
                try {
                    if (str_starts_with($dir, EdmsDocumentPathResolver::SCAN_UPLOAD_ROOT . '/')
                        && $disk->exists($dir) && $disk->allFiles($dir) === [] && $disk->allDirectories($dir) === []) {
                        $disk->deleteDirectory($dir);
                    }
                } catch (\Throwable $x) { Log::warning('Survey document folder cleanup failed: ' . $x->getMessage()); }
            }
        }
        $this->written = [];
    }

    public function written(): array
    {
        return array_column($this->written, 'path');
    }

    /** Documents filed for one record, newest first, with the scan's view URL. */
    public function for(string $ownerType, int $ownerId, ?string $kind = null): Collection
    {
        return collect(DB::connection(self::CONN)->table(self::TABLE . ' as d')
            ->leftJoin('scannings as s', 's.id', '=', 'd.scanning_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.uploaded_by')
            ->where('d.owner_type', $ownerType)->where('d.owner_id', $ownerId)
            ->when($kind, fn ($q) => $q->where('d.kind', $kind))
            ->orderByDesc('d.id')
            ->get(['d.id', 'd.kind', 'd.scanning_id', 'd.file_number', 'd.original_name', 'd.mime', 'd.size',
                   'd.is_superseded', 'd.created_at', 's.document_path', 'u.first_name', 'u.last_name']))
            ->map(function ($doc) {
                $doc->url = $doc->document_path ? route('scan-uploads.download', $doc->scanning_id) : null;
                // As User::getNameAttribute(): users has no name column.
                $doc->uploaded_by_name = trim(ucfirst((string) $doc->first_name) . ' ' . ucfirst((string) $doc->last_name)) ?: null;

                return $doc;
            });
    }

    /** owner_type / owner_id for an anchor. */
    public static function owner(array $anchor): array
    {
        $id = $anchor['keys']['survey_its_id'] ?? $anchor['keys']['cadastral_survey_job_id'] ?? $anchor['keys']['survey_change_report_id'];

        return [$anchor['source'], (int) $id];
    }
}
