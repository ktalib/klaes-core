<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception; 
use App\Models\FileIndexing;
use App\Models\Scanning;
use App\Models\PageTyping;
use App\Models\PageTypingToolLog;
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

            // Use the standard registries consistent with scan_uploads
            // Use dynamic registries from DB
            $registries = \App\Models\Registry::where('is_active', true)
                ->orderBy('name')
                ->pluck('name')
                ->toArray();

            if (empty($registries)) {
                $registries = [
                    'Lands Registry',
                    'Cadastral Registry',
                    'DCIV Registry',
                    'Secret Registry',
                    'KANGIS Registry',
                    'SLTR Registry',
                    'ST Registry',
                    'Deeds Registry',
                ];
            }

            // Return the dashboard view with selectedFileIndexing if provided
            return view('pagetyping.index', compact(
                'PageTitle',
                'PageDescription',
                'stats',
                'pendingFiles',
                'inProgressFiles',
                'completedFiles',
                'selectedFileIndexing',
                'registries'
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
            $registry = $request->get('registry', '');

            switch ($status) {
                case 'pending':
                    $result = $this->getPendingFilesPaginated($search, $limit, $page, $registry);
                    break;
                case 'in_progress':
                    $result = $this->getInProgressFilesPaginated($search, $limit, $page, $registry);
                    break;
                case 'completed':
                    $result = $this->getCompletedFilesPaginated($search, $limit, $page, $registry);
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
                // Use relations if loaded, otherwise count
                $scanningsCount = $file->scannings_count ?? $file->scannings->count();
                $pageTypingsCount = $file->pagetypings_count ?? $file->pagetypings->count();

                // Calculate progress for in-progress files
                $progress = $scanningsCount > 0 ? ($pageTypingsCount / $scanningsCount) * 100 : 0;

                // Use latest_scan_at for display if available
                $displayDate = $file->latest_scan_at ?? ($file->created_at ? $file->created_at->format('Y-m-d H:i:s') : null);
                $formattedDate = $displayDate ? (new \Carbon\Carbon($displayDate))->format('M d, Y') : 'Unknown';

                // Get typed_by user name from the first pagetyping record
                $typedByName = 'Unknown';
                if ($file->relationLoaded('pagetypings') && $file->pagetypings->isNotEmpty()) {
                    $firstTyping = $file->pagetypings->first();
                    if ($firstTyping->relationLoaded('typedBy') && $firstTyping->typedBy) {
                        $user = $firstTyping->typedBy;
                        $typedByName = ucfirst($user->first_name) . ' ' . ucfirst($user->last_name);
                    } elseif ($firstTyping->typed_by) {
                        // Fallback: query user directly if relation not loaded
                        $user = \App\Models\User::find($firstTyping->typed_by);
                        if ($user) {
                            $typedByName = ucfirst($user->first_name) . ' ' . ucfirst($user->last_name);
                        }
                    }
                }

                return [
                    'id' => $file->id,
                    'file_number' => $file->file_number,
                    'file_title' => $file->file_title,
                    'district' => $file->district,
                    'lga' => $file->lga,
                    'scannings_count' => $scanningsCount,
                    'page_typings_count' => $pageTypingsCount,
                    'progress' => round($progress, 1),
                    'created_at' => $formattedDate, // Display scan date as "created_at" for UI consistency
                    'updated_at' => $file->updated_at ? $file->updated_at->format('M d, Y H:i') : 'Unknown',
                    'status' => $file->status,
                    'typed_by_name' => $typedByName,
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
    private function getPendingFiles($search = '', $limit = 10, $registry = '')
    {
        try {
            $query = FileIndexing::on('sqlsrv')
                ->with(['mainApplication', 'scannings', 'pagetypings'])
                ->whereHas('scannings')
                ->whereDoesntHave('pagetypings');

            if ($registry) {
                $query->where('registry', $registry);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('file_number', 'like', "%{$search}%")
                        ->orWhere('file_title', 'like', "%{$search}%")
                        ->orWhere('district', 'like', "%{$search}%")
                        ->orWhere('lga', 'like', "%{$search}%");
                });
            }

            return $query->select('file_indexings.*')
                ->addSelect(['latest_scan_at' => \App\Models\Scanning::select('created_at')
                    ->whereColumn('file_indexing_id', 'file_indexings.id')
                    ->orderBy('created_at', 'desc')
                    ->limit(1)
                ])
                ->orderBy('latest_scan_at', 'desc')
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
    private function getPendingFilesPaginated($search = '', $limit = 10, $page = 1, $registry = '')
    {
        try {
            $query = FileIndexing::on('sqlsrv')
                ->with(['mainApplication', 'scannings', 'pagetypings'])
                ->whereHas('scannings')
                ->whereDoesntHave('pagetypings');

            if ($registry) {
                $query->where('registry', $registry);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('file_number', 'like', "%{$search}%")
                        ->orWhere('file_title', 'like', "%{$search}%")
                        ->orWhere('district', 'like', "%{$search}%")
                        ->orWhere('lga', 'like', "%{$search}%");
                });
            }

            $total = $query->count();
            $files = $query->select('file_indexings.*')
                ->addSelect(['latest_scan_at' => \App\Models\Scanning::select('created_at')
                    ->whereColumn('file_indexing_id', 'file_indexings.id')
                    ->orderBy('created_at', 'desc')
                    ->limit(1)
                ])
                ->orderBy('latest_scan_at', 'desc')
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
    private function getInProgressFilesPaginated($search = '', $limit = 10, $page = 1, $registry = '')
    {
        try {
            $query = FileIndexing::on('sqlsrv')
                ->with(['mainApplication', 'scannings', 'pagetypings.typedBy'])
                ->whereHas('scannings')
                ->whereHas('pagetypings');

            if ($registry) {
                $query->where('registry', $registry);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('file_number', 'like', "%{$search}%")
                        ->orWhere('file_title', 'like', "%{$search}%")
                        ->orWhere('district', 'like', "%{$search}%")
                        ->orWhere('lga', 'like', "%{$search}%");
                });
            }

            // Add subqueries for sorting and counts
            $query->select('file_indexings.*')
                ->addSelect(['latest_scan_at' => \App\Models\Scanning::select('created_at')
                    ->whereColumn('file_indexing_id', 'file_indexings.id')
                    ->orderBy('created_at', 'desc')
                    ->limit(1)
                ])
                ->orderBy('latest_scan_at', 'desc');

            // Get all potential files first
            $allFiles = $query->get();

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
    private function getCompletedFilesPaginated($search = '', $limit = 10, $page = 1, $registry = '')
    {
        try {
            $query = FileIndexing::on('sqlsrv')
                ->with(['mainApplication', 'scannings', 'pagetypings.typedBy'])
                ->whereHas('scannings')
                ->whereHas('pagetypings');

            if ($registry) {
                $query->where('registry', $registry);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('file_number', 'like', "%{$search}%")
                        ->orWhere('file_title', 'like', "%{$search}%")
                        ->orWhere('district', 'like', "%{$search}%")
                        ->orWhere('lga', 'like', "%{$search}%");
                });
            }

            // Add subqueries for sorting and counts
            $query->select('file_indexings.*')
                ->addSelect(['latest_scan_at' => \App\Models\Scanning::select('created_at')
                    ->whereColumn('file_indexing_id', 'file_indexings.id')
                    ->orderBy('created_at', 'desc')
                    ->limit(1)
                ])
                ->orderBy('latest_scan_at', 'desc');

            // Get all potential files first
            $allFiles = $query->get();

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
    private function getInProgressFiles($search = '', $limit = 10, $registry = '')
    {
        try {
            $query = FileIndexing::on('sqlsrv')
                ->with(['mainApplication', 'scannings', 'pagetypings'])
                ->whereHas('scannings')
                ->whereHas('pagetypings');

            if ($registry) {
                $query->where('registry', $registry);
            }

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
    private function getCompletedFiles($search = '', $limit = 10, $registry = '')
    {
        try {
            $query = FileIndexing::on('sqlsrv')
                ->with(['mainApplication', 'scannings', 'pagetypings.typedBy'])
                ->whereHas('scannings')
                ->whereHas('pagetypings');

            if ($registry) {
                $query->where('registry', $registry);
            }

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
    private function getPendingPageTypingCount($registry = '')
    {
        try {
            $query = FileIndexing::on('sqlsrv')
                ->whereHas('scannings')
                ->whereDoesntHave('pagetypings');

            if ($registry) {
                $query->where('registry', $registry);
            }

            return $query->count();
        } catch (Exception $e) {
            Log::error('Error getting pending page typing count', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get in-progress page typing count
     */
    private function getInProgressPageTypingCount($registry = '')
    {
        try {
            $inProgressCount = 0;

            $query = FileIndexing::on('sqlsrv')
                ->whereHas('scannings')
                ->whereHas('pagetypings')
                ->with(['scannings', 'pagetypings']);

            if ($registry) {
                $query->where('registry', $registry);
            }

            $filesWithBoth = $query->get();

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
    private function getCompletedPageTypingCount($registry = '')
    {
        try {
            $completedCount = 0;

            $query = FileIndexing::on('sqlsrv')
                ->whereHas('scannings')
                ->whereHas('pagetypings')
                ->with(['scannings', 'pagetypings']);

            if ($registry) {
                $query->where('registry', $registry);
            }

            $filesWithBoth = $query->get();

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
    private function getPageTypeMoreCount($registry = '')
    {
        try {
            // Check if is_updated column exists
            $hasIsUpdatedColumn = DB::connection('sqlsrv')
                ->getSchemaBuilder()
                ->hasColumn('file_indexings', 'is_updated');

            if ($hasIsUpdatedColumn) {
                $query = FileIndexing::on('sqlsrv')
                    ->whereHas('pagetypings') // Must have existing page typings
                    ->where('is_updated', 1); // And be marked as updated

                if ($registry) {
                    $query->where('registry', $registry);
                }

                return $query->count();
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
            $registry = $request->get('registry', '');

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

            if ($registry) {
                $query->where('registry', $registry);
            }

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('file_number', 'like', "%{$search}%")
                        ->orWhere('file_title', 'like', "%{$search}%")
                        ->orWhere('district', 'like', "%{$search}%")
                        ->orWhere('lga', 'like', "%{$search}%");
                });
            }

            $total = $query->count();

            $files = $query->select('file_indexings.*')
                ->addSelect(['latest_scan_at' => \App\Models\Scanning::select('created_at')
                    ->whereColumn('file_indexing_id', 'file_indexings.id')
                    ->orderBy('created_at', 'desc')
                    ->limit(1)
                ])
                ->orderBy('latest_scan_at', 'desc')
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get();

            $formattedFiles = $files->map(function ($file) {
                $scanningsCount = $file->scannings->count();
                $pageTypingsCount = $file->pagetypings->count();

                // Calculate existing vs new pages
                $existingPages = $pageTypingsCount;
                $newScans = max(0, $scanningsCount - $pageTypingsCount);
                $totalPages = $scanningsCount;

                // Use latest_scan_at for last_updated if available
                $displayDate = $file->latest_scan_at ?? ($file->updated_at ? $file->updated_at->format('Y-m-d H:i:s') : null);
                $formattedDate = $displayDate ? (new \Carbon\Carbon($displayDate))->format('M d, Y') : 'Unknown';

                return [
                    'id' => $file->id,
                    'file_number' => $file->file_number,
                    'file_title' => $file->file_title,
                    'district' => $file->district,
                    'lga' => $file->lga,
                    'existing_pages' => $existingPages,
                    'new_scans' => $newScans,
                    'total_pages' => $totalPages,
                    'last_updated' => $formattedDate,
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
                'page_type_other' => 'required_if:page_type,other|nullable|string|max:100',
                'page_subtype' => 'nullable|string|max:100',
                'page_subtype_other' => 'required_if:page_subtype,other|nullable|string|max:100',
                'serial_number' => 'required', // Accept both integer and string for booklet mode
                'definition' => 'nullable|integer|min:0|max:500',
                'display_order' => 'nullable|integer|min:0',
                'page_code' => 'nullable|string|max:100',
                'file_path' => 'required|string|max:255',
                'registry' => 'nullable|string|max:100',
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

            // Handle 'other' option - use custom text if 'other' is selected
            $pageTypeValue = $validated['page_type'];
            $normalizedPageType = strtolower((string) $pageTypeValue);
            if (in_array($normalizedPageType, ['other', 'others', 'oth'], true) && !empty($validated['page_type_other'])) {
                $pageTypeValue = $validated['page_type_other'];
            }

            $pageSubtypeValue = $validated['page_subtype'] ?? null;
            $normalizedPageSubtype = strtolower((string) $pageSubtypeValue);
            if (in_array($normalizedPageSubtype, ['other', 'others', 'oth'], true) && !empty($validated['page_subtype_other'])) {
                $pageSubtypeValue = $validated['page_subtype_other'];
            }

            // Check if page typing already exists
            $existingPageTyping = PageTyping::on('sqlsrv')
                ->where('file_indexing_id', $validated['file_indexing_id'])
                ->where('file_path', $validated['file_path'])
                ->where('page_number', $validated['page_number'])
                ->first();

            $derivedDisplayOrder = isset($validated['display_order'])
                ? (int) $validated['display_order']
                : max(0, ((int) ($validated['page_number'] ?? 1)) - 1);
            $derivedDefinition = $derivedDisplayOrder + 1;

            $dataToSave = [
                'page_type' => $pageTypeValue,
                'page_subtype' => $pageSubtypeValue,
                'serial_number' => $validated['serial_number'],
                'definition' => $derivedDefinition,
                'display_order' => $derivedDisplayOrder,
                'page_code' => $validated['page_code'],
                'definition_code' => ((string) $derivedDefinition) . '-' . ($validated['page_code'] ?? ''),
                'typed_by' => Auth::id(),
                // Booklet management fields
                'booklet_id' => $validated['booklet_id'] ?? null,
                'is_booklet_page' => $validated['is_booklet_page'] ?? false,
                'booklet_sequence' => $validated['booklet_sequence'] ?? null,
            ];

            if ($existingPageTyping) {
                // Update existing record
                $existingPageTyping->update($dataToSave);
                $pageTyping = $existingPageTyping;
            } else {
                // Create new record
                $pageTyping = PageTyping::on('sqlsrv')->create(array_merge(
                    $validated,
                    $dataToSave
                ));
            }

            // Handle file movement to standardized locations
            try {
                $scanning = Scanning::on('sqlsrv')->find($validated['scanning_id']);
                $fileIndexing = FileIndexing::on('sqlsrv')->find($validated['file_indexing_id']);

                if ($scanning && $fileIndexing && $fileIndexing->file_number) {
                    $fileNumber = $fileIndexing->file_number;
                    $paperSize = !empty($scanning->paper_size) ? $scanning->paper_size : 'A4';

                    // Normalize Registry
                    $targetRegistry = !empty($validated['registry']) ? $validated['registry'] : ($scanning->registry ?? $fileIndexing->registry ?? 'Lands Registry');
                    $targetRegistryName = $this->normalizeRegistry($targetRegistry);
                    $registrySlug = $this->getRegistrySlug($targetRegistryName);

                    // Get file extension and standardized filename
                    $fileExtension = pathinfo($scanning->original_filename, PATHINFO_EXTENSION);
                    if (empty($fileExtension)) {
                        $fileExtension = pathinfo($scanning->document_path ?? '', PATHINFO_EXTENSION) ?: 'pdf';
                    }
                    $rawDefinitionCode = $dataToSave['definition_code'] ?? (($dataToSave['definition'] ?? 0) . '-' . ($dataToSave['page_code'] ?? ''));
                    $safeDefinitionCode = preg_replace('/[^A-Za-z0-9\-_.]/', '_', (string) $rawDefinitionCode);
                    $safeDefinitionCode = trim((string) $safeDefinitionCode, '._- ');
                    if ($safeDefinitionCode === '') {
                        $safeDefinitionCode = $fileNumber . '_' . $scanning->id;
                    }
                    $fileName = $safeDefinitionCode . '.' . strtolower($fileExtension);

                    // 1. Resolve source path (using fallback logic)
                    $resolvedSourceRelative = $this->resolveExistingFilePath($targetRegistry, $fileNumber, $paperSize, $fileName);

                    // If not found by standardized name, try the path stored in scanning record itself
                    if (!$resolvedSourceRelative && $scanning->document_path) {
                        $resolvedSourceRelative = $scanning->document_path;
                    }

                    if ($resolvedSourceRelative) {
                        $sourceFullPath = file_storage_path('app/public/' . ltrim($resolvedSourceRelative, '/'));

                        // 2. Define standardized target paths
                        $pagetypingPath = $this->getStandardizedTargetPath($targetRegistry, $fileNumber, $paperSize, $fileName);
                        $archivePath = "EDMS/ARCHIVE_Doc_WARE/{$registrySlug}/{$fileNumber}/{$paperSize}/{$fileName}";

                        $pagetypingFullPath = file_storage_path('app/public/' . $pagetypingPath);
                        $archiveFullPath = file_storage_path('app/public/' . $archivePath);

                        // Ensure directories exist
                        Storage::disk('public')->makeDirectory(dirname($pagetypingPath));
                        Storage::disk('public')->makeDirectory(dirname($archivePath));

                        // 3. Move/Copy files
                        if (file_exists($sourceFullPath)) {
                            // Copy to Archive if not already there
                            if (!file_exists($archiveFullPath)) {
                                copy($sourceFullPath, $archiveFullPath);
                            }

                            // Move to PageTyping if it's not already there
                            if ($sourceFullPath !== $pagetypingFullPath) {
                                if (!rename($sourceFullPath, $pagetypingFullPath)) {
                                    // Fallback to copy/unlink if rename fails
                                    if (copy($sourceFullPath, $pagetypingFullPath)) {
                                        unlink($sourceFullPath);
                                    }
                                }
                            }
                        }

                        // 4. Synchronization and record updates
                        if (!empty($validated['registry']) && $validated['registry'] !== $fileIndexing->registry) {
                            $fileIndexing->update(['registry' => $targetRegistryName]);
                        }

                        $pageTyping->update([
                            'file_path' => $pagetypingPath,
                            'registry' => $targetRegistryName
                        ]);

                        $scanning->update([
                            'document_path' => $pagetypingPath,
                            'definition' => $dataToSave['definition'] ?? 0,
                            'definition_code' => $dataToSave['definition_code'] ?? null,
                            'display_order' => $dataToSave['display_order'] ?? ($scanning->display_order ?? 0),
                            'original_filename' => $fileName,
                            'status' => 'completed'
                        ]);

                        // Clean up source directory if it was a temporary or legacy one
                        $this->cleanupSourceDirectory(dirname($sourceFullPath));
                    }
                }
            } catch (Exception $fileException) {
                Log::error('Error handling file standardization in saveSingle', [
                    'error' => $fileException->getMessage(),
                    'file_indexing_id' => $validated['file_indexing_id'],
                    'scanning_id' => $validated['scanning_id']
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Page classification saved successfully!',
                'page_typing_id' => $pageTyping->id,
                'scanning' => [
                    'id' => $scanning->id,
                    'document_path' => $scanning->document_path,
                    'status' => $scanning->status,
                    'file_url' => Storage::disk('public')->url($scanning->document_path)
                ]
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
     * Delete a scanned page and its associated page typing record.
     */
    public function deletePage(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'file_indexing_id' => 'required|integer|exists:sqlsrv.file_indexings,id',
            'scanning_id' => 'required|integer|exists:sqlsrv.scannings,id',
            'page_index' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $fileIndexing = FileIndexing::on('sqlsrv')->find($validated['file_indexing_id']);
            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'File indexing record not found.',
                ], 404);
            }

            $scanning = Scanning::on('sqlsrv')
                ->where('id', $validated['scanning_id'])
                ->where('file_indexing_id', $fileIndexing->id)
                ->first();

            if (!$scanning) {
                return response()->json([
                    'success' => false,
                    'message' => 'Scanning entry not found for the specified file.',
                ], 404);
            }

            $disk = Storage::disk('public');

            DB::connection('sqlsrv')->beginTransaction();

            try {
                // Delete associated page typing records
                PageTyping::on('sqlsrv')
                    ->where('scanning_id', $scanning->id)
                    ->delete();

                // Remove the physical file
                $documentPath = $scanning->document_path;
                if ($documentPath && $disk->exists($documentPath)) {
                    $disk->delete($documentPath);
                }

                // Delete the scanning record
                $scanning->delete();

                DB::connection('sqlsrv')->commit();
            } catch (\Throwable $transactionException) {
                DB::connection('sqlsrv')->rollBack();
                throw $transactionException;
            }

            Log::info('Page deleted via PageTyping editor', [
                'file_indexing_id' => $fileIndexing->id,
                'scanning_id' => $validated['scanning_id'],
                'deleted_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Page deleted successfully.',
                'deleted_scanning_id' => (int) $validated['scanning_id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error deleting page', [
                'file_indexing_id' => $request->input('file_indexing_id'),
                'scanning_id' => $request->input('scanning_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete page: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Replace the underlying scanned document for a given page.
     */
    public function replacePage(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'file_indexing_id' => 'required|integer|exists:sqlsrv.file_indexings,id',
            'scanning_id' => 'required|integer|exists:sqlsrv.scannings,id',
            'page_index' => 'nullable|integer|min:0',
            'page_number' => 'nullable|integer|min:1',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,tif,tiff|max:20480', // 20MB max to align with scanning uploads
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $fileIndexing = FileIndexing::on('sqlsrv')->find($validated['file_indexing_id']);
            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'File indexing record not found.',
                ], 404);
            }

            $scanning = Scanning::on('sqlsrv')
                ->where('id', $validated['scanning_id'])
                ->where('file_indexing_id', $fileIndexing->id)
                ->first();

            if (!$scanning) {
                return response()->json([
                    'success' => false,
                    'message' => 'Scanning entry not found for the specified file.',
                ], 404);
            }

            /** @var \Illuminate\Http\UploadedFile|null $uploadedFile */
            $uploadedFile = $request->file('file');
            if (!$uploadedFile) {
                return response()->json([
                    'success' => false,
                    'message' => 'No file uploaded.',
                ], 422);
            }

            $disk = Storage::disk('public');
            $oldPath = $scanning->document_path;

            $extension = strtolower($uploadedFile->getClientOriginalExtension() ?: $uploadedFile->extension() ?: '');
            if (!$extension) {
                $extension = 'pdf';
            }

            $directory = 'EDMS/SCAN_UPLOAD/' . $fileIndexing->file_number;
            $timestamp = now()->format('Ymd_His');
            $filename = $fileIndexing->file_number . '_' . $scanning->id . '_' . $timestamp . '.' . $extension;

            $path = $uploadedFile->storeAs($directory, $filename, 'public');
            if (!$path) {
                throw new \RuntimeException('Unable to store uploaded file.');
            }

            DB::connection('sqlsrv')->beginTransaction();

            try {
                $scanning->document_path = $path;
                $scanning->original_filename = $uploadedFile->getClientOriginalName();
                $scanning->uploaded_by = Auth::id();
                $scanning->status = 'pending';
                $scanning->updated_at = now();
                $scanning->save();

                PageTyping::on('sqlsrv')
                    ->where('scanning_id', $scanning->id)
                    ->update(['file_path' => $path]);

                try {
                    FileIndexing::on('sqlsrv')
                        ->where('id', $fileIndexing->id)
                        ->update(['is_updated' => 1]);
                } catch (\Throwable $flagException) {
                    Log::warning('Could not flag file indexing as updated after replacement', [
                        'file_indexing_id' => $fileIndexing->id,
                        'error' => $flagException->getMessage(),
                    ]);
                }

                DB::connection('sqlsrv')->commit();
            } catch (\Throwable $transactionException) {
                DB::connection('sqlsrv')->rollBack();

                if ($disk->exists($path)) {
                    $disk->delete($path);
                }

                throw $transactionException;
            }

            if ($oldPath && $oldPath !== $path && $disk->exists($oldPath)) {
                $disk->delete($oldPath);
            }

            return response()->json([
                'success' => true,
                'message' => 'Document replaced successfully.',
                'document' => [
                    'scanning_id' => $scanning->id,
                    'file_path' => $path,
                    'file_url' => $disk->url($path),
                    'url' => $disk->url($path),
                    'display_name' => $scanning->original_filename,
                    'file_extension' => $extension,
                    'mime_type' => $uploadedFile->getMimeType(),
                    'page_index' => $validated['page_index'] ?? null,
                    'page_number' => $validated['page_number'] ?? null,
                    'updated_at' => optional($scanning->updated_at)->toDateTimeString(),
                    'file_size' => $uploadedFile->getSize(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error replacing page document', [
                'file_indexing_id' => $request->input('file_indexing_id'),
                'scanning_id' => $request->input('scanning_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to replace document: ' . $e->getMessage(),
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
                        ->orWhere('page_code', 'like', "%{$search}%")
                        ->orWhere('definition_code', 'like', "%{$search}%");
                });
            }

            $pageTypings = $query->orderByRaw('ISNULL(definition, 0) asc')
                ->orderBy('serial_number')
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
                        'definition' => $pt->definition,
                        'page_code' => $pt->page_code,
                        'definition_code' => $pt->definition_code,
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
            $nextSerialFormatted = str_pad((string) $nextSerial, 2, '0', STR_PAD_LEFT);

            // Format scannings with page information
            $scannings = $fileIndexing->scannings
                ->sort(function ($a, $b) {
                    $orderA = (int) ($a->display_order ?? 0);
                    $orderB = (int) ($b->display_order ?? 0);
                    if ($orderA !== $orderB) {
                        return $orderA <=> $orderB;
                    }

                    $defA = (int) ($a->definition ?? 0);
                    $defB = (int) ($b->definition ?? 0);
                    if ($defA !== $defB) {
                        return $defA <=> $defB;
                    }

                    return ((int) $a->id) <=> ((int) $b->id);
                })
                ->values()
                ->map(function ($scanning) use ($fileIndexing) {
                $pageTypings = $fileIndexing->pagetypings()
                    ->where('scanning_id', $scanning->id)
                    ->orderByRaw('ISNULL(definition, 0) asc')
                    ->orderBy('page_number')
                    ->get();

                // 1. Resolve Path using standardized helpers
                $paperSize = !empty($scanning->paper_size) ? $scanning->paper_size : 'A4';
                $fileExtension = pathinfo($scanning->original_filename, PATHINFO_EXTENSION);
                if (empty($fileExtension)) {
                    $fileExtension = pathinfo($scanning->document_path ?? '', PATHINFO_EXTENSION) ?: 'pdf';
                }
                    $fileName = $fileIndexing->file_number . '_' . $scanning->id . '.' . strtolower($fileExtension);

                $resolvedPath = $this->resolveExistingFilePath($fileIndexing->registry, $fileIndexing->file_number, $paperSize, $fileName);
                $documentPath = $resolvedPath ?: $scanning->document_path;

                // For now, treat each scanning as one page
                // This can be enhanced later for PDF page splitting
                 
                $totalPages = 1;
                $typedPages = $pageTypings->count();

                return [
                    'id' => $scanning->id,
                    'document_path' => $documentPath,
                    'original_filename' => $scanning->original_filename,
                    'document_type' => $scanning->document_type,
                    'paper_size' => $scanning->paper_size,
                    'display_order' => $scanning->display_order,
                    'definition' => $scanning->definition,
                    'definition_code' => $scanning->definition_code,
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
                            'definition' => $pt->definition,
                            'page_code' => $pt->page_code,
                            'definition_code' => $pt->definition_code,
                            'cover_type_id' => $pt->cover_type_id,
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
                    'registry' => $fileIndexing->scannings->first()->registry ?? $fileIndexing->registry ?? '',
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
                            'definition' => $pt->definition,
                            'page_code' => $pt->page_code,
                            'definition_code' => $pt->definition_code,
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

            return ($maxSerial ? (int) $maxSerial : 0) + 1;

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

            // Add "Other" option to page types
            $pageTypes->push([
                'id' => 'other',
                'name' => 'Other (Specify)',
                'code' => 'OTH'
            ]);

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

            // Add "Other" option to each subtype group
            foreach (array_keys($pageSubTypes) as $pageTypeId) {
                $pageSubTypes[$pageTypeId][] = [
                    'id' => 'other',
                    'name' => 'Other (Specify)',
                    'code' => 'OTH'
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
                'next_serial_formatted' => str_pad((string) $nextSerial, 2, '0', STR_PAD_LEFT),
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
     * Clean up source directory if it's empty
     */
    private function cleanupSourceDirectory($directory)
    {
        try {
            if (is_dir($directory)) {
                $files = scandir($directory);
                // '.' and '..' are always present
                if (count($files) <= 2) {
                    rmdir($directory);
                    Log::info('Empty source directory cleaned up', ['directory' => $directory]);
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to clean up source directory', [
                'directory' => $directory,
                'error' => $e->getMessage()
            ]);
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

    /**
     * Log viewer tool interactions for analytics/auditing
     */
    public function logToolAction(Request $request, $fileIndexingId)
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|string|max:50',
                'file_indexing_id' => 'required|integer|exists:sqlsrv.file_indexings,id',
                'scanning_id' => 'nullable|integer|exists:sqlsrv.scannings,id',
                'file_path' => 'nullable|string|max:255',
                'rotation' => 'nullable|numeric',
                'scale' => 'nullable|numeric',
                'translate_x' => 'nullable|numeric',
                'translate_y' => 'nullable|numeric',
                'details' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            if ((int) $fileIndexingId !== (int) $validated['file_indexing_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'File indexing mismatch.'
                ], 422);
            }

            $detailsPayload = $request->input('details');
            $details = null;

            if (!empty($detailsPayload)) {
                $details = json_encode($detailsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $logEntry = PageTypingToolLog::create([
                'file_indexing_id' => $validated['file_indexing_id'],
                'scanning_id' => $validated['scanning_id'] ?? null,
                'file_path' => $validated['file_path'] ?? null,
                'action' => $validated['action'],
                'rotation' => $validated['rotation'] ?? 0,
                'scale' => $validated['scale'] ?? 1,
                'translate_x' => $validated['translate_x'] ?? 0,
                'translate_y' => $validated['translate_y'] ?? 0,
                'details' => $details,
                'performed_by' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);

            return response()->json([
                'success' => true,
                'log_id' => $logEntry->id,
            ], 201);
        } catch (\Throwable $th) {
            Log::error('Failed to log page typing tool action', [
                'error' => $th->getMessage(),
                'file_indexing_id' => $fileIndexingId,
                'payload' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to log tool action'
            ], 500);
        }
    }

    /**
     * Persist folder ordering updates from the redesigned UI
     */
    public function updateFolderOrder(Request $request, $fileIndexingId)
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'order' => 'required|array|min:1',
                'order.*.scanning_id' => 'required|integer|exists:sqlsrv.scannings,id',
                'order.*.position' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            $orderPayload = collect($validated['order']);

            // Ensure all scannings belong to the target file indexing record
            $scanningIds = $orderPayload->pluck('scanning_id')->unique()->values();
            $belongsToFile = Scanning::on('sqlsrv')
                ->where('file_indexing_id', $fileIndexingId)
                ->whereIn('id', $scanningIds)
                ->count();

            if ($belongsToFile !== $scanningIds->count()) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more documents do not belong to this file.'
                ], 422);
            }

            DB::connection('sqlsrv')->transaction(function () use ($orderPayload) {
                foreach ($orderPayload as $item) {
                    Scanning::on('sqlsrv')
                        ->where('id', $item['scanning_id'])
                        ->update(['display_order' => $item['position']]);
                }
            });

            Log::info('Page typing folder order updated', [
                'file_indexing_id' => $fileIndexingId,
                'updated_by' => Auth::id(),
                'order_count' => $orderPayload->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Folder arrangement saved successfully.'
            ]);
        } catch (\Throwable $th) {
            Log::error('Failed to update page typing folder order', [
                'error' => $th->getMessage(),
                'file_indexing_id' => $fileIndexingId,
                'payload' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to save folder arrangement.'
            ], 500);
        }
    }

    /**
     * Normalize registry name from ID or raw string.
     */
    private function normalizeRegistry($registry)
    {
        if (empty($registry)) {
            return 'Lands Registry'; // Default fallback
        }

        // Map numeric IDs to Lands Registry as per system convention (1=Commercial, 2=Residential, 3=Agricultural)
        if (in_array($registry, ['1', '2', '3'])) {
            return 'Lands Registry';
        }

        // Return as is if already a known name, otherwise cleanup
        return $registry;
    }

    /**
     * Get slugified registry name for folder structure.
     */
    private function getRegistrySlug($registry)
    {
        $normalized = $this->normalizeRegistry($registry);

        $map = [
            'Lands Registry' => 'Lands_Registry',
            'Cadastral Registry' => 'Cadastral_Registry',
            'DCIV Registry' => 'DCIV_Registry',
            'Secret Registry' => 'Secret_Registry',
            'KANGIS Registry' => 'KANGIS_Registry',
            'SLTR Registry' => 'SLTR_Registry',
            'ST Registry' => 'ST_Registry',
            'Deeds Registry' => 'Deeds_Registry',
        ];

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $normalized);
    }

    /**
     * Resolve the actual existing path of a file with fallback logic.
     * SCAN_UPLOAD -> PAGETYPING
     */
    private function resolveExistingFilePath($registry, $fileNumber, $paperSize, $fileName)
    {
        $registrySlug = $this->getRegistrySlug($registry);
        $paperSize = !empty($paperSize) ? strtoupper($paperSize) : 'A4';

        // 1. Try Scan Upload Path
        $scanUploadPath = "EDMS/SCAN_UPLOAD/{$registrySlug}/{$fileNumber}/{$paperSize}/{$fileName}";
        if (Storage::disk('public')->exists($scanUploadPath)) {
            return $scanUploadPath;
        }

        // 2. Try Page Typing Path
        $pageTypingPath = "EDMS/PAGETYPING/{$registrySlug}/{$fileNumber}/{$paperSize}/{$fileName}";
        if (Storage::disk('public')->exists($pageTypingPath)) {
            return $pageTypingPath;
        }

        // 3. Try Legacy Logic (without paper size)
        $legacyScanPath = "EDMS/SCAN_UPLOAD/{$registrySlug}/{$fileNumber}/{$fileName}";
        if (Storage::disk('public')->exists($legacyScanPath)) {
            return $legacyScanPath;
        }

        $legacyPagePath = "EDMS/PAGETYPING/{$registrySlug}/{$fileNumber}/{$fileName}";
        if (Storage::disk('public')->exists($legacyPagePath)) {
            return $legacyPagePath;
        }

        return null;
    }

    /**
     * Get the standardized target path for page typing.
     */
    private function getStandardizedTargetPath($registry, $fileNumber, $paperSize, $fileName)
    {
        $registrySlug = $this->getRegistrySlug($registry);
        $paperSize = !empty($paperSize) ? strtoupper($paperSize) : 'A4';
        return "EDMS/PAGETYPING/{$registrySlug}/{$fileNumber}/{$paperSize}/{$fileName}";
    }
}