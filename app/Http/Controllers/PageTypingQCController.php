<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\FileIndexing;
use App\Models\Scanning;
use App\Models\PageTyping;
use App\Models\Thumbnail;

class PageTypingController extends Controller
{
    /**
     * Display the page typing dashboard or typing interface
     */
    public function index(Request $request)
    {
        try {
            $PageTitle = 'Page Typing';
            $PageDescription = 'Categorize and digitize file content';
            
            // Get file_indexing_id from request if provided
            $fileIndexingId = $request->get('file_indexing_id');
            $selectedFileIndexing = null;
            
            if ($fileIndexingId) {
                // Load the page typing interface for specific file
                $selectedFileIndexing = FileIndexing::on('sqlsrv')
                    ->with(['mainApplication', 'scannings', 'pagetypings'])
                    ->find($fileIndexingId);
                
                if (!$selectedFileIndexing) {
                    return redirect()->route('pagetyping.index')
                        ->with('error', 'File not found');
                }
                
                // Check if file has scannings
                if ($selectedFileIndexing->scannings->isEmpty()) {
                    return redirect()->route('scanning.index', ['file_indexing_id' => $fileIndexingId])
                        ->with('error', 'Please upload scanned documents first before page typing');
                }
                
                // Update the page title to reflect we're in typing mode
                $PageTitle = 'Page Typing - ' . $selectedFileIndexing->file_title;
                $PageDescription = 'Classify and label document pages';
            }
            
            // Load dashboard data
            $stats = [
                'pending_count' => $this->getPendingPageTypingCount(),
                'in_progress_count' => $this->getInProgressPageTypingCount(),
                'completed_count' => $this->getCompletedPageTypingCount(),
                'pagetype_more_count' => $this->getPageTypeMoreCount(),
            ];

            // Load pending files (files with scannings but no page typings)
            $pendingFiles = $this->getPendingFiles();

            // Load in-progress files (files with some page typings but not all pages typed)
            $inProgressFiles = $this->getInProgressFiles();

            // Load completed files (files where all pages are typed)
            $completedFiles = $this->getCompletedFiles();

            // Return the dashboard view with selectedFileIndexing if provided
            return view('pagetyping.index', compact(
                'PageTitle', 
                'PageDescription', 
                'stats', 
                'pendingFiles', 
                'inProgressFiles', 
                'completedFiles',
                'selectedFileIndexing'
            ));
        } catch (Exception $e) {
            Log::error('Error loading page typing dashboard', [
                'error' => $e->getMessage()
            ]);
            
            return view('pagetyping.index', [
                'PageTitle' => 'Page Typing',
                'PageDescription' => 'Categorize and digitize file content',
                'stats' => [
                    'pending_count' => 0,
                    'in_progress_count' => 0,
                    'completed_count' => 0,
                    'pagetype_more_count' => 0,
                ],
                'pendingFiles' => collect(),
                'inProgressFiles' => collect(),
                'completedFiles' => collect(),
                'selectedFileIndexing' => null
            ]);
        }
    }

    /**
     * Get dashboard statistics (AJAX)
     */
    public function getStats(Request $request)
    {
        try {
            $stats = [
                'pending_count' => $this->getPendingPageTypingCount(),
                'in_progress_count' => $this->getInProgressPageTypingCount(),
                'completed_count' => $this->getCompletedPageTypingCount(),
                'pagetype_more_count' => $this->getPageTypeMoreCount(),
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            Log::error('Error getting page typing stats', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading statistics',
                'stats' => [
                    'pending_count' => 0, 
                    'in_progress_count' => 0, 
                    'completed_count' => 0,
                    'pagetype_more_count' => 0
                ]
            ], 500);
        }
    }

    /**
     * Get files by status (AJAX)
     */
    public function getFilesByStatus(Request $request)
    {
        try {
            $status = $request->get('status', 'pending');
            $search = $request->get('search', '');
            $limit = $request->get('limit', 10);
            $page = $request->get('page', 1);

            switch ($status) {
                case 'pending':
                    $result = $this->getPendingFilesPaginated($search, $limit, $page);
                    break;
                case 'in_progress':
                    $result = $this->getInProgressFilesPaginated($search, $limit, $page);
                    break;
                case 'completed':
                    $result = $this->getCompletedFilesPaginated($search, $limit, $page);
                    break;
                default:
                    $result = [
                        'files' => collect(),
                        'total' => 0,
                        'current_page' => $page,
                        'last_page' => 1,
                        'per_page' => $limit
                    ];
            }

            $formattedFiles = $result['files']->values()->map(function ($file) {
                $scanningsCount = $file->scannings->count();
                $pageTypingsCount = $file->pagetypings->count();

                // Calculate progress for in-progress files
                $progress = $scanningsCount > 0 ? ($pageTypingsCount / $scanningsCount) * 100 : 0;

                return [
                    'id' => $file->id,
                    'file_number' => $file->file_number,
                    'file_title' => $file->file_title,
                    'district' => $file->district,
                    'lga' => $file->lga,
                    'scannings_count' => $scanningsCount,
                    'page_typings_count' => $pageTypingsCount,
                    'progress' => round($progress, 1),
                    'created_at' => $file->created_at ? $file->created_at->format('M d, Y') : 'Unknown',
                    'updated_at' => $file->updated_at ? $file->updated_at->format('M d, Y H:i') : 'Unknown',
                    'status' => $file->status,
                    'main_application' => $file->mainApplication ? [
                        'id' => $file->mainApplication->id,
                        'applicant_name' => $file->mainApplication->applicant_name ?? 'Unknown'
                    ] : null
                ];
            });

            return response()->json([
                'success' => true,
                'files' => $formattedFiles,
                'pagination' => [
                    'total' => $result['total'],
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                    'per_page' => $result['per_page'],
                    'from' => ($result['current_page'] - 1) * $result['per_page'] + 1,
                    'to' => min($result['current_page'] * $result['per_page'], $result['total'])
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error getting files by status', [
                'status' => $status,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading files',
                'files' => [],
                'pagination' => [
                    'total' => 0,
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $limit,
                    'from' => 0,
                    'to' => 0
                ]
            ], 500);
        }
    }

    /**
     * Get pending files (files with scannings but no page typings)
     */
    private function getPendingFiles($search = '', $limit = 10)
    {
        try {
            $query = FileIndexing::on('sqlsrv')
                ->with(['mainApplication', 'scannings', 'pagetypings'])
                ->whereHas('scannings')
                ->whereDoesntHave('pagetypings');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('file_number', 'like', "%{$search}%")
                      ->orWhere('file_title', 'like', "%{$search}%")
                      ->orWhere('district', 'like', "%{$search}%")
                      ->orWhere('lga', 'like', "%{$search}%");
                });
            }

