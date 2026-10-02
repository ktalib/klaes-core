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
                'createdAt' => optional($tracker->created_at)->toIso8601String(),
                'updatedAt' => optional($tracker->updated_at)->toIso8601String(),
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

        $baseQuery = Notification::query()
            ->forUser($user->id)
            ->where(function ($query) use ($types) {
                $query->where('module', 'file_tracking')
                    ->orWhereIn('type', $types);
            })
            ->orderByDesc('created_at');

        $totalCount = (clone $baseQuery)->count();
        $unreadCount = (clone $baseQuery)->where('is_read', false)->count();
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
            //   • Requested Files  = logged out, purpose/type NOT "In-Transit" (or SUBMITTED)
            //   • In Transit       = purpose/type = "In-Transit", still logged out
            //   • Not in Transit   = logged back into the registry (status COMPLETED)
            // In-Transit and Completed files are ordered first so they are never
            // truncated by the row limit.
            ->whereRaw("UPPER(ISNULL(status, '')) NOT IN ('CANCELED', 'CANCELLED')")
            ->orderByRaw("CASE WHEN file_request_type = 'In-Transit' OR request_purpose_name = 'In-Transit' THEN 0 WHEN UPPER(status) = 'COMPLETED' THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

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
            //   • request_purpose_name / file_request_type carry the literal "In-Transit"
            //     value when a file is logged OUT of the registry to a destination office.
            //   • status becomes COMPLETED once the file is logged back IN to the registry
            //     (the "Log-in" action in Create File Tracker).
            $fileReqType = Str::upper((string) $tracker->file_request_type);
            $reqPurpose  = Str::upper((string) $tracker->request_purpose_name);
            $statusUpper = Str::upper((string) $tracker->status);

            $isInTransitFlagged = ($fileReqType === 'IN-TRANSIT' || $reqPurpose === 'IN-TRANSIT');
            $isCanceled = in_array($statusUpper, ['CANCELED', 'CANCELLED'], true);

            if ($isCanceled) {
                // Cancelled file — no longer moving.
                $movementStatus = 'canceled';
            } elseif ($statusUpper === 'COMPLETED') {
                // Logged back into the registry → Not in Transit tab.
                $movementStatus = 'returned';
            } elseif ($isInTransitFlagged) {
                // Logged out with an "In-Transit" purpose/type → In Transit tab.
                $movementStatus = 'in_transit';
            } else {
                // Logged out for a request (SUBMITTED or any non In-Transit purpose),
                // not yet returned → Requested Files tab, shown as "Log-out".
                $movementStatus = 'logout';
            }

            $isReturned  = ($movementStatus === 'returned');
            $isInTransit = ($movementStatus === 'in_transit');
            // Requested = logged out, purpose/type is not "In-Transit", not yet returned.
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
                'createdAt' => optional($tracker->created_at)->toIso8601String(),
                'updatedAt' => optional($tracker->updated_at)->toIso8601String(),
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
     * Normalise movement log entries to a consistent structure.
     *
     * @param  array  $movementLog
     * @param  \Illuminate\Support\Collection  $officeMetadata
     * @return array
     */
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
