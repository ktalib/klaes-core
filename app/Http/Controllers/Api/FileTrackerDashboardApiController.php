<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FileTracker;
use App\Models\Notification;
use App\Models\Office; 
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FileTrackerDashboardApiController extends Controller
{
    /**
     * Land use codes used as file number prefixes, in their direct, conversion
     * ("CON-…") and recertification ("…-RC") forms.
     */
    protected const LAND_USE_CODES = [
        'RES' => 'Residential',
        'COM' => 'Commercial',
        'IND' => 'Industrial',
        'AG' => 'Agriculture',
    ];

    /**
     * The office a file was requested to: the receiving officer's posting,
     * falling back to the current office for rows logged before the receiving
     * office was captured. Used as the In-Transit tab's grouping key, so the
     * group list, the row list and the office filter all agree.
     */
    protected const REQUESTER_OFFICE_SQL =
        "ISNULL(NULLIF(LTRIM(RTRIM(receiving_office_code)), ''), ISNULL(NULLIF(LTRIM(RTRIM(current_office_code)), ''), 'UNASSIGNED'))";

    /**
     * Return aggregated metrics and tracker data for the dashboard overview.
     */
    public function overview(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 50);
        $limit = max(10, min($limit, 200));

        $totalFiles = FileTracker::count();

        $activeFiles = FileTracker::query()
            ->whereNotNull('status')
            ->whereRaw('UPPER(status) = ?', [FileTracker::STATUS_ACTIVE])
            ->count();

        $highPriorityFiles = FileTracker::query()
            ->whereNotNull('priority')
            ->whereRaw('UPPER(priority) = ?', [FileTracker::PRIORITY_HIGH])
            ->count();

        $priorityCounts = FileTracker::query()
            ->selectRaw('UPPER(priority) as priority, COUNT(*) as total')
            ->groupBy(DB::raw('UPPER(priority)'))
            ->pluck('total', 'priority');

        $priorityBreakdown = [
            FileTracker::PRIORITY_HIGH => (int) ($priorityCounts[FileTracker::PRIORITY_HIGH] ?? 0),
            FileTracker::PRIORITY_MEDIUM => (int) ($priorityCounts[FileTracker::PRIORITY_MEDIUM] ?? 0),
            FileTracker::PRIORITY_LOW => (int) ($priorityCounts[FileTracker::PRIORITY_LOW] ?? 0),
        ];

        $priorityBreakdown['OTHER'] = max(
            0,
            (int) $priorityCounts->sum() - array_sum($priorityBreakdown)
        );

        $uniqueOffices = FileTracker::query()
            ->whereNotNull('current_office_code')
            ->distinct('current_office_code')
            ->count('current_office_code');

        $officeDistributionRaw = FileTracker::query()
            ->select('current_office_code', DB::raw('COUNT(*) as total_files'))
            ->whereNotNull('current_office_code')
            ->groupBy('current_office_code')
            ->orderByDesc('total_files')
            ->limit(10)
            ->get();

        $trackers = FileTracker::query()
            ->select([
                'id',
                'tracking_id',
                'file_number',
                'file_title',
                'priority',
                'status',
                'current_office_code',
                'current_office_name',
                'movement_log',
                'created_at',
                'updated_at',
            ])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        // One query for the page's titles: file_title reads file_indexings
        // per row otherwise (see FileTracker::primeFileTitles).
        FileTracker::primeFileTitles($trackers->pluck('file_number'));

        $officeCodesNeeded = [];

        foreach ($trackers as $tracker) {
            if ($tracker->current_office_code) {
                $officeCodesNeeded[$tracker->current_office_code] = true;
            }

            foreach ((array) $tracker->movement_log as $entry) {
                $code = $this->extractOfficeCode($entry);
                if ($code) {
                    $officeCodesNeeded[$code] = true;
                }
            }
        }

        foreach ($officeDistributionRaw as $row) {
            if ($row->current_office_code) {
                $officeCodesNeeded[$row->current_office_code] = true;
            }
        }

        $officeMetadata = Office::query()
            ->whereIn('office_code', array_keys($officeCodesNeeded))
            ->get()
            ->keyBy('office_code');

        $activeStatuses = ['ACTIVE', 'PENDING', 'PENDING_ACCEPTANCE', 'IN_PROGRESS'];
        $trackerPayload = [];
        $activeTrackerCount = 0;

        foreach ($trackers as $tracker) {
            $movementLog = $this->normaliseMovementLog((array) $tracker->movement_log, $officeMetadata);
            $activeEntry = null;

            foreach ($movementLog as $entry) {
                if (in_array($entry['status'], $activeStatuses, true)) {
                    $activeEntry = $entry;
                    break;
                }
            }

            if (!$activeEntry) {
                continue;
            }

            $activeTrackerCount++;

            $currentOfficeCode = $activeEntry['officeId'] ?? $tracker->current_office_code;
            $office = $officeMetadata->get($currentOfficeCode);

            $trackerPayload[] = [
                'id' => $tracker->id,
                'fileNo' => $tracker->file_number,
                'fileName' => $tracker->file_title ?: ($tracker->file_number ?: 'File #' . $tracker->id),
                'trackingId' => $tracker->tracking_id,
                'priority' => Str::upper((string) $tracker->priority),
                'status' => Str::upper((string) $tracker->status),
                'currentOffice' => $office->office_name ?? $tracker->current_office_name,
                'currentOfficeId' => $currentOfficeCode,
                'currentOfficeDepartment' => $office->department ?? null,
                'logEntries' => $movementLog,
                'createdAt' => $this->toIso($tracker->created_at),
                'updatedAt' => $this->toIso($tracker->updated_at),
            ];
        }

        if ($activeFiles === 0 && $activeTrackerCount > 0) {
            $activeFiles = $activeTrackerCount;
        }

        $officeDistribution = $officeDistributionRaw->map(function ($row) use ($officeMetadata) {
            $office = $officeMetadata->get($row->current_office_code);

            return [
                'officeCode' => $row->current_office_code,
                'officeName' => $office->office_name ?? $row->current_office_code,
                'department' => $office->department ?? null,
                'totalFiles' => (int) $row->total_files,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'File tracker dashboard overview generated successfully.',
            'data' => [
                'stats' => [
                    'totalFiles' => $totalFiles,
                    'activeFiles' => $activeFiles,
                    'highPriorityFiles' => $highPriorityFiles,
                    'uniqueOffices' => $uniqueOffices,
                ],
                'priorityBreakdown' => $priorityBreakdown,
                'officeDistribution' => $officeDistribution,
                'trackers' => $trackerPayload,
                'generatedAt' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Provide per-user notifications (with sound) for the global header bell.
     */
    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min($limit, 20));

        $types = $request->filled('types')
            ? array_filter((array) $request->query('types'), fn ($value) => !empty($value))
            : ['file_tracking.assignment'];

        // scope=all lists EVERY module (legal_search_online, digital_request,
        // parcel_update...), not just file tracking. The header bell asks for it:
        // the post-login flash plays any unread notification, so a bell that only
        // listed file_tracking left the other modules unreadable AND unclearable —
        // "Mark all as read" stayed disabled and the pop-up replayed every login.
        $scopeAll = strtolower((string) $request->query('scope')) === 'all';

        $baseQuery = Notification::query()
            ->forUser($user->id)
            ->when(!$scopeAll, function ($query) use ($types) {
                $query->where(function ($inner) use ($types) {
                    $inner->where('module', 'file_tracking')
                        ->orWhereIn('type', $types);
                });
            })
            ->orderByDesc('created_at');

        $totalCount = (clone $baseQuery)->count();
        $unreadCount = (clone $baseQuery)->where('is_read', false)->count();

        // Unread across every module, whatever this request was scoped to. The
        // "Mark all as read" button is enabled off this number because that
        // endpoint clears everything the user owns.
        $unreadCountAll = $scopeAll
            ? $unreadCount
            : Notification::query()->forUser($user->id)->where('is_read', false)->count();
        $records = (clone $baseQuery)->limit($limit)->get();

        $notifications = $records->map(function (Notification $notification) {
            $title = $notification->title
                ?? $notification->subject
                ?? 'File tracker update';

            $body = $notification->body
                ?? $notification->message
                ?? 'A new file tracker notification is available.';

            $data = $notification->data ?? [];

            return [
                'id' => $notification->id,
                'title' => $title,
                'body' => $body,
                'type' => $notification->type,
                'isRead' => (bool) $notification->is_read,
                'createdAt' => optional($notification->created_at)->toIso8601String(),
                'data' => [
                    'fileNumber' => $data['file_number'] ?? null,
                    'fileName' => $data['file_name'] ?? null,
                    'officeName' => $data['assigned_office_name'] ?? null,
                    'reason' => $data['reason'] ?? null,
                    'raw' => $data,
                ],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'File tracker notifications retrieved successfully.',
            'data' => [
                'count' => $totalCount,
                'unreadCount' => $unreadCount,
                'unreadCountAll' => $unreadCountAll,
                'limit' => $limit,
                'items' => $notifications,
                'generatedAt' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Return aggregated metrics and tracker data for the Commissioner dashboard.
     */
    public function commissionerOverview(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 500);
        $limit = max(10, min($limit, 500)); // Increase limit for more overview

        $trackers = FileTracker::query()
            ->select([
                'id',
                'tracking_id',
                'file_number',
                'file_title',
                'priority',
                'status',
                'current_office_code',
                'current_office_name',
                'movement_log',
                'created_at',
                'updated_at',
                'file_type',
                'department',
                'file_request_type',
                'request_purpose_name',
                'date_requested',
                'created_by_name',
                'origin_office_code',
                'origin_office_name',
                'receiving_officer_name',
                'num_pages',
                'deadline',
                'timeline_days',
            ])
            // Exclude only cancelled files; the three tabs (Requested / In Transit /
            // Not in Transit) between them cover every other tracker:
            //   • Requested Files  = file_request_type SUBMITTED, still logged out
            //   • In Transit       = every other request type (MANUAL / SYSTEM /
            //                        In-Transit / unclassified), still logged out
            //   • Not in Transit   = logged back into the registry (status COMPLETED)
            // In-Transit and Completed files are ordered first so they are never
            // truncated by the row limit.
            ->whereRaw("UPPER(ISNULL(status, '')) NOT IN ('CANCELED', 'CANCELLED')")
            ->orderByRaw('CASE WHEN NOT ' . self::requestedFlagSql() . " THEN 0 WHEN UPPER(status) = 'COMPLETED' THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        // One query for the page's titles: file_title reads file_indexings
        // per row otherwise (see FileTracker::primeFileTitles).
        FileTracker::primeFileTitles($trackers->pluck('file_number'));

        $officeCodesNeeded = [];

        foreach ($trackers as $tracker) {
            if ($tracker->current_office_code) {
                $officeCodesNeeded[$tracker->current_office_code] = true;
            }

            foreach ((array) $tracker->movement_log as $entry) {
                $code = $this->extractOfficeCode($entry);
                if ($code) {
                    $officeCodesNeeded[$code] = true;
                }
            }
        }

        $officeMetadata = Office::query()
            ->whereIn('office_code', array_keys($officeCodesNeeded))
            ->get()
            ->keyBy('office_code');

        $trackerPayload = [];

        foreach ($trackers as $tracker) {
            $movementLog = $this->normaliseMovementLog((array) $tracker->movement_log, $officeMetadata);
            
            $currentOfficeCode = $tracker->current_office_code;
            $office = $officeMetadata->get($currentOfficeCode);

            // Classify the tracker against the Create File Tracker workflow.
            //   • file_request_type is SUBMITTED only when an officer requested the
            //     file; anything else (MANUAL, SYSTEM, the legacy "In-Transit"
            //     literal, or nothing at all) means the file is simply moving.
            //   • status becomes COMPLETED once the file is logged back IN to the registry
            //     (the "Log-in" action in Create File Tracker).
            $fileReqType = Str::upper((string) $tracker->file_request_type);
            $statusUpper = Str::upper((string) $tracker->status);

            $isInTransitFlagged = ($fileReqType !== self::REQUESTED_REQUEST_TYPE);
            $isCanceled = in_array($statusUpper, ['CANCELED', 'CANCELLED'], true);

            if ($isCanceled) {
                // Cancelled file — no longer moving.
                $movementStatus = 'canceled';
            } elseif ($statusUpper === 'COMPLETED') {
                // Logged back into the registry → Not in Transit tab.
                $movementStatus = 'returned';
            } elseif ($isInTransitFlagged) {
                // Logged out on any non-SUBMITTED request type → In Transit tab.
                $movementStatus = 'in_transit';
            } else {
                // A SUBMITTED request that has not been returned → Requested Files
                // tab, shown as "Log-out".
                $movementStatus = 'logout';
            }

            $isReturned  = ($movementStatus === 'returned');
            $isInTransit = ($movementStatus === 'in_transit');
            // Requested = logged out on a SUBMITTED request, not yet returned.
            $isRequested = ($movementStatus === 'logout');

            $trackerPayload[] = [
                'id' => $tracker->id,
                'fileNo' => $tracker->file_number,
                'fileName' => $tracker->file_title ?: ($tracker->file_number ?: 'File #' . $tracker->id),
                'trackingId' => $tracker->tracking_id,
                'priority' => Str::upper((string) $tracker->priority),
                'status' => Str::upper((string) $tracker->status),
                'currentOffice' => $office->office_name ?? $tracker->current_office_name,
                'currentOfficeId' => $currentOfficeCode,
                'currentOfficeDepartment' => $office->department ?? null,
                'department' => $tracker->department,
                'caseType' => $tracker->file_type ?? $tracker->request_purpose_name,
                'applicant' => $tracker->created_by_name,
                // The requester of record is the officer the file is requested
                // for (receiving_officer_name), not the clerk who keyed it in.
                'requester' => $tracker->receiving_officer_name,
                'originOffice' => $tracker->origin_office_name,
                'originOfficeCode' => $tracker->origin_office_code,
                'receivingOfficer' => $tracker->receiving_officer_name,
                'requestPurpose' => $tracker->request_purpose_name,
                'fileRequestType' => $tracker->file_request_type,
                'numPages' => $tracker->num_pages,
                'deadline' => optional($tracker->deadline)->toIso8601String(),
                'timelineDays' => $tracker->timeline_days,
                // Workflow classification (Create File Tracker parity)
                'movementStatus' => $movementStatus,
                'isInTransit' => $isInTransit,
                'isReturned' => $isReturned,
                'isCanceled' => $isCanceled,
                'isRequested' => $isRequested,
                'requestedDate' => optional($tracker->date_requested)->toIso8601String() ?? optional($tracker->created_at)->toIso8601String(),
                'requestDate' => optional($tracker->date_requested)->toIso8601String() ?? optional($tracker->created_at)->toIso8601String(),
                'logEntries' => $movementLog,
                'createdAt' => $this->toIso($tracker->created_at),
                'updatedAt' => $this->toIso($tracker->updated_at),
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Commissioner dashboard overview generated successfully.',
            'data' => [
                'trackers' => $trackerPayload,
                'generatedAt' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * The one file_request_type that means "an officer asked for this file",
     * i.e. the Requested Files tab. It is what Create File Tracker writes for
     * its "Submitted Request" type. Every other tracker — MANUAL (operator
     * log-out), SYSTEM (range-home/commissioning row), the legacy "In-Transit"
     * literal, or a row that was never classified — is a movement and belongs
     * to the In-Transit (Movement) tab.
     */
    protected const REQUESTED_REQUEST_TYPE = 'SUBMITTED';

    /**
     * The Requested predicate as SQL. The In-Transit tab is its exact negation,
     * so the two tabs can never drift apart or double-count a tracker.
     */
    protected static function requestedFlagSql(): string
    {
        return "(UPPER(ISNULL(file_request_type, '')) = '" . self::REQUESTED_REQUEST_TYPE . "')";
    }

    /**
     * Shared definition of an "in transit" file: logged out with any request type
     * other than SUBMITTED and not yet logged back in. Honours the priority and search
     * parameters — but not the office, which is the grouping key — so the group
     * list and the row list always agree.
     */
    protected function inTransitBaseQuery(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $priority = Str::upper(trim((string) $request->query('priority', 'ALL')));

        $query = FileTracker::query()
            ->whereRaw("UPPER(ISNULL(status, '')) NOT IN ('CANCELED', 'CANCELLED', 'COMPLETED')")
            ->whereRaw('NOT ' . self::requestedFlagSql());

        if ($priority !== '' && $priority !== 'ALL') {
            $query->whereRaw("UPPER(ISNULL(priority, '')) = ?", [$priority]);
        }

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['[%]', '[_]'], $search) . '%';
            $query->where(function ($inner) use ($like) {
                $inner->where('file_number', 'like', $like)
                    ->orWhere('file_title', 'like', $like)
                    ->orWhere('tracking_id', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * The In-Transit tab's page unit: requester offices, not rows. Returns one
     * page of office groups with their file counts, plus the whole-set summary
     * for the tab's cards and charts. Rows are fetched per office by inTransit()
     * only when a group is expanded, so collapsed groups cost nothing.
     */
    public function inTransitOffices(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 25);
        $perPage = max(5, min($perPage, 200));

        $page = max(1, (int) $request->query('page', 1));

        $baseQuery = $this->inTransitBaseQuery($request);

        $rawGroups = (clone $baseQuery)
            ->selectRaw(
                self::REQUESTER_OFFICE_SQL . ' as office_group, COUNT(*) as total_files,'
                . " SUM(CASE WHEN UPPER(ISNULL(priority, '')) = 'HIGH' THEN 1 ELSE 0 END) as high_total"
            )
            ->groupBy(DB::raw(self::REQUESTER_OFFICE_SQL))
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get();

        $officeMetadata = $rawGroups->isEmpty()
            ? collect()
            : Office::query()->whereIn('office_code', $rawGroups->pluck('office_group')->all())->get()->keyBy('office_code');

        $groups = $rawGroups->map(function ($row) use ($officeMetadata) {
            $office = $officeMetadata->get($row->office_group);

            return [
                'officeCode' => $row->office_group,
                // Codes without an office row (e.g. DEPARTMENT_QUEUE) read better
                // as words than as raw codes in the header, filter and chart.
                'officeName' => $office->office_name ?? Str::title(str_replace('_', ' ', (string) $row->office_group)),
                'department' => $office->department ?? null,
                'totalFiles' => (int) $row->total_files,
                // Lets a collapsed group header show its urgency without
                // fetching any of its rows.
                'highFiles' => (int) $row->high_total,
            ];
        })->values();

        // The office dropdown narrows the tab to one group; the whole-set
        // distribution below still lists every office so the dropdown keeps its
        // other options.
        $office = trim((string) $request->query('office', 'ALL'));
        $pagedGroups = ($office !== '' && $office !== 'ALL')
            ? $groups->where('officeCode', $office)->values()
            : $groups;

        $totalOffices = $pagedGroups->count();
        $lastPage = max(1, (int) ceil($totalOffices / $perPage));
        $page = min($page, $lastPage);

        $priorityExpression = "UPPER(ISNULL(priority, ''))";

        // Summary cards follow whatever is on screen, so they narrow with the
        // office dropdown too.
        $statsQuery = (clone $baseQuery);

        if ($office !== '' && $office !== 'ALL') {
            $statsQuery->whereRaw(self::REQUESTER_OFFICE_SQL . ' = ?', [$office]);
        }

        $priorityCounts = $statsQuery
            ->selectRaw($priorityExpression . ' as priority_group, COUNT(*) as total')
            ->groupBy(DB::raw($priorityExpression))
            ->pluck('total', 'priority_group');

        return response()->json([
            'success' => true,
            'message' => 'In-transit requester offices retrieved successfully.',
            'data' => [
                'offices' => $pagedGroups->forPage($page, $perPage)->values(),
                'stats' => [
                    'total' => (int) $pagedGroups->sum('totalFiles'),
                    'high' => (int) ($priorityCounts[FileTracker::PRIORITY_HIGH] ?? 0),
                    'medium' => (int) ($priorityCounts[FileTracker::PRIORITY_MEDIUM] ?? 0),
                    'low' => (int) ($priorityCounts[FileTracker::PRIORITY_LOW] ?? 0),
                    'offices' => $totalOffices,
                ],
                // Whole-set distribution — drives the office filter and the chart.
                'officeDistribution' => $groups,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $totalOffices,
                    'lastPage' => $lastPage,
                    'from' => $totalOffices === 0 ? 0 : (($page - 1) * $perPage) + 1,
                    'to' => min($page * $perPage, $totalOffices),
                    'unit' => 'offices',
                ],
                'generatedAt' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Rows for the In-Transit tab. Called with an `office` (a requester office
     * code) to fill in one expanded group; without one it still returns a flat
     * paginated list.
     */
    public function inTransit(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 25);
        $perPage = max(10, min($perPage, 1000));

        $page = max(1, (int) $request->query('page', 1));

        $baseQuery = $this->inTransitBaseQuery($request);

        // The table paginates by requester office and pulls one office's rows at
        // a time, so a single group is never split across requests.
        $office = trim((string) $request->query('office', 'ALL'));

        if ($office !== '' && $office !== 'ALL') {
            $baseQuery->whereRaw(self::REQUESTER_OFFICE_SQL . ' = ?', [$office]);
        }

        $total = (clone $baseQuery)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $trackers = (clone $baseQuery)
            ->select([
                'id',
                'tracking_id',
                'file_number',
                'file_title',
                'priority',
                'status',
                'department',
                'current_office_code',
                'current_office_name',
                'origin_office_name',
                'request_purpose_name',
                'file_request_type',
                'date_requested',
                'movement_log',
                'receiving_office_code',
                'receiving_office_name',
                'receiving_officer_id',
                'receiving_officer_name',
                'created_by',
                'created_by_name',
                'created_at',
                'updated_at',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        // One query for the page's titles: file_title reads file_indexings
        // per row otherwise (see FileTracker::primeFileTitles).
        FileTracker::primeFileTitles($trackers->pluck('file_number'));

        $officeCodesNeeded = [];

        foreach ($trackers as $tracker) {
            if ($tracker->current_office_code) {
                $officeCodesNeeded[$tracker->current_office_code] = true;
            }

            if ($tracker->receiving_office_code) {
                $officeCodesNeeded[$tracker->receiving_office_code] = true;
            }

            foreach ((array) $tracker->movement_log as $entry) {
                $code = $this->extractOfficeCode($entry);
                if ($code) {
                    $officeCodesNeeded[$code] = true;
                }
            }
        }

        // Distribution by requester office — the tab's grouping key. Computed
        // before the office lookup so its codes get resolved to names too.
        // The whole-set figures live in inTransitOffices(); this one is scoped by
        // whatever filters this call carries.
        $officeDistributionRaw = (clone $baseQuery)
            ->selectRaw(self::REQUESTER_OFFICE_SQL . ' as office_group, COUNT(*) as total_files')
            ->groupBy(DB::raw(self::REQUESTER_OFFICE_SQL))
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get();

        foreach ($officeDistributionRaw as $row) {
            $officeCodesNeeded[$row->office_group] = true;
        }

        $officeMetadata = empty($officeCodesNeeded)
            ? collect()
            : Office::query()->whereIn('office_code', array_keys($officeCodesNeeded))->get()->keyBy('office_code');

        $files = $trackers->map(function (FileTracker $tracker) use ($officeMetadata) {
            $office = $officeMetadata->get($tracker->current_office_code);
            $requestedDate = $tracker->date_requested ?: $tracker->created_at;
            $requestedIso = $this->toIso($requestedDate);

            // The office the file was requested to, i.e. the posting of the
            // receiving officer. Falls back to the current office for older rows
            // that were logged before the receiving office was captured.
            $requesterOfficeCode = $tracker->receiving_office_code ?: $tracker->current_office_code;
            $requesterOffice = $officeMetadata->get($requesterOfficeCode);

            return [
                'id' => $tracker->id,
                'fileNo' => $tracker->file_number,
                'fileName' => $tracker->file_title ?: ($tracker->file_number ?: 'File #' . $tracker->id),
                'trackingId' => $tracker->tracking_id,
                'priority' => Str::upper((string) $tracker->priority),
                'status' => Str::upper((string) $tracker->status),
                'department' => $tracker->department,
                'currentOffice' => $office->office_name ?? $tracker->current_office_name,
                'currentOfficeId' => $tracker->current_office_code,
                // "Department Queue" is not a real office row, so the tracker's own
                // department is what identifies where the file actually sits.
                'currentOfficeDepartment' => $office->department ?? $tracker->department,
                'requesterOffice' => $requesterOffice->office_name
                    ?? ($tracker->receiving_office_name ?: $tracker->current_office_name),
                'requesterOfficeId' => $requesterOfficeCode,
                'requesterOfficeDepartment' => $requesterOffice->department ?? $tracker->department,
                'originOffice' => $tracker->origin_office_name,
                'requestPurpose' => $tracker->request_purpose_name,
                'caseType' => $tracker->request_purpose_name,
                'requester' => $tracker->receiving_officer_name,
                'requesterId' => $tracker->receiving_officer_id,
                'createdByName' => $tracker->created_by_name,
                'applicant' => $tracker->created_by_name,
                'movementStatus' => 'in_transit',
                'isInTransit' => true,
                'isRequested' => false,
                'isReturned' => false,
                'isCanceled' => false,
                'logEntries' => $this->normaliseMovementLog((array) $tracker->movement_log, $officeMetadata),
                'requestedDate' => $requestedIso,
                'requestDate' => $requestedIso,
                'createdAt' => $this->toIso($tracker->created_at),
                'updatedAt' => $this->toIso($tracker->updated_at),
            ];
        })->values();

        $priorityExpression = "UPPER(ISNULL(priority, ''))";

        $priorityCounts = (clone $baseQuery)
            ->selectRaw($priorityExpression . ' as priority_group, COUNT(*) as total')
            ->groupBy(DB::raw($priorityExpression))
            ->pluck('total', 'priority_group');

        $officeDistribution = $officeDistributionRaw->map(function ($row) use ($officeMetadata) {
            $office = $officeMetadata->get($row->office_group);

            return [
                'officeCode' => $row->office_group,
                // Codes without an office row (e.g. DEPARTMENT_QUEUE) read better
                // as words than as raw codes in the filter and chart.
                'officeName' => $office->office_name ?? Str::title(str_replace('_', ' ', (string) $row->office_group)),
                'department' => $office->department ?? null,
                'totalFiles' => (int) $row->total_files,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Files in transit retrieved successfully.',
            'data' => [
                'files' => $files,
                'stats' => [
                    'total' => $total,
                    'high' => (int) ($priorityCounts[FileTracker::PRIORITY_HIGH] ?? 0),
                    'medium' => (int) ($priorityCounts[FileTracker::PRIORITY_MEDIUM] ?? 0),
                    'low' => (int) ($priorityCounts[FileTracker::PRIORITY_LOW] ?? 0),
                    'offices' => $officeDistribution->count(),
                ],
                'officeDistribution' => $officeDistribution,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'lastPage' => $lastPage,
                    'from' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
                    'to' => min($page * $perPage, $total),
                ],
                'generatedAt' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Shared definition of a "requested" file: logged out with file_request_type
     * SUBMITTED and not yet logged back in — the exact complement of
     * inTransitBaseQuery(). Honours the period and
     * search parameters so the group list and the row list always agree.
     */
    protected function requestedBaseQuery(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $period = Str::lower(trim((string) $request->query('period', 'all')));

        $query = FileTracker::query()
            ->whereRaw("UPPER(ISNULL(status, '')) NOT IN ('CANCELED', 'CANCELLED', 'COMPLETED')")
            ->whereRaw(self::requestedFlagSql());

        // Period filter, applied to the request date (falling back to created_at).
        $startDate = match ($period) {
            'weekly' => now()->subWeek(),
            'monthly' => now()->subMonth(),
            'quarterly' => now()->subMonths(3),
            default => null,
        };

        if ($startDate) {
            $query->whereRaw('ISNULL(date_requested, created_at) >= ?', [$startDate]);
        }

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['[%]', '[_]'], $search) . '%';
            $query->where(function ($inner) use ($like) {
                $inner->where('file_number', 'like', $like)
                    ->orWhere('file_title', 'like', $like)
                    ->orWhere('tracking_id', 'like', $like)
                    // The requester is a visible column, so it is searchable too.
                    ->orWhere('receiving_officer_name', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * The Requested tab's page unit: departments, not rows. Returns one page of
     * department groups with their file counts, plus the whole-set summary for
     * the tab's cards. Rows are fetched per department by requestedFiles() only
     * when a group is expanded, so collapsed groups cost nothing.
     */
    public function requestedDepartments(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 25);
        $perPage = max(5, min($perPage, 200));

        $page = max(1, (int) $request->query('page', 1));

        $baseQuery = $this->requestedBaseQuery($request);
        $departmentExpression = "ISNULL(NULLIF(LTRIM(RTRIM(department)), ''), 'Unassigned')";

        $groups = (clone $baseQuery)
            ->selectRaw(
                $departmentExpression . ' as department_group, COUNT(*) as total,'
                . " SUM(CASE WHEN UPPER(ISNULL(priority, '')) = 'HIGH' THEN 1 ELSE 0 END) as high_total"
            )
            ->groupBy(DB::raw($departmentExpression))
            ->orderByRaw($departmentExpression)
            ->get()
            ->map(fn ($row) => [
                'department' => $row->department_group,
                'totalFiles' => (int) $row->total,
                // Lets a collapsed group header show its urgency without
                // fetching any of its rows.
                'highFiles' => (int) $row->high_total,
            ])
            ->values();

        $totalDepartments = $groups->count();
        $lastPage = max(1, (int) ceil($totalDepartments / $perPage));
        $page = min($page, $lastPage);

        $priorityExpression = "UPPER(ISNULL(priority, ''))";

        $priorityCounts = (clone $baseQuery)
            ->selectRaw($priorityExpression . ' as priority_group, COUNT(*) as total')
            ->groupBy(DB::raw($priorityExpression))
            ->pluck('total', 'priority_group');

        return response()->json([
            'success' => true,
            'message' => 'Requested file departments retrieved successfully.',
            'data' => [
                'departments' => $groups->forPage($page, $perPage)->values(),
                'stats' => [
                    'total' => (int) $groups->sum('totalFiles'),
                    'high' => (int) ($priorityCounts[FileTracker::PRIORITY_HIGH] ?? 0),
                    'medium' => (int) ($priorityCounts[FileTracker::PRIORITY_MEDIUM] ?? 0),
                    'low' => (int) ($priorityCounts[FileTracker::PRIORITY_LOW] ?? 0),
                    'departments' => $totalDepartments,
                ],
                'departmentCounts' => $groups,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $totalDepartments,
                    'lastPage' => $lastPage,
                    'from' => $totalDepartments === 0 ? 0 : (($page - 1) * $perPage) + 1,
                    'to' => min($page * $perPage, $totalDepartments),
                    'unit' => 'departments',
                ],
                'period' => Str::lower(trim((string) $request->query('period', 'all'))),
                'generatedAt' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Rows for the Requested tab. Called with a `department` to fill in one
     * expanded group; without one it still returns a flat paginated list (used
     * by the printed request sheet).
     */
    public function requestedFiles(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 25);
        $perPage = max(10, min($perPage, 1000));

        $page = max(1, (int) $request->query('page', 1));

        $baseQuery = $this->requestedBaseQuery($request);

        // The table paginates by department and pulls one department's rows at a
        // time, so a single group is never split across requests.
        $department = trim((string) $request->query('department', ''));

        if ($department !== '') {
            if (Str::lower($department) === 'unassigned') {
                $baseQuery->whereRaw("ISNULL(LTRIM(RTRIM(department)), '') = ''");
            } else {
                $baseQuery->where('department', $department);
            }
        }

        $total = (clone $baseQuery)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $trackers = (clone $baseQuery)
            ->select([
                'id',
                'tracking_id',
                'file_number',
                'file_title',
                'priority',
                'status',
                'department',
                'current_office_name',
                'origin_office_name',
                'request_purpose_name',
                'file_request_type',
                'date_requested',
                'current_office_code',
                'movement_log',
                'receiving_office_code',
                'receiving_office_name',
                'receiving_officer_id',
                'receiving_officer_name',
                'created_by',
                'created_by_name',
                'created_at',
                'updated_at',
            ])
            // Grouped by department: rows for one department stay contiguous so the
            // table can emit a group header per department, newest request first
            // within each group. Rows with no department sort last.
            ->orderByRaw("CASE WHEN ISNULL(LTRIM(RTRIM(department)), '') = '' THEN 1 ELSE 0 END")
            ->orderBy('department')
            ->orderByRaw('ISNULL(date_requested, created_at) DESC')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        // One query for the page's titles: file_title reads file_indexings
        // per row otherwise (see FileTracker::primeFileTitles).
        FileTracker::primeFileTitles($trackers->pluck('file_number'));

        // Office metadata for the movement log of just this page of rows.
        $officeCodesNeeded = [];

        foreach ($trackers as $tracker) {
            if ($tracker->current_office_code) {
                $officeCodesNeeded[$tracker->current_office_code] = true;
            }

            if ($tracker->receiving_office_code) {
                $officeCodesNeeded[$tracker->receiving_office_code] = true;
            }

            foreach ((array) $tracker->movement_log as $entry) {
                $code = $this->extractOfficeCode($entry);
                if ($code) {
                    $officeCodesNeeded[$code] = true;
                }
            }
        }

        $officeMetadata = empty($officeCodesNeeded)
            ? collect()
            : Office::query()->whereIn('office_code', array_keys($officeCodesNeeded))->get()->keyBy('office_code');

        $files = $trackers->map(function (FileTracker $tracker) use ($officeMetadata) {
            $requestedDate = $tracker->date_requested ?: $tracker->created_at;
            $requestedIso = $this->toIso($requestedDate);
            $office = $officeMetadata->get($tracker->current_office_code);

            // The office the file was requested to, i.e. the posting of the
            // receiving officer. Falls back to the current office for older rows
            // that were logged before the receiving office was captured.
            $requesterOfficeCode = $tracker->receiving_office_code ?: $tracker->current_office_code;
            $requesterOffice = $officeMetadata->get($requesterOfficeCode);

            return [
                'id' => $tracker->id,
                'fileNo' => $tracker->file_number,
                'fileName' => $tracker->file_title ?: ($tracker->file_number ?: 'File #' . $tracker->id),
                'trackingId' => $tracker->tracking_id,
                'priority' => Str::upper((string) $tracker->priority),
                'status' => Str::upper((string) $tracker->status),
                'department' => $tracker->department,
                'currentOffice' => $office->office_name ?? $tracker->current_office_name,
                'currentOfficeId' => $tracker->current_office_code,
                'currentOfficeDepartment' => $office->department ?? null,
                'requesterOffice' => $requesterOffice->office_name
                    ?? ($tracker->receiving_office_name ?: $tracker->current_office_name),
                'requesterOfficeId' => $requesterOfficeCode,
                'requesterOfficeDepartment' => $requesterOffice->department ?? $tracker->department,
                'originOffice' => $tracker->origin_office_name,
                'requestPurpose' => $tracker->request_purpose_name,
                'caseType' => $tracker->request_purpose_name,
                // The requester of record is the officer the file is requested
                // for (receiving_officer_name), not the clerk who keyed it in.
                'requester' => $tracker->receiving_officer_name,
                'requesterId' => $tracker->receiving_officer_id,
                'createdByName' => $tracker->created_by_name,
                'applicant' => $tracker->created_by_name,
                // The commissioner tabs share the same row/detail renderers, so the
                // workflow flags they expect are emitted here too.
                'movementStatus' => 'logout',
                'isRequested' => true,
                'isInTransit' => false,
                'isReturned' => false,
                'isCanceled' => false,
                'logEntries' => $this->normaliseMovementLog((array) $tracker->movement_log, $officeMetadata),
                'requestedDate' => $requestedIso,
                'requestDate' => $requestedIso,
                'createdAt' => $this->toIso($tracker->created_at),
                'updatedAt' => $this->toIso($tracker->updated_at),
            ];
        })->values();

        $priorityExpression = "UPPER(ISNULL(priority, ''))";

        $priorityCounts = (clone $baseQuery)
            ->selectRaw($priorityExpression . ' as priority_group, COUNT(*) as total')
            ->groupBy(DB::raw($priorityExpression))
            ->pluck('total', 'priority_group');

        // Whole-set totals per department so a group header can show its real
        // count even when the group is split across pages.
        $departmentExpression = "ISNULL(NULLIF(LTRIM(RTRIM(department)), ''), 'Unassigned')";

        $departmentCounts = (clone $baseQuery)
            ->selectRaw($departmentExpression . ' as department_group, COUNT(*) as total')
            ->groupBy(DB::raw($departmentExpression))
            ->orderByRaw($departmentExpression)
            ->get()
            ->map(fn ($row) => [
                'department' => $row->department_group,
                'totalFiles' => (int) $row->total,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Requested files retrieved successfully.',
            'data' => [
                'files' => $files,
                'stats' => [
                    'total' => $total,
                    'high' => (int) ($priorityCounts[FileTracker::PRIORITY_HIGH] ?? 0),
                    'medium' => (int) ($priorityCounts[FileTracker::PRIORITY_MEDIUM] ?? 0),
                    'low' => (int) ($priorityCounts[FileTracker::PRIORITY_LOW] ?? 0),
                    'departments' => $departmentCounts->count(),
                ],
                'departmentCounts' => $departmentCounts,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'lastPage' => $lastPage,
                    'from' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
                    'to' => min($page * $perPage, $total),
                ],
                'period' => Str::lower(trim((string) $request->query('period', 'all'))),
                'department' => $department !== '' ? $department : null,
                'generatedAt' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Paginated list of every indexed file that is NOT currently in transit.
     *
     * The three commissioner tabs are sourced differently:
     *   • Requested Files / In Transit → file_tracker rows still logged out.
     *   • Not in Transit               → the whole file_indexings registry, minus
     *     the file numbers that are currently logged out. A file only leaves this
     *     tab while it is out of the registry; once it is logged back in it
     *     reappears here (flagged as "Returned").
     */
    public function notInTransit(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 25);
        $perPage = max(10, min($perPage, 200));

        $page = max(1, (int) $request->query('page', 1));
        $search = trim((string) $request->query('search', ''));

        // A tracker that is neither completed nor cancelled means the file is
        // still out of the registry, so it belongs to one of the other tabs.
        $outOfRegistry = function ($query) {
            $query->select(DB::raw('1'))
                ->from('file_tracker')
                ->whereColumn('file_tracker.file_number', 'file_indexings.file_number')
                ->whereRaw("UPPER(ISNULL(file_tracker.status, '')) NOT IN ('CANCELED', 'CANCELLED', 'COMPLETED')");
        };

        $baseQuery = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->whereRaw('ISNULL(is_deleted, 0) = 0')
            ->whereNotNull('file_number')
            ->whereRaw("LTRIM(RTRIM(file_number)) <> ''")
            ->whereNotExists($outOfRegistry);

        if ($search !== '') {
            // The default SQL Server collation is case-insensitive, so the columns
            // are left unwrapped here to keep the index seek.
            $like = '%' . str_replace(['%', '_'], ['[%]', '[_]'], $search) . '%';
            $baseQuery->where(function ($query) use ($like) {
                $query->where('file_number', 'like', $like)
                    ->orWhere('file_title', 'like', $like)
                    ->orWhere('kangis_file_no', 'like', $like)
                    ->orWhere('new_kangis_file_no', 'like', $like);
            });
        }

        $total = (clone $baseQuery)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $rows = (clone $baseQuery)
            ->select([
                'id',
                'file_number',
                'file_title',
                'file_type',
                'land_use_type',
                'registry',
                'physical_registry',
                'general_registry',
                'location',
                'shelf_location',
                'current_location',
                'tracking_status',
                'district',
                'created_at',
                'updated_at',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        // Pull the latest tracker (if any) for just the rows on this page so the
        // table can show the last office the file visited.
        $fileNumbers = $rows->pluck('file_number')->filter()->values()->all();
        $trackers = collect();

        if (!empty($fileNumbers)) {
            $trackers = FileTracker::query()
                ->select([
                    'id',
                    'tracking_id',
                    'file_number',
                    'priority',
                    'status',
                    'current_office_code',
                    'current_office_name',
                    'origin_office_name',
                    'updated_at',
                ])
                ->whereIn('file_number', $fileNumbers)
                ->orderByDesc('updated_at')
                ->get()
                ->keyBy(fn ($tracker) => Str::upper(trim((string) $tracker->file_number)));
        }

        $files = $rows->map(function ($row) use ($trackers) {
            $tracker = $trackers->get(Str::upper(trim((string) $row->file_number)));
            $hasReturned = $tracker && Str::upper((string) $tracker->status) === 'COMPLETED';

            // `registry` holds bare values like "1" / "2" / "SLTR", so the more
            // descriptive physical/general registry names are preferred.
            $registryLabel = $row->physical_registry
                ?: $row->general_registry
                ?: ($row->registry
                    ? (is_numeric($row->registry) ? 'Registry ' . $row->registry : $row->registry)
                    : null);

            // The file number prefix is the reliable signal — land_use_type holds a
            // lot of legacy junk (bare codes, whole file numbers) — so it is only
            // consulted for numbers without a land-use prefix (e.g. KANGIS numbers).
            $landUse = $this->landUseFromFileNumber($row->file_number)
                ?: $this->normaliseLandUse($row->land_use_type);

            // Location column = the indexed property location, falling back to the
            // district and then to where the file itself sits.
            $location = $this->cleanLocation($row->location)
                ?: $this->cleanLocation($row->district)
                ?: $row->current_location
                ?: ($tracker->current_office_name ?? null)
                ?: $registryLabel;

            return [
                'id' => $row->id,
                'fileNo' => $row->file_number,
                'fileName' => $row->file_title ?: ($row->file_number ?: 'File #' . $row->id),
                'trackingId' => $tracker->tracking_id ?? null,
                'priority' => $tracker ? Str::upper((string) $tracker->priority) : null,
                'location' => $location ?: '—',
                'shelfLocation' => $row->shelf_location,
                'registry' => $registryLabel,
                'district' => $row->district,
                'landUse' => $landUse,
                'fileType' => $this->fileTypeFromLandUse($landUse) ?: $row->file_type,
                'trackingStatus' => $row->tracking_status,
                'hasTracker' => (bool) $tracker,
                'isReturned' => $hasReturned,
                'statusLabel' => $hasReturned ? 'Returned' : 'Indexed',
                'createdAt' => $this->toIso($row->created_at),
                'updatedAt' => $this->toIso($row->updated_at),
            ];
        })->values();

        // Whole-set counters for the summary cards (not just the current page).
        $returnedTotal = (clone $baseQuery)
            ->whereExists(function ($query) {
                $query->select(DB::raw('1'))
                    ->from('file_tracker')
                    ->whereColumn('file_tracker.file_number', 'file_indexings.file_number')
                    ->whereRaw("UPPER(ISNULL(file_tracker.status, '')) = 'COMPLETED'");
            })
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Files not in transit retrieved successfully.',
            'data' => [
                'files' => $files,
                'stats' => [
                    'total' => $total,
                    'returned' => $returnedTotal,
                    'neverMoved' => max(0, $total - $returnedTotal),
                ],
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'lastPage' => $lastPage,
                    'from' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
                    'to' => min($page * $perPage, $total),
                ],
                'generatedAt' => now()->toIso8601String(),
            ],
        ]);
    }

    public function markNotificationAsRead(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user() ?? auth()->user();
        $userId = $user ? (int) $user->id : 0;
        $ownerId = (int) $notification->user_id;

        if (!$userId || $ownerId !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to update this notification.',
            ], 403);
        }

        if (!$notification->is_read) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * Drop the "UNKNOWN" placeholders out of a comma-separated location so that
     * "PIECE OF LAND, UNKNOWN, UNKNOWN, KANO STATE" reads "PIECE OF LAND, KANO
     * STATE". Returns null when nothing meaningful is left.
     */
    protected function cleanLocation(?string $location): ?string
    {
        $location = trim((string) $location);

        if ($location === '') {
            return null;
        }

        $parts = array_filter(
            array_map('trim', explode(',', $location)),
            fn ($part) => $part !== '' && !in_array(Str::upper($part), ['UNKNOWN', 'N/A', 'NA', 'NIL', 'NULL'], true)
        );

        return empty($parts) ? null : implode(', ', $parts);
    }

    /**
     * Resolve the land use from a file number prefix.
     *
     * Covers the direct ("RES-…"), conversion ("CON-RES-…") and recertification
     * ("RES-RC-…", "CON-RES-RC-…") forms by matching the first land-use segment.
     */
    protected function landUseFromFileNumber(?string $fileNumber): ?string
    {
        if (!$fileNumber) {
            return null;
        }

        foreach (preg_split('/[-\s\/]+/', Str::upper(trim($fileNumber))) as $segment) {
            if (isset(self::LAND_USE_CODES[$segment])) {
                return self::LAND_USE_CODES[$segment];
            }
        }

        return null;
    }

    /**
     * Accept either a land-use code ("RES") or its full name, rejecting the
     * legacy values that ended up in file_indexings.land_use_type.
     */
    protected function normaliseLandUse(?string $landUse): ?string
    {
        $value = Str::upper(trim((string) $landUse));

        if ($value === '') {
            return null;
        }

        if (isset(self::LAND_USE_CODES[$value])) {
            return self::LAND_USE_CODES[$value];
        }

        return in_array($value, array_map('strtoupper', self::LAND_USE_CODES), true)
            ? Str::title($value)
            : null;
    }

    /**
     * File type implied by the land use: residential land is held by individuals,
     * commercial and industrial land by corporate bodies.
     */
    protected function fileTypeFromLandUse(?string $landUse): ?string
    {
        return match (Str::upper((string) $landUse)) {
            'RESIDENTIAL' => 'Individual',
            'COMMERCIAL', 'INDUSTRIAL' => 'Corporate',
            default => null,
        };
    }

    /**
     * Normalise movement log entries to a consistent structure.
     *
     * @param  array  $movementLog
     * @param  \Illuminate\Support\Collection  $officeMetadata
     * @return array
     */
    /**
     * Date columns are cast to Carbon already, so Carbon::parse() on one costs a
     * full string re-parse (~1.5ms) for nothing. At 1,000 rows × several dates
     * that was the single biggest cost in building the request sheet.
     */
    protected function toIso($value): ?string
    {
        if (!$value) {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? $value->format('c')
            : Carbon::parse($value)->toIso8601String();
    }

    protected function normaliseMovementLog(array $movementLog, Collection $officeMetadata): array
    {
        $prepared = [];

        foreach ($movementLog as $entry) {
            $officeCode = $this->extractOfficeCode($entry);
            $office = $officeCode ? $officeMetadata->get($officeCode) : null;

            $prepared[] = [
                'logId' => $entry['log_id'] ?? $entry['logId'] ?? null,
                'logInTime' => $entry['log_in_time'] ?? $entry['logInTime'] ?? null,
                'logInDate' => $entry['log_in_date'] ?? $entry['logInDate'] ?? null,
                'logOutTime' => $entry['log_out_time'] ?? $entry['logOutTime'] ?? null,
                'logOutDate' => $entry['log_out_date'] ?? $entry['logOutDate'] ?? null,
                'officeId' => $officeCode,
                'officeName' => $entry['office_name'] ?? $entry['officeName'] ?? ($office->office_name ?? null),
                'notes' => $entry['notes'] ?? null,
                'status' => Str::upper($entry['status'] ?? 'ACTIVE'),
                'receivingOfficer' => $entry['receiving_officer_name'] ?? $entry['receivingOfficer'] ?? null,
                'handledBy' => $entry['user_name'] ?? $entry['userName'] ?? null,
                'purpose' => $entry['purpose'] ?? null,
                'createdAt' => $entry['timestamp'] ?? $entry['createdAt'] ?? null,
            ];
        }

        return $prepared;
    }

    /**
     * Extract an office code from a movement log entry.
     *
     * @param  array  $entry
     * @return string|null
     */
    protected function extractOfficeCode(array $entry): ?string
    {
        $code = $entry['office_code'] ?? $entry['officeCode'] ?? $entry['office_id'] ?? $entry['officeId'] ?? null;

        return $code ? (string) $code : null;
    }
}
