<?php

namespace App\Http\Controllers;

use App\Services\ScannerService;
use App\Services\ShelfRackLocator;
use App\Models\FileIndexing;
use App\Models\FileTracker;
use App\Models\PageTyping;
use App\Models\Scanning;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FilearchiveController extends Controller
{
    private function getModuleTheme(string $module): ?array
    {
        $normalized = strtolower(trim($module));
        $themes = [
            'kangis' => [
                'title' => 'KANGIS',
                'bg' => 'bg-gradient-to-r from-yellow-500 via-amber-400 to-yellow-600',
                'icon' => 'map',
                'text_muted' => 'text-yellow-100',
            ],
            'sltr' => [
                'title' => 'SLTR',
                'bg' => 'bg-gradient-to-r from-blue-600 via-blue-500 to-blue-700',
                'icon' => 'book-open',
                'text_muted' => 'text-blue-100',
            ],
            'dciv' => [
                'title' => 'DCIV',
                'bg' => 'bg-gradient-to-r from-purple-600 via-purple-500 to-purple-700',
                'icon' => 'shield',
                'text_muted' => 'text-purple-100',
            ],
            'cadastral' => [
                'title' => 'CADASTRAL',
                'bg' => 'bg-gradient-to-r from-emerald-600 via-emerald-500 to-emerald-700',
                'icon' => 'layers',
                'text_muted' => 'text-emerald-100',
            ],
        ];

        return $themes[$normalized] ?? null;
    }

    public function index(Request $request)
    {
        $module = $request->get('url', '');
        $moduleTheme = $this->getModuleTheme($module);

        $PageTitle = $moduleTheme ? $moduleTheme['title'] . ' Digital Archive' : 'File Digital Archive';
        $PageDescription = 'Access and manage digitally archived files';
        $isStorageDemoMode = $this->isStorageDemoMode($module);

        if ($isStorageDemoMode) {
            $completedFiles = $this->buildStorageDemoPaginator($request, $module);

            // Avoid expensive full-folder scans for initial page render.
            // `buildStorageDemoPaginator` now attaches `storage_total_pages` when available.
            $stats = [
                'total_archived' => $completedFiles->total(),
                'recently_added' => $completedFiles->total(),
                'total_pages' => $completedFiles->storage_total_pages ?? $completedFiles->total(),
                'storage_used' => $completedFiles->storage_total_bytes ?? 'N/A',
            ];

            $popularPageTypes = collect();

            $currentYear = (int) date('Y');
            $yearOptions = collect(range($currentYear, 1981));
            $registryFolderName = $this->getRegistryFolderName($module);
            $registryOptions = collect([$registryFolderName => $registryFolderName]);

            // Storage-demo folders have no tracker data — everything reads as
            // "in the registry" for the legend, and the filter is inert.
            $statusCounts = [
                'registry' => $completedFiles->total(),
                'in_transit' => 0,
                'missing' => \App\Models\MissingFile::where('status', '!=', \App\Models\MissingFile::STATUS_FOUND)->count(),
            ];
            $activeStatusFilter = null;

            return view('filearchive.index', compact(
                'PageTitle',
                'PageDescription',
                'completedFiles',
                'stats',
                'popularPageTypes',
                'module',
                'yearOptions',
                'registryOptions',
                'statusCounts',
                'activeStatusFilter'
            ));
        }

        $completedFiles = FileIndexing::query()
            ->select(['id', 'file_number', 'file_title', 'land_use_type', 'district', 'updated_at', 'plot_number', 'location', 'registry', 'shelf_location'])
            ->with([
                'fileTracking' => function ($query) {
                    $query->select(['id', 'file_indexing_id', 'status', 'assignment_status', 'current_location', 'movement_history']);
                },
                'pagetypings' => function ($query) {
                    $query->select(['id', 'file_indexing_id', 'page_number', 'page_type', 'page_subtype', 'page_code', 'definition_code', 'definition', 'serial_number', 'typed_by', 'cover_type_id', 'created_at', 'updated_at', 'file_path', 'scanning_id'])
                        ->with([
                            'typedBy:id,first_name,last_name',
                            'pageType:id,PageType',
                            'pageSubType:id,PageSubType',
                            'coverType:Id,Name',
                            'scanning:id,file_indexing_id,document_path,display_order,original_filename,document_type,created_at'
                        ])
                        ->orderByRaw('ISNULL(definition, 0) asc')
                        ->orderBy('page_number');
                },
                'scannings' => function ($query) {
                    $query->select(['id', 'file_indexing_id', 'document_path', 'display_order', 'original_filename', 'document_type', 'created_at']);
                },
                // Get the cover type from the first page
                'firstPageTyping' => function ($query) {
                    $query->select(['id', 'file_indexing_id', 'page_number', 'page_code', 'cover_type_id', 'page_type', 'page_subtype', 'file_path', 'scanning_id'])
                        ->with([
                            'coverType:Id,Name',
                            'pageType:id,PageType',
                            'pageSubType:id,PageSubType',
                            'scanning:id,file_indexing_id,document_path,display_order,original_filename,document_type,created_at'
                        ])
                        ->where('page_number', 1)
                        ->orWhere(function ($q) {
                            $q->whereRaw('page_number = (SELECT MIN(page_number) FROM pagetypings pt2 WHERE pt2.file_indexing_id = pagetypings.file_indexing_id)');
                        });
                }
            ])
            ->withCount(['pagetypings', 'scannings'])
            ->orderBy('updated_at', 'desc');

        if (!$isStorageDemoMode) {
            // Default behavior: only files where ALL scans are typed
            $completedFiles->whereHas('pagetypings')
                ->whereHas('scannings')
                ->whereColumn(
                    DB::raw('(SELECT COUNT(*) FROM pagetypings WHERE pagetypings.file_indexing_id = file_indexings.id)'),
                    '>=',
                    DB::raw('(SELECT COUNT(*) FROM scannings WHERE scannings.file_indexing_id = file_indexings.id)')
                );
        }

        // Apply registry/module filter (e.g. ?url=kangis => only KANGIS Registry files)
        if ($module !== '') {
            $completedFiles->where('registry', 'like', '%' . $module . '%');
        }

        // Apply search filters if provided
        if ($request->filled('search')) {
            $search = $request->get('search');
            $field = $request->get('field', 'all');

            $completedFiles->where(function ($query) use ($search, $field) {
                if ($field === 'all' || $field === 'fileName') {
                    $query->orWhere('file_title', 'like', "%{$search}%");
                }
                if ($field === 'all' || $field === 'fileNumber') {
                    $query->orWhere('file_number', 'like', "%{$search}%");
                }
                if ($field === 'all' || $field === 'type') {
                    $query->orWhere('land_use_type', 'like', "%{$search}%");
                }
                if ($field === 'all' || $field === 'page') {
                    $query->orWhereHas('pagetypings', function ($q) use ($search) {
                        $q->where('page_type', 'like', "%{$search}%")
                            ->orWhere('page_subtype', 'like', "%{$search}%");
                    });
                }
            });
        }

        // Apply category filter
        if ($request->filled('category') && $request->get('category') !== 'all') {
            $category = $request->get('category');
            switch ($category) {
                case 'land':
                    $completedFiles->whereIn('land_use_type', ['Residential', 'Commercial', 'Industrial']);
                    break;
                case 'legal':
                    $completedFiles->whereHas('pagetypings', function ($q) {
                        $q->whereIn('page_type', ['Deed', 'Certificate', 'Legal Document']);
                    });
                    break;
                case 'admin':
                    $completedFiles->whereHas('pagetypings', function ($q) {
                        $q->whereIn('page_type', ['Application Form', 'Letter', 'Administrative']);
                    });
                    break;
            }
        }

        // Apply cover type filter
        if ($request->filled('cover_type') && $request->get('cover_type') !== 'all') {
            $coverType = $request->get('cover_type');
            $completedFiles->whereHas('firstPageTyping.coverType', function ($q) use ($coverType) {
                if ($coverType === 'front') {
                    $q->where('Name', 'like', '%front%');
                } elseif ($coverType === 'back') {
                    $q->where('Name', 'like', '%back%');
                }
            });
        }

        // Apply year filter (matches year in file_number, e.g. RES-2026-7)
        if ($request->filled('year') && $request->get('year') !== 'all') {
            $year = $request->get('year');
            $completedFiles->where('file_number', 'like', "%-{$year}-%");
        }

        // Apply registry filter
        if ($request->filled('registry') && $request->get('registry') !== 'all') {
            $completedFiles->where('registry', $request->get('registry'));
        }

        // ── Status legend counts + optional filter (across the WHOLE result set,
        //    not just the current page). Ground truth mirrors the card colours
        //    and App\Services\FileLocationResolver: a file is physically OUT when
        //    its LATEST tracker is non-terminal (not logged back / cancelled) and
        //    carries real movement. Uses a SQL subquery so it scales past SQL
        //    Server's 2100-parameter limit on large registries.
        $countsBase = clone $completedFiles;
        $totalForCounts = (clone $countsBase)->count();
        $transitCount = (clone $countsBase)
            ->whereIn('file_number', $this->inTransitFileNumberSubquery())
            ->count();
        $missingCount = \App\Models\MissingFile::where('status', '!=', \App\Models\MissingFile::STATUS_FOUND)->count();
        $statusCounts = [
            'registry' => max($totalForCounts - $transitCount, 0),
            'in_transit' => $transitCount,
            'missing' => $missingCount,
        ];

        $activeStatusFilter = in_array($request->get('status_filter'), ['registry', 'in_transit'], true)
            ? $request->get('status_filter')
            : null;
        if ($activeStatusFilter === 'in_transit') {
            $completedFiles->whereIn('file_number', $this->inTransitFileNumberSubquery());
        } elseif ($activeStatusFilter === 'registry') {
            $completedFiles->whereNotIn('file_number', $this->inTransitFileNumberSubquery());
        }

        // Apply sorting - default by year ascending (oldest first: 1981, 1982, ...)
        $sort = $request->get('sort', 'year_asc');
        
        // Remove default ordering (orderBy('updated_at', 'desc')) and reapply
        $completedFiles = $completedFiles->reorder();
        
        switch ($sort) {
            case 'year_asc':
                // Sort by year ascending (oldest files first: 1981, 1982, ...). Within the
                // same year, tie-break by registry (1, 2, 3) before falling back to a plain
                // file_number string sort — otherwise "CON-RES-1981-x" sorts ahead of
                // "RES-1981-x" (C < R alphabetically), even though Registry 1 (RES/COM/IND/AG)
                // is the one meant to lead each year, not Registry 3 (CON-*).
                $completedFiles->orderByRaw("CASE
                    WHEN file_number LIKE '%-1981-%' THEN 1
                    WHEN file_number LIKE '%-1982-%' THEN 2
                    WHEN file_number LIKE '%-1983-%' THEN 3
                    WHEN file_number LIKE '%-1984-%' THEN 4
                    WHEN file_number LIKE '%-1985-%' THEN 5
                    WHEN file_number LIKE '%-1986-%' THEN 6
                    WHEN file_number LIKE '%-1987-%' THEN 7
                    WHEN file_number LIKE '%-1988-%' THEN 8
                    WHEN file_number LIKE '%-1989-%' THEN 9
                    WHEN file_number LIKE '%-1990-%' THEN 10
                    WHEN file_number LIKE '%-1991-%' THEN 11
                    WHEN file_number LIKE '%-1992-%' THEN 12
                    WHEN file_number LIKE '%-1993-%' THEN 13
                    WHEN file_number LIKE '%-1994-%' THEN 14
                    WHEN file_number LIKE '%-1995-%' THEN 15
                    WHEN file_number LIKE '%-1996-%' THEN 16
                    WHEN file_number LIKE '%-1997-%' THEN 17
                    WHEN file_number LIKE '%-1998-%' THEN 18
                    WHEN file_number LIKE '%-1999-%' THEN 19
                    WHEN file_number LIKE '%-2000-%' THEN 20
                    WHEN file_number LIKE '%-2001-%' THEN 21
                    WHEN file_number LIKE '%-2002-%' THEN 22
                    WHEN file_number LIKE '%-2003-%' THEN 23
                    WHEN file_number LIKE '%-2004-%' THEN 24
                    WHEN file_number LIKE '%-2005-%' THEN 25
                    WHEN file_number LIKE '%-2006-%' THEN 26
                    WHEN file_number LIKE '%-2007-%' THEN 27
                    WHEN file_number LIKE '%-2008-%' THEN 28
                    WHEN file_number LIKE '%-2009-%' THEN 29
                    WHEN file_number LIKE '%-2010-%' THEN 30
                    WHEN file_number LIKE '%-2011-%' THEN 31
                    WHEN file_number LIKE '%-2012-%' THEN 32
                    WHEN file_number LIKE '%-2013-%' THEN 33
                    WHEN file_number LIKE '%-2014-%' THEN 34
                    WHEN file_number LIKE '%-2015-%' THEN 35
                    WHEN file_number LIKE '%-2016-%' THEN 36
                    WHEN file_number LIKE '%-2017-%' THEN 37
                    WHEN file_number LIKE '%-2018-%' THEN 38
                    WHEN file_number LIKE '%-2019-%' THEN 39
                    WHEN file_number LIKE '%-2020-%' THEN 40
                    WHEN file_number LIKE '%-2021-%' THEN 41
                    WHEN file_number LIKE '%-2022-%' THEN 42
                    WHEN file_number LIKE '%-2023-%' THEN 43
                    WHEN file_number LIKE '%-2024-%' THEN 44
                    WHEN file_number LIKE '%-2025-%' THEN 45
                    WHEN file_number LIKE '%-2026-%' THEN 46
                    ELSE 47
                END ASC,
                CASE registry
                    WHEN '1' THEN 1
                    WHEN '2' THEN 2
                    WHEN '3' THEN 3
                    ELSE 4
                END ASC,
                file_number ASC");
                break;
            case 'year_desc':
                // Sort by year descending (newest files first: 2026, 2025, ...)
                $completedFiles->orderByRaw("CASE 
                    WHEN file_number LIKE '%-2026-%' THEN 1
                    WHEN file_number LIKE '%-2025-%' THEN 2
                    WHEN file_number LIKE '%-2024-%' THEN 3
                    WHEN file_number LIKE '%-2023-%' THEN 4
                    WHEN file_number LIKE '%-2022-%' THEN 5
                    WHEN file_number LIKE '%-2021-%' THEN 6
                    WHEN file_number LIKE '%-2020-%' THEN 7
                    WHEN file_number LIKE '%-2019-%' THEN 8
                    WHEN file_number LIKE '%-2018-%' THEN 9
                    WHEN file_number LIKE '%-2017-%' THEN 10
                    WHEN file_number LIKE '%-2016-%' THEN 11
                    WHEN file_number LIKE '%-2015-%' THEN 12
                    WHEN file_number LIKE '%-2014-%' THEN 13
                    WHEN file_number LIKE '%-2013-%' THEN 14
                    WHEN file_number LIKE '%-2012-%' THEN 15
                    WHEN file_number LIKE '%-2011-%' THEN 16
                    WHEN file_number LIKE '%-2010-%' THEN 17
                    WHEN file_number LIKE '%-2009-%' THEN 18
                    WHEN file_number LIKE '%-2008-%' THEN 19
                    WHEN file_number LIKE '%-2007-%' THEN 20
                    WHEN file_number LIKE '%-2006-%' THEN 21
                    WHEN file_number LIKE '%-2005-%' THEN 22
                    WHEN file_number LIKE '%-2004-%' THEN 23
                    WHEN file_number LIKE '%-2003-%' THEN 24
                    WHEN file_number LIKE '%-2002-%' THEN 25
                    WHEN file_number LIKE '%-2001-%' THEN 26
                    WHEN file_number LIKE '%-2000-%' THEN 27
                    WHEN file_number LIKE '%-1999-%' THEN 28
                    WHEN file_number LIKE '%-1998-%' THEN 29
                    WHEN file_number LIKE '%-1997-%' THEN 30
                    WHEN file_number LIKE '%-1996-%' THEN 31
                    WHEN file_number LIKE '%-1995-%' THEN 32
                    WHEN file_number LIKE '%-1994-%' THEN 33
                    WHEN file_number LIKE '%-1993-%' THEN 34
                    WHEN file_number LIKE '%-1992-%' THEN 35
                    WHEN file_number LIKE '%-1991-%' THEN 36
                    WHEN file_number LIKE '%-1990-%' THEN 37
                    WHEN file_number LIKE '%-1989-%' THEN 38
                    WHEN file_number LIKE '%-1988-%' THEN 39
                    WHEN file_number LIKE '%-1987-%' THEN 40
                    WHEN file_number LIKE '%-1986-%' THEN 41
                    WHEN file_number LIKE '%-1985-%' THEN 42
                    WHEN file_number LIKE '%-1984-%' THEN 43
                    WHEN file_number LIKE '%-1983-%' THEN 44
                    WHEN file_number LIKE '%-1982-%' THEN 45
                    WHEN file_number LIKE '%-1981-%' THEN 46
                    ELSE 47
                END ASC,
                CASE registry
                    WHEN '1' THEN 1
                    WHEN '2' THEN 2
                    WHEN '3' THEN 3
                    ELSE 4
                END ASC,
                file_number ASC");
                break;
            case 'file_number_asc':
                $completedFiles->orderBy('file_number', 'asc');
                break;
            case 'file_number_desc':
                $completedFiles->orderBy('file_number', 'desc');
                break;
            case 'title_asc':
                $completedFiles->orderBy('file_title', 'asc');
                break;
            case 'title_desc':
                $completedFiles->orderBy('file_title', 'desc');
                break;
            case 'updated_desc':
            default:
                $completedFiles->orderBy('updated_at', 'desc');
                break;
        }

        $completedFiles = $completedFiles->paginate(12)->appends($request->query());

        // Files with no recorded shelf_location fall back to the rack/shelf the
        // shelf_rack_ranges map implies for their file number.
        app(ShelfRackLocator::class)->applyTo($completedFiles->getCollection());

        if ($isStorageDemoMode) {
            $completedFiles = $this->enrichStorageMetadataForPaginator($completedFiles, $module);
        }

        $isStorageDemoFallback = false;
        if ($isStorageDemoMode && $completedFiles->total() === 0) {
            $completedFiles = $this->buildStorageDemoPaginator($request, $module);
            $isStorageDemoFallback = true;
        }

        // Calculate statistics — cached for 5 minutes to avoid repeated heavy queries
        $statsCacheKey = "filearchive_stats_{$module}";
        $stats = Cache::remember($statsCacheKey, 300, function () use ($module, $isStorageDemoMode) {
            $statsBase = FileIndexing::query();
            if (!$isStorageDemoMode) {
                $statsBase->whereHas('pagetypings')
                    ->whereHas('scannings')
                    ->whereColumn(
                        DB::raw('(SELECT COUNT(*) FROM pagetypings WHERE pagetypings.file_indexing_id = file_indexings.id)'),
                        '>=',
                        DB::raw('(SELECT COUNT(*) FROM scannings WHERE scannings.file_indexing_id = file_indexings.id)')
                    );
            }
            if ($module !== '') {
                $statsBase = $statsBase->where('registry', 'like', '%' . $module . '%');
            }
            return [
                'total_archived' => (clone $statsBase)->count(),
                'recently_added' => (clone $statsBase)
                    ->where('updated_at', '>=', now()->subDays(30))->count(),
                'total_pages' => $module !== ''
                    ? PageTyping::whereHas('fileIndexing', fn($q) => $q->where('registry', 'like', '%' . $module . '%'))->count()
                    : PageTyping::count(),
                'storage_used' => $this->calculateStorageUsed($module),
            ];
        });

        if ($isStorageDemoFallback) {
            $stats['total_archived'] = $completedFiles->total();
            $stats['recently_added'] = $completedFiles->total();
            $stats['total_pages'] = $completedFiles->total();
            $stats['storage_used'] = $this->calculateStorageFolderSize($module);
        }

        // Get popular page types for filters
        $popularPageTypes = PageTyping::select('page_type', DB::raw('count(*) as count'))
            ->groupBy('page_type')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        // Get available years (from file number) and registries for filter dropdowns
        $currentYear = (int) date('Y');
        $yearOptions = collect(range($currentYear, 1981));

        // Registry 1/2/3 are the canonical Land registries derived from the file
        // number (see App\Services\RegistryDetector / config/file_ranges.php).
        // Keyed by the numeric value stored in file_indexings.registry, so the
        // filter below can match directly against that column.
        $registryOptions = collect([
            '1' => 'Registry 1 - Land',
            '2' => 'Registry 2 - Land',
            '3' => 'Registry 3 - Land',
        ]);

        return view('filearchive.index', compact(
            'PageTitle',
            'PageDescription',
            'completedFiles',
            'stats',
            'popularPageTypes',
            'module',
            'moduleTheme',
            'yearOptions',
            'registryOptions',
            'statusCounts',
            'activeStatusFilter'
        ));
    }

    /**
     * whereIn subquery selecting the file numbers that are physically OUT of the
     * registry. Mirrors App\Services\FileLocationResolver: a file is IN_TRANSIT
     * when its LATEST tracker (highest id) is non-terminal — i.e. not logged back
     * in (COMPLETED / LOG-IN) or cancelled — and still carries movement entries.
     * Returned as a closure so it composes into whereIn/whereNotIn as a real SQL
     * subquery (no PHP array, so no 2100-parameter ceiling).
     */
    private function inTransitFileNumberSubquery(): \Closure
    {
        return function ($query) {
            $latest = DB::connection('sqlsrv')->table('file_tracker')
                ->select('file_number', DB::raw('MAX(id) as max_id'))
                ->groupBy('file_number');

            $query->select('ft.file_number')
                ->from('file_tracker as ft')
                ->joinSub($latest, 'latest', function ($join) {
                    $join->on('latest.file_number', '=', 'ft.file_number')
                        ->on('latest.max_id', '=', 'ft.id');
                })
                ->whereRaw("UPPER(LTRIM(RTRIM(ft.status))) NOT IN ('COMPLETED','CANCELLED','CANCELED','LOG-IN')")
                ->whereNotNull('ft.movement_log')
                // movement_log is ntext; cast so LEN is valid and '[]' (empty) is excluded.
                ->whereRaw('LEN(CAST(ft.movement_log AS NVARCHAR(MAX))) > 2');
        };
    }

    /**
     * Get file details for modal display
     */
    public function getFileDetails($id)
    {
        $file = FileIndexing::with([
            'pagetypings.typedBy:id,first_name,last_name',
            'pagetypings.coverType',
            'pagetypings.pageType',
            'pagetypings.pageSubType',
            'scannings'
        ])
            ->withCount(['pagetypings', 'scannings'])
            ->findOrFail($id);

        // Transform pagetypings to include proper relationship data
        $transformedPageTypings = [];
        foreach ($file->pagetypings as $pageTyping) {
            $pageSubTypeObj = $pageTyping->pageSubType;
            $transformedSubtype = $pageSubTypeObj ? [
                'id' => $pageSubTypeObj->id,
                'name' => $pageSubTypeObj->PageSubType,
                'PageSubType' => $pageSubTypeObj->PageSubType,
                'code' => $pageSubTypeObj->code
            ] : $pageTyping->page_subtype;

            $transformedPageTypings[] = [
                'id' => $pageTyping->id,
                'page_number' => $pageTyping->page_number,
                'page_type' => $pageTyping->pageType ? [
                    'id' => $pageTyping->pageType->id,
                    'name' => $pageTyping->pageType->PageType,
                    'PageType' => $pageTyping->pageType->PageType,
                    'code' => $pageTyping->pageType->code
                ] : $pageTyping->page_type,
                'page_subtype' => $transformedSubtype,
                'serial_number' => $pageTyping->serial_number,
                'page_code' => $pageTyping->page_code,
                'definition' => $pageTyping->definition,
                'definition_code' => $pageTyping->definition_code,
                'cover_type' => $pageTyping->coverType ? [
                    'id' => $pageTyping->coverType->Id,
                    'name' => $pageTyping->coverType->Name,
                    'code' => $pageTyping->coverType->code
                ] : null,
                'typed_by' => $pageTyping->typedBy ? [
                    'id' => $pageTyping->typedBy->id,
                    'name' => $pageTyping->typedBy->first_name . ' ' . $pageTyping->typedBy->last_name,
                    'first_name' => $pageTyping->typedBy->first_name,
                    'last_name' => $pageTyping->typedBy->last_name
                ] : null,
                'created_at' => $pageTyping->created_at,
                'updated_at' => $pageTyping->updated_at
            ];
        }

        app(ShelfRackLocator::class)->applyTo([$file]);

        // Create response data with transformed pagetypings
        $responseData = $file->toArray();
        $responseData['pagetypings'] = $transformedPageTypings;

        return response()->json([
            'success' => true,
            'file' => $responseData
        ]);
    }

    /**
     * Get document pages for viewer    
     */
    public function getDocumentPages(Request $request, $id)
    {
        $module = $request->get('url', '');

        if (str_starts_with((string) $id, 'storage-demo-')) {
            $decodedPath = $this->decodeStorageDemoPath((string) $id, $module);
            if (!$decodedPath) {
                return response()->json([
                    'success' => false,
                    'message' => 'File could not be resolved.'
                ], 404);
            }
            return response()->json($this->buildStorageDocumentPagesPayload($decodedPath, (string) $id, $module));
        }

        $file = FileIndexing::with([
            'pagetypings' => function ($query) {
                $query->orderByRaw('ISNULL(definition, 0) asc')
                    ->orderBy('page_number')
                    ->with([
                        'coverType',
                        'typedBy:id,first_name,last_name',
                        'qcReviewer:id,first_name,last_name',
                        'pageType',
                        'pageSubType',
                        'scanning:id,file_indexing_id,document_path,display_order,original_filename,document_type,created_at'
                    ]);
            }
        ])->findOrFail($id);

        if ($this->isStorageDemoMode($module) && $file->pagetypings->isEmpty()) {
            $folderPath = $this->resolveStorageFolderByFileNumber((string) $file->file_number, $module);
            if ($folderPath) {
                return response()->json($this->buildStorageDocumentPagesPayload(
                    $folderPath,
                    (string) $id,
                    $module,
                    (string) $file->file_number,
                    (string) ($file->file_title ?: $file->file_number)
                ));
            }
        }

        $pathPrefixes = [
            'storage/app/public/',
            'app/public/',
            'public/',
            'storage/'
        ];

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'];

        $resolveMedia = function (?string $rawPath) use ($pathPrefixes, $imageExtensions) {
            if (!$rawPath) {
                return null;
            }

            $rawPath = str_replace('\\', '/', $rawPath);
            $trimmedRawPath = ltrim($rawPath, '/');

            $normalizedPath = $trimmedRawPath;
            foreach ($pathPrefixes as $prefix) {
                if (stripos($normalizedPath, $prefix) === 0) {
                    $normalizedPath = substr($normalizedPath, strlen($prefix));
                    break;
                }
            }
            $normalizedPath = ltrim($normalizedPath, '/');

            $extension = null;
            if ($normalizedPath) {
                $extension = strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION));
            } elseif ($trimmedRawPath) {
                $extension = strtolower(pathinfo($trimmedRawPath, PATHINFO_EXTENSION));
            }

            $viewerUrl = null;
            if ($normalizedPath && Storage::disk('public')->exists($normalizedPath)) {
                $viewerUrl = Storage::disk('public')->url($normalizedPath);
            }

            if (!$viewerUrl) {
                $publicFile = public_path($trimmedRawPath);
                if ($trimmedRawPath && file_exists($publicFile)) {
                    $viewerUrl = asset($trimmedRawPath);
                }
            }

            if (!$viewerUrl) {
                return null;
            }

            $mediaType = 'document';
            $thumbnailUrl = null;
            if ($extension && in_array($extension, $imageExtensions)) {
                $mediaType = 'image';
                $thumbnailUrl = $viewerUrl;
            } elseif ($extension === 'pdf') {
                $mediaType = 'pdf';
            }

            return [
                'viewer_url' => $viewerUrl,
                'thumbnail_url' => $thumbnailUrl,
                'media_type' => $mediaType,
                'extension' => $extension,
                'normalized_path' => $normalizedPath,
            ];
        };

        $pages = $file->pagetypings->map(function ($pageTyping) use ($resolveMedia, $imageExtensions) {
            $mediaSources = [
                ['path' => optional($pageTyping->scanning)->document_path, 'source' => 'scanning'],
                ['path' => $pageTyping->file_path, 'source' => 'pagetypings']
            ];

            $media = null;
            $mediaSource = null;
            foreach ($mediaSources as $candidate) {
                $media = $resolveMedia($candidate['path'] ?? null);
                if ($media) {
                    $mediaSource = $candidate['source'];
                    break;
                }
            }

            if (!$media) {
                $media = [
                    'viewer_url' => null,
                    'thumbnail_url' => null,
                    'media_type' => 'document',
                    'extension' => null,
                    'normalized_path' => null,
                ];
            }

            if ($media['media_type'] === 'document') {
                if (method_exists($pageTyping, 'isImagePage') && $pageTyping->isImagePage()) {
                    $media['media_type'] = 'image';
                    if (!$media['thumbnail_url'] && $media['viewer_url']) {
                        $media['thumbnail_url'] = $media['viewer_url'];
                    }
                } elseif (method_exists($pageTyping, 'isPdfPage') && $pageTyping->isPdfPage()) {
                    $media['media_type'] = 'pdf';
                }
            }

            // A page is "image-editable" if it resolves to an image OR it is
            // meant to be an image (a candidate path has an image extension)
            // but the file is missing/broken — so the reviewer can Replace it.
            $isImagePage = ($media['media_type'] === 'image');
            if (!$isImagePage) {
                foreach ([optional($pageTyping->scanning)->document_path, $pageTyping->file_path] as $candidatePath) {
                    if (!$candidatePath) {
                        continue;
                    }
                    $candidateExt = strtolower(pathinfo(str_replace('\\', '/', $candidatePath), PATHINFO_EXTENSION));
                    if (in_array($candidateExt, $imageExtensions)) {
                        $isImagePage = true;
                        break;
                    }
                }
            }

            return [
                'pagetyping_id' => $pageTyping->id,
                'page_number' => $pageTyping->page_number,
                'page_type' => $pageTyping->pageType ? [
                    'id' => $pageTyping->pageType->id,
                    'name' => $pageTyping->pageType->PageType,
                    'code' => $pageTyping->pageType->code
                ] : [
                    'id' => $pageTyping->page_type,
                    'name' => 'Unknown Type',
                    'code' => 'UNK'
                ],
                'page_subtype' => $pageTyping->pageSubType ? [
                    'id' => $pageTyping->pageSubType->id,
                    'name' => $pageTyping->pageSubType->PageSubType,
                    'code' => $pageTyping->pageSubType->code
                ] : ($pageTyping->page_subtype ? [
                        'id' => $pageTyping->page_subtype,
                        'name' => 'Unknown Subtype',
                        'code' => 'UNK'
                    ] : null),
                'page_code' => $pageTyping->page_code,
                'definition' => $pageTyping->definition,
                'definition_code' => $pageTyping->definition_code,
                'serial_number' => $pageTyping->serial_number,
                'cover_type' => $pageTyping->coverType ? [
                    'id' => $pageTyping->coverType->Id,
                    'name' => $pageTyping->coverType->Name,
                    'code' => $pageTyping->coverType->code
                ] : null,
                'typed_by' => $pageTyping->typedBy ? [
                    'name' => $pageTyping->typedBy->first_name . ' ' . $pageTyping->typedBy->last_name
                ] : null,
                'viewer_url' => $media['viewer_url'],
                'thumbnail_url' => $media['thumbnail_url'],
                'media_type' => $media['media_type'],
                'media_source' => $mediaSource,
                'pdf_page_number' => method_exists($pageTyping, 'getPdfPageNumber') ? $pageTyping->getPdfPageNumber() : null,
                'scanning_id' => optional($pageTyping->scanning)->id,
                'scanning_display_order' => optional($pageTyping->scanning)->display_order,
                'scanning_original_filename' => optional($pageTyping->scanning)->original_filename,
                'scanning_document_type' => optional($pageTyping->scanning)->document_type,
                'scanning_document_path' => optional($pageTyping->scanning)->document_path,
                'is_editable' => $isImagePage,
                'is_broken' => $isImagePage && empty($media['viewer_url']),
                'qc_status' => $pageTyping->qc_status ?: 'pending',
                'qc_reviewed_by' => $pageTyping->qcReviewer ? trim($pageTyping->qcReviewer->first_name . ' ' . $pageTyping->qcReviewer->last_name) : null,
                'qc_reviewed_at' => $pageTyping->qc_reviewed_at ? $pageTyping->qc_reviewed_at->format('Y-m-d H:i') : null,
                'qc_override_note' => $pageTyping->qc_override_note,
                'has_qc_issues' => (bool) $pageTyping->has_qc_issues,
                'created_at' => $pageTyping->created_at->format('Y-m-d H:i:s')
            ];
        });

        return response()->json([
            'success' => true,
            'file' => [
                'id' => $file->id,
                'file_number' => $file->file_number,
                'file_title' => $file->file_title,
                'total_pages' => $file->pagetypings->count()
            ],
            'pages' => $pages
        ]);
    }

    /**
     * Resolve the exact public-disk relative path currently backing a page's
     * image, using the same prefix-stripping order as getDocumentPages(). The
     * returned path is what an in-place edit must overwrite (filename kept).
     */
    private function resolveInPlaceImagePath(PageTyping $pageTyping): ?string
    {
        $pathPrefixes = ['storage/app/public/', 'app/public/', 'public/', 'storage/'];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'];

        $candidates = [
            optional($pageTyping->scanning)->document_path,
            $pageTyping->file_path,
        ];

        // Remember the first image-typed path even if its file is missing, so a
        // broken image can be (re)created in place with its original filename.
        $firstImagePath = null;

        foreach ($candidates as $rawPath) {
            if (!$rawPath) {
                continue;
            }

            $normalized = ltrim(str_replace('\\', '/', $rawPath), '/');
            foreach ($pathPrefixes as $prefix) {
                if (stripos($normalized, $prefix) === 0) {
                    $normalized = substr($normalized, strlen($prefix));
                    break;
                }
            }
            $normalized = ltrim($normalized, '/');
            if (!$normalized) {
                continue;
            }

            $ext = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
            if (!in_array($ext, $imageExtensions)) {
                continue;
            }

            // Prefer a path whose file actually exists on disk.
            if (Storage::disk('public')->exists($normalized)) {
                return $normalized;
            }
            if ($firstImagePath === null) {
                $firstImagePath = $normalized;
            }
        }

        // May point at a currently-missing file (broken image) — the caller
        // will recreate it at this exact path/filename.
        return $firstImagePath;
    }

    /**
     * Quality-control edit: overwrite the page image in place with an edited
     * version produced client-side. The filename/path is preserved; the
     * original is backed up first so the pre-edit version is recoverable.
     */
    public function applyPageEdits(Request $request, PageTyping $pageTyping)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:jpg,jpeg,png,webp|max:51200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $targetPath = $this->resolveInPlaceImagePath($pageTyping);
        if (!$targetPath) {
            return response()->json([
                'success' => false,
                'message' => 'The original image for this page could not be located, so it cannot be edited in place.',
            ], 404);
        }

        $extension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'];
        if (!in_array($extension, $imageExtensions)) {
            return response()->json([
                'success' => false,
                'message' => 'Only image pages can be edited.',
            ], 422);
        }

        try {
            $disk = Storage::disk('public');

            // 1) Back up the current file before overwriting it — but only if it
            //    actually exists (a broken/missing image has nothing to back up).
            $backupPath = null;
            if ($disk->exists($targetPath)) {
                $backupPath = 'EDMS/SCAN_UPLOAD/_qc_backups/' . $targetPath . '.' . now()->format('YmdHis') . '.' . $extension;
                $disk->copy($targetPath, $backupPath);
            }

            // 2) Write in place — SAME path, SAME filename (recreates it if missing).
            $disk->put($targetPath, file_get_contents($request->file('file')->getRealPath()));

            // 3) Keep metadata honest without touching the filename/path.
            if ($pageTyping->scanning) {
                $pageTyping->scanning->update([
                    'file_size' => $disk->size($targetPath),
                    'is_pdf_converted' => false,
                ]);
            }

            Log::info('File archive QC edit applied in place', [
                'pagetyping_id' => $pageTyping->id,
                'path' => $targetPath,
                'backup' => $backupPath,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document updated successfully.',
                'viewer_url' => $disk->url($targetPath) . '?v=' . now()->timestamp,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Unable to apply QC edits to archive page', [
                'pagetyping_id' => $pageTyping->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to apply edits: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Record a quality-control review decision for a page (passed/failed),
     * capturing the reviewer and timestamp on the pagetypings row.
     */
    public function setPageQcStatus(Request $request, PageTyping $pageTyping)
    {
        $validator = Validator::make($request->all(), [
            'qc_status' => 'required|in:passed,failed',
            'qc_override_note' => 'nullable|string|max:1000',
            'has_qc_issues' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $status = $request->input('qc_status');

        $pageTyping->update([
            'qc_status' => $status,
            'qc_reviewed_by' => Auth::id(),
            'qc_reviewed_at' => now(),
            'qc_override_note' => $request->input('qc_override_note'),
            'has_qc_issues' => $request->boolean('has_qc_issues', $status === 'failed'),
        ]);

        $pageTyping->load('qcReviewer:id,first_name,last_name');

        return response()->json([
            'success' => true,
            'message' => 'QC status saved.',
            'qc_status' => $pageTyping->qc_status,
            'qc_reviewed_by' => $pageTyping->qcReviewer
                ? trim($pageTyping->qcReviewer->first_name . ' ' . $pageTyping->qcReviewer->last_name)
                : null,
            'qc_reviewed_at' => optional($pageTyping->qc_reviewed_at)->format('Y-m-d H:i'),
            'qc_override_note' => $pageTyping->qc_override_note,
            'has_qc_issues' => (bool) $pageTyping->has_qc_issues,
        ]);
    }

    /**
     * Re-classify (edit the "file type") a single page from the archive's
     * document viewer. Unlike pagetyping's save-single this never renames or
     * moves the physical file — it only rewrites the classification columns and
     * the derived page_code / definition_code on the pagetypings row.
     */
    public function updatePageClassification(Request $request, PageTyping $pageTyping)
    {
        $validator = Validator::make($request->all(), [
            'cover_type_id'      => 'required|integer',
            'page_type'          => 'required|string|max:100',
            'page_type_other'    => 'required_if:page_type,other,others,oth|nullable|string|max:100',
            'page_subtype'       => 'nullable|string|max:100',
            'page_subtype_other' => 'required_if:page_subtype,other,others,oth|nullable|string|max:100',
            'serial_number'      => 'required|integer|min:0',
            'page_code'          => 'required|string|max:100',
            'registry'           => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $isOther = static fn ($value) => in_array(strtolower((string) $value), ['other', 'others', 'oth'], true);

        // Page type: a numeric id resolves the PageType FK; "other" stores the
        // custom text in both columns so the pageType() relationship falls back
        // to page_type_others when displayed (same shape as legacy records).
        if ($isOther($validated['page_type'])) {
            $pageTypeValue  = $validated['page_type_other'];
            $pageTypeOthers = $validated['page_type_other'];
        } else {
            $pageTypeValue  = $validated['page_type'];
            $pageTypeOthers = null;
        }

        $pageSubtypeRaw = $validated['page_subtype'] ?? null;
        if ($pageSubtypeRaw === null || $pageSubtypeRaw === '') {
            $pageSubtypeValue  = null;
            $pageSubtypeOthers = null;
        } elseif ($isOther($pageSubtypeRaw)) {
            $pageSubtypeValue  = $validated['page_subtype_other'];
            $pageSubtypeOthers = $validated['page_subtype_other'];
        } else {
            $pageSubtypeValue  = $pageSubtypeRaw;
            $pageSubtypeOthers = null;
        }

        // Keep definition_code aligned with the (possibly changed) page_code,
        // preserving the existing definition/position prefix.
        $definition = $pageTyping->definition ?? (max(0, (int) $pageTyping->page_number - 1) + 1);

        $updates = [
            'cover_type_id'       => (int) $validated['cover_type_id'],
            'page_type'           => $pageTypeValue,
            'page_type_others'    => $pageTypeOthers,
            'page_subtype'        => $pageSubtypeValue,
            'page_subtype_others' => $pageSubtypeOthers,
            'serial_number'       => (int) $validated['serial_number'],
            'page_code'           => $validated['page_code'],
            'definition_code'     => ((string) $definition) . '-' . $validated['page_code'],
            'typed_by'            => Auth::id(),
        ];

        if (!empty($validated['registry'])) {
            $updates['registry'] = $validated['registry'];
        }

        $pageTyping->update($updates);

        // Reload relationships so the viewer can refresh the row in place.
        $pageTyping->load(['pageType', 'pageSubType', 'coverType']);

        $pageTypeName = $pageTyping->pageType->PageType ?? ($pageTypeOthers ?: 'Unknown Type');
        $pageSubTypeName = $pageTyping->pageSubType->PageSubType ?? ($pageSubtypeOthers ?: null);

        Log::info('File archive page re-classified', [
            'pagetyping_id' => $pageTyping->id,
            'page_code'     => $pageTyping->page_code,
            'user_id'       => Auth::id(),
        ]);

        return response()->json([
            'success'    => true,
            'message'    => 'Page classification updated.',
            'page_code'  => $pageTyping->page_code,
            'serial_number' => $pageTyping->serial_number,
            'cover_type' => $pageTyping->coverType ? [
                'id'   => $pageTyping->coverType->Id,
                'name' => $pageTyping->coverType->Name,
                'code' => $pageTyping->coverType->code,
            ] : null,
            'page_type' => [
                'id'   => $pageTyping->pageType->id ?? $pageTyping->page_type,
                'name' => $pageTypeName,
                'code' => $pageTyping->pageType->code ?? (strtoupper(substr((string) ($pageTypeOthers ?? ''), 0, 4)) ?: 'OTH'),
            ],
            'page_subtype' => $pageSubTypeName ? [
                'id'   => $pageTyping->pageSubType->id ?? $pageTyping->page_subtype,
                'name' => $pageSubTypeName,
                'code' => $pageTyping->pageSubType->code ?? (strtoupper(substr((string) ($pageSubtypeOthers ?? ''), 0, 4)) ?: 'OTH'),
            ] : null,
        ]);
    }

    /**
     * Delete a single page (pagetypings row) from the archive viewer. The
     * backing scanning/physical file is only removed when no other page still
     * references it (e.g. sibling pages of the same source PDF), so deleting
     * one page never destroys the others.
     */
    public function deletePage(Request $request, PageTyping $pageTyping)
    {
        try {
            $scanningId = $pageTyping->scanning_id;
            $scanning = $pageTyping->scanning;

            DB::connection('sqlsrv')->beginTransaction();

            try {
                $pageTyping->delete();

                $scanningOrphaned = false;
                if ($scanningId) {
                    $remaining = PageTyping::on('sqlsrv')
                        ->where('scanning_id', $scanningId)
                        ->count();

                    if ($remaining === 0 && $scanning) {
                        $scanningOrphaned = true;

                        $disk = Storage::disk('public');
                        $documentPath = $scanning->document_path;
                        if ($documentPath && $disk->exists($documentPath)) {
                            $disk->delete($documentPath);
                        }

                        $scanning->delete();
                    }
                }

                DB::connection('sqlsrv')->commit();

                Log::info('File archive page deleted', [
                    'pagetyping_id'     => $pageTyping->id,
                    'scanning_id'       => $scanningId,
                    'scanning_removed'  => $scanningOrphaned,
                    'user_id'           => Auth::id(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Page deleted.',
                    'scanning_removed' => $scanningOrphaned,
                ]);
            } catch (\Throwable $transactionException) {
                DB::connection('sqlsrv')->rollBack();
                throw $transactionException;
            }
        } catch (\Throwable $e) {
            Log::error('Error deleting archive page', [
                'pagetyping_id' => $pageTyping->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete page: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Printable File Movement History sheet for a file, populated from the
     * file tracker module's movement_log (all trackers ever opened for the
     * file number, so previously logged-out files keep their full history).
     */
    public function printMovementHistory(Request $request)
    {
        $fileNumber = trim((string) $request->get('file_number', ''));
        abort_if($fileNumber === '', 404, 'File number is required.');

        $fileIndexing = FileIndexing::query()
            ->select(['id', 'file_number', 'file_title', 'registry', 'shelf_location', 'created_at'])
            ->where('file_number', $fileNumber)
            ->first();

        $latestTracker = FileTracker::where('file_number', $fileNumber)
            ->orderByDesc('updated_at')
            ->first();

        // Combined lineage: when this file belongs to a parcel-update / merger group
        // (Change of Purpose, Subdivision, Merger, Extension, Separation, Temporary File),
        // stitch every related file's movement log into one continuous timeline. Decommissioned
        // parents come first — each ending with a File Decommissioning marker — followed by each
        // surviving child opening with a File Commissioning marker.
        $group = app(\App\Services\FileMergerService::class)->resolveGroup($fileNumber);

        if (empty($group)) {
            $rows = $this->buildFileMovementRows($fileNumber);
        } else {
            $rows = [];
            foreach ($group as $member) {
                $memberFile = trim((string) ($member['file_number'] ?? ''));
                if ($memberFile === '') {
                    continue;
                }
                $isParent = ($member['role'] ?? '') === \App\Models\FileMerger::ROLE_PARENT;

                if ($isParent) {
                    $rows = array_merge($rows, $this->buildFileMovementRows($memberFile));
                    $rows[] = $this->lineageMarkerRow(
                        'FILE DECOMMISSIONING — ' . $memberFile,
                        $member['date_decommissioned'] ?? null,
                        ''
                    );
                } else {
                    $rows[] = $this->lineageMarkerRow(
                        'FILE COMMISSIONING — ' . $memberFile,
                        $member['date_commissioned'] ?? null,
                        'New file commissioned'
                    );
                    $rows = array_merge($rows, $this->buildFileMovementRows($memberFile));
                }
            }
        }

        return view('filearchive.print.file_movement_history', [
            'fileNumber' => $fileNumber,
            'trackingId' => $latestTracker->tracking_id ?? '',
            'fileTitle' => $fileIndexing->file_title ?? ($latestTracker->file_title ?? ''),
            'registry' => $fileIndexing->registry ?? ($latestTracker->registry_code ?? ''),
            'shelf' => $this->shelfFor($fileIndexing),
            'rows' => $rows,
        ]);
    }

    /**
     * A file's recorded shelf_location, falling back to the rack/shelf derived
     * from the shelf_rack_ranges map. Empty string when neither is available.
     */
    private function shelfFor($fileIndexing): string
    {
        $recorded = trim((string) ($fileIndexing->shelf_location ?? ''));

        if ($recorded !== '' || !$fileIndexing) {
            return $recorded;
        }

        return (string) app(ShelfRackLocator::class)->resolve(
            $fileIndexing->file_number ?? '',
            $fileIndexing->registry ?? null
        );
    }

    /**
     * A single synthetic lineage row (File Commissioning / File Decommissioning) inserted
     * between related files' movement logs on the combined File Movement History sheet.
     */
    private function lineageMarkerRow(string $office, $date, string $remarks): array
    {
        $formatted = '';
        if (!empty($date)) {
            try {
                $formatted = Carbon::parse($date)->format('d/m/Y');
            } catch (\Throwable $e) {
                $formatted = '';
            }
        }

        return [
            'office' => $office,
            'officer' => '',
            'date' => $formatted,
            'duration' => '',
            'remarks' => $remarks,
            'request_purpose' => '—',
            'timeline' => '—',
            'expected_return_date' => '—',
            'delay_reason' => '—',
        ];
    }

    /**
     * Build the movement-history rows for a single file: its archive "home" row followed by
     * every tracker movement, ordered chronologically. Extracted so the combined lineage view
     * can assemble one continuous timeline across every file in a merger group.
     */
    private function buildFileMovementRows(string $fileNumber): array
    {
        $trackers = FileTracker::where('file_number', $fileNumber)
            ->orderBy('created_at')
            ->get();

        $fileIndexing = FileIndexing::query()
            ->select(['id', 'file_number', 'file_title', 'registry', 'shelf_location', 'created_at'])
            ->where('file_number', $fileNumber)
            ->first();

        $entries = [];
        foreach ($trackers as $tracker) {
            $log = $tracker->movement_log;
            if (!is_array($log)) {
                $log = json_decode((string) $log, true) ?: [];
            }
            $trackerActive = strtoupper((string) $tracker->status) === FileTracker::STATUS_ACTIVE;
            // Request Purpose / Timeline / Expected Return Date belong to the tracker
            // (one per tracking cycle), not the individual movement entry — carried
            // alongside each entry so a re-tracked file's rows reflect the purpose
            // that was active during that cycle.
            foreach ($log as $entry) {
                if (is_array($entry)) {
                    $entries[] = [
                        'entry' => $entry,
                        'tracker_active' => $trackerActive,
                        'origin_office' => trim((string) $tracker->origin_office_name),
                        'request_purpose_name' => trim((string) $tracker->request_purpose_name),
                        'deadline' => $tracker->deadline,
                        'days_until_deadline' => $tracker->days_until_deadline,
                        'timeline_status' => $tracker->timeline_status,
                    ];
                }
            }
        }

        usort($entries, fn ($a, $b) => $this->movementEntryTimestamp($a['entry']) <=> $this->movementEntryTimestamp($b['entry']));

        $rows = [];
        $entryCount = count($entries);
        foreach ($entries as $index => $item) {
            $entry = $item['entry'];
            $login = $this->parseMovementDateTime($entry['log_in_date'] ?? null, $entry['log_in_time'] ?? null);
            $logout = $this->parseMovementDateTime($entry['log_out_date'] ?? null, $entry['log_out_time'] ?? null);
            $status = strtolower((string) ($entry['status'] ?? ''));

            // Return-to-registry rows (the manual log-back writes an
            // action=status_updated entry at the tracker's origin registry)
            // are the "login" bookend of a movement out; their duration is the
            // registry stay — from the log-back until the next dispatch.
            $officeName = trim((string) ($entry['office_name'] ?? ($entry['receiving_office_name'] ?? '')));
            $isReturn = ($entry['action'] ?? '') === 'status_updated'
                || in_array($status, ['logged_out', 'logged_in'], true)
                || ($officeName !== '' && $item['origin_office'] !== '' && strcasecmp($officeName, $item['origin_office']) === 0);

            // Duration per movement runs from the log-out (the dispatch that
            // took the file out) until the log-in that brought it back: use
            // the entry's own log-out when it is a real one (some flows stamp
            // log_out equal to log_in at creation time), otherwise the start
            // of the next entry — for a completed round trip that is exactly
            // the registry log-back. Never mix the two clocks: log_in/log_out
            // are wall-clock local while 'timestamp' is UTC.
            $isOpen = in_array($status, ['active', 'pending_acceptance', 'in_progress'], true);
            $duration = '';
            if ($isReturn) {
                $startTs = $this->movementEntryTimestamp($entry);
                $nextTs = $index + 1 < $entryCount ? $this->movementEntryTimestamp($entries[$index + 1]['entry']) : 0;
                if ($startTs && $nextTs > $startTs) {
                    $duration = $this->formatDurationMinutes(intdiv($nextTs - $startTs, 60));
                }
            } elseif ($login && $logout && $logout->greaterThan($login)) {
                $duration = $this->formatDurationMinutes((int) abs($logout->diffInMinutes($login)));
            } else {
                $startTs = $this->movementEntryTimestamp($entry);
                $nextTs = $index + 1 < $entryCount ? $this->movementEntryTimestamp($entries[$index + 1]['entry']) : 0;
                if ($startTs && $nextTs > $startTs) {
                    $duration = $this->formatDurationMinutes(intdiv($nextTs - $startTs, 60));
                } elseif ($item['tracker_active'] && $isOpen) {
                    // Mirrors App\Services\FileLocationResolver ground truth:
                    // only a movement on an ACTIVE tracker is still in progress.
                    $duration = 'Ongoing';
                }
            }

            $officer = trim((string) ($entry['receiving_officer_name'] ?? ''))
                ?: trim((string) ($entry['user_name'] ?? ''));

            $remarks = trim((string) ($entry['notes'] ?? ''))
                ?: trim((string) ($entry['completion_notes'] ?? ''))
                ?: ucwords(str_replace('_', ' ', $status));

            $delayReason = trim((string) ($entry['delay_reason'] ?? ''));

            $rows[] = [
                'office' => $officeName ?: '—',
                'officer' => $officer,
                'date' => $login ? $login->format('d/m/Y') : ($logout ? $logout->format('d/m/Y') : ''),
                'duration' => $duration,
                'remarks' => $remarks,
                'request_purpose' => $item['request_purpose_name'] !== '' ? $item['request_purpose_name'] : '—',
                'timeline' => $this->formatTimelineForPrint($item['timeline_status'] ?? null, $item['days_until_deadline'] ?? null),
                'expected_return_date' => $item['deadline'] ? Carbon::parse($item['deadline'])->format('d/m/Y') : '—',
                'delay_reason' => $delayReason !== '' ? $delayReason : '—',
            ];
        }

        $latestTracker = $trackers->sortByDesc('updated_at')->first();

        // Default "home location" first row — mirrors the tracker log table:
        // the file's permanent registry/archive centre with its rack/shelf,
        // dated from the file's original indexing record.
        $homeRegistry = trim((string) ($latestTracker->origin_office_name ?? ''))
            ?: trim((string) ($fileIndexing->registry ?? ''))
            ?: 'Registry / Archive';
        $homeShelf = $this->shelfFor($fileIndexing);

        // The archive home row is not a movement out, so like return rows it
        // carries no duration.
        array_unshift($rows, [
            'office' => $homeRegistry . ' — Shelf/Rack ' . ($homeShelf !== '' ? $homeShelf : '-'),
            'officer' => '',
            'date' => $fileIndexing && $fileIndexing->created_at ? $fileIndexing->created_at->format('d/m/Y') : '',
            'duration' => '',
            'remarks' => 'In Archive',
            'request_purpose' => '—',
            'timeline' => '—',
            'expected_return_date' => '—',
            'delay_reason' => '—',
        ]);

        return $rows;
    }

    /**
     * Green/Amber/Red timeline label for the print sheet — mirrors
     * FileTracker::getTimelineStatusAttribute() / the on-screen day-count badge.
     */
    private function formatTimelineForPrint(?string $status, $daysUntilDeadline): string
    {
        if (!$status) {
            return '—';
        }

        if ($daysUntilDeadline === null) {
            $label = ['green' => 'On Track', 'amber' => 'Due Soon', 'red' => 'Overdue'][$status] ?? ucfirst($status);
        } elseif ($daysUntilDeadline > 0) {
            $label = $daysUntilDeadline . ' day' . ($daysUntilDeadline === 1 ? '' : 's') . ' left';
        } elseif ($daysUntilDeadline === 0) {
            $label = 'Due today';
        } else {
            $abs = abs($daysUntilDeadline);
            $label = $abs . ' day' . ($abs === 1 ? '' : 's') . ' overdue';
        }

        return $label;
    }

    private function movementEntryTimestamp(array $entry): int
    {
        if (!empty($entry['timestamp'])) {
            $parsed = strtotime((string) $entry['timestamp']);
            if ($parsed !== false) {
                return $parsed;
            }
        }

        $dateTime = $this->parseMovementDateTime($entry['log_in_date'] ?? null, $entry['log_in_time'] ?? null)
            ?: $this->parseMovementDateTime($entry['log_out_date'] ?? null, $entry['log_out_time'] ?? null);

        return $dateTime ? $dateTime->getTimestamp() : 0;
    }

    private function parseMovementDateTime(?string $date, ?string $time): ?Carbon
    {
        if (!$date) {
            return null;
        }

        try {
            return Carbon::parse(trim($date . ' ' . ($time ?: '00:00')));
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function formatDurationMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return max($minutes, 1) . ' min';
        }

        $hours = intdiv($minutes, 60);
        if ($hours < 24) {
            $rest = $minutes % 60;
            return $hours . ' hr' . ($hours > 1 ? 's' : '') . ($rest ? " {$rest} min" : '');
        }

        $days = intdiv($hours, 24);
        $restHours = $hours % 24;
        return $days . ' day' . ($days > 1 ? 's' : '') . ($restHours ? " {$restHours} hr" . ($restHours > 1 ? 's' : '') : '');
    }

    /**
     * Search files with advanced filters
     */
    public function search(Request $request)
    {
        $query = FileIndexing::whereHas('pagetypings')
            ->whereHas('scannings')
            ->whereColumn(
                DB::raw('(SELECT COUNT(*) FROM pagetypings WHERE pagetypings.file_indexing_id = file_indexings.id)'),
                '>=',
                DB::raw('(SELECT COUNT(*) FROM scannings WHERE scannings.file_indexing_id = file_indexings.id)')
            )
            ->with([
                'pagetypings.typedBy',
                'scannings',
                'fileTracking' => function ($query) {
                    $query->select(['id', 'file_indexing_id', 'status', 'assignment_status']);
                }
            ])
            ->withCount(['pagetypings', 'scannings']);

        // Apply registry/module filter
        $module = $request->get('url', '');
        $isStorageDemoMode = $this->isStorageDemoMode($module);

        if ($isStorageDemoMode) {
            $files = $this->buildStorageDemoPaginator($request, $module);

            return response()->json([
                'success' => true,
                'files' => $files,
                'html' => view('filearchive.partials.files_grid_content', compact('files'))->render()
            ]);
        }

        if ($module !== '') {
            $query->where('registry', 'like', '%' . $module . '%');
        }

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('file_title', 'like', "%{$search}%")
                    ->orWhere('file_number', 'like', "%{$search}%")
                    ->orWhereHas('pagetypings', function ($subQ) use ($search) {
                        $subQ->where('page_type', 'like', "%{$search}%");
                    });
            });
        }

        // Apply year filter (matches year in file_number, e.g. RES-2026-7)
        if ($request->filled('year') && $request->get('year') !== 'all') {
            $year = $request->get('year');
            $query->where('file_number', 'like', "%-{$year}-%");
        }

        // Apply registry filter
        if ($request->filled('registry') && $request->get('registry') !== 'all') {
            $query->where('registry', $request->get('registry'));
        }

        $files = $query->paginate(12);

        app(ShelfRackLocator::class)->applyTo($files->getCollection());

        if ($isStorageDemoMode && $files->total() === 0) {
            $files = $this->buildStorageDemoPaginator($request, $module);
        } elseif ($isStorageDemoMode) {
            $files = $this->enrichStorageMetadataForPaginator($files, $module);
        }

        return response()->json([
            'success' => true,
            'files' => $files,
            'html' => view('filearchive.partials.files_grid_content', compact('files'))->render()
        ]);
    }

    /**
     * Calculate storage used by archived files
     */
    private function calculateStorageUsed(string $module = ''): string
    {
        // Prefer indexed byte sizes on scannings table for speed and accuracy.
        $query = Scanning::query();

        if ($module !== '') {
            $query->whereHas('fileIndexing', function ($q) use ($module) {
                $q->where('registry', 'like', '%' . $module . '%');
            });
        }

        // SQL Server SUM(int) returns int; cast to BIGINT to avoid overflow on large datasets.
        $totalBytes = (int) ($query
            ->selectRaw('SUM(CAST(ISNULL(file_size, 0) AS BIGINT)) as total_bytes')
            ->value('total_bytes') ?? 0);

        return $this->formatBytes($totalBytes);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, $power === 0 ? 0 : 1) . ' ' . $units[$power];
    }

    private function isStorageDemoMode(string $module): bool
    {
        $allowed = ['kangis', 'sltr', 'dciv', 'cadastral'];
        return in_array(strtolower(trim($module)), $allowed, true);
    }

    private function getRegistryFolderName(string $module): string
    {
        $map = [
            'kangis' => 'KANGIS_Registry',
            'sltr' => 'SLTR_Registry',
            'dciv' => 'DCIV_Registry',
            'cadastral' => 'Cadastral_Registry',
        ];
        return $map[strtolower(trim($module))] ?? 'KANGIS_Registry';
    }

    private function buildStorageDemoPaginator(Request $request, string $module): LengthAwarePaginator
    {
        $disk     = $this->getStorageDisk($module);
        $basePath = $this->getStorageBasePath($module);

        if (!$disk->exists($basePath)) {
            return new LengthAwarePaginator([], 0, 12, (int) $request->get('page', 1), [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]);
        }

        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'];
        $search             = trim((string) $request->get('search', ''));

        // ── Cached folder index ──────────────────────────────────────────────────
        // The filesystem scan + group-by is expensive when there are many files.
        // Cache it for 5 minutes; the cache is busted when a new file is uploaded
        // (call Cache::forget("filearchive_folder_index_{$module}") after uploads).
        $cacheKey    = "filearchive_folder_index_{$module}";
        $folderIndex = Cache::remember($cacheKey, 300, function () use ($disk, $basePath, $allowedExtensions) {
            try {
                $allFiles = collect($disk->allFiles($basePath));
            } catch (\Throwable $e) {
                $allFiles = collect();
            }

            $normalizedBase = rtrim(str_replace('\\', '/', $basePath), '/');

            // Group by top-level folder
            $groups = $allFiles
                ->filter(fn($p) => in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), $allowedExtensions, true))
                ->groupBy(function (string $path) use ($normalizedBase) {
                    $normalized = ltrim(str_replace('\\', '/', $path), '/');
                    if (str_starts_with($normalized, $normalizedBase . '/')) {
                        $relative = substr($normalized, strlen($normalizedBase) + 1);
                    } elseif (str_starts_with($normalized, $normalizedBase)) {
                        $relative = ltrim(substr($normalized, strlen($normalizedBase)), '/');
                    } else {
                        $relative = $normalized;
                    }
                    $top = explode('/', $relative)[0] ?? $relative;
                    return rtrim($normalizedBase . '/' . trim($top, '/'), '/');
                })
                ->map(fn($files) => collect($files)->values());

            // Compute sort timestamp once per folder (avoids N lastModified calls at render time)
            $sorted = $groups->keys()->values()
                ->map(function (string $folderPath) use ($groups, $disk) {
                    $first = $groups->get($folderPath)?->first();
                    $ts = 0;
                    if ($first) {
                        try { $ts = (int) $disk->lastModified($first); } catch (\Throwable $e) {}
                    }
                    return ['path' => $folderPath, 'ts' => $ts];
                })
                ->sortByDesc('ts')
                ->values();

            return [
                'groups'      => $groups->all(),          // folder → [file paths]
                'sorted'      => $sorted->all(),           // [['path' => ..., 'ts' => ...]]
                'total_files' => $allFiles
                    ->filter(fn($p) => in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), $allowedExtensions, true))
                    ->count(),
            ];
        });

        $groups      = collect($folderIndex['groups']);
        $sortedPaths = collect($folderIndex['sorted']);   // [{path, ts}]
        $totalFiles  = (int) ($folderIndex['total_files'] ?? 0);

        // ── Apply search filter (cheap — only basename matching) ─────────────
        if ($search !== '') {
            $sortedPaths = $sortedPaths->filter(
                fn($entry) => stripos(basename($entry['path']), $search) !== false
            )->values();
        }

        // ── Paginate the folder list ─────────────────────────────────────────
        $perPage     = 12;
        $currentPage = max(1, (int) $request->get('page', 1));
        $offset      = ($currentPage - 1) * $perPage;
        $totalFolders = $sortedPaths->count();

        $pageItems = $sortedPaths->slice($offset, $perPage)->map(function (array $entry) use ($groups, $module) {
            $path     = $entry['path'];
            $ts       = $entry['ts'];
            $files    = collect($groups[$path] ?? [])->values();
            $preview  = $this->pickPreviewFileFromFiles($files->all());
            $encoded  = rtrim(strtr(base64_encode($path), '+/', '-_'), '=');

            return (object) [
                'id'                  => 'storage-demo-' . $encoded,
                'file_number'         => strtoupper(basename($path)),
                'file_title'          => str_replace(['_', '-'], ' ', basename($path)),
                'land_use_type'       => null,
                'district'            => $this->getRegistryFolderName($module),
                'updated_at'          => $ts ? date('Y-m-d H:i:s', $ts) : now()->format('Y-m-d H:i:s'),
                'plot_number'         => null,
                'location'            => null,
                'registry'            => $this->getRegistryFolderName($module),
                'pagetypings_count'   => max(1, $files->count()),
                'scannings_count'     => max(1, $files->count()),
                'fileTracking'        => null,
                'pagetypings'         => collect(),
                'scannings'           => collect(),
                'firstPageTyping'     => null,
                'storage_preview_path' => $preview,
                'storage_preview_url'  => $preview ? $this->getStorageUrl($preview, $module) : null,
            ];
        });

        $paginator = new LengthAwarePaginator(
            $pageItems,
            $totalFolders,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $paginator->storage_total_pages = $totalFiles;
        $paginator->storage_total_bytes = null;

        return $paginator;
    }

    private function decodeStorageDemoPath(string $id, string $module): ?string
    {
        $encoded = substr($id, strlen('storage-demo-'));
        if ($encoded === '') {
            return null;
        }

        $decoded = base64_decode(strtr($encoded, '-_', '+/') . str_repeat('=', (4 - strlen($encoded) % 4) % 4), true);
        if (!$decoded) {
            return null;
        }

        $decoded = trim(str_replace('\\', '/', $decoded), '/');
        $basePath = $this->getStorageBasePath($module);
        if (!str_starts_with(strtolower($decoded), strtolower($basePath))) {
            return null;
        }

        $disk = $this->getStorageDisk($module);
        if ($disk->exists($decoded) || $this->isStorageDemoDirectory($decoded, $module)) {
            return $decoded;
        }

        return null;
    }

    private function isStorageDemoDirectory(string $path, string $module): bool
    {
        $disk = $this->getStorageDisk($module);
        return !empty($disk->files($path)) || !empty($disk->directories($path));
    }

    private function calculateStorageFolderSize(string $module): string
    {
        // Use the already-cached folder index to avoid a second full allFiles scan
        $cacheKey    = "filearchive_folder_index_{$module}";
        $folderIndex = Cache::get($cacheKey);

        if ($folderIndex !== null) {
            $groups = collect($folderIndex['groups'] ?? []);
            $disk   = $this->getStorageDisk($module);
            $totalBytes = $groups->flatten(1)->sum(function ($path) use ($disk) {
                try { return (int) $disk->size($path); } catch (\Throwable $e) { return 0; }
            });
            return $this->formatBytes((int) $totalBytes);
        }

        // Fallback: full scan (only when cache is cold)
        $disk     = $this->getStorageDisk($module);
        $basePath = $this->getStorageBasePath($module);

        if (!$disk->exists($basePath)) {
            return '0 B';
        }

        $totalBytes = collect($disk->allFiles($basePath))->sum(function (string $path) use ($disk) {
            try { return (int) $disk->size($path); } catch (\Throwable $e) { return 0; }
        });

        return $this->formatBytes((int) $totalBytes);
    }

    /** Call this after a file upload to invalidate the folder listing cache. */
    public static function bustFolderIndexCache(string $module): void
    {
        Cache::forget("filearchive_folder_index_{$module}");
        Cache::forget("filearchive_stats_{$module}");
    }

    private function getStorageDisk(string $module)
    {
        $registry = strtoupper(trim($module));
        $rootOverride = trim((string) env("{$registry}_STORAGE_ROOT", env('KANGIS_STORAGE_ROOT', '')));
        if ($rootOverride !== '') {
            return Storage::build([
                'driver' => 'local',
                'root' => $rootOverride,
                'url' => rtrim((string) env("{$registry}_STORAGE_URL_PREFIX", env('KANGIS_STORAGE_URL_PREFIX', '/storage')), '/'),
                'visibility' => 'public',
            ]);
        }

        return Storage::disk(env("{$registry}_STORAGE_DISK", env('KANGIS_STORAGE_DISK', 'public')));
    }

    private function getStorageBasePath(string $module): string
    {
        $registry = strtoupper(trim($module));
        $folderName = $this->getRegistryFolderName($module);

        $configuredBasePath = trim(str_replace('\\', '/', (string) env("{$registry}_REGISTRY_BASE_PATH", env('KANGIS_REGISTRY_BASE_PATH', ''))), '/');
        if ($configuredBasePath !== '') {
            return $configuredBasePath;
        }

        $rootOverride = trim((string) env("{$registry}_STORAGE_ROOT", env('KANGIS_STORAGE_ROOT', '')));
        if ($rootOverride !== '') {
            $disk = $this->getStorageDisk($module);
            $appPublicPath = "app/public/EDMS/UPLOAD/{$folderName}";
            if ($disk->exists($appPublicPath)) {
                return $appPublicPath;
            }
        }

        return "EDMS/UPLOAD/{$folderName}";
    }

    private function getStorageUrl(string $relativePath, string $module): string
    {
        $registry = strtoupper(trim($module));
        $prefix = rtrim((string) env("{$registry}_STORAGE_URL_PREFIX", env('KANGIS_STORAGE_URL_PREFIX', '/storage')), '/');
        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (str_starts_with(strtolower($normalized), 'app/public/')) {
            $normalized = substr($normalized, strlen('app/public/'));
        }

        return $prefix . '/' . ltrim($normalized, '/');
    }

    private function getStorageDemoFiles(string $folderPath, array $allowedExtensions, $disk): array
    {
        return collect($disk->allFiles($folderPath))
            ->filter(function (string $path) use ($allowedExtensions) {
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                return in_array($extension, $allowedExtensions, true);
            })
            ->values()
            ->all();
    }

    private function pickPreviewFileFromFiles(array $files): ?string
    {
        if (empty($files)) {
            return null;
        }

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'];
        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($extension, $imageExtensions, true)) {
                return $file;
            }
        }

        return $files[0];
    }

    private function countStoragePages(string $module): int
    {
        $disk = $this->getStorageDisk($module);
        $basePath = $this->getStorageBasePath($module);

        if (!$disk->exists($basePath)) {
            return 0;
        }

        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'];

        return (int) collect($disk->directories($basePath))
            ->sum(function (string $folderPath) use ($allowedExtensions, $disk, $module) {
                return count($this->getStorageDemoFiles($folderPath, $allowedExtensions, $disk));
            });
    }

    private function enrichStorageMetadataForPaginator(LengthAwarePaginator $paginator, string $module): LengthAwarePaginator
    {
        foreach ($paginator->items() as $item) {
            $fileNumber = trim((string) ($item->file_number ?? ''));
            if ($fileNumber === '') {
                continue;
            }

            $folderPath = $this->resolveStorageFolderByFileNumber($fileNumber, $module);
            if (!$folderPath) {
                continue;
            }

            $files = collect($this->getStorageDemoFiles(
                $folderPath,
                ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'],
                $this->getStorageDisk($module)
            ));

            if ($files->isEmpty()) {
                continue;
            }

            $previewPath = $this->pickPreviewFileFromFiles($files->all());
            $item->storage_folder_path = $folderPath;
            $item->storage_page_count = $files->count();
            $item->storage_preview_path = $previewPath;
            $item->storage_preview_url = $previewPath ? $this->getStorageUrl($previewPath, $module) : null;
        }

        return $paginator;
    }

    private function resolveStorageFolderByFileNumber(string $fileNumber, string $module): ?string
    {
        $basePath = $this->getStorageBasePath($module);
        $disk = $this->getStorageDisk($module);
        $normalized = trim(str_replace('\\', '/', $fileNumber));

        $candidates = array_values(array_unique([
            trim($basePath . '/' . $normalized, '/'),
            trim($basePath . '/' . str_replace('-', ' ', $normalized), '/'),
            trim($basePath . '/' . str_replace(' ', '-', $normalized), '/'),
            trim($basePath . '/' . str_replace('/', '-', $normalized), '/'),
            trim($basePath . '/' . str_replace(['/', '-'], ' ', $normalized), '/'),
        ]));

        foreach ($candidates as $candidate) {
            if ($this->isStorageDemoDirectory($candidate, $module)) {
                return $candidate;
            }
        }

        $target = strtolower(preg_replace('/\s+/', ' ', str_replace(['-', '/'], ' ', $normalized)));
        foreach ($disk->directories($basePath) as $directory) {
            $name = basename(str_replace('\\', '/', $directory));
            $normalizedName = strtolower(preg_replace('/\s+/', ' ', str_replace(['-', '/'], ' ', $name)));
            if ($normalizedName === $target && $this->isStorageDemoDirectory($directory, $module)) {
                return $directory;
            }
        }

        return null;
    }

    private function buildStorageDocumentPagesPayload(string $path, string $id, string $module, ?string $overrideFileNumber = null, ?string $overrideTitle = null): array
    {
        $disk = $this->getStorageDisk($module);
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'];
        $documentExtensions = array_merge($imageExtensions, ['pdf']);

        $paths = collect();
        if ($this->isStorageDemoDirectory($path, $module)) {
            $paths = collect($disk->allFiles($path));
        } elseif ($disk->exists($path)) {
            $paths = collect([$path]);
        }

        $paths = $paths
            ->filter(function (string $filePath) use ($documentExtensions) {
                $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                return in_array($extension, $documentExtensions, true);
            })
            ->sortBy(function (string $filePath) {
                $filename = pathinfo($filePath, PATHINFO_FILENAME);
                preg_match('/^(\d+)/', $filename, $matches);
                $prefix = isset($matches[1]) ? (int) $matches[1] : PHP_INT_MAX;
                return sprintf('%010d-%s', $prefix, strtolower($filename));
            })
            ->values();

        if ($paths->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No files found for this record.',
                'file' => [
                    'id' => $id,
                    'file_number' => $overrideFileNumber ?: '-',
                    'file_title' => $overrideTitle ?: '-',
                    'total_pages' => 0,
                ],
                'pages' => []
            ];
        }

        $fileLabel = basename($this->isStorageDemoDirectory($path, $module) ? $path : dirname($path));
        $fileNumber = $overrideFileNumber ?: strtoupper($fileLabel);
        $fileTitle = $overrideTitle ?: str_replace(['_', '-'], ' ', $fileLabel);

        $pages = $paths->values()->map(function (string $filePath, int $index) use ($imageExtensions, $module) {
            $viewerUrl = $this->getStorageUrl($filePath, $module);
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $filename = pathinfo($filePath, PATHINFO_FILENAME);
            $mediaType = in_array($extension, $imageExtensions, true) ? 'image' : ($extension === 'pdf' ? 'pdf' : 'document');

            return [
                'page_number' => $index + 1,
                'page_type' => null,
                'page_subtype' => null,
                'page_code' => strtoupper($filename),
                'definition' => null,
                'definition_code' => null,
                'serial_number' => null,
                'cover_type' => null,
                'typed_by' => null,
                'viewer_url' => $viewerUrl,
                'thumbnail_url' => $mediaType === 'image' ? $viewerUrl : null,
                'media_type' => $mediaType,
                'media_source' => 'storage',
                'pdf_page_number' => null,
                'scanning_id' => null,
                'scanning_display_order' => null,
                'scanning_original_filename' => basename($filePath),
                'scanning_document_type' => strtoupper($extension),
                'scanning_document_path' => $filePath,
                'created_at' => now()->format('Y-m-d H:i:s')
            ];
        })->values();

        return [
            'success' => true,
            'file' => [
                'id' => $id,
                'file_number' => $fileNumber,
                'file_title' => $fileTitle,
                'total_pages' => $pages->count(),
            ],
            'pages' => $pages,
        ];
    }
}



