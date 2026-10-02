<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FileIndexing;
use App\Models\Scanning;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnindexedScanningController extends Controller
{
    public function index()
    {
        try {
            // Get real statistics from the database
            $stats = [
                'uploads_today' => $this->getUploadsTodayCount(),
                'pending_indexing' => $this->getPendingIndexingCount(),
                'total_unindexed' => $this->getTotalUnindexedCount()
            ];

            // Get recent uploads (files created from unindexed uploads)
            $recentUploads = FileIndexing::on('sqlsrv')
                ->with(['scannings', 'uploader'])
                ->where(function($query) {
                    $query->where('file_number', 'like', 'AUTO-%')
                          ->orWhere('file_number', 'like', 'TEMP-%');
                })
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function($file) {
                    return [
                        'id' => $file->id,
                        'file_number' => $file->file_number,
                        'file_title' => $file->file_title,
                        'scanned_documents' => $file->scannings->count(),
                        'created_at' => $file->created_at->format('M d, Y H:i'),
                        'status' => $file->scannings->count() > 0 ? 'Scanned' : 'Indexed Only'
                    ];
                });

            return view('scanning.unindexed', compact('stats', 'recentUploads'));
        } catch (\Exception $e) {
            Log::error('Error loading unindexed scanning interface', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            // Fallback with default values
            $stats = [
                'uploads_today' => 0,
                'pending_indexing' => 0,
                'total_unindexed' => 0
            ];
            $recentUploads = collect([]);
            
            return view('scanning.unindexed', compact('stats', 'recentUploads'));
        }
    }

    /**
     * Get uploads today count
     */
    private function getUploadsTodayCount()
    {
        try {
            return FileIndexing::on('sqlsrv')
                ->where(function($query) {
                    $query->where('file_number', 'like', 'AUTO-%')
                          ->orWhere('file_number', 'like', 'TEMP-%');
                })
                ->whereDate('created_at', today())
                ->count();
        } catch (\Exception $e) {
            Log::warning('Could not get uploads today count', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get pending indexing count
     */
    private function getPendingIndexingCount()
    {
        try {
            // Files that are temporary but haven't been fully processed
            return FileIndexing::on('sqlsrv')
                ->where('file_number', 'like', 'TEMP-%')
                ->whereDoesntHave('scannings')
                ->count();
        } catch (\Exception $e) {
            Log::warning('Could not get pending indexing count', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get total unindexed count
     */
    private function getTotalUnindexedCount()
    {
        try {
            return FileIndexing::on('sqlsrv')
                ->where(function($query) {
                    $query->where('file_number', 'like', 'AUTO-%')
                          ->orWhere('file_number', 'like', 'TEMP-%');
                })
                ->count();
        } catch (\Exception $e) {
            Log::warning('Could not get total unindexed count', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Process OCR for uploaded files
     */
    public function processOcr(Request $request)
    {
        try {
            $files = $request->input('files', []);
            $results = [];

            foreach ($files as $file) {
                // This would integrate with actual OCR processing
                // For now, return mock results that match the expected format
                $results[] = [
                    'file' => $file,
                    'extractedText' => 'Sample extracted text from ' . $file['name'],
                    'metadata' => [
                        'extractedFileNumber' => 'AUTO-' . time(),
                        'detectedOwner' => 'Sample Owner Name',
                        'plotNumber' => 'Plot-' . rand(100, 999),
                        'landUseType' => 'Residential',
                        'district' => 'Sample District',
                        'documentType' => 'Certificate of Occupancy'
                    ]
                ];
            }

            return response()->json([
                'success' => true,
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('OCR processing failed', [
                'error' => $e->getMessage(),
                'files' => $request->input('files', [])
            ]);

            return response()->json([
                'success' => false,
                'message' => 'OCR processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create indexing entry from unindexed file
     */
    public function createIndexingEntry(Request $request)
    {
        try {
            $fileData = $request->input('file');
            $metadata = $request->input('metadata', []);

            // Create file indexing record
            $fileIndexing = FileIndexing::on('sqlsrv')->create([
                'file_number' => $metadata['extractedFileNumber'] ?? 'AUTO-' . time(),
                'file_title' => $metadata['detectedOwner'] ?? $fileData['name'],
                'plot_number' => $metadata['plotNumber'] ?? '',
                'land_use_type' => $metadata['landUseType'] ?? 'Unknown',
                'district' => $metadata['district'] ?? 'Unknown',
                'lga' => $metadata['district'] ?? 'Unknown',
                'has_cofo' => false,
                'is_merged' => false,
                'has_transaction' => true,
                'is_problematic' => false,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'file_indexing' => [
                    'id' => $fileIndexing->id,
                    'file_number' => $fileIndexing->file_number,
                    'file_title' => $fileIndexing->file_title
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create indexing entry', [
                'error' => $e->getMessage(),
                'file_data' => $request->input('file'),
                'metadata' => $request->input('metadata')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create indexing entry: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unindexed files list
     */
    public function getUnindexedFiles(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $status = $request->get('status', 'all');

            $query = FileIndexing::on('sqlsrv')
                ->with(['scannings', 'uploader'])
                ->where(function($q) {
                    $q->where('file_number', 'like', 'AUTO-%')
                      ->orWhere('file_number', 'like', 'TEMP-%');
                });

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('file_number', 'like', "%{$search}%")
                      ->orWhere('file_title', 'like', "%{$search}%");
                });
            }

            if ($status !== 'all') {
                if ($status === 'indexed_only') {
                    $query->whereDoesntHave('scannings');
                } elseif ($status === 'scanned') {
                    $query->whereHas('scannings');
                }
            }

            $files = $query->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'files' => $files->items(),
                'pagination' => [
                    'current_page' => $files->currentPage(),
                    'total_pages' => $files->lastPage(),
                    'total_items' => $files->total(),
                    'per_page' => $files->perPage()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get unindexed files', [
                'error' => $e->getMessage(),
                'search' => $request->get('search'),
                'status' => $request->get('status')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an unindexed file
     */
    public function deleteUnindexedFile($id)
    {
        try {
            $fileIndexing = FileIndexing::on('sqlsrv')->findOrFail($id);
            
            // Check if it's actually an unindexed file
            if (!str_starts_with($fileIndexing->file_number, 'AUTO-') && 
                !str_starts_with($fileIndexing->file_number, 'TEMP-')) {
                return response()->json([
                    'success' => false,
                    'message' => 'This is not an unindexed file and cannot be deleted.'
                ], 403);
            }

            // Delete associated scanning records and files
            if ($fileIndexing->scannings) {
                foreach ($fileIndexing->scannings as $scanning) {
                    // Delete file from storage
                    if (Storage::disk('public')->exists($scanning->document_path)) {
                        Storage::disk('public')->delete($scanning->document_path);
                    }
                    $scanning->delete();
                }
            }

            // Delete the file indexing record
            $fileIndexing->delete();

            Log::info('Unindexed file deleted', [
                'file_indexing_id' => $id,
                'file_number' => $fileIndexing->file_number,
                'deleted_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete unindexed file', [
                'file_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper methods
    private function formatFileSize($bytes)
    {
        if ($bytes === 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}