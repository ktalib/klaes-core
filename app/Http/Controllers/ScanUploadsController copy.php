<?php

namespace App\Http\Controllers;

use App\Models\FileIndexing;
use App\Models\PageTyping;
use App\Models\Scanning;
use App\Services\ScanUploads\BlindScanIngestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class ScanUploadsController extends Controller
{
    private $blindScanIngestionService;

    public function __construct(BlindScanIngestionService $blindScanIngestionService)
    {
        $this->blindScanIngestionService = $blindScanIngestionService;
    }

    /**
     * Display the scan uploads dashboard.
     */
    public function index(Request $request)
    {
        $PageTitle = 'Scan Uploads';
        $PageDescription = 'Manage scan uploads and document processing.';

        $stats = $this->getDashboardStats();
        $uploadsPaginator = $this->buildUploadsPaginator($request);

        $payload = [
            'stats' => $stats,
            'uploads' => $uploadsPaginator->items(),
            'pagination' => [
                'current_page' => $uploadsPaginator->currentPage(),
                'per_page' => $uploadsPaginator->perPage(),
                'total' => $uploadsPaginator->total(),
                'next_page' => $uploadsPaginator->hasMorePages() ? $uploadsPaginator->currentPage() + 1 : null,
                'prev_page' => $uploadsPaginator->currentPage() > 1 ? $uploadsPaginator->currentPage() - 1 : null,
                'next_page_url' => $uploadsPaginator->nextPageUrl(),
                'prev_page_url' => $uploadsPaginator->previousPageUrl(),
                'page_query' => 'pag_next',
            ],
        ];

        return view('scan_uploads.index', compact(
            'PageTitle',
            'PageDescription',
            'payload',
            'uploadsPaginator'
        ));
    }

    public function discoverBlindScan(Request $request)
    {
        $validated = $request->validate([
            'file_number' => 'required|string|max:255',
        ]);

        try {
            $manifest = $this->blindScanIngestionService->discover($validated['file_number']);

            return response()->json([
                'success' => true,
                'data' => $manifest,
            ]);
        } catch (Throwable $exception) {
            Log::error('Blind scan discovery failed', [
                'error' => $exception->getMessage(),
                'file_number' => $validated['file_number'],
            ]);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 404);
        }
    }

    public function transferBlindScan(Request $request)
    {
        $validated = $request->validate([
            'file_number' => 'required|string|max:255',
            'file_indexing_id' => 'nullable|integer|exists:sqlsrv.file_indexings,id',
            'registry' => 'required|string|in:Lands Registry,Cadastral Registry,DCIV Registry,Secret Registry,KANGIS Registry,SLTR Registry,ST Registry,Deeds Registry',
            'files' => 'nullable|array',
            'files.*.relative_path' => 'required_with:files|string|max:1024',
        ]);

        try {
            $fileIndexing = $this->resolveFileIndexing([
                'file_indexing_id' => $validated['file_indexing_id'] ?? null,
                'file_number' => $validated['file_number'],
            ]);

            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to find the specified indexed file.',
                ], 404);
            }

            $selectedFiles = (isset($validated['files']) && $validated['files'] !== null) ? $validated['files'] : [];
            $transferred = $this->blindScanIngestionService->transfer(
                $validated['file_number'],
                $selectedFiles,
                $fileIndexing->id,
                $validated['registry'] ?? null
            );

            $documents = collect($transferred['files'] ?? [])
                ->map(fn(Scanning $scan) => $this->formatDocumentPayload($scan))
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Blind scan documents moved successfully.',
                'data' => [
                    'file_number' => $transferred['file_number'],
                    'documents' => $documents,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Blind scan transfer failed', [
                'error' => $exception->getMessage(),
                'file_number' => $validated['file_number'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to move blind scan documents: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch grouped upload logs with optional filtering.
     */
    public function log(Request $request)
    {
        try {
            $paginator = $this->buildUploadsPaginator($request);

            return response()->json([
                'success' => true,
                'data' => $paginator->items(),
                'count' => $paginator->total(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'next_page' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
                    'prev_page' => $paginator->currentPage() > 1 ? $paginator->currentPage() - 1 : null,
                    'next_page_url' => $paginator->nextPageUrl(),
                    'prev_page_url' => $paginator->previousPageUrl(),
                    'page_query' => 'pag_next',
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Scan log fetch failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch scan logs.',
            ], 500);
        }
    }

    /**
     * Handle single file upload.
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_indexing_id' => 'nullable|integer|exists:sqlsrv.file_indexings,id',
            'file_number' => 'nullable|string|max:255',
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,bmp,tiff,webp,pdf|max:512000',
            'definition' => 'nullable|integer|min:0|max:500',
            'paper_size' => 'nullable|string|in:A4,A5,A3,Letter,Legal,Custom',
            'document_type' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'display_order' => 'nullable|integer|min:0',
            'parent_scan_id' => 'nullable|integer|exists:sqlsrv.scannings,id',
            'is_pdf_converted' => 'sometimes|boolean',
            'original_filename' => 'nullable|string|max:255',
            'registry' => 'required|string|in:Lands Registry,Cadastral Registry,DCIV Registry,Secret Registry,KANGIS Registry,SLTR Registry,ST Registry,Deeds Registry',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $payload = $validator->validated();
            $file = $request->file('file');

            // Resolve the FileIndexing record
            $fileIndexing = $this->resolveFileIndexing($payload);
            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to find the specified indexed file.',
                ], 404);
            }

            // Extract file metadata
            $originalName = (isset($payload['original_filename']) && $payload['original_filename'] !== null) ? $payload['original_filename'] : $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());
            $fileSize = $file->getSize();
            $paperSize = (isset($payload['paper_size']) && $payload['paper_size'] !== null) ? $payload['paper_size'] : $this->detectPaperSize($file);
            $documentType = (isset($payload['document_type']) && $payload['document_type'] !== null) ? $payload['document_type'] : $this->detectDocumentType($originalName);
            $displayOrder = (isset($payload['display_order']) && $payload['display_order'] !== null)
                ? (int) $payload['display_order']
                : 0;
            $definition = $displayOrder + 1;
            $definitionCode = $definition . '-' . $fileIndexing->file_number;

            // Generate storage directory and filename
            // Map Registry to Folder Name
            $registryMap = [
                'Lands Registry' => 'Lands_Registry',
                'Cadastral Registry' => 'Cadastral_Registry',
                'DCIV Registry' => 'DCIV_Registry',
                'Secret Registry' => 'Secret_Registry',
                'KANGIS Registry' => 'KANGIS_Registry',
                'SLTR Registry' => 'SLTR_Registry',
                'ST Registry' => 'ST_Registry',
                'Deeds Registry' => 'Deeds_Registry',
            ];
            $registryFolder = (isset($registryMap[$payload['registry']]) && $registryMap[$payload['registry']] !== null) ? $registryMap[$payload['registry']] : 'Lands_Registry'; // Default fallback

            $directory = 'EDMS/SCAN_UPLOAD/' . $registryFolder . '/' . $fileIndexing->file_number;
            $filename = $this->generateFilename($fileIndexing, $extension, $definitionCode);

            // Store file using Laravel's Storage facade
            $storedPath = $file->storeAs($directory, $filename, 'public');

            if (!$storedPath) {
                throw new \Exception('Failed to store file on disk');
            }

            Log::info('File stored successfully', [
                'path' => $storedPath,
                'size' => $fileSize,
                'file_indexing_id' => $fileIndexing->id,
            ]);

            // Create Scanning record with all metadata
            $scanning = Scanning::on('sqlsrv')->create([
                'file_indexing_id' => $fileIndexing->id,
                'document_path' => $storedPath,
                'uploaded_by' => Auth::id(),
                'status' => 'pending',
                'definition' => $definition,
                'definition_code' => $definitionCode,
                'original_filename' => $originalName,
                'paper_size' => $paperSize,
                'document_type' => $documentType,
                'notes' => (isset($payload['notes']) && $payload['notes'] !== null) ? $payload['notes'] : null,
                'display_order' => $displayOrder,
                'file_size' => $fileSize,
                'registry' => $payload['registry'],
                'is_pdf_converted' => (isset($payload['is_pdf_converted']) && $payload['is_pdf_converted'] !== null) ? $payload['is_pdf_converted'] : false,
                'parent_scan_id' => (isset($payload['parent_scan_id']) && $payload['parent_scan_id'] !== null) ? $payload['parent_scan_id'] : null,
            ]);

            // Mark file_indexing as updated
            try {
                $fileIndexing->update(['is_updated' => 1]);
            } catch (Throwable $e) {
                Log::warning('Could not update file_indexing.is_updated', [
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info('Scan upload completed', [
                'file_indexing_id' => $fileIndexing->id,
                'scanning_id' => $scanning->id,
                'uploaded_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully.',
                'data' => $this->formatDocumentPayload($scanning->fresh(['fileIndexing', 'uploader'])),
            ]);
        } catch (Throwable $exception) {
            Log::error('Scan upload failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to upload document: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Replace an existing scan document with an edited version.
     */
    public function applyEdits(Request $request, Scanning $scan)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,bmp,tiff,webp|max:51200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $fileIndexing = $scan->fileIndexing;
            $directory = $fileIndexing
                ? 'EDMS/SCAN_UPLOAD/' . $fileIndexing->file_number
                : 'EDMS/SCAN_UPLOAD/UNASSIGNED';

            $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $fileNum = ($fileIndexing ? $fileIndexing->file_number : 'scan');
            $slug = Str::slug($fileNum);
            $filename = "{$slug}_edited_" . now()->timestamp . '.' . $extension;

            $storedPath = $file->storeAs($directory, $filename, 'public');
            if (!$storedPath) {
                throw new \RuntimeException('Failed to store edited document.');
            }

            if ($scan->document_path) {
                try {
                    Storage::disk('public')->delete($scan->document_path);
                } catch (Throwable $e) {
                    Log::warning('Unable to delete previous scan document during applyEdits', [
                        'scan_id' => $scan->id,
                        'path' => $scan->document_path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $scan->update([
                'document_path' => $storedPath,
                'file_size' => $file->getSize(),
                'original_filename' => $file->getClientOriginalName() ?: $filename,
                'is_pdf_converted' => false,
            ]);

            Log::info('Scan document updated after in-app edits', [
                'scan_id' => $scan->id,
                'path' => $storedPath,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document updated successfully.',
                'data' => $this->formatDocumentPayload($scan->fresh(['fileIndexing', 'uploader'])),
            ]);
        } catch (Throwable $exception) {
            Log::error('Unable to apply edits to scan document', [
                'scan_id' => $scan->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to apply edits: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Persist drag/drop order from preview sidebar.
     */
    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.scan_id' => 'required|integer|exists:sqlsrv.scannings,id',
            'items.*.display_order' => 'required|integer|min:0',
            'items.*.original_filename' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        try {
            DB::connection('sqlsrv')->transaction(function () use ($payload) {
                foreach ($payload['items'] as $entry) {
                    $scan = Scanning::on('sqlsrv')->with('fileIndexing')->find($entry['scan_id']);
                    if (!$scan) {
                        continue;
                    }

                    $displayOrder = (int) $entry['display_order'];
                    $definition = $displayOrder + 1;
                    $fileNumber = optional($scan->fileIndexing)->file_number;
                    $definitionCode = $fileNumber ? ($definition . '-' . $fileNumber) : ($scan->definition_code ?? null);

                    $updateData = [
                        'display_order' => $displayOrder,
                        'definition' => $definition,
                        'definition_code' => $definitionCode,
                    ];

                    if (!empty($entry['original_filename'])) {
                        $updateData['original_filename'] = $entry['original_filename'];
                    }

                    $scan->update($updateData);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Document order updated successfully.',
            ]);
        } catch (Throwable $exception) {
            Log::error('Failed to persist scan reorder', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to save document order: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a scan record and associated file.
     */
    public function destroy(Scanning $scan)
    {
        try {
            // Check if scan has associated page typing (constraint)
            $pageTypingCount = PageTyping::on('sqlsrv')
                ->where('scanning_id', $scan->id)
                ->count();

            if ($pageTypingCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete scan: page typing is already in progress.',
                ], 409);
            }

            // Get document path before deletion
            $documentPath = $scan->document_path;
            $fileNumber = ($scan->fileIndexing ? $scan->fileIndexing->file_number : null);

            // Delete the file from storage
            if ($documentPath) {
                Storage::disk('public')->delete($documentPath);
                Log::info('File deleted from storage', ['path' => $documentPath]);
            }

            // Delete the database record
            $scan->delete();

            // Attempt to clean up empty directories
            if ($fileNumber) {
                try {
                    $directory = file_storage_path('app/public/EDMS/SCAN_UPLOAD/' . $fileNumber);
                    if (is_dir($directory) && count(scandir($directory)) <= 2) {
                        rmdir($directory);
                    }
                } catch (Throwable $e) {
                    Log::warning('Could not clean up empty directory', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Scan deleted', ['scan_id' => $scan->id]);

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully.',
            ]);
        } catch (Throwable $exception) {
            Log::error('Scan deletion failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to delete document.',
            ], 500);
        }
    }

    /**
     * Debug endpoint: return filesystem diagnostics.
     */
    public function debug()
    {
        try {
            $basePath = file_storage_path('app/public/EDMS/SCAN_UPLOAD');
            $writable = is_writable($basePath);

            // Count top-level items only (Non-recursive to stay fast)
            $fileCount = 0;
            $dirCount = 0;
            if (is_dir($basePath)) {
                $items = scandir($basePath);
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $fullPath = $basePath . DIRECTORY_SEPARATOR . $item;
                    if (is_file($fullPath)) {
                        $fileCount++;
                    } elseif (is_dir($fullPath)) {
                        $dirCount++;
                    }
                }
            }

            // Build directory tree (limited depth - Level 1 registries only)
            $tree = $this->buildDirectoryTree($basePath, 0, 1);

            return response()->json([
                'success' => true,
                'data' => [
                    'base_path' => $basePath,
                    'exists' => is_dir($basePath),
                    'writable' => $writable,
                    'file_count' => $fileCount,
                    'directory_count' => $dirCount,
                    'tree' => $tree,
                    'storage_disk' => 'public',
                    'disk_free_space' => disk_free_space($basePath) ?: 'N/A',
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Debug endpoint failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve debug information.',
            ], 500);
        }
    }

    /**
     * Get dashboard statistics.
     */
    protected function getDashboardStats()
    {
        try {
            $today = now()->startOfDay();

            $todayCount = Scanning::on('sqlsrv')
                ->where('created_at', '>=', $today)
                ->count();

            $pendingPageTyping = PageTyping::on('sqlsrv')
                ->where('status', 'pending')
                ->count();

            $totalScannedFiles = FileIndexing::on('sqlsrv')
                ->whereHas('scannings')
                ->count();

            $allTimeDocuments = Scanning::on('sqlsrv')->count();

            return [
                'today_uploads' => $todayCount,
                'pending_page_typing' => $pendingPageTyping,
                'total_scanned' => $totalScannedFiles,
                'all_time_documents' => $allTimeDocuments,
            ];
        } catch (Throwable $e) {
            Log::warning('Dashboard stats computation failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'today_uploads' => 0,
                'pending_page_typing' => 0,
                'total_scanned' => 0,
                'all_time_documents' => 0,
            ];
        }
    }

    /**
     * Build the uploads paginator shared by the dashboard and API.
     */
    protected function buildUploadsPaginator(Request $request)
    {
        $perPage = 20;
        $pageQuery = 'pag_next';
        $currentPage = max((int) $request->query($pageQuery, 1), 1);

        $query = FileIndexing::on('sqlsrv')
            ->whereHas('scannings', function ($scanningQuery) use ($request) {
                $this->applyScanningFilters($scanningQuery, $request);
            })
            ->with([
                'scannings' => function ($scanningQuery) use ($request) {
                    $this->applyScanningFilters($scanningQuery, $request);
                    $scanningQuery->with('uploader')
                        ->orderByRaw('ISNULL(definition, 0) asc')
                        ->orderByRaw('ISNULL(display_order, 0) asc')
                        ->orderByDesc('created_at');
                }
            ])
            ->when($request->filled('file_number'), function ($fileQuery) use ($request) {
                $fileQuery->where('file_number', $request->input('file_number'));
            })
            ->orderByDesc(
                Scanning::on('sqlsrv')
                    ->select('created_at')
                    ->whereColumn('file_indexings.id', 'scannings.file_indexing_id')
                    ->latest()
                    ->take(1)
            );

        return $query->paginate($perPage, ['*'], $pageQuery, $currentPage)
            ->through(function (FileIndexing $fileIndexing) {
                $documents = $fileIndexing->scannings->map(function (Scanning $scan) {
                    return $this->formatDocumentPayload($scan);
                })->values()->all();

                $latestScan = $fileIndexing->scannings->first();

                return [
                    'file_indexing_id' => $fileIndexing->id,
                    'fileNumber' => $fileIndexing->file_number ?? 'N/A',
                    'fileTitle' => $fileIndexing->file_title ?? 'Indexed File',
                    'documents' => $documents,
                    'date' => ($latestScan && $latestScan->created_at) ? $latestScan->created_at->toIso8601String() : null,
                    'status' => (isset($documents[0]) && isset($documents[0]['status'])) ? $documents[0]['status'] : 'pending',
                ];
            });
    }

    protected function applyScanningFilters($query, Request $request)
    {
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->input('document_type'));
        }

        return $query;
    }

    /**
     * Format a Scanning record into the normalized API response.
     */
    protected function formatDocumentPayload(Scanning $scan)
    {
        $fileIndexing = $scan->fileIndexing;
        $uploader = $scan->uploader;
        $fileNumber = ($fileIndexing ? $fileIndexing->file_number : 'N/A');
        $publicPath = $scan->document_path ? asset('storage/' . ltrim($scan->document_path, '/')) : null;

        return [
            'id' => $scan->id,
            'fileIndexingId' => $scan->file_indexing_id,
            'fileNumber' => $fileNumber,
            'fileName' => $scan->original_filename,
            'originalName' => $scan->original_filename,
            'definition' => $scan->definition ?? 0,
            'definitionCode' => $scan->definition_code,
            'paperSize' => $scan->paper_size,
            'documentType' => $scan->document_type,
            'fileSize' => $scan->file_size,
            'isPdfConverted' => (bool) $scan->is_pdf_converted,
            'parentScanId' => $scan->parent_scan_id,
            'status' => $scan->status,
            'uploadedAt' => ($scan->created_at ? $scan->created_at->toIso8601String() : null),
            'uploadedBy' => ($uploader ? $uploader->name : 'System'),
            'downloadUrl' => $publicPath,
            'webPath' => $publicPath,
            'serverPath' => $publicPath,
            'displayOrder' => $scan->display_order,
            'notes' => $scan->notes,
            'documentPath' => $scan->document_path,
        ];
    }

    /**
     * Resolve a FileIndexing record from payload parameters.
     */
    protected function resolveFileIndexing(array $payload)
    {
        if (!empty($payload['file_indexing_id'])) {
            return FileIndexing::on('sqlsrv')->find($payload['file_indexing_id']);
        }

        if (!empty($payload['file_number'])) {
            return FileIndexing::on('sqlsrv')
                ->where('file_number', $payload['file_number'])
                ->first();
        }

        return null;
    }

    /**
     * Generate a unique filename with timestamp and random suffix.
     */
    protected function generateFilename(FileIndexing $fileIndexing, string $extension, ?string $definitionCode = null)
    {
        $baseName = $definitionCode ?: $fileIndexing->file_number;
        $slug = Str::slug($baseName, '-');
        $timestamp = now()->timestamp;
        $random = Str::random(6);

        return "{$slug}_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Detect paper size from file dimensions (stub for image processing).
     */
    protected function detectPaperSize($file)
    {
        // In real implementation, use getimagesize() or similar
        return 'A4';
    }

    /**
     * Detect document type from filename or MIME.
     */
    protected function detectDocumentType(string $filename)
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'PDF Document',
            'jpg', 'jpeg' => 'JPEG Image',
            'png' => 'PNG Image',
            'gif' => 'GIF Image',
            'bmp' => 'BMP Image',
            'tiff' => 'TIFF Image',
            'webp' => 'WebP Image',
            default => 'Document',
        };
    }

    /**
     * Build a directory tree structure up to a depth limit.
     */
    protected function buildDirectoryTree(string $dir, int $depth = 0, int $maxDepth = 3)
    {
        $tree = [];

        if ($depth > $maxDepth || !is_dir($dir)) {
            return $tree;
        }

        try {
            $files = @scandir($dir);
            if (!$files) {
                return $tree;
            }

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $filePath = $dir . DIRECTORY_SEPARATOR . $file;
                if (is_dir($filePath)) {
                    $tree[$file] = $this->buildDirectoryTree($filePath, $depth + 1, $maxDepth);
                } else {
                    $tree[$file] = filesize($filePath) . ' bytes';
                }
            }
        } catch (Throwable $e) {
            Log::warning('Directory tree scan failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $tree;
    }
}
