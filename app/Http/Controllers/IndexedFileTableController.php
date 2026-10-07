<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExecutesMasterDelete;
use App\Models\FileIndexing;
use App\Services\AuditService;
use App\Services\IndexedFilesFilterOptions;
use App\Services\IndexingDuplicateService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class IndexedFileTableController extends Controller
{
    // Typed confirmation + audit entry for Master Delete, shared with the
    // Recommendation / RofO master deletes so all of them read the same.
    use ExecutesMasterDelete;

    public function __construct(private IndexedFilesFilterOptions $filterOptions)
    {
    }

    public function index()
    {
        $PageTitle = 'Indexed Files';
        $PageDescription = 'Browse indexed files with fast server-side pagination.';

        return view('indexed_files.index', compact('PageTitle', 'PageDescription'));
    }

    public function stats(Request $request): JsonResponse
    {
        $registry = trim((string) $request->input('registry', ''));
        $isCorrespondingFile = filter_var($request->input('is_corresponding_file', false), FILTER_VALIDATE_BOOLEAN);
        $cacheRegistry = $registry !== '' ? strtoupper($registry) : 'ALL';
        // The counts below are scoped to the viewer for non-admins, so the cache key
        // has to carry the viewer too - otherwise the first caller's private totals
        // are served to everyone else for the next 60 seconds.
        $cacheScope = (string) (Auth::id() ?? 'guest');
        $cacheKey = $isCorrespondingFile
            ? "indexed_files_stats_{$cacheRegistry}_corresponding_u{$cacheScope}"
            : "indexed_files_stats_{$cacheRegistry}_u{$cacheScope}";

        $stats = Cache::remember($cacheKey, 60, function () use ($registry, $isCorrespondingFile) {
            $baseQuery = FileIndexing::on('sqlsrv');

            if ($registry !== '') {
                if (strtoupper($registry) === 'KANGIS') {
                    $baseQuery->whereIn('registry', ['KANGIS', 'KANGIS Registry']);
                } else {
                    $baseQuery->where('registry', $registry);
                }
            }

            if ($isCorrespondingFile) {
                $baseQuery->where('is_corresponding_file', 1);
            }

            // Apply user-level restriction for non-admins, except for KANGIS.
            // The tiles count the operator's own work, so this scope always applies
            // here - only the searchable listings open up to every indexer.
            $this->applyOwnFilesScope($baseQuery, $registry, false);

            $totalIndexed = (clone $baseQuery)->count();

            $today = Carbon::today('Africa/Lagos');
            $indexedToday = (clone $baseQuery)
                ->where('created_at', '>=', $today)
                ->count();

            $uniqueRegistries = (clone $baseQuery)
                ->whereNotNull('registry')
                ->where('registry', '<>', '')
                ->distinct('registry')
                ->count('registry');

            $topLandUses = (clone $baseQuery)
                ->select('land_use_type', DB::raw('COUNT(*) as total'))
                ->groupBy('land_use_type')
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->limit(4)
                ->get()
                ->map(function ($row) {
                    return [
                        'land_use_type' => $row->land_use_type ?? 'Unknown',
                        'total' => (int) $row->total,
                    ];
                });

            return [
                'total_indexed' => $totalIndexed,
                'indexed_today' => $indexedToday,
                'unique_registries' => $uniqueRegistries,
                'top_land_uses' => $topLandUses,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));
        $page = max(1, (int) $request->input('page', 1));
        $registry = $request->input('registry');
        $query = $this->buildListQuery($request);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->listResponse($paginator, $registry);
    }

    /** Most rows one export will return; an unfiltered export would be ~170,000. */
    private const EXPORT_LIMIT = 20000;

    /**
     * Download the rows the table is showing as a CSV (opens in Excel).
     *
     * Same rows as the table: the page sends its search box, registry and Advanced
     * Search filters straight to this URL - no preview step.
     */
    public function export(Request $request)
    {
        $query = $this->buildListQuery($request);

        if ($from = $this->parseFilterDate($request->input('start_date'))) {
            $query->where('file_indexings.created_at', '>=', $from->startOfDay());
        }
        if ($to = $this->parseFilterDate($request->input('end_date'))) {
            $query->where('file_indexings.created_at', '<=', $to->endOfDay());
        }

        $items = $query->limit(self::EXPORT_LIMIT)->get();

        $creatorIds = $items->pluck('created_by')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '' && ctype_digit($value) && (int) $value > 0)
            ->unique()->values();
        $creators = collect();
        foreach ($creatorIds->chunk(1000) as $chunk) {
            $creators = $creators->union(DB::connection('sqlsrv')->table('users')
                ->whereIn('id', $chunk->map(fn ($value) => (int) $value)->all())
                ->get(['id', 'first_name', 'last_name', 'username'])
                ->mapWithKeys(function ($user) {
                    $name = trim(sprintf('%s %s', $user->first_name ?? '', $user->last_name ?? ''));
                    return [(string) $user->id => $name !== '' ? $name : trim((string) ($user->username ?? ''))];
                })
                ->filter());
        }

        $dash = fn ($value) => ($value === null || trim((string) $value) === '') ? '-' : trim((string) $value);

        $shelfLookup = $this->buildBatchShelfLookup($items, collect());

        $data = $items->values()->map(function ($item, $i) use ($creators, $dash, $shelfLookup) {
            $fileNo = trim((string) $item->file_number);
            if ($fileNo === '' || $fileNo === '-') {
                $fileNo = (string) $item->temp_file_no;
            }

            return [
                'sn' => $i + 1,
                'file_number' => $dash($fileNo),
                'file_title' => $dash($item->file_title),
                'general_registry' => $dash($item->general_registry),
                'plot_number' => $dash($item->plot_number),
                'tp_no' => $dash($item->tp_no),
                'land_use_type' => $dash($item->land_use_type),
                'district' => $dash($item->district),
                'lga' => $dash($item->lga),
                'shelf_location' => $dash($this->resolveShelfLocation($item, $shelfLookup)),
                'indexed_by' => $this->resolveCreatorName($item->created_by, $creators),
                'indexed_at' => $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('Y-m-d') : '-',
            ];
        })->all();

        $headings = ['S/N', 'File No', 'File Name', 'Gen. Registry', 'Plot No', 'TP No', 'Land Use',
            'District', 'LGA', 'Shelf/Rack', 'Indexed By', 'Indexed Date'];
        $filename = 'indexed-files-' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($headings, $data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads names correctly
            fputcsv($out, $headings);
            foreach ($data as $row) {
                fputcsv($out, array_values($row));
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * The listing query - search box, registry, Advanced Search filters, own-files
     * scope and sort - shared by the table and its export so both see the same rows.
     */
    private function buildListQuery(Request $request)
    {
        $search = $this->normalizeSearch($request->input('search'));
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortMap = [
            'tracking_id' => 'file_indexings.tracking_id',
            'shelf_location' => 'file_indexings.shelf_location',
            'registry' => 'file_indexings.registry',
            'registry_batch_no' => 'file_indexings.registry_batch_no',
            'sys_batch_no' => 'file_indexings.sys_batch_no',
            'batch_no' => 'file_indexings.batch_no',
            'group_no' => 'file_indexings.group',
            'file_number' => 'file_indexings.file_number',
            'file_title' => 'file_indexings.file_title',
            'plot_number' => 'file_indexings.plot_number',
            'created_at' => 'file_indexings.created_at',
            'indexed_at' => 'file_indexings.created_at',
            'tp_no' => 'file_indexings.tp_no',
            'lpkn_no' => 'file_indexings.lpkn_no',
            'land_use_type' => 'file_indexings.land_use_type',
            'district' => 'file_indexings.district',
            'lga' => 'file_indexings.lga',
            'general_registry' => 'file_indexings.general_registry',
            'physical_registry' => 'file_indexings.physical_registry',
            'id' => 'file_indexings.id',
        ];

        // Default to the indexed date, not the id. Backfill commands insert rows with a
        // historical created_at, so a new id no longer implies a newer indexing date and
        // an id-ordered list reads as a broken date sort.
        $sortInput = (string) $request->input('sort', 'created_at');
        $sortColumn = $sortMap[$sortInput] ?? 'file_indexings.created_at';

        $query = FileIndexing::on('sqlsrv')
            ->select([
                'file_indexings.id',
                'file_indexings.tracking_id',
                'file_indexings.shelf_location',
                'file_indexings.registry',
                'file_indexings.registry_batch_no',
                'file_indexings.sys_batch_no',
                'file_indexings.batch_no',
                DB::raw('[file_indexings].[group] as group_no'),
                'file_indexings.file_number',
                'file_indexings.file_title',
                'file_indexings.gender',
                'file_indexings.gender_source',
                'file_indexings.plot_number',
                'file_indexings.created_at',
                'file_indexings.tp_no',
                'file_indexings.lpkn_no',
                'file_indexings.land_use_type',
                'file_indexings.district',
                'file_indexings.lga',
                'file_indexings.general_registry',
                'file_indexings.physical_registry',
                'file_indexings.created_by',
                'file_indexings.batch_generated',
                'file_indexings.last_batch_id',
                'file_indexings.location',
                'file_indexings.temp_file_no',
                'file_indexings.kangis_fileno_placeholder',
                'file_indexings.corresponding_fileno',
                'file_indexings.pp_lands_fileno',
                'file_indexings.pp_lands_matching',
                'file_indexings.pp_lands_date_matched',
                'file_indexings.pp_lands_time_matched',
                'file_indexings.mls_file_no',
                'file_indexings.kangis_file_no',
                'file_indexings.new_kangis_file_no',
                'file_indexings.related_fileno',
                'file_indexings.parent_prop_id',
                'file_indexings.dciv_status',
                'file_indexings.dciv_fileno',
                'file_indexings.dciv_reason',
                'file_indexings.latitude',
                'file_indexings.longitude',
            ]);

        $isCorrespondingFile = filter_var($request->input('is_corresponding_file', false), FILTER_VALIDATE_BOOLEAN);
        if ($isCorrespondingFile) {
            $query->where('file_indexings.is_corresponding_file', 1);
        }

        $registry = $request->input('registry');
        if ($registry !== null && $registry !== '') {
            $registryUpper = strtoupper(trim((string) $registry));

            // Treat 'KANGIS' as covering both 'KANGIS' and 'KANGIS Registry'
            if ($registryUpper === 'KANGIS') {
                $query->whereIn('file_indexings.registry', ['KANGIS', 'KANGIS Registry']);
                $query->addSelect(DB::raw("(SELECT TOP 1 LTRIM(RTRIM(file_number)) FROM file_indexing_links WHERE file_indexing_id = file_indexings.id AND file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> '' ORDER BY id ASC) as related_file_no"));
            } else {
                $query->where('file_indexings.registry', $registry);
            }
        }

        if ($search !== null) {
            $like = '%' . $this->escapeLikePattern($search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('file_indexings.file_number', 'like', $like)
                    ->orWhere('file_indexings.temp_file_no', 'like', $like)
                    ->orWhere('file_indexings.file_title', 'like', $like)
                    ->orWhere('file_indexings.tracking_id', 'like', $like)
                    ->orWhere('file_indexings.registry', 'like', $like)
                    ->orWhere('file_indexings.sys_batch_no', 'like', $like)
                    ->orWhere('file_indexings.batch_no', 'like', $like)
                    ->orWhere(DB::raw('[file_indexings].[group]'), 'like', $like)
                    ->orWhere('file_indexings.plot_number', 'like', $like)
                    ->orWhere('file_indexings.district', 'like', $like)
                    ->orWhere('file_indexings.lga', 'like', $like);
            });
        }

        // Advanced Search - per-column narrowing from the panel above the table.
        $advancedCount = $this->applyAdvancedFilters($query, $request);

        // A non-admin's default listing is their own indexing work, but a search has
        // to reach every file in the registry - a file number is only ever typed in
        // to find that file, and who indexed it is not known to the person looking.
        // An advanced filter is the same kind of deliberate lookup, so it opens the
        // scope too; otherwise a file number typed into the panel finds nothing.
        $this->applyOwnFilesScope($query, $request->input('registry', ''), $search !== null || $advancedCount > 0);

        if ($sortColumn === 'file_indexings.created_at') {
            // id alone breaks the tie: created_at is millisecond-precision so ties are
            // effectively impossible, and leaving updated_at out of the ORDER BY lets
            // idx_file_indexings_created_at satisfy it without a sort operator.
            $query->orderBy('file_indexings.created_at', $direction)
                ->orderBy('file_indexings.id', $direction === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy($sortColumn, $direction);
            if ($sortColumn !== 'file_indexings.id') {
                $query->orderBy('file_indexings.id', 'desc');
            }
        }

        return $query;
    }

    private function listResponse($paginator, $registry): JsonResponse
    {
        $items = Collection::make($paginator->items());

        $indexedIds = $items->pluck('id')->map(function ($value) {
            return is_numeric($value) ? (int) $value : null;
        })->filter()->values();

        // The physical Shelf/Rack label lives in the print-label batch tables and is
        // often missing from file_indexings (KANGIS never syncs it back; MLS labels
        // printed before indexing never sync either). Build a fallback lookup across
        // every registry so the indexed table can show the shelf location.
        $registryUpperForShelf = strtoupper(trim((string) $registry));
        $shelfLookup = $this->buildBatchShelfLookup($items, $indexedIds);

        // New KANGIS (KN####) rows are indexed standalone and carry no Old KANGIS
        // number of their own, so the Kangis FileNo column would show a dash. Resolve
        // it from the surrounding links (cached map, see buildOldKangisLookup()).
        $oldKangisLookup = $registryUpperForShelf === 'KANGIS'
            ? $this->buildOldKangisLookup()
            : [];

        $scanningCounts = $indexedIds->isEmpty()
            ? collect()
            : DB::connection('sqlsrv')
                ->table('scannings')
                ->select('file_indexing_id', DB::raw('COUNT(*) as total'))
                ->whereIn('file_indexing_id', $indexedIds)
                ->groupBy('file_indexing_id')
                ->pluck('total', 'file_indexing_id');

        $pageTypingCounts = $indexedIds->isEmpty()
            ? collect()
            : DB::connection('sqlsrv')
                ->table('pagetypings')
                ->select('file_indexing_id', DB::raw('COUNT(*) as total'))
                ->whereIn('file_indexing_id', $indexedIds)
                ->groupBy('file_indexing_id')
                ->pluck('total', 'file_indexing_id');

        // Keep the actual document counts; only workflow progress starts again.
        $resetBaseline = \App\Support\EdmsWorkflowReset::baseline();
        $workflowScanningCounts = $resetBaseline
            ? \App\Support\EdmsWorkflowReset::counts('scannings', $indexedIds, $resetBaseline) : $scanningCounts;
        $workflowTypingCounts = $resetBaseline
            ? \App\Support\EdmsWorkflowReset::counts('pagetypings', $indexedIds, $resetBaseline) : $pageTypingCounts;

        $relatedFileCounts = $indexedIds->isEmpty()
            ? collect()
            : DB::connection('sqlsrv')
                ->table('file_indexing_links')
                ->select('file_indexing_id', DB::raw('COUNT(*) as total'))
                ->whereIn('file_indexing_id', $indexedIds)
                ->groupBy('file_indexing_id')
                ->pluck('total', 'file_indexing_id');

        // Pre-fetch grouping fallbacks to avoid N+1 queries using display or temporary file number
        $fileNumbers = $items->map(function ($item) {
            $displayFileNo = $item->file_number;
            if (empty($displayFileNo) || trim($displayFileNo) === '' || trim($displayFileNo) === '-') {
                if (!empty($item->temp_file_no) && trim($item->temp_file_no) !== '' && trim($item->temp_file_no) !== '-' && strtoupper(trim($item->temp_file_no)) !== 'NONE') {
                    $displayFileNo = $item->temp_file_no;
                }
            }
            return $displayFileNo;
        })->filter()->unique()->values();

        $groupingFallbacks = $fileNumbers->isEmpty()
            ? collect()
            : DB::connection('sqlsrv')
                ->table('grouping')
                ->whereIn('awaiting_fileno', $fileNumbers)
                ->select('awaiting_fileno', 'registry', 'registry_batch_no', 'sys_batch_no', 'mdc_batch_no')
                ->get()
                ->groupBy('awaiting_fileno')
                ->map(function ($rows) {
                    return $rows->first(); // Take first if multiple
                });

        // Pre-compute which file numbers actually have a folder under any of the
        // EDMS registry roots, so the UI can hide the "View Files" button when empty.
        // Cadastral scans live under Cadastral_Registry1/{corresponding_fileno},
        // so check both file_number and corresponding_fileno as folder keys.
        $edmsKeys = $items->flatMap(function ($item) {
            $keys = [];
            $fn = trim((string) ($item->file_number ?? ''));
            if ($fn !== '' && $fn !== '-') {
                $keys[] = $fn;
            }
            $corr = trim((string) ($item->corresponding_fileno ?? ''));
            if ($corr !== '' && $corr !== '-') {
                $keys[] = $corr;
            }
            return $keys;
        })->filter()->unique()->values();
        $edmsFolderMap = $this->buildEdmsFolderMap($edmsKeys);

        // Pre-compute which file_numbers are flagged as duplicates (present in
        // duplicate_fileno), so the UI can enable the "Duplicate Call-up" action.
        $duplicateSet = $fileNumbers->isEmpty()
            ? collect()
            : DB::connection('sqlsrv')
                ->table('duplicate_fileno')
                ->whereIn('file_number', $fileNumbers)
                ->distinct()
                ->pluck('file_number')
                ->map(fn ($v) => trim((string) $v))
                ->flip();

        // created_by holds a typed display name on most rows but a users.id on ~2,500 of
        // them, so the Indexed By column has to resolve the numeric ones or it prints a
        // bare "50553". Only the ids on this page are looked up.
        $creatorIds = $items->pluck('created_by')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '' && ctype_digit($value) && (int) $value > 0)
            ->unique()
            ->values();

        $creators = $creatorIds->isEmpty()
            ? collect()
            : DB::connection('sqlsrv')->table('users')
                ->whereIn('id', $creatorIds->map(fn ($value) => (int) $value)->all())
                ->get(['id', 'first_name', 'last_name', 'username'])
                ->mapWithKeys(function ($user) {
                    $name = trim(sprintf('%s %s', $user->first_name ?? '', $user->last_name ?? ''));
                    if ($name === '') {
                        $name = trim((string) ($user->username ?? ''));
                    }

                    return [(string) $user->id => $name];
                })
                ->filter();

        $data = $items->map(function ($item) use ($scanningCounts, $pageTypingCounts, $workflowScanningCounts, $workflowTypingCounts, $creators, $groupingFallbacks, $relatedFileCounts, $edmsFolderMap, $duplicateSet, $shelfLookup, $oldKangisLookup) {
            $scanned = (int) ($scanningCounts->get($item->id) ?? 0);
            $typed = (int) ($pageTypingCounts->get($item->id) ?? 0);
            $hasRelatedFilesFromLinks = (int) ($relatedFileCounts->get($item->id) ?? 0) > 0;

            // Check if there are related files in the JSON column
            $jsonRelated = null;
            if (!empty($item->related_fileno)) {
                $jsonRelated = json_decode($item->related_fileno, true);
            }
            $hasRelatedFilesFromJson = !empty($jsonRelated) && is_array($jsonRelated);

            $hasRelatedFiles = $hasRelatedFilesFromLinks || $hasRelatedFilesFromJson;

            $relatedFileDisplay = '-';
            if ($hasRelatedFilesFromJson && count($jsonRelated) > 0) {
                $relatedFileDisplay = $jsonRelated[0];
                if (count($jsonRelated) > 1) {
                    $relatedFileDisplay .= ' (+' . (count($jsonRelated) - 1) . ')';
                }
            } elseif ($hasRelatedFilesFromLinks) {
                // We don't have the first link number easily here without another query or mapping, 
                // so we'll let the JS handle the 'View' button or show a generic count.
                $relatedFileDisplay = 'Linked Records';
            }

            $status = 'Indexed';
            if ((int) $workflowTypingCounts->get($item->id, 0) > 0) {
                $status = 'Typed';
            } elseif ((int) $workflowScanningCounts->get($item->id, 0) > 0) {
                $status = 'Scanned';
            }

            $indexedAt = $item->created_at instanceof Carbon
                ? $item->created_at
                : Carbon::parse($item->created_at);

            // Use display file number with fallback to temp file number
            $displayFileNo = $item->file_number;
            $isTempFallback = false;
            if (empty($displayFileNo) || trim($displayFileNo) === '' || trim($displayFileNo) === '-') {
                if (!empty($item->temp_file_no) && trim($item->temp_file_no) !== '' && trim($item->temp_file_no) !== '-' && strtoupper(trim($item->temp_file_no)) !== 'NONE') {
                    $displayFileNo = $item->temp_file_no;
                    $isTempFallback = true;
                }
            }

            // Use pre-fetched fallback
            $fallback = $groupingFallbacks->get($displayFileNo);

            // Resolve Shelf/Rack: prefer the value stored on the indexed file, then
            // fall back to the print-label batch tables keyed by file_indexing_id
            // first, then by any of the file's number variants.
            $shelfLocation = $this->resolveShelfLocation($item, $shelfLookup);

            $rowData = [
                'id' => (int) $item->id,
                'tracking_id' => $item->tracking_id ?? '-',
                'shelf_location' => $shelfLocation ?? '-',
                'registry' => $item->registry ?: 1,
                'registry_batch_no' => $item->registry_batch_no ?: '-',
                'sys_batch_no' => $item->sys_batch_no ?: '-',
                'batch_no' => $item->batch_no ?: '-',
                'group_no' => $item->group_no ?? '-',
                'file_number' => !empty($displayFileNo) ? $displayFileNo : '-',
                'is_temp_fallback' => $isTempFallback,
                'file_title' => $item->file_title ?? '-',
                // Drives the gender row tint. gender_source separates a captured
                // value from one GenderNormalizer inferred off the file title.
                'gender' => $item->gender ?: null,
                'gender_source' => $item->gender_source ?: null,
                'plot_number' => $item->plot_number ?? '-',
                'indexed_at' => $indexedAt ? $indexedAt->format('Y-m-d H:i') : null,
                'indexed_date' => $indexedAt ? $indexedAt->toDateString() : null,
                'indexed_by' => $this->resolveCreatorName($item->created_by, $creators),
                'tp_no' => $item->tp_no ?? '-',
                'lpkn_no' => $item->lpkn_no ?? '-',
                'land_use_type' => $item->land_use_type ?? '-',
                'district' => $item->district ?? '-',
                'lga' => $item->lga ?? '-',
                'location' => $item->location ?? '-',
                'general_registry' => $item->general_registry
                    ?? \App\Models\FileIndexing::detectRegistryFromFileNumber($displayFileNo)
                    ?? '-',
                'physical_registry' => $item->physical_registry ?? '-',
                'latitude' => $item->latitude !== null ? (float) $item->latitude : null,
                'longitude' => $item->longitude !== null ? (float) $item->longitude : null,
                'status' => $status,
                'batch_generated' => $this->normalizeBoolean($item->batch_generated),
                'last_batch_id' => $item->last_batch_id ?? null,
                'has_related_files' => $hasRelatedFiles,
                'view_url' => route('fileindex.show', ['fileindex' => $item->id]),
                'related_file_display' => $relatedFileDisplay,
            ];

            // Eloquent attributes are dynamic; avoid property_exists() here.
            $rowData['related_file_no_json'] = $item->related_fileno ?? null;
            $rowData['related_file_no'] = $item->related_file_no ?? '-';
            $rowData['temp_file_no'] = $item->temp_file_no ?? null;
            $rowData['kangis_fileno_placeholder'] = $item->kangis_fileno_placeholder ?? null;
            $rowData['corresponding_fileno'] = $item->corresponding_fileno ?? null;
            $rowData['pp_lands_fileno'] = $item->pp_lands_fileno ?? null;
            $rowData['pp_lands_matching'] = (int) ($item->pp_lands_matching ?? 0);
            $rowData['pp_lands_date_matched'] = $item->pp_lands_date_matched ?? null;
            $rowData['pp_lands_time_matched'] = $item->pp_lands_time_matched ?? null;
            $rowData['mls_file_no'] = $item->mls_file_no ?? null;
            $rowData['kangis_file_no'] = $this->resolveOldKangisNumber($item, $displayFileNo, $oldKangisLookup);
            $rowData['new_kangis_file_no'] = $item->new_kangis_file_no ?? null;

            // Flags whether scanned files exist in any EDMS registry folder,
            // keyed by the display file number or its corresponding file number.
            $correspondingKey = trim((string) ($item->corresponding_fileno ?? ''));
            $rowData['has_edms_files'] = (bool) (
                ($edmsFolderMap[$displayFileNo] ?? false)
                || ($correspondingKey !== '' && $correspondingKey !== '-'
                    ? ($edmsFolderMap[$correspondingKey] ?? false)
                    : false)
            );

            // Flags whether this file number is in duplicate_fileno (enables Call-up)
            $rowData['has_duplicate'] = $duplicateSet->has(trim((string) $displayFileNo));

            // DCIV investigation flag: 1 when this file is referenced as a related
            // file by a DCIV record. Reason + DCIV file number are shown when set.
            $rowData['dciv_status'] = (int) ($item->dciv_status ?? 0);
            $rowData['dciv_fileno'] = $item->dciv_fileno ?? null;
            $rowData['dciv_reason'] = $item->dciv_reason ?? null;

            return $rowData;
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function viewList(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));
        $page = max(1, (int) $request->input('page', 1));
        $search = $this->normalizeSearch($request->input('search'));
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Sort mapping for the new view
        $sortMap = [
            'registry' => 'file_indexings.registry',
            'file_number' => 'file_indexings.file_number',
            'file_title' => 'file_indexings.file_title',
            'current_holder' => 'file_indexings.current_holder',
            'original_holder' => 'file_indexings.original_holder',
            'plot_number' => 'file_indexings.plot_number',
            'district' => 'file_indexings.district',
            'lga' => 'file_indexings.lga',
            'general_registry' => 'file_indexings.general_registry',
            'physical_registry' => 'file_indexings.physical_registry',
            'state' => 'state', // Calculated column
            'id' => 'file_indexings.id',
        ];

        $sortInput = (string) $request->input('sort', 'id');
        $sortColumn = $sortMap[$sortInput] ?? 'file_indexings.id';

        $query = FileIndexing::on('sqlsrv')
            ->select([
                'file_indexings.id',
                'file_indexings.registry',
                'file_indexings.file_number',
                'file_indexings.file_title',
                'file_indexings.gender',
                'file_indexings.gender_source',
                'file_indexings.current_holder',
                'file_indexings.original_holder',
                'file_indexings.plot_number',
                'file_indexings.district',
                'file_indexings.lga',
                'file_indexings.general_registry',
                'file_indexings.physical_registry',
                'file_indexings.land_use_type',
                'file_indexings.related_fileno',
                'file_indexings.temp_file_no',
                DB::raw("'Kano' as state"),
            ]);

        if ($search !== null) {
            $like = '%' . $this->escapeLikePattern($search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('file_indexings.file_number', 'like', $like)
                    ->orWhere('file_indexings.temp_file_no', 'like', $like)
                    ->orWhere('file_indexings.file_title', 'like', $like)
                    ->orWhere('file_indexings.registry', 'like', $like)
                    ->orWhere('file_indexings.current_holder', 'like', $like)
                    ->orWhere('file_indexings.original_holder', 'like', $like)
                    ->orWhere('file_indexings.plot_number', 'like', $like)
                    ->orWhere('file_indexings.district', 'like', $like)
                    ->orWhere('file_indexings.lga', 'like', $like);
            });
        }

        // A non-admin's default listing is their own indexing work, but a search has
        // to reach every file in the registry - a file number is only ever typed in
        // to find that file, and who indexed it is not known to the person looking.
        $this->applyOwnFilesScope($query, $request->input('registry', ''), $search !== null);

        // Apply sorting
        if ($sortColumn === 'state') {
            // Constant column, no real sort impact unless mixed
            $query->orderBy('file_indexings.id', $direction === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy($sortColumn, $direction);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $items = Collection::make($paginator->items());

        $indexedIds = $items->pluck('id')->map(function ($value) {
            return is_numeric($value) ? (int) $value : null;
        })->filter()->values();

        $relatedFileCounts = $indexedIds->isEmpty()
            ? collect()
            : DB::connection('sqlsrv')
                ->table('file_indexing_links')
                ->select('file_indexing_id', DB::raw('COUNT(*) as total'))
                ->whereIn('file_indexing_id', $indexedIds)
                ->groupBy('file_indexing_id')
                ->pluck('total', 'file_indexing_id');

        $data = $items->map(function ($item) use ($relatedFileCounts) {
            $hasRelatedFilesFromLinks = (int) ($relatedFileCounts->get($item->id) ?? 0) > 0;

            $jsonRelated = null;
            if (!empty($item->related_fileno)) {
                $jsonRelated = json_decode($item->related_fileno, true);
            }
            $hasRelatedFilesFromJson = !empty($jsonRelated) && is_array($jsonRelated);
            $hasRelatedFiles = $hasRelatedFilesFromLinks || $hasRelatedFilesFromJson;

            $relatedFileDisplay = '-';
            if ($hasRelatedFilesFromJson && count($jsonRelated) > 0) {
                $relatedFileDisplay = $jsonRelated[0];
                if (count($jsonRelated) > 1) {
                    $relatedFileDisplay .= ' (+' . (count($jsonRelated) - 1) . ')';
                }
            } elseif ($hasRelatedFilesFromLinks) {
                $relatedFileDisplay = 'Linked Records';
            }

            $displayFileNo = $item->file_number;
            $isTempFallback = false;
            if (empty($displayFileNo) || trim($displayFileNo) === '' || trim($displayFileNo) === '-') {
                if (!empty($item->temp_file_no) && trim($item->temp_file_no) !== '' && trim($item->temp_file_no) !== '-' && strtoupper(trim($item->temp_file_no)) !== 'NONE') {
                    $displayFileNo = $item->temp_file_no;
                    $isTempFallback = true;
                }
            }

            return [
                'id' => (int) $item->id,
                'registry' => $item->registry ?? 1,
                'file_number' => !empty($displayFileNo) ? $displayFileNo : '-',
                'is_temp_fallback' => $isTempFallback,
                'file_title' => $item->file_title ?? '-',
                'gender' => $item->gender ?: null,
                'gender_source' => $item->gender_source ?: null,
                'current_holder' => $item->current_holder ?? '-',
                'original_holder' => $item->original_holder ?? '-',
                'plot_number' => $item->plot_number ?? '-',
                'district' => $item->district ?? '-',
                'lga' => $item->lga ?? '-',
                'general_registry' => $item->general_registry
                    ?? \App\Models\FileIndexing::detectRegistryFromFileNumber($displayFileNo)
                    ?? '-',
                'physical_registry' => $item->physical_registry ?? '-',
                'land_use_type' => $item->land_use_type ?? '-',
                'state' => $item->state ?? 'Kano',
                'has_related_files' => $hasRelatedFiles,
                'related_file_display' => $relatedFileDisplay,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    private function normalizeBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes'], true);
        }

        return false;
    }

    /**
     * Restrict a file_indexings query to the rows the current user indexed.
     *
     * Skipped for admin-ish roles, for the KANGIS registry, and - importantly -
     * whenever the caller is searching: a global search must find a file no matter
     * who captured it. created_by holds the indexer's display name, not an id.
     */
    private function applyOwnFilesScope($query, $registry, bool $isSearching): void
    {
        if ($isSearching) {
            return;
        }

        $currentUser = Auth::user();
        if (!$currentUser) {
            return;
        }

        $assignRole = strtolower((string) ($currentUser->assign_role ?? ''));
        if (in_array($assignRole, ['super admin', 'supper admin', 'administrator', 'admin', 'editor'])) {
            return;
        }

        if (strtoupper(trim((string) $registry)) === 'KANGIS') {
            return;
        }

        $userName = trim(sprintf('%s %s', $currentUser->first_name ?? '', $currentUser->last_name ?? ''));
        if ($userName === '') {
            $userName = $currentUser->name ?? $currentUser->email ?? null;
        }

        if ($userName) {
            $query->where('file_indexings.created_by', $userName);
        }
    }

    /**
     * Columns the Advanced Search panel can narrow on with a free-text value.
     * Key = request parameter, value = the column to match.
     */
    private const ADVANCED_TEXT_FIELDS = [
        'adv_file_number' => 'file_indexings.file_number',
        'adv_file_title' => 'file_indexings.file_title',
        'adv_plot_number' => 'file_indexings.plot_number',
        'adv_district' => 'file_indexings.district',
    ];

    /**
     * Columns the panel narrows on with an exact value picked from a dropdown.
     */
    private const ADVANCED_EXACT_FIELDS = [
        'adv_general_registry' => 'file_indexings.general_registry',
        'adv_land_use_type' => 'file_indexings.land_use_type',
        'adv_lga' => 'file_indexings.lga',
        'adv_created_by' => 'file_indexings.created_by',
    ];

    /**
     * Apply the Advanced Search panel's filters to the listing query.
     *
     * Every clause is AND-ed: the panel narrows where the global search box widens,
     * so "anything mentioning Kano" can be combined with "indexed by me in July".
     *
     * @return int how many filters were actually applied (0 = the panel was empty)
     */
    private function applyAdvancedFilters($query, Request $request): int
    {
        $applied = 0;

        // contains (default) | starts | exact - applies to the free-text boxes only.
        $mode = strtolower(trim((string) $request->input('adv_match_mode', 'contains')));
        if (!in_array($mode, ['contains', 'starts', 'exact'], true)) {
            $mode = 'contains';
        }

        foreach (self::ADVANCED_TEXT_FIELDS as $param => $column) {
            $value = $this->normalizeSearch($request->input($param));
            if ($value === null) {
                continue;
            }

            $applied++;

            if ($mode === 'exact') {
                $query->where($column, $value);
                continue;
            }

            $escaped = $this->escapeLikePattern($value);
            $query->where($column, 'like', $mode === 'starts' ? $escaped . '%' : '%' . $escaped . '%');
        }

        foreach (self::ADVANCED_EXACT_FIELDS as $param => $column) {
            $value = $this->normalizeSearch($request->input($param));
            if ($value === null) {
                continue;
            }

            $applied++;

            // lga and created_by are offered as a canonical value standing for a set of
            // raw spellings (see IndexedFilesFilterOptions), so picking "Kumbotso" has
            // to match KUMBTSO too, and picking a person has to match both the rows that
            // named them and the rows that recorded their users.id.
            $rawColumn = str_replace('file_indexings.', '', $column);
            $variants = $this->filterOptions->rawValuesFor(
                $rawColumn,
                $value,
                (string) $request->input('registry', '')
            );

            if (count($variants) === 1) {
                $query->where($column, $variants[0]);
            } else {
                $query->whereIn($column, $variants);
            }
        }

        $from = $this->parseFilterDate($request->input('adv_indexed_from'));
        if ($from) {
            $applied++;
            $query->where('file_indexings.created_at', '>=', $from->startOfDay());
        }

        $to = $this->parseFilterDate($request->input('adv_indexed_to'));
        if ($to) {
            $applied++;
            // Inclusive of the whole end day - a user picking today expects today's rows.
            $query->where('file_indexings.created_at', '<', $to->copy()->addDay()->startOfDay());
        }

        return $applied;
    }

    /**
     * Parse a yyyy-mm-dd value from the panel. Anything unparseable is ignored so a
     * half-typed date never silently empties the table.
     */
    private function parseFilterDate($value): ?Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw, 'Africa/Lagos');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Dropdown values for the Advanced Search panel, read from the rows that exist.
     *
     * district is deliberately absent: it holds ~8,800 distinct values, which is a
     * text box, not a dropdown.
     */
    public function filterOptions(Request $request): JsonResponse
    {
        $built = $this->filterOptions->build(trim((string) $request->input('registry', '')));

        return response()->json([
            'success' => true,
            'data' => $built['options'],
        ]);
    }

    private function normalizeSearch($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function escapeLikePattern(string $value): string
    {
        return addcslashes($value, '%_[]');
    }

    /**
     * Normalise a file number for cross-table matching (strip separators/case).
     */
    private function normalizeFileNoKey($value): string
    {
        $normalized = strtoupper(trim((string) $value));
        if ($normalized === '' || $normalized === '-' || $normalized === 'NONE') {
            return '';
        }

        // '_' is a separator variant of '-' in this data (RES_RC_1983_1147), which the
        // related_file_number rebuild normalises the same way — so it is stripped too.
        return preg_replace('/[\s\-\/\._]+/', '', $normalized);
    }

    /** Legacy ("Old") KANGIS file-number prefixes. */
    private const OLD_KANGIS_PREFIXES = ['KNML', 'MLKN', 'MNKL', 'KNGP'];

    /**
     * True for a legacy KANGIS number (KNML/MLKN/MNKL/KNGP — usually with a space,
     * e.g. "MLKN 341").
     */
    private function isOldKangisNumber($value): bool
    {
        $normalized = strtoupper(trim((string) $value));

        foreach (self::OLD_KANGIS_PREFIXES as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True for a New KANGIS number: "KN" + digits with no separator ("KN3561").
     * "KN 120" / "KN-120" is a legacy land file, not a New KANGIS number.
     */
    private function isNewKangisNumber($value): bool
    {
        return (bool) preg_match('/^KN\d+$/', strtoupper(trim((string) $value)));
    }

    /**
     * Resolve the Old KANGIS number to display for an indexed row.
     *
     * A stored kangis_file_no that merely repeats the row's own file number
     * (e.g. KN153 -> "KN153") is noise rather than a mapping, so it is dropped and
     * the resolved lookup is used instead.
     */
    private function resolveOldKangisNumber($item, ?string $displayFileNo, array $lookup): ?string
    {
        $existing = trim((string) ($item->kangis_file_no ?? ''));
        $ownKey = $this->normalizeFileNoKey($displayFileNo);

        if ($existing !== '' && $this->normalizeFileNoKey($existing) !== $ownKey) {
            return $existing;
        }

        return $lookup[$ownKey] ?? null;
    }

    /**
     * Map every New KANGIS number (KN####) to its Old KANGIS counterpart.
     *
     * New KANGIS files are indexed on their own row with nothing in kangis_file_no,
     * so the legacy number has to be reverse-looked-up from the links around them.
     * Sources, in descending trust (they were verified not to disagree):
     *   1. kangis_mapping        — curated overrides
     *   2. parent_prop_id        — the parent row created by kangis:index-new-kangis
     *   3. new_kangis_file_no    — an Old KANGIS row pointing down at the KN
     *   4. related_fileno JSON   — an Old KANGIS row listing the KN among its links
     *
     * PropID_Master / fileNumber are deliberately NOT used: their kangisFileNo is not
     * reliably a KANGIS number (see LegalSearchService::resolveKangisAliasForLandFile)
     * and for KN rows it usually just repeats the KN itself.
     *
     * Cached because the map covers the whole registry, not just the current page.
     *
     * @return array<string,string> normalised KN number => Old KANGIS number
     */
    private function buildOldKangisLookup(): array
    {
        return Cache::remember('indexed_files_old_kangis_lookup', 600, function () {
            $conn = DB::connection('sqlsrv');
            $map = [];

            // First writer wins, so sources are consumed in trust order.
            $put = function ($newKangis, $oldKangis) use (&$map): void {
                $key = $this->normalizeFileNoKey($newKangis);
                $value = trim((string) $oldKangis);

                if ($key === '' || isset($map[$key])) {
                    return;
                }
                if (!$this->isNewKangisNumber($newKangis) || !$this->isOldKangisNumber($value)) {
                    return;
                }

                $map[$key] = $value;
            };

            // 1. Curated mapping table.
            try {
                foreach ($conn->table('kangis_mapping')->get(['file_number', 'kangis_file_no']) as $row) {
                    $put($row->file_number, $row->kangis_file_no);
                }
            } catch (\Throwable $e) {
                // Table may not exist in some environments — skip silently.
            }

            // 2. parent_prop_id -> the parent file's own number.
            try {
                $knRows = $conn->table('file_indexings')
                    ->whereNull('deleted_at')
                    ->where('file_number', 'like', 'KN[0-9]%')
                    ->whereNotNull('parent_prop_id')
                    ->get(['file_number', 'parent_prop_id']);

                $parentPropIds = $knRows->pluck('parent_prop_id')->filter()->unique()->values();
                $parents = [];
                if ($parentPropIds->isNotEmpty()) {
                    foreach ($conn->table('file_indexings')
                        ->whereNull('deleted_at')
                        ->whereIn('prop_id', $parentPropIds->all())
                        ->get(['prop_id', 'file_number']) as $parent) {
                        if ($this->isOldKangisNumber($parent->file_number)) {
                            $parents[(string) $parent->prop_id] = trim((string) $parent->file_number);
                        }
                    }
                }

                foreach ($knRows as $row) {
                    $parentNo = $parents[(string) $row->parent_prop_id] ?? null;
                    if ($parentNo !== null) {
                        $put($row->file_number, $parentNo);
                    }
                }
            } catch (\Throwable $e) {
                // fail-open: the column simply stays blank
            }

            // 3. An Old KANGIS row naming the KN in new_kangis_file_no.
            try {
                foreach ($conn->table('file_indexings')
                    ->whereNull('deleted_at')
                    ->where('new_kangis_file_no', 'like', 'KN[0-9]%')
                    ->get(['file_number', 'new_kangis_file_no']) as $row) {
                    if ($this->isOldKangisNumber($row->file_number)) {
                        $put($row->new_kangis_file_no, $row->file_number);
                    }
                }
            } catch (\Throwable $e) {
                // fail-open
            }

            // 4. An Old KANGIS row listing the KN in its related_fileno JSON.
            try {
                foreach ($conn->table('file_indexings')
                    ->whereNull('deleted_at')
                    ->where('related_fileno', 'like', '%"KN%')
                    ->get(['file_number', 'related_fileno']) as $row) {
                    if (!$this->isOldKangisNumber($row->file_number)) {
                        continue;
                    }

                    $related = json_decode((string) $row->related_fileno, true);
                    if (!is_array($related)) {
                        continue;
                    }

                    foreach ($related as $candidate) {
                        $put($candidate, $row->file_number);
                    }
                }
            } catch (\Throwable $e) {
                // fail-open
            }

            return $map;
        });
    }

    /**
     * Every registry's print-label table (the set FileLocationResolver::LABEL_TABLES
     * reads). When a file has labels in several, the latest label wins.
     */
    private const SHELF_LABEL_TABLES = [
        'kangis_print_label_batch_items',
        'sltr_print_label_batch_items',
        'st_print_label_batch_items',
        'cadastral_print_label_batch_items',
        'dciv_print_label_batch_items',
        'print_label_batch_items',
    ];

    /**
     * Build a Shelf/Rack lookup from the print-label batch tables of every registry.
     * The batch items carry the assigned shelf label (e.g. "A1"); we key the
     * result by file_indexing_id and by normalised file number so the indexed
     * table can display the shelf even when file_indexings.shelf_location is blank.
     *
     * Labels printed before the file was indexed carry a placeholder (<= 0)
     * file_indexing_id and are never synced onto file_indexings, so the
     * file-number match is what finds most of them.
     *
     * @return array{byId: array<int,string>, byFileNo: array<string,string>}
     */
    private function buildBatchShelfLookup(Collection $items, Collection $indexedIds): array
    {
        $byId = [];
        $byFileNo = [];

        // Only rows still lacking a shelf need the lookup.
        $items = $items->filter(fn ($item) => $this->isBlankShelf($item->shelf_location ?? null));
        $indexedIds = $items->pluck('id')
            ->filter(fn ($value) => is_numeric($value) && (int) $value > 0)
            ->map(fn ($value) => (int) $value)
            ->unique()->values();

        // Gather candidate file numbers across the columns that may hold the
        // number that was used when the label batch was generated.
        $candidateNumbers = collect();
        foreach ($items as $item) {
            foreach ($this->shelfCandidateNumbers($item) as $candidate) {
                if ($this->normalizeFileNoKey($candidate) !== '') {
                    $candidateNumbers->push(trim((string) $candidate));
                }
            }
        }
        $candidateNumbers = $candidateNumbers->unique()->values();

        if ($indexedIds->isEmpty() && $candidateNumbers->isEmpty()) {
            return ['byId' => $byId, 'byFileNo' => $byFileNo];
        }

        foreach (self::SHELF_LABEL_TABLES as $table) {
            try {
                if ($indexedIds->count() + $candidateNumbers->count() > 2000) {
                    // CSV export (up to 20,000 rows): SQL Server caps a statement at
                    // 2,100 parameters and file_number is unindexed, so one streamed
                    // pass over the table beats dozens of chunked scans.
                    $wantedIds = array_flip($indexedIds->all());
                    $wantedNos = array_flip($candidateNumbers->map(fn ($n) => $this->normalizeFileNoKey($n))->all());
                    $rows = DB::connection('sqlsrv')->table($table)
                        ->select('id', 'file_indexing_id', 'file_number', 'shelf_location', 'created_at')
                        ->whereNotNull('shelf_location')
                        ->where('shelf_location', '<>', '')
                        ->cursor()
                        ->filter(fn ($row) => isset($wantedIds[(int) $row->file_indexing_id])
                            || isset($wantedNos[$this->normalizeFileNoKey($row->file_number)]));
                } else {
                    $rows = $this->shelfLabelRows($table, 'file_indexing_id', $indexedIds->all())
                        ->merge($this->shelfLabelRows($table, 'file_number', $candidateNumbers->all()));
                }

                foreach ($rows as $row) {
                    $shelf = trim((string) $row->shelf_location);
                    if ($shelf === '' || stripos($shelf, 'N/A') !== false) {
                        continue;
                    }
                    $label = [
                        'shelf' => $shelf,
                        'no' => $this->normalizeFileNoKey($row->file_number),
                        // Latest label wins, across every table: created_at, then id.
                        'rank' => sprintf('%s|%012d', (string) $row->created_at, (int) $row->id),
                    ];
                    if ((int) $row->file_indexing_id > 0) {
                        $this->keepLatestLabel($byId, (int) $row->file_indexing_id, $label);
                    }
                    if ($label['no'] !== '') {
                        $this->keepLatestLabel($byFileNo, $label['no'], $label);
                    }
                }
            } catch (\Throwable $e) {
                // Table may not exist in some environments — skip silently.
                continue;
            }
        }

        return ['byId' => $byId, 'byFileNo' => $byFileNo];
    }

    private function keepLatestLabel(array &$map, $key, array $label): void
    {
        if (!isset($map[$key]) || strcmp($label['rank'], $map[$key]['rank']) > 0) {
            $map[$key] = $label;
        }
    }

    private function shelfLabelRows(string $table, string $column, array $values): Collection
    {
        if (empty($values)) {
            return collect();
        }

        return DB::connection('sqlsrv')->table($table)
            ->select('id', 'file_indexing_id', 'file_number', 'shelf_location', 'created_at')
            ->whereIn($column, $values)
            ->whereNotNull('shelf_location')
            ->where('shelf_location', '<>', '')
            ->get();
    }

    private function shelfCandidateNumbers($item): array
    {
        return [
            $item->file_number ?? null,
            $item->kangis_fileno_placeholder ?? null,
            $item->new_kangis_file_no ?? null,
            $item->kangis_file_no ?? null,
            $item->mls_file_no ?? null,
            $item->temp_file_no ?? null,
            $item->st_fillno ?? null,
            $item->dciv_fileno ?? null,
        ];
    }

    private function isBlankShelf($value): bool
    {
        return $value === null || in_array(trim((string) $value), ['', '-'], true);
    }

    /**
     * Shelf/Rack for one row: the value stored on the indexed file, else the
     * latest print-label matched by file_indexing_id or any of the file's numbers.
     * (shelf:backfill-from-labels writes that same latest label onto the file.)
     */
    private function resolveShelfLocation($item, array $shelfLookup): ?string
    {
        if (!$this->isBlankShelf($item->shelf_location ?? null)) {
            return trim((string) $item->shelf_location);
        }

        $keys = [];
        foreach ($this->shelfCandidateNumbers($item) as $candidate) {
            $candidateKey = $this->normalizeFileNoKey($candidate);
            if ($candidateKey !== '') {
                $keys[$candidateKey] = true;
            }
        }

        $best = null;
        // An id match counts only when the label's number belongs to this file,
        // so a label mis-linked to the wrong file_indexing_id is ignored.
        $idLabel = $shelfLookup['byId'][(int) $item->id] ?? null;
        if ($idLabel && ($idLabel['no'] === '' || isset($keys[$idLabel['no']]))) {
            $best = $idLabel;
        }
        foreach (array_keys($keys) as $key) {
            $label = $shelfLookup['byFileNo'][$key] ?? null;
            if ($label && ($best === null || strcmp($label['rank'], $best['rank']) > 0)) {
                $best = $label;
            }
        }

        return $best['shelf'] ?? null;
    }

    private function resolveCreatorName($rawCreatedBy, Collection $creators): string
    {
        if ($rawCreatedBy === null) {
            return 'Unknown';
        }

        $trimmed = trim((string) $rawCreatedBy);
        if ($trimmed === '') {
            return 'Unknown';
        }

        // Most rows hold the indexer's name already; the rest hold their users.id, and
        // '0' is the system sentinel that names nobody.
        if (ctype_digit($trimmed)) {
            return $creators->get($trimmed) ?? 'Unknown';
        }

        return $trimmed;
    }

    /**
     * Get fallback batch/registry data from grouping table
     */
    private function getGroupingFallback(?string $fileNumber): array
    {
        if (!$fileNumber) {
            return [];
        }

        $grouping = DB::connection('sqlsrv')
            ->table('grouping')
            ->where('awaiting_fileno', $fileNumber)
            ->select('registry', 'registry_batch_no', 'sys_batch_no', 'mdc_batch_no')
            ->orderByDesc('date_index')
            ->orderByDesc('id')
            ->first();

        if (!$grouping) {
            return [];
        }

        return [
            'registry' => $grouping->registry,
            'registry_batch_no' => $grouping->registry_batch_no,
            'sys_batch_no' => $grouping->sys_batch_no,
            'mdc_batch_no' => $grouping->mdc_batch_no,
        ];
    }

    public function getRelatedFiles($id): JsonResponse
    {
        try {
            // 1. Fetch links from the file_indexing_links table (usually children/subdivisions)
            $relatedLinks = DB::connection('sqlsrv')
                ->table('file_indexing_links as fil')
                ->join('file_indexings as fi', 'fil.file_indexing_id', '=', 'fi.id')
                ->where('fil.file_indexing_id', $id)
                ->select([
                    'fil.id',
                    'fil.file_indexing_id',
                    'fil.file_number',
                    'fil.file_title',
                    'fil.plot_number',
                    'fil.tp_no',
                    'fil.lpkn_no',
                    'fil.location',
                    'fil.created_by',
                    'fil.created_at',
                    'fi.file_number as main_file_number',
                    'fi.temp_file_no as main_temp_file_no'
                ])
                ->get()
                ->toArray();

            $relatedLinks = array_map(function ($link) {
                $linkArr = (array) $link;
                $mainFn = $linkArr['main_file_number'] ?? '';
                $mainIsTempFallback = false;
                if (empty($mainFn) || trim($mainFn) === '' || trim($mainFn) === '-') {
                    $mainTemp = $linkArr['main_temp_file_no'] ?? '';
                    if (!empty($mainTemp) && trim($mainTemp) !== '' && trim($mainTemp) !== '-' && strtoupper(trim($mainTemp)) !== 'NONE') {
                        $linkArr['main_file_number'] = $mainTemp;
                        $mainIsTempFallback = true;
                    }
                }
                $linkArr['main_is_temp_fallback'] = $mainIsTempFallback;
                return $linkArr;
            }, $relatedLinks);

            // 2. Fetch the main record to check for parent links in the JSON column
            $mainRecord = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $id)
                ->select(['file_number', 'related_fileno', 'file_title', 'temp_file_no', 'prop_id', 'parent_prop_id'])
                ->first();

            $finalResults = $relatedLinks;

            if ($mainRecord && !empty($mainRecord->related_fileno)) {
                $parents = $this->parseRelatedFilenoList($mainRecord->related_fileno);

                if (is_array($parents)) {
                    // Fetch all titles for these parent numbers in one query
                    $parentTitles = DB::connection('sqlsrv')
                        ->table('file_indexings')
                        ->whereIn('file_number', $parents)
                        ->pluck('file_title', 'file_number')
                        ->toArray();

                    $mainFileNumber = $mainRecord->file_number;
                    $mainIsTempFallback = false;
                    if (empty($mainFileNumber) || trim($mainFileNumber) === '' || trim($mainFileNumber) === '-') {
                        if (!empty($mainRecord->temp_file_no) && trim($mainRecord->temp_file_no) !== '' && trim($mainRecord->temp_file_no) !== '-' && strtoupper(trim($mainRecord->temp_file_no)) !== 'NONE') {
                            $mainFileNumber = $mainRecord->temp_file_no;
                            $mainIsTempFallback = true;
                        }
                    }

                    foreach ($parents as $parentNo) {
                        if (empty($parentNo) || $parentNo === '[]' || $parentNo === '-')
                            continue;

                        // Avoid duplicates if already in links
                        $exists = false;
                        foreach ($relatedLinks as $link) {
                            $linkFn = is_object($link) ? ($link->file_number ?? '') : ($link['file_number'] ?? '');
                            if (strtoupper(trim($linkFn)) === strtoupper(trim($parentNo))) {
                                $exists = true;
                                break;
                            }
                        }

                        if (!$exists) {
                            $finalResults[] = [
                                'id' => 'json_' . md5($parentNo),
                                'file_indexing_id' => $id,
                                'file_number' => $parentNo,
                                'file_title' => $parentTitles[$parentNo] ?? 'Related File',
                                'plot_number' => '-',
                                'tp_no' => '-',
                                'lpkn_no' => '-',
                                'location' => '-',
                                'created_by' => 'System',
                                'created_at' => null,
                                'main_file_number' => !empty($mainFileNumber) ? $mainFileNumber : '-',
                                'main_is_temp_fallback' => $mainIsTempFallback,
                                'is_json_parent' => true
                            ];
                        }
                    }
                }
            }

            // Backfill blank detail fields from each related file's own indexed record,
            // so the modal shows real File Title / Location / Plot / TP / LPKN on open
            // instead of dashes (covers link rows and temp files via temp_file_no).
            $this->backfillRelatedFromIndex($finalResults);

            return response()->json([
                'success' => true,
                'data' => $finalResults,
                // Lets the Unlink confirmation state up-front whether removing a given
                // related file would also clear the record's linked Property ID, without
                // a second round-trip. See unlinkRelatedFile() for the actual rule.
                'meta' => [
                    'file_indexing_id' => (int) $id,
                    'prop_id' => $mainRecord->prop_id ?? null,
                    'parent_prop_id' => $mainRecord->parent_prop_id ?? null,
                    'total' => count($finalResults),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load related files.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fill blank detail fields (file_title, location, plot_number, tp_no, lpkn_no) on
     * each related-file result from its own latest file_indexings row, matched on
     * file_number OR temp_file_no. Batched (single query) to avoid N+1.
     *
     * @param array<int, array<string, mixed>> $results
     */
    private function backfillRelatedFromIndex(array &$results): void
    {
        // Collect the distinct, normalized file numbers present in the results.
        $numbers = [];
        foreach ($results as $r) {
            $fn = is_object($r) ? ($r->file_number ?? '') : ($r['file_number'] ?? '');
            $fn = strtoupper(trim((string) $fn));
            if ($fn !== '' && $fn !== '-') {
                $numbers[$fn] = true;
            }
        }
        if (empty($numbers)) {
            return;
        }
        $keys = array_keys($numbers);

        // One query for every candidate, matched on either file_number or temp_file_no.
        // Ordered by id so the latest row wins when the map is built.
        $rows = DB::connection('sqlsrv')->table('file_indexings')
            ->where(function ($query) use ($keys) {
                $query->whereIn(DB::raw('UPPER(LTRIM(RTRIM(file_number)))'), $keys)
                    ->orWhereIn(DB::raw('UPPER(LTRIM(RTRIM(temp_file_no)))'), $keys);
            })
            ->orderBy('id')
            ->get(['file_number', 'temp_file_no', 'file_title', 'location', 'plot_number', 'tp_no', 'lpkn_no', 'prop_id']);

        $map = [];
        foreach ($rows as $row) {
            foreach (['file_number', 'temp_file_no'] as $col) {
                $key = strtoupper(trim((string) ($row->$col ?? '')));
                if ($key !== '') {
                    $map[$key] = $row; // later id overwrites -> latest indexed record wins
                }
            }
        }

        $isBlank = static function ($value): bool {
            $value = trim((string) $value);
            return $value === '' || $value === '-';
        };

        foreach ($results as &$r) {
            $isObj = is_object($r);
            $fn = strtoupper(trim((string) ($isObj ? ($r->file_number ?? '') : ($r['file_number'] ?? ''))));
            if ($fn === '' || !isset($map[$fn])) {
                continue;
            }
            $src = $map[$fn];
            foreach (['file_title', 'location', 'plot_number', 'tp_no', 'lpkn_no'] as $field) {
                $current = $isObj ? ($r->$field ?? null) : ($r[$field] ?? null);
                if ($isBlank($current) && !$isBlank($src->$field ?? null)) {
                    if ($isObj) {
                        $r->$field = $src->$field;
                    } else {
                        $r[$field] = $src->$field;
                    }
                }
            }

            // Never present on the link/JSON rows themselves — the Unlink dialog uses it
            // to tell the operator whether the record's parent_prop_id came from this file.
            if ($isObj) {
                $r->prop_id = $src->prop_id ?? null;
            } else {
                $r['prop_id'] = $src->prop_id ?? null;
            }
        }
        unset($r);
    }

    /**
     * Decode a related_fileno value into a list of file numbers.
     *
     * The column is a JSON array on most rows, but legacy rows hold a bare string or a
     * comma-separated list (see the normalize_related_fileno_format migration, which
     * deliberately does NOT split on '&' — that character occurs inside individual
     * legacy file numbers).
     *
     * @return array<int, string>
     */
    private function parseRelatedFilenoList($raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '[]') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = str_contains($raw, ',') ? explode(',', $raw) : [$raw];
        }

        $out = [];
        foreach ($decoded as $value) {
            if (is_array($value)) {
                continue;
            }
            $value = trim((string) $value);
            if ($value !== '' && $value !== '-') {
                $out[] = $value;
            }
        }

        return array_values($out);
    }

    public function updateRelatedFile(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'file_number' => 'required|string',
                'file_title' => 'required|string',
                'location' => 'nullable|string',
                'plot_number' => 'nullable|string',
                'tp_no' => 'nullable|string',
                'lpkn_no' => 'nullable|string',
            ]);

            DB::connection('sqlsrv')
                ->table('file_indexing_links')
                ->where('id', $id)
                ->update([
                    'file_number' => $validated['file_number'],
                    'file_title' => $validated['file_title'],
                    'location' => $validated['location'],
                    'plot_number' => $validated['plot_number'],
                    'tp_no' => $validated['tp_no'],
                    'lpkn_no' => $validated['lpkn_no'],
                    'updated_at' => now(),
                    'updated_by' => Auth::user()->name ?? 'System',
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Related file updated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update related file.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unlink one related file number from an indexed record.
     *
     * A related file lives in either of two stores (see getRelatedFiles()):
     *   * a file_indexing_links row              -> the row is deleted
     *   * a token in file_indexings.related_fileno -> spliced out of the array
     * The modal sends back the row id it displayed: numeric ids are link rows,
     * "json_*" ids are related_fileno tokens. The file number is always matched too,
     * so a stale id from an open modal cannot remove the wrong link.
     *
     * Property ID: parent_prop_id / ancestral_prop_id are cleared ONLY when the file
     * being unlinked is the one that justified them — its prop_id equals the record's
     * parent_prop_id — AND no remaining related file still resolves to that prop_id.
     * A parent_prop_id set by some other path (a commissioning branch,
     * KangisParentLinkService) is left alone; the response says so.
     *
     * The record's own prop_id and its PropID_Master row are never touched: prop_id is
     * per-parcel, so dropping a lineage link does not invalidate the parcel identity.
     */
    public function unlinkRelatedFile(Request $request, $id, AuditService $audit): JsonResponse
    {
        $fileId = (int) $id;
        if ($fileId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid file ID.'], 422);
        }

        $validated = $request->validate([
            'file_number' => 'required|string|max:500',
            'link_id' => 'nullable|string|max:100',
        ]);

        $target = trim($validated['file_number']);
        $targetKey = $this->normalizeFileNoKey($target);
        if ($targetKey === '') {
            return response()->json(['success' => false, 'message' => 'A related file number is required.'], 422);
        }

        $conn = DB::connection('sqlsrv');

        $record = $conn->table('file_indexings')
            ->where('id', $fileId)
            ->select(['id', 'file_number', 'related_fileno', 'prop_id', 'parent_prop_id'])
            ->first();

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Indexed file not found.'], 404);
        }

        $hasAncestral = Schema::connection('sqlsrv')->hasColumn('file_indexings', 'ancestral_prop_id');
        if ($hasAncestral) {
            $record->ancestral_prop_id = $conn->table('file_indexings')
                ->where('id', $fileId)
                ->value('ancestral_prop_id');
        }

        $linkId = trim((string) ($validated['link_id'] ?? ''));

        try {
            $result = DB::connection('sqlsrv')->transaction(function () use (
                $conn, $record, $fileId, $target, $targetKey, $linkId, $hasAncestral
            ) {
                // ---- 1. Link rows -------------------------------------------------
                // Scoped to this record and matched on the normalised number, so a
                // stale link_id cannot delete a different file's link.
                $linkQuery = $conn->table('file_indexing_links')->where('file_indexing_id', $fileId);
                if (ctype_digit($linkId)) {
                    $linkQuery->where('id', (int) $linkId);
                }

                $linkRows = $linkQuery->get(['id', 'file_number', 'file_title']);
                $doomedLinkIds = [];
                foreach ($linkRows as $link) {
                    if ($this->normalizeFileNoKey($link->file_number) === $targetKey) {
                        $doomedLinkIds[] = (int) $link->id;
                    }
                }

                $removedLinks = 0;
                if (!empty($doomedLinkIds)) {
                    $removedLinks = $conn->table('file_indexing_links')
                        ->whereIn('id', $doomedLinkIds)
                        ->delete();
                }

                // ---- 2. related_fileno JSON --------------------------------------
                $originalRelated = $record->related_fileno;
                $tokens = $this->parseRelatedFilenoList($originalRelated);
                $kept = [];
                $removedFromJson = false;
                foreach ($tokens as $token) {
                    if ($this->normalizeFileNoKey($token) === $targetKey) {
                        $removedFromJson = true;
                        continue;
                    }
                    $kept[] = $token;
                }

                $newRelated = $originalRelated;
                if ($removedFromJson) {
                    $newRelated = empty($kept) ? null : json_encode(array_values($kept));
                    $conn->table('file_indexings')
                        ->where('id', $fileId)
                        ->update(['related_fileno' => $newRelated, 'updated_at' => now()]);
                }

                // ---- 3. Property ID linkage --------------------------------------
                $parentPropId = trim((string) ($record->parent_prop_id ?? ''));
                $propIdCleared = false;
                $propIdNote = null;

                if ($parentPropId === '') {
                    $propIdNote = 'no_parent_prop_id';
                } else {
                    // Every file number still related to this record after the removal.
                    $remainingNumbers = $kept;
                    foreach ($conn->table('file_indexing_links')
                        ->where('file_indexing_id', $fileId)
                        ->pluck('file_number') as $fn) {
                        $remainingNumbers[] = $fn;
                    }

                    $propIdOf = function (array $numbers) use ($conn): array {
                        $numbers = array_values(array_filter(array_map(
                            fn ($n) => trim((string) $n),
                            $numbers
                        ), fn ($n) => $n !== '' && $n !== '-'));

                        if (empty($numbers)) {
                            return [];
                        }

                        return $conn->table('file_indexings')
                            ->whereIn('file_number', $numbers)
                            ->whereNotNull('prop_id')
                            ->pluck('prop_id')
                            ->map(fn ($v) => trim((string) $v))
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();
                    };

                    $unlinkedPropIds = $propIdOf([$target]);
                    $remainingPropIds = $propIdOf($remainingNumbers);

                    if (!in_array($parentPropId, $unlinkedPropIds, true)) {
                        // parent_prop_id was not contributed by the file being unlinked.
                        $propIdNote = 'parent_prop_id_from_another_source';
                    } elseif (in_array($parentPropId, $remainingPropIds, true)) {
                        // Another surviving related file still points at the same parcel.
                        $propIdNote = 'still_justified_by_remaining_related_file';
                    } else {
                        $clear = ['parent_prop_id' => null, 'updated_at' => now()];
                        if ($hasAncestral) {
                            $clear['ancestral_prop_id'] = null;
                        }
                        $conn->table('file_indexings')->where('id', $fileId)->update($clear);
                        $propIdCleared = true;
                    }
                }

                $remaining = count($kept) + $conn->table('file_indexing_links')
                    ->where('file_indexing_id', $fileId)
                    ->count();

                return [
                    'removed_links' => $removedLinks,
                    'removed_from_json' => $removedFromJson,
                    'original_related' => $originalRelated,
                    'new_related' => $newRelated,
                    'prop_id_cleared' => $propIdCleared,
                    'cleared_prop_id' => $propIdCleared ? $parentPropId : null,
                    'prop_id_note' => $propIdNote,
                    'remaining' => $remaining,
                ];
            });
        } catch (\Throwable $e) {
            Log::error('Failed to unlink related file number', [
                'file_indexing_id' => $fileId,
                'related_file_number' => $target,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The unlink failed and was rolled back: ' . $e->getMessage(),
            ], 500);
        }

        if ($result['removed_links'] === 0 && !$result['removed_from_json']) {
            return response()->json([
                'success' => false,
                'message' => sprintf('"%s" is not linked to this record (it may already have been unlinked).', $target),
            ], 404);
        }

        // Audit is best-effort: the unlink is already committed, and AuditService
        // rethrows on failure, so a logging problem must not surface as a failed unlink.
        try {
            $audit->logAction(
                'DELETED',
                'file_indexing_related_file',
                $fileId,
                [
                    'file_number' => $record->file_number,
                    'related_fileno' => $result['original_related'],
                    'parent_prop_id' => $record->parent_prop_id,
                    'ancestral_prop_id' => $record->ancestral_prop_id ?? null,
                ],
                [
                    'unlinked_file_number' => $target,
                    'related_fileno' => $result['new_related'],
                    'removed_link_rows' => $result['removed_links'],
                    'removed_from_related_fileno' => $result['removed_from_json'],
                    'prop_id_cleared' => $result['prop_id_cleared'],
                    'cleared_prop_id' => $result['cleared_prop_id'],
                    'prop_id_note' => $result['prop_id_note'],
                ],
                sprintf('Unlinked related file %s from %s', $target, $record->file_number)
            );
        } catch (\Throwable $e) {
            Log::warning('Related-file unlink succeeded but the audit entry failed', [
                'file_indexing_id' => $fileId,
                'related_file_number' => $target,
                'error' => $e->getMessage(),
            ]);
        }

        $message = sprintf('Unlinked "%s".', $target);
        if ($result['prop_id_cleared']) {
            $message .= sprintf(' Property ID %s was also cleared.', $result['cleared_prop_id']);
        } elseif ($result['prop_id_note'] === 'still_justified_by_remaining_related_file') {
            $message .= ' The linked Property ID was kept — another related file still points at it.';
        } elseif ($result['prop_id_note'] === 'parent_prop_id_from_another_source') {
            $message .= ' The linked Property ID was kept — it was not set by this related file.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'file_number' => $target,
                'removed_link_rows' => $result['removed_links'],
                'removed_from_related_fileno' => $result['removed_from_json'],
                'remaining' => $result['remaining'],
                'prop_id_cleared' => $result['prop_id_cleared'],
                'cleared_prop_id' => $result['cleared_prop_id'],
                'prop_id_note' => $result['prop_id_note'],
            ],
        ]);
    }

    public function updateCoordinates(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
            ]);

            $fileIndexing = FileIndexing::on('sqlsrv')->findOrFail($id);
            $fileIndexing->latitude = $validated['latitude'] ?? null;
            $fileIndexing->longitude = $validated['longitude'] ?? null;
            $fileIndexing->save();

            return response()->json([
                'success' => true,
                'message' => 'Coordinates updated successfully.',
                'data' => [
                    'latitude' => $fileIndexing->latitude,
                    'longitude' => $fileIndexing->longitude,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update coordinates.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function markAsDuplicate(Request $request, $id): JsonResponse
    {
        try {
            $fileId = (int) $id;
            if ($fileId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file ID.',
                ], 422);
            }

            if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'is_duplicate')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Column is_duplicate not found on file_indexings table.',
                ], 500);
            }

            $affected = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->update([
                    'is_duplicate' => 1,
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Indexed file not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'File marked as duplicate successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark file as duplicate.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * What "Move to Indexing Duplicates" would do to this record: the tables it
     * deletes from with row counts, the record/transaction tables it leaves alone
     * (PRA, CofO, file history, file tracker, commissioning register) with their
     * counts, and anything that blocks the move outright.
     *
     * Read-only — the confirmation dialog calls this before the operator commits.
     */
    public function previewIndexingDuplicateMove($id, IndexingDuplicateService $service): JsonResponse
    {
        $fileId = (int) $id;
        if ($fileId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file ID.',
            ], 422);
        }

        try {
            $preview = $service->preview($fileId);
        } catch (\Throwable $e) {
            Log::error('Failed to preview indexing duplicate move', [
                'file_indexing_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not check what this move would delete: ' . $e->getMessage(),
            ], 500);
        }

        if ($preview['status'] === 'not_found') {
            return response()->json([
                'success' => false,
                'message' => 'Indexed file not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'blocked' => $preview['status'] === 'blocked',
            'preview' => [
                'file_number'       => $preview['file_number'],
                'file_title'        => $preview['file_title'],
                'matched_numbers'   => $preview['matched_numbers'],
                'blocking'          => $preview['blocking'],
                'deletions'         => $preview['deletions'],
                'retained'          => $preview['retained'],
                'transaction_total' => $preview['transaction_total'],
            ],
        ]);
    }

    /**
     * Move an indexed file into indexing_duplicates and remove it from the live
     * tables (file_indexings, fileNumber, customers_staging, entities_staging).
     *
     * Refused when the file carries scans, page typings, bills or tracking
     * movements — see IndexingDuplicateService::BLOCKING_REFERENCES.
     */
    public function moveToIndexingDuplicates(Request $request, $id, IndexingDuplicateService $service): JsonResponse
    {
        $fileId = (int) $id;
        if ($fileId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file ID.',
            ], 422);
        }

        $reason = trim((string) $request->input('reason', ''));
        $duplicateOf = trim((string) $request->input('duplicate_of', ''));

        try {
            $result = $service->move(
                $fileId,
                $reason !== '' ? $reason : null,
                $duplicateOf !== '' ? $duplicateOf : null
            );
        } catch (\Throwable $e) {
            Log::error('Failed to move indexed file to indexing duplicates', [
                'file_indexing_id' => $fileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The move failed and was rolled back: ' . $e->getMessage(),
            ], 500);
        }

        switch ($result['status']) {
            case 'not_found':
                return response()->json([
                    'success' => false,
                    'message' => 'Indexed file not found.',
                ], 404);

            case 'already_moved':
                return response()->json([
                    'success' => false,
                    'message' => sprintf(
                        '%s was already moved to indexing duplicates by %s.',
                        $result['file_number'],
                        $result['moved_by'] ?: 'another user'
                    ),
                ], 409);

            case 'blocked':
                return response()->json([
                    'success' => false,
                    'blocked' => true,
                    'message' => sprintf(
                        '%s cannot be moved: it still has %s. Detach or delete those first.',
                        $result['file_number'],
                        implode(', ', array_values($result['dependencies']))
                    ),
                    'dependencies' => $result['dependencies'],
                ], 409);
        }

        $counts = $result['counts'];
        Cache::forget('indexed_files_old_kangis_lookup');

        return response()->json([
            'success' => true,
            'message' => sprintf('%s moved to indexing duplicates.', $result['file_number']),
            'details' => [
                'indexing_duplicate_id' => $result['indexing_duplicate_id'],
                'file_indexings_deleted' => $counts['file_indexings'],
                'fileNumber_deleted' => $counts['fileNumber'],
                'customers_staging_deleted' => $counts['customers_staging'],
                'entities_staging_deleted' => $counts['entities_staging'],
                'child_rows_deleted' => $counts['child_rows'],
                'mls_file_no_retained' => $result['mls_file_no_retained'],
                'retained_references' => $result['retained_references'],
            ],
        ]);
    }

    /**
     * Master Delete: erase an indexed file from the live tables with no
     * indexing_duplicates archive behind it.
     *
     * Same removal as "Move to Indexing Duplicates" (one code path — see
     * IndexingDuplicateService::purge()), but framed as what it is: the record was
     * wrong, not duplicated, and must not resurface on the Indexing Duplicates
     * screen. The audit entry is all that survives.
     *
     * Three gates, in order: super admin, the file number typed back, and the
     * service refusing a file that carries scans, page typings, bills or SPAS
     * applications. The UI hides the entry from non-admins as a courtesy; this is
     * the gate that counts, because the endpoint is reachable directly.
     */
    public function masterDelete(Request $request, $id, IndexingDuplicateService $service): JsonResponse
    {
        $fileId = (int) $id;
        if ($fileId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file ID.',
            ], 422);
        }

        if ($denied = $this->denyUnlessIndexingMasterDeleter()) {
            return $denied;
        }

        $file = DB::connection('sqlsrv')->table('file_indexings')
            ->where('id', $fileId)
            ->first(['id', 'file_number', 'temp_file_no', 'file_title']);

        if (!$file) {
            return response()->json([
                'success' => false,
                'message' => 'Indexed file not found.',
            ], 404);
        }

        // The operator types the file number back. Falls back to temp_file_no for a
        // record indexed on a temporary number, because that is what the row shows.
        $expected = trim((string) ($file->file_number ?? ''));
        if ($expected === '' || $expected === '-') {
            $expected = trim((string) ($file->temp_file_no ?? ''));
        }

        if ($expected === '') {
            return response()->json([
                'success' => false,
                'message' => 'This record has no file number to confirm against. Report it rather than forcing it through.',
            ], 422);
        }

        if ($denied = $this->denyUnlessConfirmationMatches($request, $expected)) {
            return $denied;
        }

        $reason = trim((string) $request->input('reason', ''));

        try {
            $result = $service->purge($fileId, $reason !== '' ? $reason : null);
        } catch (\Throwable $e) {
            Log::error('Master Delete of indexed file failed', [
                'file_indexing_id' => $fileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The delete failed and was rolled back: ' . $e->getMessage(),
            ], 500);
        }

        if ($result['status'] === 'not_found') {
            return response()->json([
                'success' => false,
                'message' => 'Indexed file not found.',
            ], 404);
        }

        if ($result['status'] === 'blocked') {
            return response()->json([
                'success' => false,
                'blocked' => true,
                'message' => sprintf(
                    '%s cannot be deleted: it still has %s. Detach or delete those first.',
                    $result['file_number'],
                    implode(', ', array_values($result['dependencies']))
                ),
                'dependencies' => $result['dependencies'],
            ], 409);
        }

        $counts = $result['counts'];

        // Flatten the per-table child / file-number counts so the audit detail line
        // names every table that lost rows, not only the four headline ones.
        $auditCounts = array_filter([
            'file_indexings'    => $counts['file_indexings'],
            'fileNumber'        => $counts['fileNumber'],
            'customers_staging' => $counts['customers_staging'],
            'entities_staging'  => $counts['entities_staging'],
        ]) + ($counts['file_number_rows'] ?? []) + ($counts['child_rows'] ?? []);

        $this->logMasterDelete(
            'FileIndexing',
            $fileId,
            $result['snapshot'] ?? [],
            $auditCounts,
            sprintf('indexed file %s', $result['file_number'])
        );

        Cache::forget('indexed_files_old_kangis_lookup');

        return response()->json([
            'success' => true,
            'message' => sprintf('%s was permanently deleted.', $result['file_number']),
            'details' => [
                'file_indexings_deleted'    => $counts['file_indexings'],
                'fileNumber_deleted'        => $counts['fileNumber'],
                'customers_staging_deleted' => $counts['customers_staging'],
                'entities_staging_deleted'  => $counts['entities_staging'],
                'file_number_rows_deleted'  => $counts['file_number_rows'],
                'child_rows_deleted'        => $counts['child_rows'],
                'mls_file_no_retained'      => $result['mls_file_no_retained'],
                'retained_references'       => $result['retained_references'],
            ],
        ]);
    }

    /**
     * Who may Master Delete an indexed file.
     *
     * Deliberately NOT ExecutesMasterDelete::denyUnlessMasterDeleter(): that one
     * compares assign_role to the exact string "Supper Admin", which fails for a
     * user whose assign_role is a CSV of several roles. This page already decides
     * "Move to Indexing Duplicates" — an equally destructive cross-table purge of
     * the same record — by asking whether "Supper Admin" is among the assigned
     * roles, and the two entries sit in one menu, so they have to answer alike or
     * the menu contradicts itself.
     *
     * Kept identical to the $hasSuperRole computation in indexed_files/index.blade.php
     * and components/indexed-files-table.blade.php, which is what hides the entry.
     */
    private function denyUnlessIndexingMasterDeleter(): ?JsonResponse
    {
        $user = Auth::user();
        $roles = $user && is_array($user->assign_role)
            ? $user->assign_role
            : array_filter(array_map('trim', explode(',', (string) ($user->assign_role ?? ''))));

        if (!$user || !in_array('Supper Admin', $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action. Only a Supper Admin can execute a Master Delete.',
            ], 403);
        }

        return null;
    }

    public function setTempFile(Request $request, $id): JsonResponse
    {
        try {
            $fileId = (int) $id;
            if ($fileId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file ID.',
                ], 422);
            }

            $tempFileNo = trim((string) $request->input('temp_file_no', ''));
            if ($tempFileNo === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Temporary File Number is required.',
                ], 422);
            }

            // Find the file indexing record
            $fileIndexing = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->first();

            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Indexed file not found.',
                ], 404);
            }

            $hasTransaction = $request->has('has_transaction') ? (int) filter_var($request->input('has_transaction'), FILTER_VALIDATE_BOOLEAN) : null;

            // Update file_indexings table
            $updateData = [
                'has_temp_file' => 1,
                'temp_file_no' => $tempFileNo,
                'updated_at' => now(),
            ];

            if ($hasTransaction !== null) {
                $updateData['has_transaction'] = $hasTransaction;
            }

            DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->update($updateData);

            // Also update fileNumber table if a matching record exists
            $fileNumber = $fileIndexing->file_number;
            if ($fileNumber) {
                $fileNumberRecord = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->where('kangisFileNo', $fileNumber)
                    ->orWhere('mlsfNo', $fileNumber)
                    ->orWhere('NewKANGISFileNo', $fileNumber)
                    ->first();

                if ($fileNumberRecord) {
                    $fnUpdateData = [
                        'has_temp_file' => 1,
                        'temp_file_no' => $tempFileNo,
                    ];
                    if ($hasTransaction !== null) {
                        $fnUpdateData['has_transaction'] = $hasTransaction;
                    }

                    DB::connection('sqlsrv')
                        ->table('fileNumber')
                        ->where('id', $fileNumberRecord->id)
                        ->update($fnUpdateData);
                }
            }

            $updatedFile = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Temporary file number and transaction status saved successfully.',
                'data' => $updatedFile,
            ]);
        } catch (\Throwable $e) {
            //Log the error
            Log::error('Error setting temporary file number: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to set temporary file number.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Match an indexed file to a correspondence file number.
     * Sets is_corresponding_file = 1 and stores the chosen corresponding_fileno.
     *
     * POST /api/indexed-files/{id}/match-correspondence
     */
    public function matchCorrespondence(Request $request, $id): JsonResponse
    {
        try {
            $fileId = (int) $id;
            if ($fileId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file ID.',
                ], 422);
            }

            $correspondingFileNo = trim((string) $request->input('corresponding_fileno', ''));
            if ($correspondingFileNo === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Correspondence File Number is required.',
                ], 422);
            }

            $exists = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->exists();

            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Indexed file not found.',
                ], 404);
            }

            DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->update([
                    'is_corresponding_file' => 1,
                    'corresponding_fileno'  => $correspondingFileNo,
                    'updated_at'            => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => "Correspondence file {$correspondingFileNo} matched successfully.",
            ]);
        } catch (\Throwable $e) {
            Log::error('Error matching correspondence file: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to match correspondence file.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the correspondence match from an indexed file.
     * Clears is_corresponding_file and corresponding_fileno (e.g. mistaken match).
     *
     * POST /api/indexed-files/{id}/unmatch-correspondence
     */
    public function unmatchCorrespondence(Request $request, $id): JsonResponse
    {
        try {
            $fileId = (int) $id;
            if ($fileId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file ID.',
                ], 422);
            }

            $affected = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->update([
                    'is_corresponding_file' => 0,
                    'corresponding_fileno'  => null,
                    'updated_at'            => now(),
                ]);

            if ($affected === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Indexed file not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Correspondence match removed successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error removing correspondence match: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove correspondence match.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Match an indexed (Land) file to a Physical Planning shadow file number.
     * Stores pp_lands_fileno + sets pp_lands_matching = 1 with the match date/time
     * on file_indexings, and mirrors the match flag/timestamps onto the fileNumber
     * record so the Lands matching module reflects the match.
     *
     * POST /api/indexed-files/{id}/match-physical-planning
     */
    public function matchPhysicalPlanning(Request $request, $id): JsonResponse
    {
        try {
            $fileId = (int) $id;
            if ($fileId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file ID.',
                ], 422);
            }

            $ppLandsFileNo = trim((string) $request->input('pp_lands_fileno', ''));
            if ($ppLandsFileNo === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Physical Planning File Number is required.',
                ], 422);
            }

            $file = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->first(['id', 'file_number']);

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'Indexed file not found.',
                ], 404);
            }

            $now = now();

            DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->update([
                    'pp_lands_fileno'        => $ppLandsFileNo,
                    'pp_lands_matching'      => 1,
                    'pp_lands_date_matched'  => $now->toDateString(),
                    'pp_lands_time_matched'  => $now->toTimeString(),
                    'updated_at'             => $now,
                ]);

            // Mirror the match onto the fileNumber record (matching-only architecture)
            $landFileNo = trim((string) ($file->file_number ?? ''));
            if ($landFileNo !== '') {
                DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->where('mlsfNo', $landFileNo)
                    ->update([
                        'pp_lands_matching'     => 1,
                        'pp_lands_date_matched' => $now->toDateString(),
                        'pp_lands_time_matched' => $now->toTimeString(),
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Physical Planning file {$ppLandsFileNo} matched successfully.",
            ]);
        } catch (\Throwable $e) {
            Log::error('Error matching Physical Planning file: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to match Physical Planning file.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the Physical Planning match from an indexed file.
     * Clears pp_lands_matching / pp_lands_fileno and the match timestamps.
     *
     * POST /api/indexed-files/{id}/unmatch-physical-planning
     */
    public function unmatchPhysicalPlanning(Request $request, $id): JsonResponse
    {
        try {
            $fileId = (int) $id;
            if ($fileId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file ID.',
                ], 422);
            }

            $file = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->first(['id', 'file_number']);

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'Indexed file not found.',
                ], 404);
            }

            $now = now();

            DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->update([
                    'pp_lands_fileno'        => null,
                    'pp_lands_matching'      => 0,
                    'pp_lands_date_matched'  => null,
                    'pp_lands_time_matched'  => null,
                    'updated_at'             => $now,
                ]);

            $landFileNo = trim((string) ($file->file_number ?? ''));
            if ($landFileNo !== '') {
                DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->where('mlsfNo', $landFileNo)
                    ->update([
                        'pp_lands_matching'     => 0,
                        'pp_lands_date_matched' => null,
                        'pp_lands_time_matched' => null,
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Physical Planning match removed successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error removing Physical Planning match: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove Physical Planning match.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check whether a selected KANGIS file number (e.g. "MLKN 1") already has
     * existing KANGIS-variant siblings in file_indexings.
     * Used for the live pre-submit check in the create form.
     *
     * GET /api/kangis-placeholder/check?file_number=MLKN+1&exclude_id=123
     */
    public function checkKangisPlaceholder(Request $request): JsonResponse
    {
        $raw = trim((string) $request->query('file_number', $request->query('value', '')));
        $excludeId = (int) $request->query('exclude_id', 0);

        if ($raw === '') {
            return response()->json(['bare_exists' => false, 'has_siblings' => false, 'count' => 0, 'siblings' => []]);
        }

        // Check whether the bare file number already exists (any record, not just
        // placeholder ones — the first indexed record won't have a placeholder set).
        $bareQuery = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$raw]);

        if ($excludeId > 0) {
            $bareQuery->where('id', '!=', $excludeId);
        }

        $bareExists = $bareQuery->exists();

        // Count existing _N suffixed variants (file_number = raw + '_' + digits).
        $suffixedQuery = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('file_number', 'like', str_replace('_', '[_]', $raw) . '[_]%');

        if ($excludeId > 0) {
            $suffixedQuery->where('id', '!=', $excludeId);
        }

        $suffixedVariants = $suffixedQuery
            ->get(['id', 'file_number', 'kangis_fileno_placeholder', 'kangis_fileno_resolved'])
            ->filter(function ($row) use ($raw) {
                $suffix = substr(trim((string) $row->file_number), strlen($raw) + 1);
                return ctype_digit($suffix) && $suffix !== '';
            })->values();

        $suffixedCount = $suffixedVariants->count();

        // Determine which collision case this is so the frontend shows the right message.
        // Case A: no bare, no suffixed  → first record, will be saved bare (no suffix)
        // Case B: bare exists, count=0  → first collision: bare→_1, new→_2
        // Case C: bare gone, count>0   → subsequent: new→_(count+1)
        // Case D: bare+count>0          → unusual: new→_(count+1)
        $firstCollision = $bareExists && $suffixedCount === 0;

        return response()->json([
            'bare_exists' => $bareExists,
            'has_siblings' => $bareExists || $suffixedCount > 0,
            'count' => $suffixedCount,
            'first_collision' => $firstCollision,
            'siblings' => $suffixedVariants->map(fn($r) => [
                'id' => $r->id,
                'file_number' => $r->file_number,
                'placeholder' => $r->kangis_fileno_placeholder,
                'resolved' => $r->kangis_fileno_resolved,
            ])->values(),
        ]);
    }

    /**
     * Build a map of file_number => true for any file_number that has a
     * matching folder under one of the EDMS registry roots. Cheap because
     * we only scandir() each registry root once per request and reuse a
     * static cache for the lifetime of the process where possible.
     *
     * @param  \Illuminate\Support\Collection<int,string>  $fileNumbers
     * @return array<string,bool>
     */
    private function buildEdmsFolderMap($fileNumbers): array
    {
        if (!$fileNumbers || $fileNumbers->isEmpty()) {
            return [];
        }

        $resolvePath = function (string $path): string {
            return function_exists('file_storage_path') ? file_storage_path($path) : storage_path($path);
        };

        // Standard EDMS registry roots
        $registries = ['Cadastral_Registry1', 'Cadastral_Registry', 'SLTR_Registry', 'DCIV_Registry', 'KANGIS_Registry', 'Lands_Registry'];
        $registryRoots = [];
        foreach ($registries as $registry) {
            $root = realpath($resolvePath('app/public/EDMS/UPLOAD/' . $registry));
            if ($root && is_dir($root)) {
                $registryRoots[] = $root;
            }
        }

        $map = [];
        foreach ($fileNumbers as $fn) {
            $key = trim((string) $fn);
            if ($key === '') {
                continue;
            }

            // Instead of scanning the entire root once, check if the specific 
            // file number folder exists in any of the registry roots.
            // Much faster for paginated results (e.g. 20-50 checks vs 50,000+).
            $found = false;
            foreach ($registryRoots as $root) {
                if (is_dir($root . DIRECTORY_SEPARATOR . $key)) {
                    $found = true;
                    break;
                }
            }
            $map[$key] = $found;
        }

        return $map;
    }

    /**
     * List files inside the EDMS Cadastral_Registry folder for a given indexed file.
     * Path: storage/app/public/EDMS/UPLOAD/Cadastral_Registry/{fileNumber}/**
     */
    public function getEdmsFiles($id): JsonResponse
    {
        try {
            $fileId = (int) $id;
            if ($fileId <= 0) {
                return response()->json(['success' => false, 'message' => 'Invalid ID.'], 422);
            }

            $row = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->select(['id', 'file_number', 'corresponding_fileno'])
                ->first();

            if (!$row) {
                return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
            }

            $fileNumber = trim((string) ($row->file_number ?? ''));
            $correspondingFileNo = trim((string) ($row->corresponding_fileno ?? ''));

            // The scanned documents are stored in a folder named after the
            // corresponding file number (the physical registry file). Prefer it,
            // then fall back to the file number.
            $folderCandidates = array_values(array_unique(array_filter([
                $correspondingFileNo,
                $fileNumber,
            ], fn ($v) => $v !== '' && $v !== '-')));

            if (empty($folderCandidates)) {
                return response()->json(['success' => true, 'has_files' => false, 'files' => [], 'file_number' => null]);
            }

            // Allow the caller to specify which registry folder to look in
            $allowedFolders = ['Cadastral_Registry1', 'Cadastral_Registry', 'SLTR_Registry', 'DCIV_Registry', 'KANGIS_Registry', 'Lands_Registry'];
            $requestedFolder = request()->query('folder', 'Cadastral_Registry1');
            $registryFolder = in_array($requestedFolder, $allowedFolders, true) ? $requestedFolder : 'Cadastral_Registry1';

            // Use file_storage_path() to honour the dedicated STORAGE_PATH (.env)
            // because the storage_path() override in app/Helper/helper.php is
            // shadowed by Laravel's own helpers.php at autoload time.
            $resolveStoragePath = function (string $path): string {
                return function_exists('file_storage_path')
                    ? file_storage_path($path)
                    : storage_path($path);
            };

            $baseRoot = realpath($resolveStoragePath('app/public/EDMS/UPLOAD/' . $registryFolder));
            if (!$baseRoot) {
                return response()->json(['success' => true, 'has_files' => false, 'files' => [], 'file_number' => $fileNumber]);
            }

            $folderPath = $baseRoot . DIRECTORY_SEPARATOR . $fileNumber;
            $realFolder = realpath($folderPath);

            // Security: prevent path traversal
            if (!$realFolder || strncmp($realFolder, $baseRoot, strlen($baseRoot)) !== 0) {
                return response()->json(['success' => true, 'has_files' => false, 'files' => [], 'file_number' => $fileNumber]);
            }

            $storagePublicRoot = realpath($resolveStoragePath('app/public'));
            $files = [];
            $this->scanEdmsFolder($realFolder, $baseRoot, $storagePublicRoot, $files);

            return response()->json([
                'success' => true,
                'has_files' => !empty($files),
                'file_number' => $fileNumber,
                'files' => $files,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to load files.'], 500);
        }
    }

    /**
     * Recursively scan a folder for viewable files (images + PDFs).
     */
    private function scanEdmsFolder(string $dir, string $baseRoot, string $storagePublicRoot, array &$files): void
    {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'tif', 'tiff', 'bmp', 'webp', 'pdf'];
        $entries = @scandir($dir) ?: [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $dir . DIRECTORY_SEPARATOR . $entry;
            $realPath = realpath($fullPath);

            // Security: stay inside base root
            if (!$realPath || strncmp($realPath, $baseRoot, strlen($baseRoot)) !== 0) {
                continue;
            }

            if (is_dir($realPath)) {
                $this->scanEdmsFolder($realPath, $baseRoot, $storagePublicRoot, $files);
            } else {
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) {
                    continue;
                }

                $relative = ltrim(str_replace('\\', '/', substr($realPath, strlen($storagePublicRoot))), '/');
                $files[] = [
                    'name' => $entry,
                    'url' => asset('storage/' . $relative),
                    'type' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'tif', 'tiff', 'bmp', 'webp'], true) ? 'image' : 'pdf',
                    'ext' => $ext,
                ];
            }
        }
    }

    public function updateKangisPlaceholder(Request $request, $id): JsonResponse
    {
        try {
            $fileId = (int) $id;
            if ($fileId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file ID.',
                ], 422);
            }

            $placeholder = trim((string) $request->input('placeholder', ''));
            if ($placeholder === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Placeholder is required.',
                ], 422);
            }

            $fileIndexing = \Illuminate\Support\Facades\DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->first();

            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Indexed file not found.',
                ], 404);
            }

            $affected = \Illuminate\Support\Facades\DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->update([
                    'kangis_fileno_placeholder' => $placeholder,
                    'updated_at' => now(),
                ]);

            // Also update kangis_grouping table if matching record exists
            $fileNumber = $fileIndexing->file_number;
            if ($fileNumber) {
                // Strip suffix if any (e.g. KNML 1_2 -> KNML 1)
                $baseFileNumber = preg_replace('/_\d+$/', '', $fileNumber);

                \Illuminate\Support\Facades\DB::connection('sqlsrv')
                    ->table('kangis_grouping')
                    ->where('kangis_awaiting_fileno', $baseFileNumber)
                    ->update([
                        'kangis_fileno_placeholder' => $placeholder,
                        'updated_at' => now(),
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'KANGIS placeholder updated successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update placeholder.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function findFile(Request $request): JsonResponse
    {
        try {
            $fileNumber = trim((string) $request->query('file_number', ''));
            if ($fileNumber === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'File number is required.',
                ], 422);
            }

            $file = \Illuminate\Support\Facades\DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('file_number', $fileNumber)
                ->orWhere('kangis_fileno_placeholder', $fileNumber)
                ->first();

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $file,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
