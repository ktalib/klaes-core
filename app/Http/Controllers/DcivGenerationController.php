<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\FileIndexing;
use App\Models\FileNumber;
use App\Models\DcivGrouping;
use App\Models\DcivFileNo;
use App\Models\DcivSerialControl;
use App\Models\MasterDcivLink;
use App\Models\Lga;
use App\Models\LandUse;
use App\Models\Purpose;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DcivGenerationController extends Controller
{
    /**
     * Display the GKN generation form.
     */
    public function index(Request $request)
    {
        $PageTitle = 'DCIV File Number Commissioning';
        $PageDescription = '';

        $districts = District::where('is_active', 1)->orderBy('name')->get();
        $lgas = Lga::where('is_active', 1)->orderBy('name')->get();
        $landUses = LandUse::orderBy('landuse')->get();
        $purposes = Purpose::orderBy('name')->get();
        $user = Auth::user();

        // Calculate Statistics - include file_indexings DCIV/LPCC records not commissioned via the DCIV UI
        $fiDcivCount = DB::connection('sqlsrv')->table('file_indexings as fi')
            ->whereIn('fi.registry', ['DCIV', 'LPCC'])
            ->whereNotNull('fi.file_number')
            ->where(function ($q) {
                $q->where('fi.is_deleted', 0)->orWhereNull('fi.is_deleted');
            })
            // Only count records that were NOT created by the DCIV commissioning UI
            ->where(function ($q) {
                $q->whereNull('fi.source')->orWhere('fi.source', '!=', 'DCIV FileNo Commissioning');
            })
            ->count();

        $stats = [
            'total' => DcivFileNo::where('is_deleted', 0)->count() + $fiDcivCount,
            'today' => DcivFileNo::where('is_deleted', 0)->whereDate('commissioning_date', date('Y-m-d'))->count(),
            'month' => DcivFileNo::where('is_deleted', 0)
                ->whereMonth('commissioning_date', date('m'))
                ->whereRaw('YEAR(commissioning_date) = ?', [date('Y')])
                ->count(),
        ];

        // Serial Control Status
        $serialStatuses = DcivSerialControl::where('year', date('Y'))
            ->get()
            ->keyBy('prefix');

        // The years a manual serial may be issued in, per prefix.
        //
        // Taken from the RESERVOIR, not from the counters: dciv_grouping is seeded a
        // year at a time and still holds thousands of unclaimed numbers going back
        // years, while only the current year has ever had a counter initialized.
        // Listing counters alone would offer one year and hide every number the
        // registry actually has to give.
        //
        // A year with no initialized counter is still offered, flagged, because the
        // fix is a one-off in System Admin -> Configurable Entries -> Serial
        // Initialization and the officer needs to see the year is there before being
        // told to go and set it up.
        $initializedYears = DcivSerialControl::where('is_initialized', 1)
            ->get(['prefix', 'year'])
            ->groupBy('prefix')
            ->map(fn ($rows) => $rows->pluck('year')->map(fn ($y) => (int) $y)->all())
            ->all();

        $serialYears = collect(['DCIV', 'LPCC'])
            ->mapWithKeys(function ($prefix) use ($initializedYears) {
                // Years that still have a number left to claim for this prefix.
                $reservoirYears = DcivGrouping::query()
                    ->where('dciv_awaiting_fileno', 'LIKE', $prefix . '-%')
                    ->where(function ($q) {
                        $q->where('mapping', 0)->orWhereNull('mapping');
                    })
                    ->whereNotNull('year')
                    ->distinct()
                    ->pluck('year')
                    ->map(fn ($y) => (int) $y)
                    ->filter(fn ($y) => $y >= 2000 && $y <= (int) date('Y'));

                $ready = collect($initializedYears[$prefix] ?? []);

                return [$prefix => $reservoirYears
                    ->merge($ready)
                    ->push((int) date('Y'))
                    ->unique()
                    ->sortDesc()
                    ->values()
                    ->map(fn ($year) => [
                        'year' => $year,
                        'initialized' => $ready->contains($year),
                    ])
                    ->all()];
            })
            ->all();

        return view('dciv_generation.index', compact(
            'PageTitle', 'PageDescription', 'districts', 'lgas', 'landUses', 'purposes', 'stats', 'user', 'serialStatuses', 'serialYears'
        ));
    }

    /**
     * Server-side DataTable endpoint for DCIV records.
     * Combines dciv_file_no (generated) and file_indexings (registry=DCIV, not in dciv_file_no).
     */
    public function data(Request $request)
    {
        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 20);
        $search = $request->input('search.value', '');
        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        // Map DataTables column index to DB column (matches JS columns order)
        // JS index 0 is S/N (not orderable)
        $columns = [
            0 => 'sn', // Placeholder
            1 => 'full_file_number',
            2 => 'related_fileno',
            3 => 'file_name',
            4 => 'tp_no',
            5 => 'plot_no',
            6 => 'lga',
            7 => 'location',
            8 => 'created_by_name',
            9 => 'commissioning_time',
            10 => 'record_created_at',
            11 => 'source_table',
        ];
        
        $orderColumn = $columns[$orderColumnIndex] ?? 'record_created_at';
        if ($orderColumn === 'sn' || !in_array($orderColumn, $columns)) {
            $orderColumn = 'record_created_at';
        }

        $conn = DB::connection('sqlsrv');

        // Part 1: dciv_file_no records (grouped by batch - one representative per batch).
        // Only show a dciv_file_no record as COMMISSIONED when its matching file_indexings row was
        // created by the commissioning workflow (source = 'DCIV FileNo Commissioning').  If a
        // file_indexings record with a different source exists for the same file number it means
        // the file was indexed via the indexing module (not commissioned) and should appear in
        // Part 2 as an INDEXED FILE instead.
        $dcivSql = "
            SELECT d.id, d.full_file_number, d.related_fileno, d.file_name,
                   d.plot_no, d.tp_no, d.location, d.lga, d.created_by,
                   ISNULL(CONCAT(u.first_name, ' ', u.last_name), 'System') as created_by_name,
                   d.commissioning_time,
                   d.commissioning_date,
                   d.created_at as record_created_at,
                   d.batch_no,
                   'dciv_file_no' as source_table
            FROM dciv_file_no d
            LEFT JOIN users u ON d.created_by = u.id
            WHERE d.id IN (
                SELECT MAX(id) FROM dciv_file_no WHERE is_deleted = 0
                GROUP BY CASE WHEN batch_no IS NULL THEN CAST(id AS VARCHAR(100)) ELSE batch_no END
            )
            AND d.is_deleted = 0
            -- Exclude dciv_file_no records whose file was indexed (not commissioned) via file indexing module
            AND NOT EXISTS (
                SELECT 1 FROM file_indexings fi_chk
                WHERE fi_chk.file_number IS NOT NULL
                AND UPPER(LTRIM(RTRIM(fi_chk.file_number))) = UPPER(LTRIM(RTRIM(d.full_file_number)))
                AND fi_chk.registry IN ('DCIV', 'LPCC')
                AND (fi_chk.is_deleted = 0 OR fi_chk.is_deleted IS NULL)
                AND (fi_chk.source IS NULL OR fi_chk.source != 'DCIV FileNo Commissioning')
            )
        ";

        // Part 2: file_indexings records (DCIV or LPCC registry) that were NOT created by the
        // DCIV commissioning UI.  Source-based exclusion is used instead of NOT EXISTS so that
        // files imported/migrated into dciv_file_no still display correctly as INDEXED FILE here.
        $fiSql = "
            SELECT fi.id, fi.file_number as full_file_number, fi.related_fileno,
                   fi.file_title as file_name, fi.plot_number as plot_no, fi.tp_no,
                   fi.location, fi.lga, NULL as created_by,
                   ISNULL(fi.created_by, 'System') as created_by_name,
                   CONVERT(VARCHAR(8), fi.created_at, 108) as commissioning_time,
                   CAST(fi.created_at AS DATE) as commissioning_date,
                   fi.created_at as record_created_at,
                   NULL as batch_no,
                   'file_indexings' as source_table
            FROM file_indexings fi
            WHERE fi.registry IN ('DCIV', 'LPCC')
            AND (fi.is_deleted = 0 OR fi.is_deleted IS NULL)
            AND fi.file_number IS NOT NULL
            -- Exclude records created by the commissioning UI (those appear from dciv_file_no as COMMISSIONED)
            AND (fi.source IS NULL OR fi.source != 'DCIV FileNo Commissioning')
        ";

        $combinedSql = "({$dcivSql}) UNION ALL ({$fiSql})";

        // Total count (unfiltered)
        $totalRecords = $conn->select("SELECT COUNT(*) as cnt FROM ({$combinedSql}) as t")[0]->cnt;

        // Build data query with optional search
        $bindings = [];
        $searchWhere = '';

        if ($search) {
            $searchWhere = " WHERE (full_file_number LIKE ? OR file_name LIKE ? OR location LIKE ? OR plot_no LIKE ? OR lga LIKE ? OR created_by_name LIKE ?)";
            $bindings = array_fill(0, 6, "%{$search}%");

            $filteredRecords = $conn->select(
                "SELECT COUNT(*) as cnt FROM ({$combinedSql}) as t {$searchWhere}",
                $bindings
            )[0]->cnt;
        } else {
            $filteredRecords = $totalRecords;
        }

        // Fetch paginated records with ordering
        // Priority Sort: 'dciv_file_no' (COMMISSIONED) records come first (0), then 'file_indexings' (INDEXED FILE) (1).
        $prioritySort = "CASE WHEN source_table = 'dciv_file_no' THEN 0 ELSE 1 END ASC";
        $dataSql = "SELECT * FROM ({$combinedSql}) as t {$searchWhere} ORDER BY {$prioritySort}, [{$orderColumn}] {$orderDir} OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        $records = $conn->select($dataSql, array_merge($bindings, [$start, $length]));

        // --- Batch pre-fetch: eliminates N+1 queries for related files and batch counts ---

        // 1. dciv_link counts keyed by UPPERCASE(main_file_number) — for dciv_file_no records
        $dcivFileNums = collect($records)
            ->filter(fn($r) => stripos((string)($r->source_table ?? ''), 'file_index') === false)
            ->pluck('full_file_number')
            ->filter()->map(fn($v) => strtoupper(trim((string)$v)))->unique()->values()->all();

        $dcivLinkCountMap = [];
        if (!empty($dcivFileNums)) {
            $conn->table('dciv_link')
                ->selectRaw('UPPER(LTRIM(RTRIM(main_file_number))) as fn, COUNT(*) as cnt')
                ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(main_file_number)))'), $dcivFileNums)
                ->groupBy(DB::raw('UPPER(LTRIM(RTRIM(main_file_number)))'))
                ->get()
                ->each(function ($r) use (&$dcivLinkCountMap) {
                    $dcivLinkCountMap[(string)$r->fn] = (int)$r->cnt;
                });
        }

        // 2a. file_indexing_links counts keyed by file_indexing_id (primary lookup)
        $fiIds = collect($records)
            ->filter(fn($r) => stripos((string)($r->source_table ?? ''), 'file_index') !== false)
            ->pluck('id')->filter()->map(fn($v) => (int)$v)->unique()->values()->all();

        // 2b. file_indexing_links counts keyed by UPPERCASE(file_number) (fallback lookup)
        $fiFileNums = collect($records)
            ->filter(fn($r) => stripos((string)($r->source_table ?? ''), 'file_index') !== false)
            ->pluck('full_file_number')
            ->filter()->map(fn($v) => strtoupper(trim((string)$v)))->unique()->values()->all();

        $fiLinkByIdMap = [];
        if (!empty($fiIds)) {
            $conn->table('file_indexing_links')
                ->selectRaw('file_indexing_id, COUNT(*) as cnt')
                ->whereIn('file_indexing_id', $fiIds)
                ->whereNull('deleted_at')
                ->groupBy('file_indexing_id')
                ->get()
                ->each(function ($r) use (&$fiLinkByIdMap) {
                    $fiLinkByIdMap[(int)$r->file_indexing_id] = (int)$r->cnt;
                });
        }

        $fiLinkByFnMap = [];
        if (!empty($fiFileNums)) {
            $conn->table('file_indexing_links')
                ->selectRaw('UPPER(LTRIM(RTRIM(file_number))) as fn, COUNT(*) as cnt')
                ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(file_number)))'), $fiFileNums)
                ->whereNull('deleted_at')
                ->groupBy(DB::raw('UPPER(LTRIM(RTRIM(file_number)))'))
                ->get()
                ->each(function ($r) use (&$fiLinkByFnMap) {
                    $fiLinkByFnMap[(string)$r->fn] = (int)$r->cnt;
                });
        }

        // 3. Batch member counts keyed by batch_no
        $batchNos = collect($records)
            ->filter(fn($r) => !empty($r->batch_no))
            ->pluck('batch_no')->unique()->values()->all();

        $batchCountMap = [];
        if (!empty($batchNos)) {
            DcivFileNo::whereIn('batch_no', $batchNos)->where('is_deleted', 0)
                ->selectRaw('batch_no, COUNT(*) as cnt')
                ->groupBy('batch_no')
                ->get()
                ->each(function ($r) use (&$batchCountMap) {
                    $batchCountMap[$r->batch_no] = (int)$r->cnt;
                });
        }

        // Format data for DataTables
        $data = collect($records)->map(function ($record) use ($dcivLinkCountMap, $fiLinkByIdMap, $fiLinkByFnMap, $batchCountMap) {
            $record = (object) $record;
            // Use resilient source detection because SQL unions/casts may alter casing/format.
            $sourceTable = (string) ($record->source_table ?? '');
            $isFromFileIndexings = stripos($sourceTable, 'file_index') !== false;

            // Related files button — uses pre-fetched maps (no per-row DB queries)
            // dciv_file_no rows use dciv_link; file_indexings rows use file_indexing_links.
            $relatedHtml = '';
            if ($isFromFileIndexings) {
                $fn = strtoupper(trim((string)($record->full_file_number ?? '')));
                // ID-based count is authoritative; fall back to file_number match if no ID entry.
                $linkCount = $fiLinkByIdMap[(int)$record->id] ?? $fiLinkByFnMap[$fn] ?? 0;
                if ($linkCount > 0) {
                    $relatedHtml = '<button class="inline-flex items-center gap-1.5 text-[9px] font-black text-amber-600 hover:text-amber-800 underline underline-offset-4 decoration-amber-200 uppercase tracking-widest whitespace-nowrap view-related-files-btn"'
                        . ' data-file-number="' . e($record->full_file_number) . '"'
                        . ' data-source="fi"'
                        . ' data-id="' . $record->id . '">'
                        . '<i data-lucide="link-2" class="h-2.5 w-2.5"></i> ' . $linkCount . '&nbsp;Related FileNo(s)</button>';
                } else {
                    $relatedHtml = '<span class="text-slate-300 text-xs">—</span>';
                }
            } else {
                // dciv_file_no: dciv_link is the authoritative source for related files
                $fn = strtoupper(trim((string)($record->full_file_number ?? '')));
                $linkCount = $dcivLinkCountMap[$fn] ?? 0;
                if ($linkCount > 0) {
                    $relatedHtml = '<button class="inline-flex items-center gap-1.5 text-[9px] font-black text-amber-600 hover:text-amber-800 underline underline-offset-4 decoration-amber-200 uppercase tracking-widest whitespace-nowrap view-related-files-btn"'
                        . ' data-file-number="' . e($record->full_file_number) . '"'
                        . ' data-source="dciv">'
                        . '<i data-lucide="link-2" class="h-2.5 w-2.5"></i> ' . $linkCount . '&nbsp;Related FileNo(s)</button>';
                } else {
                    $relatedHtml = '<span class="text-slate-300 text-xs">—</span>';
                }
            }

            // File number with optional batch group (uses pre-fetched batch count)
            $fileNumberHtml = '<div class="flex flex-col">'
                . '<span class="font-mono font-bold text-blue-600 text-xs whitespace-nowrap">' . e($record->full_file_number) . '</span>';
            if (!$isFromFileIndexings && $record->batch_no) {
                $batchCount = $batchCountMap[$record->batch_no] ?? 0;
                if ($batchCount > 1) {
                    $fileNumberHtml .= '<button onclick="this.dispatchEvent(new CustomEvent(\'open-batch\', {bubbles:true, detail:\'' . e($record->batch_no) . '\'}))" class="mt-1 flex items-center gap-1.5 text-[9px] font-black text-blue-500 hover:text-blue-700 underline underline-offset-4 decoration-blue-200 uppercase tracking-widest whitespace-nowrap">'
                        . '<i data-lucide="layers" class="h-2.5 w-2.5"></i> Batch Group (' . $batchCount . ')</button>';
                }
            }
            // View Files button (EDMS DCIV_Registry)
            $fileNumberHtml .= '<button class="mt-1 flex items-center gap-1.5 text-[9px] font-black text-teal-600 hover:text-teal-800 uppercase tracking-widest whitespace-nowrap dciv-view-files-btn" data-file-number="' . e($record->full_file_number) . '">'
                . '<i data-lucide="folder-open" class="h-2.5 w-2.5"></i> View Files</button>';
            $fileNumberHtml .= '</div>';

            // Commissioned by
            $userInitial = strtoupper(substr($record->created_by_name ?? 'S', 0, 1));
            $userName = e(strtoupper($record->created_by_name ?? 'System'));
            $commissionedByHtml = '<div class="flex items-center gap-2">'
                . '<div class="w-6 h-6 rounded-lg bg-blue-50 flex items-center justify-center text-[10px] font-black text-blue-600 border border-blue-100">' . $userInitial . '</div>'
                . '<span class="font-bold text-slate-600">' . $userName . '</span></div>';

            // Time & Date formatting
            $formattedTime = $record->commissioning_time;
            $formattedDate = $record->commissioning_date;
            try {
                if ($formattedTime) $formattedTime = \Carbon\Carbon::parse($record->commissioning_time)->format('h:i A');
                if ($formattedDate) $formattedDate = \Carbon\Carbon::parse($record->commissioning_date)->format('jS M, Y');
            } catch (\Exception $e) {
                // Keep raw values on parse failure
            }

            // Time & Date badges with source-specific colors
            if ($isFromFileIndexings) {
                // Amber for File Indexing
                $timeHtml = '<span class="px-2 py-1 bg-amber-50 rounded-md border border-amber-100 font-black text-amber-600 text-[11px]">' . e($formattedTime ?? '—') . '</span>';
                $dateHtml = '<span class="px-2 py-1 bg-amber-50 rounded-md border border-amber-100 font-black text-amber-700 text-[11px]">' . e($formattedDate ?? '—') . '</span>';
                $sourceHtml = '<span class="inline-flex items-center px-3 py-1 rounded-full text-[9px] font-black bg-amber-50 text-amber-700 border border-amber-100 uppercase tracking-widest">INDEXED FILE</span>';
            } else {
                // Green for Commissioned
                $timeHtml = '<span class="px-2 py-1 bg-emerald-50 rounded-md border border-emerald-100 font-black text-emerald-600 text-[11px]">' . e($formattedTime ?? '—') . '</span>';
                $dateHtml = '<span class="px-2 py-1 bg-emerald-50 rounded-md border border-emerald-100 font-black text-emerald-700 text-[11px]">' . e($formattedDate ?? '—') . '</span>';
                $sourceHtml = '<span class="inline-flex items-center px-3 py-1 rounded-full text-[9px] font-black bg-emerald-50 text-emerald-700 border border-emerald-100 uppercase tracking-widest">COMMISSIONED</span>';
            }

            // Actions: full menu for dciv_file_no, view-only for file_indexings
            $eventId = $isFromFileIndexings ? "'fi-{$record->id}'" : $record->id;

            $masterDeleteHtml = view('dciv_generation.partials.master-delete', [
                'recordId' => $isFromFileIndexings ? 'fi-' . $record->id : $record->id,
                'fileNumber' => $record->full_file_number,
            ])->render();

            if ($isFromFileIndexings) {
                $actionsHtml = '<div class="relative inline-block dciv-action-menu">'
                    . '<button onclick="toggleDcivMenu(this)" class="p-1 hover:bg-slate-100 rounded-md transition-colors">'
                    . '<i data-lucide="more-vertical" class="h-4 w-4 text-slate-400"></i></button>'
                    . '<div class="dciv-menu hidden absolute right-0 bottom-full mb-1 w-44 bg-white border border-slate-200 rounded-lg shadow-xl z-50 overflow-hidden">'
                    . '<button onclick="this.closest(\'.dciv-action-menu\').querySelector(\'.dciv-menu\').classList.add(\'hidden\'); this.dispatchEvent(new CustomEvent(\'view-record\', {bubbles:true, detail:' . $eventId . '}))" class="w-full px-4 py-2 text-left text-xs font-bold text-slate-700 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-2">'
                    . '<i data-lucide="eye" class="h-3.5 w-3.5"></i> View</button>'
                    . $masterDeleteHtml
                    . '</div></div>';
            } else {
                $recordObj = (object) [
                    'id' => $record->id,
                    'full_file_number' => $record->full_file_number,
                    'file_name' => $record->file_name,
                    'plot_no' => $record->plot_no,
                    'tp_no' => $record->tp_no,
                    'location' => $record->location,
                    'lga' => $record->lga,
                ];
                $recordJson = htmlspecialchars(json_encode($recordObj), ENT_QUOTES, 'UTF-8');
                $actionsHtml = '<div class="relative inline-block dciv-action-menu">'
                    . '<button onclick="toggleDcivMenu(this)" class="p-1 hover:bg-slate-100 rounded-md transition-colors">'
                    . '<i data-lucide="more-vertical" class="h-4 w-4 text-slate-400"></i></button>'
                    . '<div class="dciv-menu hidden absolute right-0 bottom-full mb-1 w-44 bg-white border border-slate-200 rounded-lg shadow-xl z-50 overflow-hidden">'
                    . '<button onclick="this.closest(\'.dciv-action-menu\').querySelector(\'.dciv-menu\').classList.add(\'hidden\'); this.dispatchEvent(new CustomEvent(\'edit-record\', {bubbles:true, detail:' . $record->id . '}))" class="w-full px-4 py-2 text-left text-xs font-bold text-slate-700 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-2">'
                    . '<i data-lucide="edit-3" class="h-3.5 w-3.5"></i> Edit</button>'
                    . '<button onclick="this.closest(\'.dciv-action-menu\').querySelector(\'.dciv-menu\').classList.add(\'hidden\'); this.dispatchEvent(new CustomEvent(\'view-record\', {bubbles:true, detail:' . $record->id . '}))" class="w-full px-4 py-2 text-left text-xs font-bold text-slate-700 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-2">'
                    . '<i data-lucide="eye" class="h-3.5 w-3.5"></i> View</button>'
                    . '<button onclick="this.closest(\'.dciv-action-menu\').querySelector(\'.dciv-menu\').classList.add(\'hidden\'); this.dispatchEvent(new CustomEvent(\'open-printer-manager\', {bubbles:true, detail:' . $recordJson . '}))" class="w-full px-4 py-2 text-left text-[10px] font-black text-slate-700 hover:bg-green-50 hover:text-green-600 flex items-center gap-2 uppercase tracking-tighter">'
                    . '<i data-lucide="clipboard-check" class="h-3.5 w-3.5 text-green-500"></i> Printer Manager</button>'
                    . $masterDeleteHtml
                    . '</div></div>';
            }

            return [
                'file_number' => $fileNumberHtml,
                'related_fileno' => $relatedHtml,
                'file_name' => strtoupper(Str::limit($record->file_name ?? '', 30)) ?: '—',
                'tp_no' => strtoupper($record->tp_no ?? '') ?: '—',
                'plot_no' => strtoupper($record->plot_no ?? '') ?: '—',
                'lga' => strtoupper($record->lga ?? '') ?: '—',
                'location' => strtoupper(Str::limit($record->location ?? '', 40)) ?: '—',
                'commissioned_by' => $commissionedByHtml,
                'time' => $timeHtml,
                'date' => $dateHtml,
                'source_table' => $record->source_table,
                'source' => $sourceHtml,
                'actions' => $actionsHtml,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * Fetch available GKN records for preview.
     */
    public function getAvailableDciv(Request $request)
    {
        try {
            $limit = $request->query('limit', 1);
            $prefix = $request->query('prefix', 'DCIV');
            // Manual serial mode: the operator names the serial - and the year it
            // belongs to - instead of taking last_serial + 1 in the current year. Used
            // to fill a gap the counter has run past, including one in an earlier year.
            $requestedSerial = $request->query('serial');
            $isManual = is_numeric($requestedSerial) && (int) $requestedSerial > 0;
            // Honoured whenever one is supplied - the form only sends a year in manual
            // mode, and the counter for that year has to be read before a serial is
            // typed, not after.
            $year = $this->resolveYear($request->query('year'));
        
            // 1. Determine the next serial from OUR Serial Control table
            $control = DcivSerialControl::where('prefix', $prefix)
                ->where('year', $year)
                ->first();

            $initialized = $control && $control->is_initialized;

            // The counter only matters to Auto, which takes last_serial + 1. A typed
            // (manual) serial just needs its tracking ID from the grouping table, so
            // an uninitialized counter does not block it.
            if (!$initialized && !$isManual) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'next_serial' => 'Not Initialized',
                    'year' => $year,
                    'is_initialized' => false
                ]);
            }

            $nextSerial = $isManual ? (int) $requestedSerial : $control->last_serial + 1;

            // 2. Construct the exact file number(s) we expect to find in the grouping table
            $constructedFileNos = [];
            for ($i = 0; $i < $limit; $i++) {
                $constructedFileNos[] = "{$prefix}-{$year}-" . ($nextSerial + $i);
            }

            // 3. Search ONLY for these specific file numbers
            $dcivRecords = DcivGrouping::whereIn('dciv_awaiting_fileno', $constructedFileNos)
                ->where(function($q) {
                    $q->where('mapping', 0)->orWhereNull('mapping');
                })
                ->orderBy('id', 'asc')
                ->get();

            $availableCount = $dcivRecords->count();

            // A manual serial may name a number that was already commissioned. The
            // grouping lookup above already excludes it (mapping = 1), but say so
            // plainly rather than leaving the form showing "Not Found".
            $taken = [];
            if ($isManual) {
                $taken = DcivFileNo::whereIn('full_file_number', $constructedFileNos)
                    ->where('is_deleted', 0)
                    ->pluck('full_file_number')
                    ->all();
            }

            Log::info("DCIV Strict Lookup ({$prefix}, {$year}): Constructing: " . implode(', ', $constructedFileNos) . " | Found: {$availableCount}");

            return response()->json([
                'success' => true,
                'data' => $dcivRecords,
                'next_serial' => $nextSerial,
                'auto_serial' => $initialized ? $control->last_serial + 1 : null,
                'is_manual' => $isManual,
                'taken' => $taken,
                'year' => $year,
                'is_initialized' => $initialized
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching available DCIV: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Error fetching available DCIV records'
            ], 500);
        }
    }

    /**
     * The year a file number is built in.
     *
     * A manual request may name an earlier year to fill a gap in that year's series;
     * callers that are not in manual mode simply pass nothing. Anything outside a
     * sane range, or a year in the future, falls back to the current year rather
     * than minting a number in a series nobody keeps.
     */
    private function resolveYear($requested): int
    {
        $current = (int) date('Y');

        if (!is_numeric($requested)) {
            return $current;
        }

        $year = (int) $requested;

        return ($year >= 1900 && $year <= $current) ? $year : $current;
    }

    /**
     * Store the generated DCIV file numbers and sync across tables.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'prefix' => 'required|in:DCIV,LPCC',
            'batch_mode' => 'required|boolean',
            // 'manual' lets the operator name the starting serial; 'auto' takes
            // last_serial + 1 from dciv_serial_control. Absent = auto, so older
            // callers keep the behaviour they had.
            'serial_mode' => 'nullable|in:auto,manual',
            'serial_number' => 'required_if:serial_mode,manual|nullable|integer|min:1',
            // Only read in manual mode; an auto generate is always the current year.
            'year' => 'nullable|integer|min:1900|max:2100',
            'land_use_id' => 'nullable|integer',
            'purpose_id' => 'nullable|integer',
            'quantity' => 'required_if:batch_mode,true|integer|min:1|max:50',
            'file_title' => 'required|string|max:500',
            'plot_number' => 'nullable|string|max:255',
            'tp_no' => 'nullable|string|max:255',
            'district_id' => 'nullable',
            'lga_id' => 'required|integer',
            'location' => 'required|string',
            'location_entries' => 'nullable|array',
            'location_entries.*.plot_number' => 'nullable|string|max:100',
            'location_entries.*.tp_no' => 'nullable|string|max:100',
            'location_entries.*.district_id' => 'nullable',
            'location_entries.*.custom_district' => 'nullable|string|max:255',
            'location_entries.*.lga_id' => 'nullable|integer',
            'location_entries.*.location' => 'nullable|string',
            'relatedFiles' => 'nullable|array',
            'relatedFiles.*.file_number' => 'nullable|string|max:255',
            'relatedFiles.*.secondary_title' => 'nullable|string|max:500',
            'reason' => 'nullable|string|max:1000',
        ]);

        $batchMode = $validated['batch_mode'] ?? false;
        $quantity = $batchMode ? (int)$validated['quantity'] : 1;
        $manualSerial = ($validated['serial_mode'] ?? 'auto') === 'manual';
        $selectedPrefix = $validated['prefix'];
        // A manual serial carries its own year, because the serial series is per year:
        // DCIV-2025-400 is not the same number as DCIV-2026-400, and the counter that
        // issues it is a different row.
        $currentYear = $manualSerial
            ? $this->resolveYear($validated['year'] ?? null)
            : (int) date('Y');

        // Generate a unique batch number if in batch mode
        $batchNo = $batchMode ? 'DCIV-B-' . date('Ymd-His') . '-' . rand(100, 999) : null;

        DB::connection('sqlsrv')->beginTransaction();

        try {
            // Determine the next serial number within the transaction from Control Table
            $control = DcivSerialControl::where('prefix', $selectedPrefix)
                ->where('year', $currentYear)
                ->lockForUpdate()
                ->first();

            // Auto needs the counter to know what is next. A manual serial names its
            // own number and only needs that number's tracking ID, so it goes ahead
            // without one.
            $initialized = $control && $control->is_initialized;
            if (!$initialized && !$manualSerial) {
                throw new \Exception("Serial numbering for {$selectedPrefix} in {$currentYear} has not been initialized.");
            }

            $nextSerial = $manualSerial
                ? (int) $validated['serial_number']
                : $control->last_serial + 1;

            // Strict match: Construct the specific file numbers we need tracking IDs for
            $constructedFileNos = [];
            for ($i = 0; $i < $quantity; $i++) {
                $constructedFileNos[] = "{$selectedPrefix}-{$currentYear}-" . ($nextSerial + $i);
            }

            // A manual serial can point at a number that has already been issued.
            // Catch it here so the operator gets the real reason instead of the
            // grouping table's "insufficient records", which is what an already
            // mapped row would otherwise look like.
            if ($manualSerial) {
                $alreadyIssued = DcivFileNo::whereIn('full_file_number', $constructedFileNos)
                    ->where('is_deleted', 0)
                    ->lockForUpdate()
                    ->pluck('full_file_number')
                    ->all();

                if (!empty($alreadyIssued)) {
                    throw new \Exception("Already commissioned: " . implode(', ', $alreadyIssued));
                }
            }

            // Fetch and lock ONLY the specific matching records from dciv_grouping
            $dcivRecords = DcivGrouping::whereIn('dciv_awaiting_fileno', $constructedFileNos)
                ->where(function($q) {
                    $q->where('mapping', 0)->orWhereNull('mapping');
                })
                ->orderBy('id', 'asc') // This ordering should align  
                ->lockForUpdate()
                ->get();

            if ($dcivRecords->count() < $quantity) {
                $missing = array_diff($constructedFileNos, $dcivRecords->pluck('dciv_awaiting_fileno')->toArray());
                throw new \Exception("Insufficient matching records found in grouping table. Missing: " . implode(', ', $missing));
            }

            $now = now();
            $userId = Auth::id();
            $userName = Auth::user()->name ?? Auth::user()->email ?? 'System';
            $entityName = $validated['file_title'];

            $selectedLandUse = $validated['land_use_id'] ? LandUse::find($validated['land_use_id']) : null;
            $landUseName = $selectedLandUse->landuse ?? $selectedPrefix;


            $generatedNumbers = [];

        foreach ($dcivRecords as $index => $dciv) {
            // Determine location details for this specific entry
            $plotNo = $validated['plot_number'];
            $tpNo = $validated['tp_no'] ?? null;
            $loc = $validated['location'];
            $currentDistrictId = $validated['district_id'];
            $currentCustomDistrict = $request->input('custom_district');
            $currentLgaId = $validated['lga_id'];

            // Override with batch-specific entry if available
            if ($batchMode && isset($validated['location_entries'][$index])) {
                $entry = $validated['location_entries'][$index];
                $plotNo = $entry['plot_number'] ?? $plotNo;
                $tpNo = $entry['tp_no'] ?? $tpNo;
                $loc = $entry['location'] ?? $loc;
                $currentDistrictId = $entry['district_id'] ?? $currentDistrictId;
                $currentCustomDistrict = $entry['custom_district'] ?? $currentCustomDistrict;
                $currentLgaId = $entry['lga_id'] ?? $currentLgaId;
            }

            // Fetch names for district and lga
            $currentDistrictName = null;
            if ($currentDistrictId === 'Other') {
                $currentDistrictName = $currentCustomDistrict;
            } else {
                $district = District::find($currentDistrictId);
                $currentDistrictName = $district->name ?? null;
            }
            
            $lga = Lga::find($currentLgaId);
            $currentLgaName = $lga->name ?? null;

            // Determine file number using selected prefix and incremented serial
            $prefix = $selectedPrefix;
            $year = $currentYear;
            $serial = $nextSerial + $index;
            $dcivNumber = "{$prefix}-{$year}-{$serial}";
            $trackingId = $dciv->tracking_id;

            // 2. Update dciv_grouping (Link the grouping record to this new file number)
            $dciv->update([
                'mapping' => 1,
                'dciv_fileno' => $dcivNumber,
                'created_by' => $userId,
                'updated_at' => $now,
            ]);

            // 3. Insert into fileNumber (Legacy Table)
            FileNumber::create([
                'tracking_id' => $trackingId,
                'mlsfNo' => $dcivNumber,
                'dciv_fileno' => $dcivNumber,
                'FileName' => $entityName,
                'plot_no' => $plotNo,
                'location' => $loc,
                'lga' => $currentLgaName,
                'type' => $prefix,
                'SOURCE' => "{$prefix}_Generation",
                'commissioning_date' => $now,
                'created_by' => $userId,
                'is_deleted' => 0,
            ]);

            // Collect the related file numbers up front so they can be persisted to
            // dciv_file_no.related_fileno (JSON) as before.
            $relatedFileNumbers = [];
            if (!empty($validated['relatedFiles'])) {
                foreach ($validated['relatedFiles'] as $rel) {
                    if (!empty($rel['file_number'])) {
                        $relatedFileNumbers[] = $rel['file_number'];
                    }
                }
            }

            $dcivReason = $validated['reason'] ?? null;

            // 4. Insert into dciv_file_no (Dedicated Metadata Table) FIRST so its id is
            //    available for the master_dciv_links foreign key below.
            $dcivRecord = DcivFileNo::create([
                'batch_no' => $batchNo,
                'prefix' => $prefix,
                'year' => $year,
                'serial_number' => $serial,
                'full_file_number' => $dcivNumber,
                'file_name' => $entityName,
                'plot_no' => $plotNo,
                'tp_no' => $tpNo,
                'location' => $loc,
                'lga' => $currentLgaName,
                'tracking_id' => $trackingId,
                'created_by' => $userId,
                'commissioning_date' => $now,
                'commissioning_time' => $now->format('H:i:s'),
                'land_use_id' => $validated['land_use_id'] ?? null,
                'purpose_id' => $validated['purpose_id'] ?? null,
                'related_fileno' => !empty($relatedFileNumbers) ? json_encode($relatedFileNumbers) : null,
                'dciv_reason' => $dcivReason,
            ]);

            // Persist the related-file links to dciv_link (general) + master_dciv_links,
            // and flag each linked file's indexing row as under DCIV investigation.
            if (!empty($validated['relatedFiles'])) {
                foreach ($validated['relatedFiles'] as $rel) {
                    if (empty($rel['file_number'])) {
                        continue;
                    }

                    $relatedNo = $rel['file_number'];
                    $relatedTitle = $rel['secondary_title'] ?? null;

                    // Save to dciv_link before file_indexings as requested
                    DB::connection('sqlsrv')->table('dciv_link')->insert([
                        'main_file_number' => $dcivNumber,
                        'related_file_number' => $relatedNo,
                        'secondary_title' => $relatedTitle,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    // Resolve a title from file_indexings when none was provided.
                    if (empty($relatedTitle)) {
                        $relatedTitle = DB::connection('sqlsrv')->table('file_indexings')
                            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper(trim($relatedNo))])
                            ->value('file_title');
                    }

                    // Master link row (typed columns + general related_file_number).
                    MasterDcivLink::create(array_merge([
                        'dciv_file_no_id' => $dcivRecord->id,
                        'dciv_file_number' => $dcivNumber,
                        'dciv_reason' => $dcivReason,
                        'related_file_number' => $relatedNo,
                        'related_file_title' => $relatedTitle,
                        'created_by' => $userId,
                    ], MasterDcivLink::typeColumns($relatedNo)));

                    // Flag the related file's indexing row (if it exists) as DCIV-linked.
                    FileIndexing::whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper(trim($relatedNo))])
                        ->update([
                            'dciv_status' => 1,
                            'dciv_fileno' => $dcivNumber,
                            'dciv_reason' => $dcivReason,
                        ]);
                }
            }

            FileIndexing::create([
                'tracking_id' => $trackingId,
                'file_number' => $dcivNumber,
                'file_title' => $entityName,
                'land_use_type' => $landUseName,
                'plot_number' => $plotNo,
                'tp_no' => $tpNo,
                'location' => $loc,
                'district' => $currentDistrictName,
                'lga' => $currentLgaName,
                'created_by' => $userName,
                'workflow_status' => 'indexed',
                'source' => 'DCIV FileNo Commissioning',
                'related_fileno' => !empty($relatedFileNumbers) ? json_encode($relatedFileNumbers) : null,
            ]);


                $generatedNumbers[] = [
                    'file_number' => $dcivNumber,
                    'tracking_id' => $trackingId
                ];
            }

            // Update Control Table. A manual serial may sit behind the counter
            // (filling a gap), so never let last_serial move backwards - that would
            // hand the same number out twice on the next auto generate.
            // An uninitialized counter is left alone: advancing and locking it from a
            // manual serial would set its starting point by accident, and that is
            // only ever done under Serial Initialization.
            if ($initialized) {
                $control->update([
                    'last_serial' => max($control->last_serial, $nextSerial + $quantity - 1),
                    'is_locked' => true, // Lock after first generate as per user request
                    'updated_at' => $now,
                ]);
            }

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully generated " . count($generatedNumbers) . " record(s)",
                'data' => $generatedNumbers
            ]);

        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('DCIV Generation Failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch all records for a specific batch.
     */
    public function getBatchMembers($batchNo)
    {
        try {
            $records = DcivFileNo::with(['user'])
                ->where('batch_no', $batchNo)
                ->where('is_deleted', 0)
                ->orderBy('full_file_number', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $records
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch a single record for editing/viewing.
     * Supports both dciv_file_no records (numeric ID) and file_indexings records (fi-{id} prefix).
     */
    public function edit($id)
    {
        try {
            // Handle file_indexings records (prefixed with fi-)
            if (str_starts_with((string) $id, 'fi-')) {
                return $this->viewFileIndexingRecord((int) substr($id, 3));
            }

            $record = DcivFileNo::with(['user:id,first_name,last_name'])
                ->findOrFail($id);

            $createdByName = trim(collect([
                optional($record->user)->first_name,
                optional($record->user)->last_name,
            ])->filter()->implode(' '));

            $record->created_by_name = $createdByName !== '' ? $createdByName : (string) ($record->created_by ?? 'System');
            
            // Fetch related files from dciv_link
            $relatedFiles = DB::connection('sqlsrv')
                ->table('dciv_link')
                ->where('main_file_number', $record->full_file_number)
                ->get()
                ->map(function($item) {
                    return [
                        'file_number' => $item->related_file_number,
                        'secondary_title' => $item->secondary_title
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $record,
                'related_files' => $relatedFiles
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * View a file_indexings record mapped to dciv_file_no response format.
     */
    private function viewFileIndexingRecord(int $id)
    {
        $record = FileIndexing::findOrFail($id);

        $data = (object) [
            'id' => 'fi-' . $record->id,
            'full_file_number' => $record->file_number,
            'file_name' => $record->file_title,
            'plot_no' => $record->plot_number,
            'tp_no' => $record->tp_no,
            'location' => $record->location,
            'lga' => $record->lga,
            'land_use_id' => null,
            'purpose_id' => null,
            'dciv_reason' => $record->dciv_reason ?? null,
            'tracking_id' => $record->tracking_id,
            'commissioning_date' => optional($record->created_at)->format('Y-m-d'),
            'commissioning_time' => optional($record->created_at)->format('H:i:s'),
            'formatted_date' => optional($record->created_at)->format('jS M, Y'),
            'formatted_time' => optional($record->created_at)->format('h:i A'),
            'created_by_name' => $record->created_by ?? 'System',
            'source' => 'file_indexings',
        ];

        // Build related files from the record's related_fileno JSON
        $relatedFiles = collect();
        if ($record->related_fileno) {
            $related = json_decode($record->related_fileno, true);
            if (is_array($related)) {
                $relatedFiles = collect($related)->map(fn($fn) => [
                    'file_number' => $fn,
                    'secondary_title' => null,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'related_files' => $relatedFiles,
        ]);
    }

    /**
     * Update GKN record metadata.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'file_name' => 'required|string|max:500',
            'land_use_id' => 'nullable|integer',
            'purpose_id' => 'nullable|integer',
            'plot_no' => 'nullable|string|max:100',
            'tp_no' => 'nullable|string|max:100',
            'district_id' => 'nullable',
            'lga_id' => 'required|integer',
            'location' => 'required|string',
            'custom_district' => 'nullable|string|max:255',
            'reason' => 'nullable|string|max:1000',
        ]);

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $record = DcivFileNo::findOrFail($id);
            $oldFileNo = $record->full_file_number;

            $districtName = null;
            if ($validated['district_id'] === 'Other') {
                $districtName = $validated['custom_district'];
            } else {
                $district = District::find($validated['district_id']);
                $districtName = $district->name ?? null;
            }

            $lga = Lga::find($validated['lga_id']);
            $landUse = $validated['land_use_id'] ? LandUse::find($validated['land_use_id']) : null;

            // Update record
            $record->update([
                'file_name' => $validated['file_name'],
                'plot_no' => $validated['plot_no'],
                'tp_no' => $validated['tp_no'],
                'location' => $validated['location'],
                'lga' => $lga->name ?? $record->lga,
                'land_use_id' => $validated['land_use_id'] ?? $record->land_use_id,
                'purpose_id' => $validated['purpose_id'] ?? $record->purpose_id,
                'dciv_reason' => $validated['reason'] ?? $record->dciv_reason,
            ]);

            // Sync with file_indexings
            FileIndexing::where('file_number', $oldFileNo)->update([
                'file_title' => $validated['file_name'],
                'plot_number' => $validated['plot_no'],
                'tp_no' => $validated['tp_no'],
                'location' => $validated['location'],
                'district' => $districtName,
                'lga' => $lga->name ?? null,
                'land_use_type' => $landUse->landuse ?? null,
            ]);

            // Sync with fileNumber
            FileNumber::where('mlsfNo', $oldFileNo)->update([
                'FileName' => $validated['file_name'],
                'plot_no' => $validated['plot_no'],
                'location' => $validated['location'],
                'lga' => $lga->name ?? null,
            ]);

            // Propagate the (possibly updated) reason to the master link table and to
            // every related file's indexing row flagged under this DCIV file.
            $newReason = $validated['reason'] ?? $record->dciv_reason;

            MasterDcivLink::where('dciv_file_number', $oldFileNo)
                ->update(['dciv_reason' => $newReason]);

            FileIndexing::where('dciv_fileno', $oldFileNo)
                ->update(['dciv_reason' => $newReason]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Record updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch file title for a given file number.
     */
    public function getFileTitle(Request $request)
    {
        try {
            $fileNumber = $request->query('file_number');
            if (!$fileNumber) {
                return response()->json(['success' => false, 'message' => 'File number is required'], 400);
            }

            // Try file_indexing_links
            $link = DB::connection('sqlsrv')->table('file_indexing_links')
                ->where('file_number', $fileNumber)
                ->first();

            if ($link && !empty($link->file_title)) {
                return response()->json(['success' => true, 'title' => $link->file_title]);
            }

            // Try fileNumber
            $file = DB::connection('sqlsrv')->table('fileNumber')
                ->where('mlsfNo', $fileNumber)
                ->first();

            if ($file && !empty($file->FileName)) {
                return response()->json(['success' => true, 'title' => $file->FileName]);
            }

            // Try file_indexings as fallback
            $indexing = DB::connection('sqlsrv')->table('file_indexings')
                ->where('file_number', $fileNumber)
                ->first();

            if ($indexing && !empty($indexing->file_title)) {
                return response()->json(['success' => true, 'title' => $indexing->file_title]);
            }

            return response()->json(['success' => false, 'message' => 'File title not found']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch related file numbers for a given DCIV file number.
     * Supports both dciv_link (source=dciv) and file_indexing_links (source=fi).
     */
    public function getRelatedFiles(Request $request, $fileNumber)
    {
        try {
            $source = $request->query('source', 'dciv');

            if ($source === 'fi') {
                // Fetch from file_indexing_links using file_indexing_id, with fallback to file_number match.
                $fileIndexingId = $request->query('id');
                $normalizedFileNumber = strtoupper(trim((string) $fileNumber));

                $baseQuery = DB::connection('sqlsrv')
                    ->table('file_indexing_links')
                    ->whereNull('deleted_at');

                if (!empty($fileIndexingId)) {
                    $baseQuery->where('file_indexing_id', $fileIndexingId);
                } elseif ($normalizedFileNumber !== '') {
                    $baseQuery->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$normalizedFileNumber]);
                }

                $relatedRows = $baseQuery->orderBy('id', 'asc')->get();

                // If nothing is linked by id, try a direct file_number fallback.
                if ($relatedRows->isEmpty() && $normalizedFileNumber !== '') {
                    $relatedRows = DB::connection('sqlsrv')
                        ->table('file_indexing_links')
                        ->whereNull('deleted_at')
                        ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$normalizedFileNumber])
                        ->orderBy('id', 'asc')
                        ->get();
                }

                $relatedFiles = $relatedRows->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'related_file_number' => $item->file_number,
                        'secondary_title' => $item->file_title ?? null,
                        'plot_number' => $item->plot_number ?? null,
                        'tp_no' => $item->tp_no ?? null,
                        'lpkn_no' => $item->lpkn_no ?? null,
                        'location' => $item->location ?? null,
                    ];
                });
            } else {
                // Fetch from dciv_link table using main_file_number
                $relatedFiles = DB::connection('sqlsrv')
                    ->table('dciv_link')
                    ->where('main_file_number', $fileNumber)
                    ->orderBy('id', 'asc')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'related_file_number' => $item->related_file_number,
                            'secondary_title' => $item->secondary_title,
                            'plot_number' => null,
                            'tp_no' => null,
                            'lpkn_no' => null,
                            'location' => null,
                        ];
                    });
            }

            return response()->json([
                'success' => true,
                'source' => $source,
                'data' => $relatedFiles
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Return EDMS files for a given DCIV file number from DCIV_Registry folder.
     */
    public function getEdmsFiles($fileNumber)
    {
        try {
            $fileNumber = strtoupper(trim($fileNumber));
            $basePath = storage_path('app/public/EDMS/UPLOAD/DCIV_Registry');
            $folderPath = $basePath . DIRECTORY_SEPARATOR . $fileNumber;

            if (!is_dir($folderPath)) {
                return response()->json(['has_files' => false, 'file_number' => $fileNumber, 'files' => []]);
            }

            $files = [];
            $realBase = realpath($basePath);

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($folderPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $realPath = $file->getRealPath();
                    // Security: ensure path stays within expected base directory
                    if ($realBase && strpos($realPath, $realBase) !== 0) {
                        continue;
                    }
                    $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $realPath);
                    $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
                    $url = asset('storage/EDMS/UPLOAD/DCIV_Registry/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath));
                    $files[] = [
                        'name' => $file->getFilename(),
                        'url' => $url,
                        'type' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'image' : ($ext === 'pdf' ? 'pdf' : 'other'),
                        'ext' => $ext,
                    ];
                }
            }

            return response()->json([
                'has_files' => count($files) > 0,
                'file_number' => $fileNumber,
                'files' => $files,
            ]);
        } catch (\Exception $e) {
            return response()->json(['has_files' => false, 'file_number' => $fileNumber ?? '', 'files' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Serial initialization moved to System Admin → Configurable Entries →
     * Serial Initialization (Land & Deeds). That one screen sets and locks the
     * starting serial for every generator; see
     * ConfigurableEntriesController::SERIAL_REGISTERS.
     */
}