            return $query->orderBy('created_at', 'desc')
                         ->limit($limit)
                         ->get();
        } catch (Exception $e) {
            Log::error('Error getting pending files', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Get pending files (files with scannings but no page typings) - Paginated
     */
    private function getPendingFilesPaginated($search = '', $limit = 10, $page = 1)
    {
        try {
            $query = FileIndexing::on('sqlsrv')
                ->with(['mainApplication', 'scannings', 'pagetypings'])
                ->whereHas('scannings')
                ->whereDoesntHave('pagetypings');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('file_number', 'like', "%{$search}%")
                      ->orWhere('file_title', 'like', "%{$search}%")
                      ->orWhere('district', 'like', "%{$search}%")
                      ->orWhere('lga', 'like', "%{$search}%");
                });
            }

            $total = $query->count();
            $files = $query->orderBy('created_at', 'desc')
                         ->skip(($page - 1) * $limit)
                         ->take($limit)
                         ->get();

            return [
                'files' => $files,
                'total' => $total,
                'current_page' => $page,
                'last_page' => ceil($total / $limit),
                'per_page' => $limit
            ];
        } catch (Exception $e) {
            Log::error('Error getting pending files paginated', ['error' => $e->getMessage()]);
            return [
                'files' => collect(),
                'total' => 0,
                'current_page' => $page,
                'last_page' => 1,
                'per_page' => $limit
            ];
        }
    }

    /**
     * Get in-progress files (files with some page typings but not all pages typed) - Paginated
     */
    private function getInProgressFilesPaginated($search = '', $limit = 10, $page = 1)
    {
        try {
            $query = FileIndexing::on('sqlsrv')
                ->with(['mainApplication', 'scannings', 'pagetypings'])
                ->whereHas('scannings')
                ->whereHas('pagetypings');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('file_number', 'like', "%{$search}%")
                      ->orWhere('file_title', 'like', "%{$search}%")
                      ->orWhere('district', 'like', "%{$search}%")
                      ->orWhere('lga', 'like', "%{$search}%");
                });
            }

            // Get all potential files first - increase limit to ensure we have enough to filter
            $allFiles = $query->orderBy('updated_at', 'desc')->get();

            // Filter to only include files where not all pages are typed
            $filteredFiles = $allFiles->filter(function ($file) {
                $totalPages = $file->scannings->count();
                $typedPages = $file->pagetypings->count();
                return $typedPages > 0 && $typedPages < $totalPages;
            });

            $total = $filteredFiles->count();
            
            // Apply pagination to the filtered results
            $offset = ($page - 1) * $limit;
            $files = $filteredFiles->slice($offset, $limit);

            return [
                'files' => $files,
                'total' => $total,
                'current_page' => $page,
                'last_page' => ceil($total / $limit),
                'per_page' => $limit
            ];
        } catch (Exception $e) {
            Log::error('Error getting in-progress files paginated', ['error' => $e->getMessage()]);
            return [
                'files' => collect(),
                'total' => 0,
                'current_page' => $page,
                'last_page' => 1,
                'per_page' => $limit
            ];
        }
    }

    /**
     * Get completed files (files where all pages are typed) - Paginated
     */
    private function getCompletedFilesPaginated($search = '', $limit = 10, $page = 1)
    {
        try {
            $query = FileIndexing::on('sqlsrv')
                ->with(['mainApplication', 'scannings', 'pagetypings.typedBy'])
                ->whereHas('scannings')
                ->whereHas('pagetypings');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('file_number', 'like', "%{$search}%")
                      ->orWhere('file_title', 'like', "%{$search}%")
                      ->orWhere('district', 'like', "%{$search}%")
                      ->orWhere('lga', 'like', "%{$search}%");
                });
            }

            // Get all potential files first
            $allFiles = $query->orderBy('updated_at', 'desc')->get();

            // Filter to only include files where all pages are typed
            $filteredFiles = $allFiles->filter(function ($file) {
                $totalPages = $file->scannings->count();
                $typedPages = $file->pagetypings->count();
                return $totalPages > 0 && $typedPages >= $totalPages;
            });

            $total = $filteredFiles->count();
            
            // Apply pagination to the filtered results
            $offset = ($page - 1) * $limit;
            $files = $filteredFiles->slice($offset, $limit);

            return [
                'files' => $files,
                'total' => $total,
                'current_page' => $page,
                'last_page' => ceil($total / $limit),
                'per_page' => $limit
            ];
        } catch (Exception $e) {
            Log::error('Error getting completed files paginated', ['error' => $e->getMessage()]);
            return [
                'files' => collect(),
                'total' => 0,
                'current_page' => $page,
                'last_page' => 1,
                'per_page' => $limit
            ];
        }
    }

    /**
     * Get in-progress files (files with some page typings but not all pages typed)
     */
    private function getInProgressFiles($search = '', $limit = 10)
    {
        try {
            $query = FileIndexing::on('sqlsrv')
                ->with(['mainApplication', 'scannings', 'pagetypings'])
                ->whereHas('scannings')
                ->whereHas('pagetypings');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('file_number', 'like', "%{$search}%")
                      ->orWhere('file_title', 'like', "%{$search}%")
                      ->orWhere('district', 'like', "%{$search}%")
                      ->orWhere('lga', 'like', "%{$search}%");
                });
            }

            $files = $query->orderBy('updated_at', 'desc')
                          ->limit($limit * 2) // Get more to filter
                          ->get();

            // Filter to only include files where not all pages are typed
            $inProgressFiles = $files->filter(function ($file) {
                $totalPages = $file->scannings->count();
                $typedPages = $file->pagetypings->count();
                return $typedPages > 0 && $typedPages < $totalPages;
            })->take($limit);

            return $inProgressFiles;
        } catch (Exception $e) {
            Log::error('Error getting in-progress files', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Get completed files (files where all pages are typed)
     */
    private function getCompletedFiles($search = '', $limit = 10)
    {
        try {
            $query = FileIndexing::on('sqlsrv')
                ->with(['mainApplication', 'scannings', 'pagetypings.typedBy'])
                ->whereHas('scannings')
                ->whereHas('pagetypings');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('file_number', 'like', "%{$search}%")
                      ->orWhere('file_title', 'like', "%{$search}%")
                      ->orWhere('district', 'like', "%{$search}%")
                      ->orWhere('lga', 'like', "%{$search}%");
                });
            }

            $files = $query->orderBy('updated_at', 'desc')
                          ->limit($limit * 2) // Get more to filter
                          ->get();

            // Filter to only include files where all pages are typed
            $completedFiles = $files->filter(function ($file) {
                $totalPages = $file->scannings->count();
                $typedPages = $file->pagetypings->count();
                return $totalPages > 0 && $typedPages >= $totalPages;
            })->take($limit);

            return $completedFiles;
        } catch (Exception $e) {
            Log::error('Error getting completed files', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Get pending page typing count
     */
    private function getPendingPageTypingCount()
    {
        try {
            return FileIndexing::on('sqlsrv')
                ->whereHas('scannings')
                ->whereDoesntHave('pagetypings')
                ->count();
        } catch (Exception $e) {
            Log::error('Error getting pending page typing count', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get in-progress page typing count
     */
    private function getInProgressPageTypingCount()
    {
        try {
            $inProgressCount = 0;
            
            $filesWithBoth = FileIndexing::on('sqlsrv')
                ->whereHas('scannings')
                ->whereHas('pagetypings')
                ->with(['scannings', 'pagetypings'])
                ->get();
            
            foreach ($filesWithBoth as $file) {
                $totalPages = $file->scannings->count();
                $typedPages = $file->pagetypings->count();
                
                if ($typedPages > 0 && $typedPages < $totalPages) {
                    $inProgressCount++;
                }
            }
            
            return $inProgressCount;
        } catch (Exception $e) {
            Log::error('Error getting in-progress page typing count', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get completed page typing count
     */
    private function getCompletedPageTypingCount()
    {
        try {
            $completedCount = 0;
            
            $filesWithBoth = FileIndexing::on('sqlsrv')
                ->whereHas('scannings')
                ->whereHas('pagetypings')
                ->with(['scannings', 'pagetypings'])
                ->get();
            
            foreach ($filesWithBoth as $file) {
                $totalPages = $file->scannings->count();
                $typedPages = $file->pagetypings->count();
                
                if ($typedPages >= $totalPages && $totalPages > 0) {
                    $completedCount++;
                }
            }
            
            return $completedCount;
        } catch (Exception $e) {
            Log::error('Error getting completed page typing count', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get PageType More count (files with is_updated = 1)
     */
    private function getPageTypeMoreCount()
    {
        try {
            // Check if is_updated column exists
            $hasIsUpdatedColumn = DB::connection('sqlsrv')
                ->getSchemaBuilder()
                ->hasColumn('file_indexings', 'is_updated');
            
            if ($hasIsUpdatedColumn) {
                return FileIndexing::on('sqlsrv')
                    ->whereHas('pagetypings') // Must have existing page typings
                    ->where('is_updated', 1) // And be marked as updated
                    ->count();
            }
            
            return 0; // Column doesn't exist yet
        } catch (Exception $e) {
            Log::warning('Could not get PageType More count (is_updated column may be missing)', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get files for PageType More (AJAX)
     */
    public function getPageTypeMoreFiles(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $limit = $request->get('limit', 10);
            $page = $request->get('page', 1);

            // Check if is_updated column exists
            $hasIsUpdatedColumn = DB::connection('sqlsrv')
                ->getSchemaBuilder()
                ->hasColumn('file_indexings', 'is_updated');
            
            if (!$hasIsUpdatedColumn) {
                return response()->json([
                    'success' => true,
                    'files' => [],
                    'message' => 'is_updated column not found. Please run the database migration script.'
                ]);
            }

            $query = FileIndexing::on('sqlsrv')
                ->with(['mainApplication', 'scannings', 'pagetypings'])
                ->whereHas('pagetypings') // Must have existing page typings
                ->where('is_updated', 1); // And be marked as updated

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('file_number', 'like', "%{$search}%")
                      ->orWhere('file_title', 'like', "%{$search}%")
                      ->orWhere('district', 'like', "%{$search}%")
                      ->orWhere('lga', 'like', "%{$search}%");
                });
            }

            $files = $query->orderBy('updated_at', 'desc')
                          ->skip(($page - 1) * $limit)
                          ->take($limit)
                          ->get();

            $total = $query->count();

            $formattedFiles = $files->map(function ($file) {
                $scanningsCount = $file->scannings->count();
                $pageTypingsCount = $file->pagetypings->count();
                
                // Calculate existing vs new pages
                $existingPages = $pageTypingsCount;
                $newScans = max(0, $scanningsCount - $pageTypingsCount);
                $totalPages = $scanningsCount;

                return [
                    'id' => $file->id,
                    'file_number' => $file->file_number,
                    'file_title' => $file->file_title,
                    'district' => $file->district,
                    'lga' => $file->lga,
                    'existing_pages' => $existingPages,
                    'new_scans' => $newScans,
                    'total_pages' => $totalPages,
                    'last_updated' => $file->updated_at ? $file->updated_at->format('M d, Y') : 'Unknown',
                    'status' => 'Updated',
                    'is_updated' => $file->is_updated ?? false,
                    'main_application' => $file->mainApplication ? [
                        'id' => $file->mainApplication->id,
                        'applicant_name' => $file->mainApplication->applicant_name ?? 'Unknown'
                    ] : null
                ];
            });

            return response()->json([
                'success' => true,
                'files' => $formattedFiles,
                'pagination' => [
                    'total' => $total,
                    'current_page' => $page,
                    'last_page' => ceil($total / $limit),
                    'per_page' => $limit,
                    'from' => ($page - 1) * $limit + 1,
                    'to' => min($page * $limit, $total)
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error getting PageType More files', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading PageType More files: ' . $e->getMessage(),
                'files' => []
            ], 500);
        }
    }

    /**
     * Save single page typing (AJAX)
     */
    public function saveSingle(Request $request)
    {
        try {
            // Debug logging
            \Log::info('PageTyping saveSingle called with data:', $request->all());
            
            $validator = Validator::make($request->all(), [
                'file_indexing_id' => 'required|integer|exists:sqlsrv.file_indexings,id',
                'scanning_id' => 'required|integer|exists:sqlsrv.scannings,id',
                'page_number' => 'required|integer|min:1',
                'cover_type_id' => 'required|integer',
                'page_type' => 'required|string|max:100',
                'page_subtype' => 'nullable|string|max:100',
                'serial_number' => 'required', // Accept both integer and string for booklet mode
                'page_code' => 'nullable|string|max:100',
                'file_path' => 'required|string|max:255',
                // Booklet management fields
                'booklet_id' => 'nullable|string|max:50',
                'is_booklet_page' => 'nullable|boolean',
                'booklet_sequence' => 'nullable|string|max:5',
            ]);

            if ($validator->fails()) {
                \Log::error('PageTyping validation failed:', [
                    'errors' => $validator->errors(),
                    'data' => $request->all()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            \Log::info('PageTyping validation passed:', $validated);

            // Check if page typing already exists
            $existingPageTyping = PageTyping::on('sqlsrv')
                ->where('file_indexing_id', $validated['file_indexing_id'])
                ->where('file_path', $validated['file_path'])
                ->where('page_number', $validated['page_number'])
                ->first();

            if ($existingPageTyping) {
                // Update existing record
                $existingPageTyping->update([
                    'page_type' => $validated['page_type'],
                    'page_subtype' => $validated['page_subtype'],
                    'serial_number' => $validated['serial_number'],
                    'page_code' => $validated['page_code'],
                    'typed_by' => Auth::id(),
                    // Booklet management fields
                    'booklet_id' => $validated['booklet_id'] ?? null,
                    'is_booklet_page' => $validated['is_booklet_page'] ?? false,
                    'booklet_sequence' => $validated['booklet_sequence'] ?? null,
                ]);
                $pageTyping = $existingPageTyping;
            } else {
                // Create new record
                $pageTyping = PageTyping::on('sqlsrv')->create(array_merge($validated, [
                    'typed_by' => Auth::id(),
                    // Ensure booklet fields have defaults if not provided
                    'booklet_id' => $validated['booklet_id'] ?? null,
                    'is_booklet_page' => $validated['is_booklet_page'] ?? false,
                    'booklet_sequence' => $validated['booklet_sequence'] ?? null,
                ]));
            }

            // Handle file copying to the new locations
            try {
                // Get the original file path from scanning record
                $scanning = \DB::connection('sqlsrv')->table('scannings')->find($validated['scanning_id']);

                if ($scanning && $scanning->document_path) {
                    // Get file indexing record to get file number
                    $fileIndexing = FileIndexing::on('sqlsrv')->find($validated['file_indexing_id']);

                    if ($fileIndexing && $fileIndexing->file_number) {
                        $fileNumber = $fileIndexing->file_number;
                        $originalPath = file_storage_path('app/public/' . $scanning->document_path);

                        // Get file extension from original file
                        $fileExtension = pathinfo($scanning->original_filename, PATHINFO_EXTENSION);
                        
                        // Handle case where file has no extension
                        if (empty($fileExtension)) {
                            // Try to get extension from document_path as fallback
                            $pathExtension = pathinfo($scanning->document_path, PATHINFO_EXTENSION);
                            $fileExtension = !empty($pathExtension) ? $pathExtension : 'pdf'; // default to pdf
                        }
                        
                        $fileName = $fileNumber . '.' . strtolower($fileExtension);
                        
                        // Validate the constructed filename
                        if (substr($fileName, -1) === '.') {
                            Log::error('Invalid filename constructed - missing extension', [
                                'file_number' => $fileNumber,
                                'original_filename' => $scanning->original_filename ?? 'N/A',
                                'document_path' => $scanning->document_path ?? 'N/A',
                                'extracted_extension' => $fileExtension,
                                'constructed_filename' => $fileName
                            ]);
                            throw new \Exception('Invalid filename: missing file extension for file ' . $fileNumber);
                        }

                        // Define the two target locations
                        $pagetypingPath = 'EDMS/PAGETYPING/' . $fileNumber . '/' . $fileName;
                        $archivePath = 'EDMS/ARCHIVE_Doc_WARE/' . $fileNumber . '/' . $fileName;

                        $pagetypingFullPath = file_storage_path('app/public/' . $pagetypingPath);
                        $archiveFullPath = file_storage_path('app/public/' . $archivePath);

                        // Create directories if they don't exist
                        $pagetypingDir = dirname($pagetypingFullPath);
                        $archiveDir = dirname($archiveFullPath);

                        if (!file_exists($pagetypingDir)) {
                            mkdir($pagetypingDir, 0755, true);
                        }

                        if (!file_exists($archiveDir)) {
                            mkdir($archiveDir, 0755, true);
                        }

                        // Copy file to PAGETYPING location
                        if (file_exists($originalPath) && !file_exists($pagetypingFullPath)) {
                            if (!copy($originalPath, $pagetypingFullPath)) {
                                $error = error_get_last();
                                Log::error('Failed to copy file to PAGETYPING location', [
                                    'original' => $originalPath,
                                    'target' => $pagetypingFullPath,
                                    'file_number' => $fileNumber,
                                    'original_filename' => $scanning->original_filename ?? 'N/A',
                                    'document_path' => $scanning->document_path ?? 'N/A',
                                    'file_extension' => $fileExtension,
                                    'constructed_filename' => $fileName,
                                    'error' => $error['message'] ?? 'Unknown error'
                                ]);
                            } else {
                                Log::info('File copied to PAGETYPING location', [
                                    'file_number' => $fileNumber,
                                    'path' => $pagetypingPath
                                ]);
                            }
                        } else {
                            if (!file_exists($originalPath)) {
                                Log::warning('Original file does not exist', [
                                    'original_path' => $originalPath,
                                    'file_number' => $fileNumber
                                ]);
                            }
                            if (file_exists($pagetypingFullPath)) {
                                Log::info('File already exists at PAGETYPING location', [
                                    'target' => $pagetypingFullPath,
                                    'file_number' => $fileNumber
                                ]);
                            }
                        }

                        // Copy file to ARCHIVE location
                        if (file_exists($originalPath) && !file_exists($archiveFullPath)) {
                            if (!copy($originalPath, $archiveFullPath)) {
                                Log::warning('Failed to copy file to ARCHIVE location', [
                                    'original' => $originalPath,
                                    'target' => $archiveFullPath
                                ]);
                            } else {
                                Log::info('File copied to ARCHIVE location', [
                                    'file_number' => $fileNumber,
                                    'path' => $archivePath
                                ]);
                            }
                        }

                        // Update the page typing record with the new file path
                        $pageTyping->update([
                            'file_path' => $pagetypingPath
                        ]);
                    } else {
                        Log::warning('File indexing record not found or missing file number', [
                            'file_indexing_id' => $validated['file_indexing_id']
                        ]);
                    }
                }
            } catch (Exception $fileException) {
                Log::error('Error handling file copy in page typing save', [
                    'error' => $fileException->getMessage(),
                    'file_indexing_id' => $validated['file_indexing_id'],
                    'scanning_id' => $validated['scanning_id']
                ]);
                // Don't fail the entire operation if file copy fails
            }

            return response()->json([
                'success' => true,
                'message' => 'Page classification saved successfully!',
                'page_typing_id' => $pageTyping->id
            ]);

        } catch (Exception $e) {
            Log::error('Error saving single page typing', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error saving page classification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get page typings for a file indexing (AJAX)
     */
    public function getPageTypings(Request $request)
    {
        try {
            $fileIndexingId = $request->get('file_indexing_id');
            $search = $request->get('search', '');

            if (!$fileIndexingId) {
                return response()->json([
                    'success' => false,
                    'message' => 'File indexing ID is required'
                ], 400);
            }

            $query = PageTyping::on('sqlsrv')
                ->with(['fileIndexing', 'typedBy'])
                ->where('file_indexing_id', $fileIndexingId);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('page_type', 'like', "%{$search}%")
                        ->orWhere('page_subtype', 'like', "%{$search}%")
                        ->orWhere('page_code', 'like', "%{$search}%");
                });
            }

            $pageTypings = $query->orderBy('serial_number')
                ->orderBy('page_number')
                ->limit(100)
                ->get();

            return response()->json([
                'success' => true,
                'page_typings' => $pageTypings->map(function ($pt) {
                    return [
                        'id' => $pt->id,
                        'page_number' => $pt->page_number,
                        'page_type' => $pt->page_type,
                        'page_subtype' => $pt->page_subtype,
                        'serial_number' => $pt->serial_number,
                        'page_code' => $pt->page_code,
                        'file_path' => $pt->file_path,
                        'scanning_id' => $pt->scanning_id,
                        'file_indexing' => $pt->fileIndexing ? [
                            'id' => $pt->fileIndexing->id,
                            'file_number' => $pt->fileIndexing->file_number,
                            'file_title' => $pt->fileIndexing->file_title,
                        ] : null,
                        'typed_by' => $pt->typedBy ? $pt->typedBy->name : 'Unknown',
                        'created_at' => $pt->created_at ? $pt->created_at->format('M d, Y H:i') : 'Unknown',
                    ];
                })
            ]);

        } catch (Exception $e) {
            Log::error('Error getting page typings', [
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading page typings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file details with scannings for typing interface (AJAX)
     */
    public function getFileDetails(Request $request)
    {
        try {
            $fileIndexingId = $request->get('file_indexing_id');
            $pageTypeId = $request->get('page_type_id'); // Optional page type for existing files
            
            if (!$fileIndexingId) {
                return response()->json([
                    'success' => false,
                    'message' => 'File indexing ID is required'
                ], 400);
            }

            $fileIndexing = FileIndexing::on('sqlsrv')
                ->with(['mainApplication', 'scannings', 'pagetypings'])
                ->find($fileIndexingId);

            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            // Check if this is an existing file (has page typings) or new file
            $existingPageTypings = $fileIndexing->pagetypings;
            $isExistingFile = $existingPageTypings->count() > 0;
            
            // Calculate next serial number based on file status
            $nextSerial = $this->calculateNextSerial($fileIndexing, $pageTypeId, $isExistingFile);
            $nextSerialFormatted = str_pad((string)$nextSerial, 2, '0', STR_PAD_LEFT);

            // Format scannings with page information
            $scannings = $fileIndexing->scannings->map(function ($scanning) use ($fileIndexing) {
                $pageTypings = $fileIndexing->pagetypings()
                    ->where('scanning_id', $scanning->id)
                    ->get();

                // For now, treat each scanning as one page
                // This can be enhanced later for PDF page splitting
                $totalPages = 1;
                $typedPages = $pageTypings->count();

                return [
                    'id' => $scanning->id,
                    'document_path' => $scanning->document_path,
                    'original_filename' => $scanning->original_filename,
                    'document_type' => $scanning->document_type,
                    'paper_size' => $scanning->paper_size,
                    'status' => $scanning->status,
                    'total_pages' => $totalPages,
                    'typed_pages' => $typedPages,
                    'is_fully_typed' => $typedPages >= $totalPages,
                    'page_typings' => $pageTypings->map(function ($pt) {
                        return [
                            'id' => $pt->id,
                            'page_number' => $pt->page_number,
                            'page_type' => $pt->page_type,
                            'page_subtype' => $pt->page_subtype,
                            'serial_number' => $pt->serial_number,
                            'page_code' => $pt->page_code,
                        ];
                    })
                ];
            });

            $totalPages = $scannings->sum('total_pages');
            $typedPages = $scannings->sum('typed_pages');

            return response()->json([
                'success' => true,
                'next_serial' => $nextSerial, // also include at root for convenience
                'is_existing_file' => $isExistingFile,
                'file' => [
                    'id' => $fileIndexing->id,
                    'file_number' => $fileIndexing->file_number,
                    'file_title' => $fileIndexing->file_title,
                    'district' => $fileIndexing->district,
                    'lga' => $fileIndexing->lga,
                    'is_existing_file' => $isExistingFile,
                    'main_application' => $fileIndexing->mainApplication ? [
                        'id' => $fileIndexing->mainApplication->id,
                        'applicant_name' => $fileIndexing->mainApplication->applicant_name ?? 'Unknown'
                    ] : null,
                    'scannings' => $scannings,
                    'total_scannings' => $scannings->count(),
                    'total_pages' => $totalPages,
                    'typed_pages' => $typedPages,
                    'progress' => $totalPages > 0 ? round(($typedPages / $totalPages) * 100, 1) : 0,
                    'is_completed' => $typedPages >= $totalPages && $totalPages > 0,
                    'next_serial' => $nextSerial,
                    'next_serial_formatted' => $nextSerialFormatted,
                    'existing_page_typings' => $existingPageTypings->map(function ($pt) {
                        return [
                            'id' => $pt->id,
                            'page_number' => $pt->page_number,
                            'page_type' => $pt->page_type,
                            'page_subtype' => $pt->page_subtype,
                            'serial_number' => $pt->serial_number,
                            'page_code' => $pt->page_code,
                        ];
                    })
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error getting file details', [
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading file details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate the next serial number based on file status and page type
     */
    private function calculateNextSerial($fileIndexing, $pageTypeId = null, $isExistingFile = false)
    {
        try {
            // SIMPLIFIED LOGIC: Always find highest serial number across all files and increment by 1
            $maxSerial = PageTyping::on('sqlsrv')
                ->where('file_indexing_id', $fileIndexing->id)
                ->max('serial_number');
            
            return ($maxSerial ? (int)$maxSerial : 0) + 1;
            
        } catch (Exception $e) {
            Log::error('Error calculating next serial number', [
                'file_indexing_id' => $fileIndexing->id,
                'page_type_id' => $pageTypeId,
                'is_existing_file' => $isExistingFile,
                'error' => $e->getMessage()
            ]);
            
            // Return 1 as fallback
            return 1;
        }
    }

    /**
     * Get page typing data (cover types, page types, subtypes) for the typing interface
     */
    public function getTypingData(Request $request)
    {
        try {
            // Load Cover Types
            $coverTypes = DB::connection('sqlsrv')
                ->table('CoverType')
                ->select('Id as id', 'Name as name')
                ->get()
                ->map(function ($coverType) {
                    // Generate code from name (FC for Front Cover, BC for Back Cover)
                    $code = '';
                    if (stripos($coverType->name, 'front') !== false) {
                        $code = 'FC';
                    } elseif (stripos($coverType->name, 'back') !== false) {
                        $code = 'BC';
                    } else {
                        // Generate code from first letters of words
                        $words = explode(' ', $coverType->name);
                        $code = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                    }
                    
                    return [
                        'id' => $coverType->id,
                        'name' => $coverType->name,
                        'code' => $code
                    ];
                });

            // Load Page Types
            $pageTypes = DB::connection('sqlsrv')
                ->table('PageType')
                ->select('id', 'PageType as name')
                ->get()
                ->map(function ($pageType) {
                    // Generate code from name
                    $words = explode(' ', $pageType->name);
                    $code = '';
                    foreach ($words as $word) {
                        $code .= strtoupper(substr($word, 0, 1));
                    }
                    // Limit to 4 characters max
                    $code = substr($code, 0, 4);
                    
                    return [
                        'id' => $pageType->id,
                        'name' => $pageType->name,
                        'code' => $code
                    ];
                });

            // Load Page Sub Types grouped by PageTypeId
            $pageSubTypesRaw = DB::connection('sqlsrv')
                ->table('PageSubType')
                ->select('id', 'PageTypeId', 'PageSubType as name')
                ->get();

            $pageSubTypes = [];
            foreach ($pageSubTypesRaw as $subType) {
                if (!isset($pageSubTypes[$subType->PageTypeId])) {
                    $pageSubTypes[$subType->PageTypeId] = [];
                }
                
                // Generate code from name
                $words = explode(' ', $subType->name);
                $code = '';
                foreach ($words as $word) {
                    $code .= strtoupper(substr($word, 0, 1));
                }
                // Limit to 4 characters max
                $code = substr($code, 0, 4);
                
                $pageSubTypes[$subType->PageTypeId][] = [
                    'id' => $subType->id,
                    'name' => $subType->name,
                    'code' => $code
                ];
            }

            return response()->json([
                'success' => true,
                'cover_types' => $coverTypes,
                'page_types' => $pageTypes,
                'page_sub_types' => $pageSubTypes
            ]);

        } catch (Exception $e) {
            Log::error('Error getting page typing data', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading page typing data: ' . $e->getMessage(),
                'cover_types' => [],
                'page_types' => [],
                'page_sub_types' => []
            ], 500);
        }
    }

    /**
     * Get next serial number for a specific page type in an existing file (AJAX)
     */
    public function getNextSerialForPageType(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file_indexing_id' => 'required|integer|exists:sqlsrv.file_indexings,id',
                'page_type_id' => 'required|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            $fileIndexingId = $validated['file_indexing_id'];
            $pageTypeId = $validated['page_type_id'];

            $fileIndexing = FileIndexing::on('sqlsrv')->find($fileIndexingId);
            
            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            // Check if this is an existing file (has page typings)
            $existingPageTypings = PageTyping::on('sqlsrv')
                ->where('file_indexing_id', $fileIndexingId)
                ->count();
            
            $isExistingFile = $existingPageTypings > 0;
            
            // Calculate next serial number based on file status and page type
            $nextSerial = $this->calculateNextSerial($fileIndexing, $pageTypeId, $isExistingFile);
            
            return response()->json([
                'success' => true,
                'next_serial' => $nextSerial,
                'next_serial_formatted' => str_pad((string)$nextSerial, 2, '0', STR_PAD_LEFT),
                'is_existing_file' => $isExistingFile,
                'logic_used' => $isExistingFile ? 'existing_file_by_page_type' : 'new_file_increment'
            ]);

        } catch (Exception $e) {
            Log::error('Error getting next serial for page type', [
                'file_indexing_id' => $request->get('file_indexing_id'),
                'page_type_id' => $request->get('page_type_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error calculating serial number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified page typing
     */
    public function show($id)
    {
        try {
            $pageTyping = PageTyping::on('sqlsrv')
                ->with(['fileIndexing', 'typedBy'])
                ->find($id);

            if (!$pageTyping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page typing not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'page_typing' => [
                    'id' => $pageTyping->id,
                    'page_number' => $pageTyping->page_number,
                    'page_type' => $pageTyping->page_type,
                    'page_subtype' => $pageTyping->page_subtype,
                    'serial_number' => $pageTyping->serial_number,
                    'page_code' => $pageTyping->page_code,
                    'file_path' => $pageTyping->file_path,
                    'scanning_id' => $pageTyping->scanning_id,
                    'file_indexing' => $pageTyping->fileIndexing ? [
                        'id' => $pageTyping->fileIndexing->id,
                        'file_number' => $pageTyping->fileIndexing->file_number,
                        'file_title' => $pageTyping->fileIndexing->file_title,
                    ] : null,
                    'typed_by' => $pageTyping->typedBy ? $pageTyping->typedBy->name : 'Unknown',
                    'created_at' => $pageTyping->created_at ? $pageTyping->created_at->format('M d, Y H:i') : 'Unknown',
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error showing page typing', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading page typing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified page typing
     */
    public function edit($id)
    {
        try {
            $pageTyping = PageTyping::on('sqlsrv')
                ->with(['fileIndexing'])
                ->find($id);

            if (!$pageTyping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page typing not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'page_typing' => $pageTyping
            ]);

        } catch (Exception $e) {
            Log::error('Error loading page typing for editing', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading page typing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified page typing
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'page_type' => 'required|string|max:100',
                'page_subtype' => 'nullable|string|max:100',
                'serial_number' => 'required|integer|min:1',
                'page_code' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $pageTyping = PageTyping::on('sqlsrv')->find($id);

            if (!$pageTyping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page typing not found'
                ], 404);
            }

            $pageTyping->update([
                'page_type' => $request->page_type,
                'page_subtype' => $request->page_subtype,
                'serial_number' => $request->serial_number,
                'page_code' => $request->page_code,
                'typed_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Page typing updated successfully!',
                'page_typing' => $pageTyping
            ]);

        } catch (Exception $e) {
            Log::error('Error updating page typing', [
                'id' => $id,
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating page typing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified page typing
     */
    public function destroy($id)
    {
        try {
            $pageTyping = PageTyping::on('sqlsrv')->find($id);

            if (!$pageTyping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page typing not found'
                ], 404);
            }

            $pageTyping->delete();

            return response()->json([
                'success' => true,
                'message' => 'Page typing deleted successfully!'
            ]);

        } catch (Exception $e) {
            Log::error('Error deleting page typing', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting page typing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for creating a new page typing
     */
    public function create()
    {
        try {
            // Load data needed for creating page typing
            $fileIndexings = FileIndexing::on('sqlsrv')
                ->whereHas('scannings')
                ->with(['mainApplication'])
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'file_indexings' => $fileIndexings
            ]);

        } catch (Exception $e) {
            Log::error('Error loading create page typing data', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created page typing
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file_indexing_id' => 'required|integer|exists:sqlsrv.file_indexings,id',
                'page_types' => 'required|array',
                'page_types.*.scanning_id' => 'required|integer',
                'page_types.*.page_number' => 'required|integer|min:1',
                'page_types.*.page_type' => 'required|string|max:100',
                'page_types.*.page_subtype' => 'nullable|string|max:100',
                'page_types.*.serial_number' => 'required|integer|min:1',
                'page_types.*.page_code' => 'nullable|string|max:100',
                'page_types.*.file_path' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $fileIndexingId = $request->file_indexing_id;
            $pageTypes = $request->page_types;
            $createdPageTypings = [];

            foreach ($pageTypes as $pageTypeData) {
                // Check if page typing already exists
                $existingPageTyping = PageTyping::on('sqlsrv')
                    ->where('file_indexing_id', $fileIndexingId)
                    ->where('file_path', $pageTypeData['file_path'])
                    ->where('page_number', $pageTypeData['page_number'])
                    ->first();

                if ($existingPageTyping) {
                    // Update existing record
                    $existingPageTyping->update([
                        'page_type' => $pageTypeData['page_type'],
                        'page_subtype' => $pageTypeData['page_subtype'],
                        'serial_number' => $pageTypeData['serial_number'],
                        'page_code' => $pageTypeData['page_code'],
                        'typed_by' => Auth::id(),
                    ]);
                    $createdPageTypings[] = $existingPageTyping;
                } else {
                    // Create new record
                    $pageTyping = PageTyping::on('sqlsrv')->create(array_merge($pageTypeData, [
                        'file_indexing_id' => $fileIndexingId,
                        'typed_by' => Auth::id()
                    ]));
                    $createdPageTypings[] = $pageTyping;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Page typing completed successfully!',
                'created_count' => count($createdPageTypings),
                'redirect' => route('pagetyping.index')
            ]);

        } catch (Exception $e) {
            Log::error('Error storing page typing', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error saving page typing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get thumbnails for a file
     */
    public function getThumbnails(Request $request)
    {
        try {
            $fileIndexingId = $request->get('file_indexing_id');
            
            if (!$fileIndexingId) {
                return response()->json([
                    'success' => false,
                    'message' => 'File indexing ID is required'
                ], 400);
            }

            $thumbnails = Thumbnail::where('file_indexing_id', $fileIndexingId)
                ->active()
                ->orderedByPage()
                ->get();

            return response()->json([
                'success' => true,
                'thumbnails' => $thumbnails
            ]);

        } catch (Exception $e) {
            Log::error('Error getting thumbnails: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving thumbnails: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save a thumbnail for a PDF page
     */
    public function saveThumbnail(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'thumbnail' => 'required|image|mimes:jpeg,jpg,png|max:5120', // 5MB max
                'file_indexing_id' => 'required|integer',
                'page_number' => 'required|integer',
                'thumbnail_path' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $fileIndexingId = $request->input('file_indexing_id');
            $pageNumber = $request->input('page_number');
            $thumbnailPath = $request->input('thumbnail_path');

            // Get file number
            $fileIndexing = FileIndexing::on('sqlsrv')->find($fileIndexingId);
            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            // Handle file upload
            $thumbnailFile = null;
            if ($request->hasFile('thumbnail')) {
                $thumbnailFile = $request->file('thumbnail');
                
                // Create directory if it doesn't exist
                $directory = dirname(file_storage_path('app/public/' . $thumbnailPath));
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }

                // Move file to storage
                $thumbnailFile->move(file_storage_path('app/public'), $thumbnailPath);
            }

            // Save to database
            $thumbnail = Thumbnail::create([
                'file_indexing_id' => $fileIndexingId,
                'scanning_id' => $request->input('scanning_id'), // Optional
                'file_number' => $fileIndexing->file_number,
                'page_number' => $pageNumber,
                'page_type_id' => $request->input('page_type_id'), // Optional
                'thumbnail_path' => $thumbnailPath,
                'original_filename' => $request->input('original_filename'), // Optional
                'file_size' => $thumbnailFile ? $thumbnailFile->getSize() : null,
                'mime_type' => $thumbnailFile ? $thumbnailFile->getMimeType() : null,
                'is_active' => 1
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thumbnail saved successfully',
                'thumbnail' => [
                    'id' => $thumbnail->id,
                    'file_number' => $thumbnail->file_number,
                    'thumbnail_path' => $thumbnail->thumbnail_path,
                    'page_number' => $thumbnail->page_number
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error saving thumbnail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error saving thumbnail: ' . $e->getMessage()
            ], 500);
        }
    }
}