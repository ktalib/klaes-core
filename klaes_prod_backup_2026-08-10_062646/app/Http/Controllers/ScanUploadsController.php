<?php

namespace App\Http\Controllers;

use App\Models\FileIndexing;
use App\Models\PageTyping;
use App\Models\Scanning;
use App\Services\ScanUploads\BlindScanIngestionService;
use App\Services\ScanUploads\ScanReassignmentService;
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
    private $scanReassignmentService;

    public function __construct(
        BlindScanIngestionService $blindScanIngestionService,
        ScanReassignmentService $scanReassignmentService
    ) {
        $this->blindScanIngestionService = $blindScanIngestionService;
        $this->scanReassignmentService = $scanReassignmentService;
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
     * Stream a scan document file (used as fallback for client-side PDF conversion).
     */
    public function download(Scanning $scan)
    {
        try {
            $relativePath = ltrim((string) $scan->document_path, '/');
            if ($relativePath === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Document path is empty.',
                ], 404);
            }

            $absolutePath = file_storage_path('app/public/' . $relativePath);
            if (!is_file($absolutePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document file not found on disk.',
                    'path' => $relativePath,
                ], 404);
            }

            $mimeType = @mime_content_type($absolutePath) ?: 'application/octet-stream';
            return response()->file($absolutePath, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        } catch (Throwable $exception) {
            Log::error('Failed to stream scan document', [
                'scan_id' => $scan->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to open document.',
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
            ->when($request->filled('search'), function ($fileQuery) use ($request) {
                $fileQuery->where('file_number', 'LIKE', '%' . $request->input('search') . '%');
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
                
                // Pick first meaningful uploader across all documents in the batch.
                $uploadedBy = 'Not recorded';
                foreach ($documents as $document) {
                    $candidate = (isset($document['uploadedBy']) && $document['uploadedBy'] !== null)
                        ? trim((string) $document['uploadedBy'])
                        : '';

                    if (!$this->isPlaceholderUploader($candidate)) {
                        $uploadedBy = $candidate;
                        break;
                    }
                }

                if ($this->isPlaceholderUploader($uploadedBy) && $latestScan && $latestScan->uploaded_by) {
                    // Fallback: resolve from latest scan when document payloads are placeholder-only.
                    $resolved = $this->resolveUploaderName($latestScan->uploader, $latestScan->uploaded_by, $latestScan->id);
                    if (!$this->isPlaceholderUploader($resolved)) {
                        $uploadedBy = $resolved;
                    }
                }

                return [
                    'file_indexing_id' => $fileIndexing->id,
                    'fileNumber' => $fileIndexing->file_number ?? 'N/A',
                    'fileTitle' => $fileIndexing->file_title ?? 'Indexed File',
                    'documents' => $documents,
                    'date' => ($latestScan && $latestScan->created_at) ? $latestScan->created_at->toIso8601String() : null,
                    'status' => (isset($documents[0]) && isset($documents[0]['status'])) ? $documents[0]['status'] : 'pending',
                    'uploadedBy' => $uploadedBy,
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
        // Always reload fileIndexing and uploader to ensure they're fresh
        try {
            $fileIndexing = $scan->fileIndexing ?? $scan->load('fileIndexing')->fileIndexing;
        } catch (Throwable $e) {
            Log::warning('Failed to load fileIndexing', ['scan_id' => $scan->id, 'error' => $e->getMessage()]);
            $fileIndexing = null;
        }
        
        try {
            // First try the already-loaded relationship
            $uploader = $scan->uploader;
            
            // If not loaded, load it now
            if (!$uploader && $scan->uploaded_by) {
                $uploader = $scan->load('uploader')->uploader;
            }
        } catch (Throwable $e) {
            Log::warning('Failed to load uploader relationship', ['scan_id' => $scan->id, 'error' => $e->getMessage()]);
            $uploader = null;
        }
        
        // Fallback: if relationship still not loaded, try direct lookup
        if (!$uploader && $scan->uploaded_by) {
            try {
                $uploader = \App\Models\User::on('sqlsrv')->find((int) $scan->uploaded_by);
                Log::debug('Loaded uploader via direct User lookup', [
                    'scan_id' => $scan->id,
                    'user_id' => $uploader->id ?? 'NULL',
                ]);
            } catch (Throwable $e) {
                Log::warning('Failed to resolve uploader by ID', [
                    'scan_id' => $scan->id,
                    'uploaded_by' => $scan->uploaded_by,
                    'error' => $e->getMessage(),
                ]);
                $uploader = null;
            }
        }
        
        $fileNumber = ($fileIndexing ? $fileIndexing->file_number : 'N/A');
        $publicPath = $scan->document_path ? asset('storage/' . ltrim($scan->document_path, '/')) : null;

        // Build uploadedBy display name with multiple fallbacks
        $uploadedByName = $this->resolveUploaderName($uploader, $scan->uploaded_by, $scan->id);

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
            'uploadedBy' => $uploadedByName,
            'downloadUrl' => $publicPath,
            'webPath' => $publicPath,
            'serverPath' => $publicPath,
            'displayOrder' => $scan->display_order,
            'notes' => $scan->notes,
            'documentPath' => $scan->document_path,
            'registry' => $scan->registry,
        ];
    }

    /**
     * Resolve uploader name from User model or uploaded_by ID.
     * Falls back to placeholder for old records without uploader info.
     */
    protected function resolveUploaderName($uploader, $uploadedById = null, $scanId = null)
    {
        // Log what we're working with
        Log::debug('resolveUploaderName called', [
            'has_uploader' => $uploader ? true : false,
            'uploaded_by_id' => $uploadedById,
        ]);

        // If uploader model exists, try to extract name
        if ($uploader) {
            Log::debug('Uploader model found', [
                'id' => $uploader->id ?? 'NULL',
                'first_name' => $uploader->first_name ?? 'NULL',
                'last_name' => $uploader->last_name ?? 'NULL',
            ]);
            
            // Try to build from first_name and last_name
            $firstName = !empty($uploader->first_name) ? trim($uploader->first_name) : '';
            $lastName = !empty($uploader->last_name) ? trim($uploader->last_name) : '';
            $fullName = trim("{$firstName} {$lastName}");
            
            if (!empty($fullName)) {
                Log::debug('Returning fullName from uploader', ['name' => $fullName]);
                return $fullName;
            }
            
            // Fallback to name attribute
            if (!empty($uploader->name)) {
                Log::debug('Returning name attribute from uploader', ['name' => $uploader->name]);
                return $uploader->name;
            }
            
            // Fallback to ID if available
            $uploaderId = $uploader->id ?? null;
            if (!empty($uploaderId)) {
                Log::debug('Returning User#ID from uploader', ['id' => $uploaderId]);
                return "User #{$uploaderId}";
            }
        }

        // If we have an uploaded_by ID, try to look up the user
        if (!empty($uploadedById)) {
            Log::debug('No uploader model, but have ID - looking up user', ['id' => $uploadedById]);
            try {
                $user = \App\Models\User::on('sqlsrv')->find((int) $uploadedById);
                if ($user) {
                    Log::debug('User found by manual lookup', ['id' => $user->id]);
                    $firstName = !empty($user->first_name) ? trim($user->first_name) : '';
                    $lastName = !empty($user->last_name) ? trim($user->last_name) : '';
                    $fullName = trim("{$firstName} {$lastName}");
                    
                    if (!empty($fullName)) {
                        Log::debug('Returning fullName from manual lookup', ['name' => $fullName]);
                        return $fullName;
                    }
                    
                    if (!empty($user->name)) {
                        Log::debug('Returning name attr from manual lookup', ['name' => $user->name]);
                        return $user->name;
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Failed to lookup user by ID', [
                    'id' => $uploadedById,
                    'error' => $e->getMessage(),
                ]);
            }
            
            return "User #{$uploadedById}";
        }

        // Final DB-level fallback using the same scannings->users join used for manual checks.
        if (!empty($scanId)) {
            try {
                $joined = DB::connection('sqlsrv')
                    ->table('scannings as s')
                    ->leftJoin('users as u', 's.uploaded_by', '=', 'u.id')
                    ->where('s.id', (int) $scanId)
                    ->select([
                        's.uploaded_by as uploaded_by',
                        'u.first_name as first_name',
                        'u.last_name as last_name',
                    ])
                    ->first();

                if ($joined) {
                    $firstName = !empty($joined->first_name) ? trim($joined->first_name) : '';
                    $lastName = !empty($joined->last_name) ? trim($joined->last_name) : '';
                    $fullName = trim("{$firstName} {$lastName}");

                    if (!empty($fullName)) {
                        Log::debug('Returning fullName from DB join fallback', [
                            'scan_id' => $scanId,
                            'name' => $fullName,
                        ]);
                        return $fullName;
                    }

                    if (!empty($joined->uploaded_by)) {
                        Log::debug('Returning User#ID from DB join fallback', [
                            'scan_id' => $scanId,
                            'id' => $joined->uploaded_by,
                        ]);
                        return "User #{$joined->uploaded_by}";
                    }
                }
            } catch (Throwable $e) {
                Log::warning('DB join fallback for uploader failed', [
                    'scan_id' => $scanId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Ultimate fallback for records without uploader information
        Log::debug('Returning Not recorded - no uploader or ID found');
        return 'Not recorded';
    }

    /**
     * Determine whether an uploader label is a placeholder value.
     */
    protected function isPlaceholderUploader(?string $value): bool
    {
        $normalized = strtolower(trim((string) $value));

        return $normalized === ''
            || $normalized === 'not recorded'
            || $normalized === 'system'
            || $normalized === 'n/a'
            || $normalized === 'na';
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

    /**
     * Check/validate target file number for reassignment (preview destination)
     */
    public function reassignCheck(Request $request)
    {
        $validated = $request->validate([
            'target_file_number' => 'required|string|max:255',
        ]);

        try {
            $targetInfo = $this->scanReassignmentService->resolveTargetPath($validated['target_file_number']);

            return response()->json([
                'success' => true,
                'data' => [
                    'file_number' => $validated['target_file_number'],
                    'destination_type' => $targetInfo['type'],
                    'registry' => $targetInfo['registry'],
                    'file_indexing_id' => $targetInfo['file_indexing_id'],
                    'folder_exists' => $targetInfo['folder_exists'],
                    'existing_scan_count' => $targetInfo['existing_scan_count'],
                    'destination_path' => $targetInfo['relative_path'],
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Reassign check failed', [
                'error' => $exception->getMessage(),
                'target_file_number' => $validated['target_file_number'],
            ]);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 400);
        }
    }

    /**
     * Get the actual file number and registry for a scanned document
     * Uses the scanning table's file_indexing_id to find the indexed file
     */
    public function getScanFileInfo(Request $request)
    {
        $validated = $request->validate([
            'scan_id' => 'required|integer|exists:sqlsrv.scannings,id',
        ]);

        try {
            $scan = Scanning::findOrFail($validated['scan_id']);

            // If scan has a file_indexing_id, fetch the indexed file info
            if ($scan->file_indexing_id) {
                $fileIndexing = FileIndexing::findOrFail($scan->file_indexing_id);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'file_number' => $fileIndexing->file_number,
                        'registry' => $fileIndexing->registry ?? 'Unknown',
                        'file_indexing_id' => $scan->file_indexing_id,
                    ],
                ]);
            }

            // If no file_indexing_id, document is not yet indexed
            return response()->json([
                'success' => true,
                'data' => [
                    'file_number' => '',
                    'registry' => '',
                    'file_indexing_id' => null,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Get scan file info failed', [
                'error' => $exception->getMessage(),
                'scan_id' => $validated['scan_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve file information.',
            ], 400);
        }
    }

    /**
     * Check page-typing constraints for a set of scans.
     * Expects: { scan_ids: [int] }
     */
    public function reassignCheckConstraints(Request $request)
    {
        $validated = $request->validate([
            'scan_ids' => 'required|array|min:1',
            'scan_ids.*' => 'required|integer',
        ]);

        try {
            $scanIds = $validated['scan_ids'];

            $hasPageTyping = PageTyping::whereIn('scanning_id', $scanIds)->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'has_page_typing' => (bool) $hasPageTyping,
                ],
            ]);
        } catch (Throwable $ex) {
            Log::error('reassignCheckConstraints failed', ['error' => $ex->getMessage(), 'scan_ids' => $request->input('scan_ids')]);
            return response()->json(['success' => false, 'message' => 'Could not check constraints'], 500);
        }
    }

    /**
     * Reassign one or more scans to a correct file number
     */
    public function reassign(Request $request)
    {
        $validated = $request->validate([
            'scan_ids' => 'required|array|min:1',
            'scan_ids.*' => 'required|integer|exists:sqlsrv.scannings,id',
            'target_file_number' => 'required|string|max:255',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $results = $this->scanReassignmentService->reassignBatch(
                $validated['scan_ids'],
                $validated['target_file_number'],
                $validated['reason'] ?? null
            );

            $movedCount = count($results['success']);
            $failedCount = count($results['failed']);

            if ($movedCount === 0) {
                $errorDetail = '';
                if (!empty($results['failed'])) {
                    $errorDetail = $results['failed'][0]['error'] ?? '';
                }
                return response()->json([
                    'success' => false,
                    'message' => $errorDetail ?: 'No documents were reassigned.',
                    'data' => $results,
                ], 400);
            }

            $message = "{$movedCount} document(s) reassigned to {$validated['target_file_number']}.";
            if ($failedCount > 0) {
                $message .= " ({$failedCount} failed)";
            }

            $documents = collect($results['success'])
                ->map(function ($result) {
                    return $this->formatDocumentPayload($result['scan']);
                })
                ->values();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'moved_count' => $movedCount,
                    'failed_count' => $failedCount,
                    'destination_type' => $results['success'][0]['to']['destination_type'] ?? null,
                    'documents' => $documents,
                    'failed_scans' => $results['failed'],
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Scan reassignment failed', [
                'error' => $exception->getMessage(),
                'scan_ids' => $validated['scan_ids'],
                'target_file_number' => $validated['target_file_number'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to reassign documents: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Search file numbers for select2 dropdown
     */
    public function searchFileNumbers(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'registry' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:50',
        ]);

        try {
            $search = $validated['search'] ?? '';
            $registry = $validated['registry'] ?? null;
            $perPage = $validated['per_page'] ?? 25;

            // Search file_indexings for matching file numbers
            $query = FileIndexing::on('sqlsrv');
            
            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('file_number', 'like', '%' . $search . '%')
                      ->orWhere('file_title', 'like', '%' . $search . '%');
                });
            }
            
            // Apply registry filter if provided
            if ($registry) {
                $query->where('registry', $registry);
            }
            
            $files = $query
                ->select(
                    'file_number', 'file_title', 'registry', 'tracking_id',
                    'district', 'lga', 'street_name', 'plot_number', 'plot_size',
                    'location', 'prop_id', 'latitude', 'longitude', 'rc_no'
                )
                ->orderByDesc('created_at')
                ->limit($perPage)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $files->map(function ($file) {
                    return [
                        'file_number' => $file->file_number,
                        'file_title' => $file->file_title ?? '',
                        'registry' => $file->registry ?? '',
                        'tracking_id' => $file->tracking_id ?? '',
                        'district' => $file->district ?? '',
                        'lga' => $file->lga ?? '',
                        'street_name' => $file->street_name ?? '',
                        'plot_number' => $file->plot_number ?? '',
                        'plot_size' => $file->plot_size ?? '',
                        'location' => $file->location ?? '',
                        'prop_id' => $file->prop_id ?? '',
                        'latitude' => $file->latitude ?? '',
                        'longitude' => $file->longitude ?? '',
                        'rc_no' => $file->rc_no ?? '',
                    ];
                })->toArray(),
            ]);
        } catch (Throwable $exception) {
            Log::error('File number search failed', [
                'error' => $exception->getMessage(),
                'search' => $validated['search'] ?? '',
                'registry' => $validated['registry'] ?? '',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error searching file numbers: ' . $exception->getMessage(),
                'data' => [],
            ], 500);
        }
    }
}
