<?php

namespace App\Http\Controllers\Api\CsvImporter;

use App\Http\Controllers\Controller;
use App\Jobs\CsvImporter\FileIndexingAnalysisJob;
use App\Jobs\CsvImporter\FileIndexingImportJob;
use App\Models\CsvImportUpload;
use App\Services\CsvImporter\FileIndexing\FileIndexingImportService;
use App\Services\CsvImporter\Security\ImportAccessGuard;
use App\Services\CsvImporter\Sessions\ImportSessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FileIndexingImportController extends Controller
{
    public function __construct(
        private readonly FileIndexingImportService $importService,
        private readonly ImportSessionManager $sessions,
        private readonly ImportAccessGuard $accessGuard,
    ) {
    }

    public function upload(Request $request): JsonResponse
    {
        $this->accessGuard->assertSystemAdmin($request->user());

        $validated = $request->validate([
            'file' => ['required', 'file'],
            'test_control' => ['required', Rule::in(['TEST', 'PRODUCTION'])],
        ]);

        $file = $validated['file'];
        $testControl = strtoupper($validated['test_control']);

        try {
            $storedPath = $file->storeAs(
                'csv-importer/uploads',
                Str::uuid()->toString() . '.' . $file->getClientOriginalExtension(),
                'local'
            );
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'file' => 'Unable to store uploaded file: ' . $exception->getMessage(),
            ]);
        }

        $sessionId = $this->sessions->create([
            'type' => 'file_indexing',
            'filename' => $file->getClientOriginalName(),
            'test_control' => $testControl,
            'file_path' => $storedPath,
            'status' => 'uploaded',
            'phase' => 'preview',
            'progress' => [
                'processed' => 0,
                'total' => 0,
                'percent' => 0,
                'message' => 'File stored. Ready for preview.',
            ],
        ]);

        CsvImportUpload::create([
            'session_id' => $sessionId,
            'import_type' => 'file_indexing',
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'test_control' => $testControl,
            'status' => 'uploaded',
            'meta' => [],
        ]);

        FileIndexingAnalysisJob::dispatch($sessionId);

        return response()->json([
            'session_id' => $sessionId,
            'status' => 'queued',
            'filename' => $file->getClientOriginalName(),
            'test_control' => $testControl,
            'message' => 'Upload saved. Use the pagination controls to preview rows.',
        ]);
    }

    public function status(Request $request, string $sessionId): JsonResponse
    {
        $this->accessGuard->assertSystemAdmin($request->user());
        $session = $this->sessions->require($sessionId);
        $upload = CsvImportUpload::where('session_id', $sessionId)->first();

        return response()->json([
            'session_id' => $sessionId,
            'status' => $upload?->status ?? $session['status'] ?? 'uploaded',
            'phase' => $session['phase'] ?? 'preview',
            'filename' => $session['filename'] ?? null,
            'test_control' => $session['test_control'] ?? null,
            'progress' => $session['progress'] ?? null,
            'import_summary' => $session['import_summary'] ?? null,
            'error' => $session['error'] ?? null,
            'row_count' => $upload?->row_count,
            'summary' => $upload?->meta['summary'] ?? null,
        ]);
    }

    public function preview(Request $request, string $sessionId): JsonResponse
    {
        $this->accessGuard->assertSystemAdmin($request->user());
        $session = $this->sessions->require($sessionId);
        $upload = CsvImportUpload::where('session_id', $sessionId)->firstOrFail();

        if (($upload->status ?? 'uploaded') !== 'preview_ready') {
            return response()->json([
                'message' => 'Preview analysis is still running.',
                'status' => $upload->status ?? 'uploaded',
            ], 409);
        }

        $rowCount = $this->resolveRowCount($upload);
        $meta = $upload->meta ?? [];

        return response()->json([
            'filename' => $session['filename'],
            'test_control' => $session['test_control'],
            'row_count' => $rowCount,
            'per_page' => (int) $request->query('per_page', 50),
            'summary' => $meta['summary'] ?? null,
            'suppressed' => $meta['suppressed'] ?? [],
            'multiple_occurrences' => $meta['multiple_occurrences'] ?? [],
            'grouping_preview' => $meta['grouping_preview'] ?? [],
            'qc_findings' => $meta['qc_findings'] ?? [],
        ]);
    }

    public function previewRows(Request $request, string $sessionId): JsonResponse
    {
        $this->accessGuard->assertSystemAdmin($request->user());

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:200'],
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 50;

        $upload = CsvImportUpload::where('session_id', $sessionId)->firstOrFail();
        $path = Storage::disk('local')->path($upload->file_path);

        $rowCount = $this->resolveRowCount($upload);
        $rows = $this->importService->paginateFromPath($path, $page, $perPage);

        return response()->json([
            'rows' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $rowCount,
                'last_page' => max(1, (int) ceil(($rowCount ?: 1) / $perPage)),
            ],
        ]);
    }

    public function uploads(Request $request): JsonResponse
    {
        $this->accessGuard->assertSystemAdmin($request->user());

        $limit = (int) $request->query('limit', 25);
        $uploads = CsvImportUpload::where('import_type', 'file_indexing')
            ->orderByDesc('created_at')
            ->limit(max(5, min(100, $limit)))
            ->get();

        return response()->json([
            'data' => $uploads->map(function (CsvImportUpload $upload) {
                $meta = $upload->meta ?? [];

                return [
                    'session_id' => $upload->session_id,
                    'file_name' => $upload->file_name,
                    'test_control' => $upload->test_control,
                    'status' => $upload->status,
                    'row_count' => $upload->row_count,
                    'created_at' => optional($upload->created_at)->toIso8601String(),
                    'updated_at' => optional($upload->updated_at)->toIso8601String(),
                    'summary' => $meta['summary'] ?? null,
                    'import_summary' => $meta['import_summary'] ?? null,
                ];
            }),
        ]);
    }

    public function import(Request $request, string $sessionId): JsonResponse
    {
        $this->accessGuard->assertSystemAdmin($request->user());
        $session = $this->sessions->require($sessionId);
        $upload = CsvImportUpload::where('session_id', $sessionId)->firstOrFail();

        if (($upload->status ?? 'uploaded') !== 'preview_ready') {
            throw ValidationException::withMessages([
                'session' => 'Preview must finish before importing.',
            ]);
        }

        $this->sessions->markStatus($sessionId, 'import_queued', [
            'phase' => 'import',
            'progress' => [
                'processed' => 0,
                'total' => 0,
                'percent' => 0,
                'message' => 'Waiting for worker',
            ],
        ]);

        $upload->update(['status' => 'import_queued']);

        FileIndexingImportJob::dispatch($sessionId);

        return response()->json([
            'session_id' => $sessionId,
            'status' => 'import_queued',
            'message' => 'Import queued. Keep this page open to monitor progress.',
        ]);
    }

    private function resolveRowCount(CsvImportUpload $upload): int
    {
        if ($upload->row_count !== null) {
            return $upload->row_count;
        }

        $path = Storage::disk('local')->path($upload->file_path);
        $count = $this->importService->countRowsFromPath($path);
        $upload->update(['row_count' => $count]);

        return $count;
    }

    
}
