<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FileTracker;
use App\Models\OtherReceivingOfficer;
use App\Models\RequestPurpose;
use App\Models\User;
use App\Services\QuickActionsService;
use App\Services\UserNotificationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FileTrackerApiController extends Controller
{
    public function __construct(
        protected UserNotificationService $notificationService
    ) {
    }
    /**
     * Check whether a file is currently logged out.
     * A file is "logged out" when no movement entry has status='active' with an empty log_out_date.
     * This covers both: (a) fresh trackers where the origin entry has log_out_date set, and
     * (b) trackers where completeMovement was called and the entry became 'completed'.
     * GET /api/file-trackers/check-logout-status?file_number=XXX
     */
    public function checkLogoutStatus(Request $request): JsonResponse
    {
        $fileNumber = trim((string) $request->get('file_number', ''));

        if ($fileNumber === '') {
            return response()->json(['is_logged_out' => false]);
        }

        $existingTrackers = FileTracker::where('file_number', $fileNumber)
            ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(status,'')))) NOT IN ('COMPLETED', 'CANCELLED')")
            ->get();

        foreach ($existingTrackers as $existing) {
            $log = $existing->movement_log ?? [];
            if (empty($log)) {
                continue;
            }

            $entries = collect($log);

            // File is "checked in" only if an active entry has no log_out_date yet.
            $currentlyCheckedIn = $entries->contains(
                fn($e) => strtolower($e['status'] ?? '') === 'active' && empty($e['log_out_date'])
            );

            if (!$currentlyCheckedIn) {
                $lastEntry = $entries->last();
                return response()->json([
                    'is_logged_out'  => true,
                    'tracking_id'    => $existing->tracking_id,
                    'current_office' => $existing->current_office_name ?? ($lastEntry['office_name'] ?? null),
                ]);
            }
        }

        return response()->json(['is_logged_out' => false]);
    }

    /**
     * Get all file trackers with optional filtering
     * GET /api/file-trackers
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = FileTracker::query();

            // Apply filters
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            if ($request->has('priority') && $request->priority) {
                $query->byPriority($request->priority);
            }

            if ($request->has('department') && $request->department) {
                $query->byDepartment($request->department);
            }

            if ($request->has('created_by') && $request->created_by) {
                $query->byUser($request->created_by);
            }

            if ($request->has('overdue') && $request->overdue === 'true') {
                $query->overdue();
            }

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('tracking_id', 'LIKE', "%{$search}%")
                      ->orWhere('file_number', 'LIKE', "%{$search}%")
                      ->orWhere('file_title', 'LIKE', "%{$search}%")
                      ->orWhere('created_by_name', 'LIKE', "%{$search}%");
                });
            }

            if ($request->filled('file_numbers')) {
                $fileNumbersInput = $request->input('file_numbers');
                if (!is_array($fileNumbersInput)) {
                    $fileNumbersInput = explode(',', (string) $fileNumbersInput);
                }

                $fileNumbers = array_values(array_filter(array_map(function ($value) {
                    return trim((string) $value);
                }, $fileNumbersInput), function ($value) {
                    return $value !== '';
                }));

                if (!empty($fileNumbers)) {
                    $query->whereIn('file_number', $fileNumbers);
                }
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = (int) $request->get('per_page', 15);
            $trackers = $query->paginate($perPage);

            foreach ($trackers as $tracker) {
                $this->decorateTracker($tracker);
            }

            return response()->json([
                'success' => true,
                'data' => $trackers,
                'message' => 'File trackers retrieved successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving file trackers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new file tracker
     * POST /api/file-trackers
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $workflowTypeInput = $request->input('workflow_type');
            $isKangisWorkflowSubmission = in_array(
                $workflowTypeInput,
                [FileTracker::WORKFLOW_KANGIS_3STEP, FileTracker::WORKFLOW_KANGIS_APPROVAL, FileTracker::WORKFLOW_CROSS_MODULE_REQUEST],
                true
            );

            // SLTR "In-process" internal sections (Directors, Deputy Director, Production,
            // Geometry, Report, Other) have no office/officer records — the section itself
            // is the receiving point, so there is no real officer to select.
            $isSltrInternalNoOfficer = filter_var($request->input('sltr_internal_no_officer'), FILTER_VALIDATE_BOOLEAN);

            $validator = Validator::make($request->all(), [
                'file_number' => 'nullable|string|max:255',
                'file_title' => 'required|string|max:255',
                'file_type' => 'nullable|string|max:100',
                'priority' => 'required|in:LOW,MEDIUM,HIGH',
                'status' => 'nullable|in:Log-in,Canceled',
                'department' => 'required|string|max:100',
                'description' => 'nullable|string',
                'deadline' => 'nullable|date',
                'request_purpose_id' => 'nullable|integer|exists:sqlsrv.request_purposes,id',
                'request_purpose_other' => 'nullable|string|max:255',
                'movement_log' => 'required|array',
                'movement_log.*.office_code' => 'required|string',
                'movement_log.*.office_name' => 'required|string',
                'movement_log.*.log_in_time' => 'nullable|string',
                'movement_log.*.log_in_date' => 'nullable|date',
                'movement_log.*.log_out_time' => 'nullable|string',
                'movement_log.*.log_out_date' => 'nullable|date',
                'movement_log.*.notes' => 'nullable|string',
                'movement_log.*.origin_office_code' => 'nullable|string|max:100',
                'movement_log.*.origin_office_name' => 'nullable|string|max:255',
                'movement_log.*.origin_office_department' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'origin_office_code' => 'required|string|max:100',
                'origin_office_name' => 'required|string|max:255',
                'origin_office_department' => 'nullable|string|max:255',
                'receiving_office_code' => $isKangisWorkflowSubmission ? 'nullable|string|max:100' : 'required|string|max:100',
                'receiving_office_name' => $isKangisWorkflowSubmission ? 'nullable|string|max:255' : 'required|string|max:255',
                'receiving_officer_id' => ($isKangisWorkflowSubmission || $isSltrInternalNoOfficer) ? 'nullable|string|max:50' : 'required|string|max:50',
                'receiving_officer_name' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if the file is already logged out (no active movement entry on an existing tracker).
            // A file is "logged out" when it has been checked out to another office and has not been
            // logged back in — indicated by at least one 'completed' log entry but no 'active' one.
            $fileNumber = trim((string) ($request->input('file_number', '')));
            $currentModule = strtolower(trim((string) $request->input('module', '')));
            if ($fileNumber !== '') {
                $existingTrackers = FileTracker::where('file_number', $fileNumber)
                    ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(status,'')))) NOT IN ('COMPLETED', 'CANCELLED')")
                    ->get();

                foreach ($existingTrackers as $existing) {
                    // Only a logged-out tracker in the SAME module blocks a new logout.
                    // A file checked out under a different module/department (e.g. Cadastral)
                    // is tracked independently and must not block this module's registry.
                    if (strtolower(trim((string) ($existing->module ?? ''))) !== $currentModule) {
                        continue;
                    }

                    $log = $existing->movement_log ?? [];
                    if (empty($log)) {
                        continue;
                    }

                    $entries = collect($log);
                    $currentlyCheckedIn = $entries->contains(
                        fn($e) => strtolower($e['status'] ?? '') === 'active' && empty($e['log_out_date'])
                    );

                    if (!$currentlyCheckedIn) {
                        return response()->json([
                            'success'              => false,
                            'message'              => "File \"{$fileNumber}\" is already logged out (Tracker: {$existing->tracking_id}). Please log the file back into the registry before logging it out to a new office.",
                            'code'                 => 'file_already_logged_out',
                            'existing_tracking_id' => $existing->tracking_id,
                        ], 422);
                    }
                }
            }

            DB::beginTransaction();

            // Use the client-supplied preview tracking ID (base + registry code) when available,
            // so the saved ID always matches what was shown to the user.
            $proposedTrackingId = trim((string) $request->input('proposed_tracking_id', ''));
            $registryCode       = $request->input('origin_registry_code') ?: null;

            if ($proposedTrackingId !== '' && !FileTracker::where('tracking_id', $proposedTrackingId)->exists()) {
                $trackingId = $proposedTrackingId;
            } else {
                $trackingId = FileTracker::generateTrackingId($registryCode);
            }

            if ($isKangisWorkflowSubmission) {
                $request->merge([
                    'receiving_office_code' => $request->input('receiving_office_code') ?: FileTracker::OFFICE_DIRECTOR_GIS,
                    'receiving_office_name' => $request->input('receiving_office_name') ?: 'Director GIS',
                ]);
            }

            $resolved = [
                'numeric_id' => null,
                'name' => null,
                'is_other' => false,
            ];

            if (!$isKangisWorkflowSubmission && !$isSltrInternalNoOfficer) {
                $resolved = $this->resolveReceivingOfficer($request->receiving_officer_id, $request->receiving_officer_name);
            }

            $isOtherOfficer = !$isKangisWorkflowSubmission && !$isSltrInternalNoOfficer && $resolved['is_other'];
            $receivingOfficerId = ($isKangisWorkflowSubmission || $isSltrInternalNoOfficer)
                ? null
                : ($isOtherOfficer ? null : $resolved['numeric_id']);
            $resolvedOfficerName = $isKangisWorkflowSubmission
                ? ($request->input('receiving_officer_name') ?: $request->input('receiving_office_name') ?: 'Director GIS')
                : ($isSltrInternalNoOfficer
                    ? ($request->input('receiving_officer_name') ?: $request->input('receiving_office_name'))
                    : $resolved['name']);
            $requiresReceivingAcceptance = !$isKangisWorkflowSubmission && !$isSltrInternalNoOfficer && !$isOtherOfficer;

            // Determine if this is a KANGIS approval workflow
            $workflowType = $workflowTypeInput;
            $workflowStep = null;
            $workflowConfig = null;
            $workflowStatus = $request->status ?: FileTracker::STATUS_ACTIVE;

            if (in_array($workflowType, [FileTracker::WORKFLOW_KANGIS_3STEP, FileTracker::WORKFLOW_KANGIS_APPROVAL], true)) {
                // Creation in registry is treated as completed submission.
                $workflowType = FileTracker::WORKFLOW_KANGIS_APPROVAL;
                $workflowStep = 2;
                $workflowStatus = 'submitted';
                $originalDestName = $request->input('final_destination_name') ?: $request->input('receiving_office_name');
                $workflowConfig = [
                    'destination_office_code'          => $request->input('final_destination_code'),
                    'destination_office_name'          => $originalDestName,
                    'original_destination_office_name' => $originalDestName,
                ];
            } elseif ($workflowType === FileTracker::WORKFLOW_CROSS_MODULE_REQUEST) {
                // Cross-module request (Land ↔ KANGIS). The tracker is created by the SENDER module
                // and must be approved by the RECEIVER module before the sender can print.
                $workflowStep = 1;
                $workflowStatus = 'pending_cross_approval';
                $senderModule   = $request->input('sender_module', '');   // 'kangis' or 'land'
                $receiverModule = $request->input('receiver_module', ''); // 'land' or 'kangis'
                $workflowConfig = [
                    'sender_module'   => $senderModule,
                    'receiver_module' => $receiverModule,
                    'destination_office_name'          => $request->input('final_destination_name') ?: $request->input('receiving_office_name'),
                    'original_destination_office_name' => $request->input('final_destination_name') ?: $request->input('receiving_office_name'),
                ];
            }

            // Request Purpose: a normal purpose resolves request_purpose_name from the
            // request_purposes lookup; "Other" (request_purpose_id omitted) carries the
            // free-text request_purpose_other into request_purpose_name instead, keeping
            // request_purpose_id null since there's no matching row to reference.
            $requestPurpose = $request->filled('request_purpose_id')
                ? RequestPurpose::find($request->input('request_purpose_id'))
                : null;
            $requestPurposeOther = trim((string) $request->input('request_purpose_other', ''));
            $requestPurposeName = $requestPurpose?->name ?: ($requestPurposeOther !== '' ? $requestPurposeOther : null);

            // Create file tracker
            $tracker = FileTracker::create([
                'tracking_id' => $trackingId,
                'file_number' => $request->file_number,
                'file_title' => $request->file_title,
                'file_type' => $request->file_type,
                'priority' => $request->priority,
                'created_by' => Auth::id(),
                'created_by_name' => Auth::user()->name ?? 'System User',
                'department' => $request->department,
                'description' => $request->description,
                'status' => $workflowStatus,
                'date_created' => now(),
                'deadline' => $request->deadline,
                'request_purpose_id' => $requestPurpose?->id,
                'request_purpose_name' => $requestPurposeName,
                'total_offices' => count($request->movement_log),
                'notes' => $request->notes,
                'origin_office_code' => $request->origin_office_code,
                'origin_office_name' => $request->origin_office_name,
                'origin_office_department' => $request->origin_office_department,
                'registry_code' => $registryCode,
                'receiving_office_code' => $request->receiving_office_code,
                'receiving_office_name' => $request->receiving_office_name,
                'receiving_officer_id' => $receivingOfficerId,
                'receiving_officer_name' => $resolvedOfficerName,
                'assignment_status' => $requiresReceivingAcceptance ? FileTracker::ASSIGNMENT_PENDING : FileTracker::ASSIGNMENT_ACCEPTED,
                'assignment_accepted_at' => $requiresReceivingAcceptance ? null : now(),
                'module' => $request->input('module'),
                'workflow_type' => $workflowType,
                'workflow_step' => $workflowStep,
                'workflow_config' => $workflowConfig,
            ]);

            // Process movement log
            $processedLog = [];
            foreach ($request->movement_log as $index => $logEntry) {
                $logId = 'LOG-' . now()->format('YmdHis') . '-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);
                
                $processedEntry = [
                    'log_id' => $logId,
                    'office_code' => $logEntry['office_code'],
                    'office_name' => $logEntry['office_name'],
                    'log_in_time' => $logEntry['log_in_time'] ?? null,
                    'log_in_date' => $logEntry['log_in_date'] ?? null,
                    'log_out_time' => $logEntry['log_out_time'] ?? null,
                    'log_out_date' => $logEntry['log_out_date'] ?? null,
                    'notes' => $logEntry['notes'] ?? null,
                    'user_id' => Auth::id(),
                    'user_name' => Auth::user()->name ?? 'System User',
                    'timestamp' => now()->toISOString(),
                    'status' => $index === 0 ? ($requiresReceivingAcceptance ? 'pending_acceptance' : 'active') : 'pending',
                ];

                if ($index === 0) {
                    $processedEntry['origin_office_code'] = $request->origin_office_code;
                    $processedEntry['origin_office_name'] = $request->origin_office_name;
                    $processedEntry['origin_office_department'] = $request->origin_office_department;
                    $processedEntry['receiving_office_code'] = $request->receiving_office_code;
                    $processedEntry['receiving_office_name'] = $request->receiving_office_name;
                    $processedEntry['receiving_officer_id'] = $receivingOfficerId;
                    $processedEntry['receiving_officer_name'] = $resolvedOfficerName;
                }

                // Carry the page count recorded at log-out onto every entry it was supplied for.
                if (isset($logEntry['num_pages']) && $logEntry['num_pages'] !== '' && $logEntry['num_pages'] !== null) {
                    $processedEntry['num_pages'] = (int) $logEntry['num_pages'];
                }
                if (array_key_exists('in_digital_archive', $logEntry)) {
                    $processedEntry['in_digital_archive'] = filter_var($logEntry['in_digital_archive'], FILTER_VALIDATE_BOOLEAN);
                }

                $processedLog[] = $processedEntry;
            }

            // Persist the original page count on the tracker column so it can be
            // retrieved when the file is later logged back to the registry.
            $firstEntry = $request->movement_log[0] ?? [];
            $originalNumPages = $request->input('num_pages', $firstEntry['num_pages'] ?? null);
            if ($originalNumPages !== null && $originalNumPages !== '') {
                $tracker->num_pages = (int) $originalNumPages;
            }
            if ($request->has('in_digital_archive') || array_key_exists('in_digital_archive', $firstEntry)) {
                $tracker->in_digital_archive = filter_var(
                    $request->input('in_digital_archive', $firstEntry['in_digital_archive'] ?? false),
                    FILTER_VALIDATE_BOOLEAN
                );
            }

            $tracker->movement_log = $processedLog;
            
            // Set current office from first log entry
            if (!empty($processedLog)) {
                $tracker->current_office_code = $processedLog[0]['office_code'];
                $tracker->current_office_name = $processedLog[0]['office_name'];
                $tracker->completed_offices = 1;
            }

            if ($tracker->isKangis3Step()) {
                // Align with the updated flow: newly submitted files are with Director GIS.
                $tracker->current_office_code = FileTracker::OFFICE_DIRECTOR_GIS;
                $tracker->current_office_name = 'Director GIS';
                $tracker->receiving_office_code = FileTracker::OFFICE_DIRECTOR_GIS;
                $tracker->receiving_office_name = 'Director GIS';
            }
            
            $tracker->save();

            DB::commit();

            $this->decorateTracker($tracker);
            if ($requiresReceivingAcceptance && !$isOtherOfficer && $receivingOfficerId) {
                $this->notifyReceivingOfficer($tracker, [
                    'receiving_officer_id' => $receivingOfficerId,
                    'receiving_office_name' => $request->receiving_office_name,
                    'file_number' => $request->file_number,
                    'reason' => $request->notes,
                ]);
            }

            if ($tracker->isKangis3Step()) {
                $this->notifyWorkflowOwner(
                    $tracker,
                    sprintf('File %s sent to Director GIS', $tracker->file_number ?: $tracker->tracking_id),
                    sprintf(
                        'Tracking ID: %s • Sent to Director GIS for recommendation. Receiving details will unlock after DGIS and DG approvals.',
                        $tracker->tracking_id
                    ),
                    [
                        'workflow_status' => $tracker->status,
                        'workflow_step' => $tracker->workflow_step,
                    ]
                );
            }

            // Auto-create checkout approval when tracker is created in KANGIS, SLTR, or ST module
            if (in_array($request->input('module'), ['kangis', 'sltr', 'st'])) {
                try {
                    $this->createKangisCheckoutApproval($tracker);
                } catch (Exception $e) {
                    Log::warning('Failed to auto-create checkout approval', [
                        'tracker_id' => $tracker->id,
                        'module' => $request->input('module'),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // ── Auto-create DigitalFileRequest record for digital_request module ──
            if ($request->input('module') === 'digital_request') {
                try {
                    $drUser       = Auth::user();
                    $drSenderName = trim(($drUser->first_name ?? '') . ' ' . ($drUser->last_name ?? ''))
                                    ?: ($drUser->name ?? 'Unknown');

                    // Resolve source office from user's department
                    $drSrcOffice = null;
                    if ($drUser->department_id) {
                        $drDeptName = \Illuminate\Support\Facades\DB::connection('sqlsrv')
                            ->table('departments')
                            ->where('id', $drUser->department_id)
                            ->value('name');
                        if ($drDeptName) {
                            $drSrcOffice = \App\Models\Office::active()
                                ->where('department', $drDeptName)
                                ->first();
                        }
                    }

                    $drRecord = \App\Models\DigitalFileRequest::create([
                        'request_no'              => \App\Models\DigitalFileRequest::generateRequestNo(),
                        'file_no'                 => $tracker->file_number,
                        'file_title'              => $tracker->file_title,
                        'requester_user_id'       => $drUser->id,
                        'sending_officer'         => $drSenderName,
                        'receiving_officer'       => $resolvedOfficerName,
                        'source_office_id'        => $drSrcOffice?->id,
                        'source_office_name'      => $drSrcOffice?->office_name ?? $tracker->origin_office_name,
                        'destination_office_id'   => null,
                        'destination_office_name' => $tracker->receiving_office_name,
                        'current_file_location'   => $tracker->origin_office_name,
                        'request_status'          => \App\Models\DigitalFileRequest::STATUS_PENDING,
                        'remarks'                 => $tracker->notes,
                        'requested_at'            => now(),
                    ]);

                    // Notify receiving officer
                    if ($receivingOfficerId) {
                        $this->notificationService->create(
                            $receivingOfficerId,
                            'digital_request',
                            "Digital File Request: {$drRecord->request_no}",
                            "{$drSenderName} is requesting file {$tracker->file_number} ({$tracker->file_title}) to be sent to {$tracker->receiving_office_name}.",
                            [
                                'request_id'  => $drRecord->id,
                                'request_no'  => $drRecord->request_no,
                                'file_number' => $tracker->file_number,
                                'office_name' => $tracker->receiving_office_name,
                            ],
                            ['module' => 'digital_request']
                        );
                    }
                } catch (Exception $e) {
                    Log::warning('DigitalFileRequest auto-create failed (API)', [
                        'tracker_id' => $tracker->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $tracker,
                'message' => 'File tracker created successfully'
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating file tracker: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific file tracker
     * GET /api/file-trackers/{id}
     */
    public function show($id): JsonResponse
    {
        try {
            $tracker = FileTracker::find($id);

            if (!$tracker) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tracker not found'
                ], 404);
            }

            if (!$this->canEditTracker($tracker)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not allowed to modify this file tracker'
                ], 403);
            }

            $this->decorateTracker($tracker);

            return response()->json([
                'success' => true,
                'data' => $tracker,
                'message' => 'File tracker retrieved successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving file tracker: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a file tracker
     * PUT /api/file-trackers/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $tracker = FileTracker::find($id);

            if (!$tracker) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tracker not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'file_number' => 'nullable|string|max:255',
                'file_title' => 'nullable|string|max:255',
                'file_type' => 'nullable|string|max:100',
                'priority' => 'nullable|in:LOW,MEDIUM,HIGH',
                'department' => 'nullable|string|max:100',
                'description' => 'nullable|string',
                'deadline' => 'nullable|date',
                'status' => 'nullable|in:ACTIVE,COMPLETED,CANCELLED',
                'notes' => 'nullable|string',
                'origin_office_code' => 'nullable|string|max:100',
                'origin_office_name' => 'nullable|string|max:255',
                'origin_office_department' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $fileNumberBefore = $tracker->file_number;
            $fileTitleBefore = $tracker->file_title;

            $tracker->update($request->only([
                'file_number',
                'file_title',
                'file_type',
                'priority',
                'department',
                'description',
                'deadline',
                'status',
                'notes',
                'origin_office_code',
                'origin_office_name',
                'origin_office_department',
            ]));

            // Keep the matching file_indexings row's title in sync — the tracker and the
            // indexing record are edited from different screens but must show one title.
            if ($request->filled('file_title') && trim((string) $tracker->file_title) !== trim((string) $fileTitleBefore)) {
                $this->propagateFileTitleToIndexing($fileNumberBefore, $tracker->file_number, $tracker->file_title);
            }

            // Add computed attributes
            $this->decorateTracker($tracker);

            return response()->json([
                'success' => true,
                'data' => $tracker,
                'message' => 'File tracker updated successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating file tracker: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Keep the file_indexings row's title in sync when a tracker's file_title is edited,
     * so the two screens never show different titles for the same file number.
     */
    private function propagateFileTitleToIndexing(?string $fileNumberBefore, ?string $fileNumberAfter, ?string $newTitle): void
    {
        $fileNumbers = array_values(array_unique(array_filter([$fileNumberBefore, $fileNumberAfter])));

        if (empty($fileNumbers) || $newTitle === null || $newTitle === '') {
            return;
        }

        try {
            DB::connection('sqlsrv')->table('file_indexings')
                ->whereIn('file_number', $fileNumbers)
                ->update([
                    'file_title' => $newTitle,
                    'updated_at' => now(),
                ]);
        } catch (Exception $e) {
            Log::warning('Failed to propagate file_title to file_indexings', [
                'file_numbers' => $fileNumbers,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete a file tracker
     * DELETE /api/file-trackers/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $tracker = FileTracker::find($id);

            if (!$tracker) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tracker not found'
                ], 404);
            }

            if (!$this->canEditTracker($tracker)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not allowed to modify this file tracker'
                ], 403);
            }

            $tracker->delete();

            return response()->json([
                'success' => true,
                'message' => 'File tracker deleted successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting file tracker: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add movement to tracker
     * POST /api/file-trackers/{id}/movements
     */
    public function addMovement(Request $request, $id): JsonResponse
    {
        try {
            $tracker = FileTracker::find($id);

            if (!$tracker) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tracker not found'
                ], 404);
            }

            if (!$this->canEditTracker($tracker)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not allowed to modify this file tracker'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'office_code' => 'required|string|max:150',
                'office_name' => 'required|string|max:255',
                'log_in_time' => 'required|string',
                'log_in_date' => 'required|date',
                'notes' => 'nullable|string',
                'receiving_officer_id' => 'nullable|string|max:50',
                'receiving_officer_name' => 'nullable|string|max:255',
                'auto_accept' => 'sometimes|boolean',
                'override_note' => 'nullable|string|max:500',
                'purpose' => 'nullable|string|max:50',
                'decision' => 'nullable|string|in:submit,recommend,not_recommend,approve,reject,start_processing',
                'department' => 'nullable|string|max:150',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $resolved = $this->resolveReceivingOfficer($request->input('receiving_officer_id'), $request->input('receiving_officer_name'));
            $receivingOfficerId = $resolved['numeric_id'];
            $receivingOfficerName = $resolved['name'];
            $isOtherOfficer = $resolved['is_other'];

            $autoAccept = filter_var($request->input('auto_accept', false), FILTER_VALIDATE_BOOLEAN);
            $overrideNote = trim((string) $request->input('override_note', ''));
            $actor = $request->user();
            $actorName = $actor?->name ?? $actor?->email ?? 'System';
            $acceptanceNote = $autoAccept
                ? ($overrideNote !== '' ? $overrideNote : sprintf('Marked as received in person by %s.', $actorName))
                : null;

            $purpose = $request->input('purpose');

            $workflowUpdate = null;

            // For KANGIS approval workflows, validate and normalize transition behaviour.
            if ($tracker->isKangis3Step()) {
                $expectedStep = $tracker->getCurrentStepDefinition() ?: $tracker->getNextStepDefinition();
                $expectedPurpose = $expectedStep['purpose'] ?? null;

                if (!$purpose && $expectedPurpose) {
                    $purpose = $expectedPurpose;
                }

                // Only enforce step sequencing when the client did NOT explicitly provide a purpose.
                // When purpose is explicitly sent (e.g. DGIS sends 'recommendation' after a
                // not_recommend reset the step back to 1), trust the client and skip the mismatch check.
                $clientExplicitlySetPurpose = $request->has('purpose') && $request->input('purpose') !== null;
                $validPurposes = [
                    FileTracker::PURPOSE_SUBMISSION,
                    FileTracker::PURPOSE_RECOMMENDATION,
                    FileTracker::PURPOSE_APPROVAL,
                    FileTracker::PURPOSE_PROCESSING,
                ];
                if (!$clientExplicitlySetPurpose && $expectedPurpose && $purpose !== $expectedPurpose) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid workflow step. Expected: ' . ($expectedStep['label'] ?? $expectedPurpose),
                    ], 422);
                }

                $workflowUpdate = $this->resolveKangisWorkflowTransition($tracker, $request, $purpose);

                // Force deterministic office routing for the approval flow.
                if (!empty($workflowUpdate['office_code'])) {
                    $request->merge(['office_code' => $workflowUpdate['office_code']]);
                }
                if (!empty($workflowUpdate['office_name'])) {
                    $request->merge(['office_name' => $workflowUpdate['office_name']]);
                }
            }

            $movementOptions = [
                'receiving_officer_id' => $isOtherOfficer ? null : $receivingOfficerId,
                'receiving_officer_name' => $receivingOfficerName,
                'receiving_office_code' => $request->office_code,
                'receiving_office_name' => $request->office_name,
                'status' => ($autoAccept || $isOtherOfficer) ? 'active' : 'pending_acceptance',
                'requires_acceptance' => !$autoAccept && !$isOtherOfficer,
                'accepted_by' => ($autoAccept || $isOtherOfficer) ? ($isOtherOfficer ? null : $receivingOfficerId) : null,
                'accepted_by_name' => ($autoAccept || $isOtherOfficer) ? $receivingOfficerName : null,
                'acceptance_note' => ($autoAccept || $isOtherOfficer) ? ($overrideNote !== '' ? $overrideNote : sprintf('Marked as received in person by %s.', $actorName)) : null,
                'acceptance_source' => $autoAccept ? 'override' : ($isOtherOfficer ? 'auto' : null),
                'acceptance_override_by' => $autoAccept ? ($actor?->id ?? null) : null,
                'acceptance_override_by_name' => $autoAccept ? $actorName : null,
            ];

            if ($purpose) {
                $movementOptions['purpose'] = $purpose;
            }

            $tracker->addMovementLog(
                $request->office_code,
                $request->office_name,
                $request->log_in_time,
                $request->log_in_date,
                $request->notes,
                $actor?->id,
                $actorName,
                $movementOptions
            );

            $tracker->receiving_officer_id = $isOtherOfficer ? null : $receivingOfficerId;
            $tracker->receiving_officer_name = $receivingOfficerName;
            $tracker->receiving_office_code = $request->office_code;
            $tracker->receiving_office_name = $request->office_name;
            $tracker->assignment_status = ($autoAccept || $isOtherOfficer)
                ? FileTracker::ASSIGNMENT_ACCEPTED
                : FileTracker::ASSIGNMENT_PENDING;
            $tracker->assignment_accepted_at = ($autoAccept || $isOtherOfficer) ? now() : null;

            if ($autoAccept || $isOtherOfficer) {
                $tracker->current_holder = $receivingOfficerName;
                $tracker->current_handler = $receivingOfficerName;
            } else {
                $tracker->current_holder = null;
                $tracker->current_handler = null;
            }

            if ($workflowUpdate) {
                $tracker->status = $workflowUpdate['status'];
                $tracker->workflow_step = $workflowUpdate['next_step'];
                $tracker->current_office_code = $workflowUpdate['office_code'];
                $tracker->current_office_name = $workflowUpdate['office_name'];

                $config = is_array($tracker->workflow_config) ? $tracker->workflow_config : [];
                if (!empty($workflowUpdate['current_department'])) {
                    $config['current_department'] = $workflowUpdate['current_department'];
                    $tracker->department = $workflowUpdate['current_department'];
                }
                if (isset($workflowUpdate['queue_destination_name'])) {
                    $config['destination_office_code'] = FileTracker::OFFICE_DEPARTMENT_QUEUE;
                    // Store routing queue separately — do not overwrite original destination
                    $config['queue_office_name'] = $workflowUpdate['queue_destination_name'];
                }
                $tracker->workflow_config = $config;
            }

            $tracker->save();

            if (!$autoAccept && !$isOtherOfficer) {
                $this->notifyReceivingOfficer($tracker, [
                    'receiving_officer_id' => $receivingOfficerId,
                    'receiving_office_name' => $request->office_name,
                    'file_number' => $tracker->file_number,
                    'reason' => $request->notes,
                ]);
            }

            if ($workflowUpdate && $tracker->isKangis3Step()) {
                $this->notifyKangisWorkflowTransition($tracker, $workflowUpdate, [
                    'purpose' => $purpose,
                    'decision' => $request->input('decision'),
                    'reason' => $request->notes,
                    'actor_name' => $actorName,
                ]);
            }

            $tracker->refresh();
            $this->decorateTracker($tracker);

            return response()->json([
                'success' => true,
                'data' => $tracker,
                'message' => 'Movement added successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding movement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete current movement
     * POST /api/file-trackers/{id}/complete-movement
     */
    public function completeMovement(Request $request, $id): JsonResponse
    {
        try {
            $tracker = FileTracker::find($id);

            if (!$tracker) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tracker not found'
                ], 404);
            }

            if (!$this->canEditTracker($tracker)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not allowed to modify this file tracker'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'log_out_time' => 'nullable|string',
                'notes' => 'nullable|string',
                'num_pages' => 'nullable|integer|min:1|max:99999',
                'in_digital_archive' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $tracker->completeCurrentMovement(
                $request->log_out_time,
                $request->notes,
                $request->filled('num_pages') ? (int) $request->input('num_pages') : null,
                $request->has('in_digital_archive')
                    ? filter_var($request->input('in_digital_archive'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    : null
            );

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active movement to complete'
                ], 400);
            }

            $this->decorateTracker($tracker);

            return response()->json([
                'success' => true,
                'data' => $tracker,
                'message' => 'Movement completed successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing movement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Correct a log entry (office/officer) for a tracker.
     * PUT /api/file-trackers/{id}/logs/{logId}
     */
    public function updateLogEntry(Request $request, $id, $logId): JsonResponse
    {
        try {
            $tracker = FileTracker::find($id);

            if (!$tracker) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tracker not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'office_code' => 'required|string|max:150',
                'office_name' => 'required|string|max:255',
                'receiving_officer_id' => 'required|string|max:50',
                'receiving_officer_name' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $movementLog = is_array($tracker->movement_log)
                ? $tracker->movement_log
                : (json_decode((string) $tracker->movement_log, true) ?? []);

            $entryIndex = null;
            $previousOfficerId = null;
            $previousOfficeCode = null;
            foreach ($movementLog as $index => $entry) {
                if (($entry['log_id'] ?? null) === $logId) {
                    $entryIndex = $index;
                    $previousOfficerId = (int) ($entry['receiving_officer_id'] ?? 0);
                    $previousOfficeCode = $entry['office_code'] ?? null;
                    break;
                }
            }

            if ($entryIndex === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Log entry not found for this tracker'
                ], 404);
            }

            $resolved = $this->resolveReceivingOfficer($request->receiving_officer_id, $request->receiving_officer_name);
            $officerId = $resolved['numeric_id'];
            $officerName = $resolved['name'];
            $isOtherOfficer = $resolved['is_other'];

            DB::beginTransaction();

            $movementLog[$entryIndex]['office_code'] = $request->office_code;
            $movementLog[$entryIndex]['office_name'] = $request->office_name;
            $movementLog[$entryIndex]['receiving_officer_id'] = $isOtherOfficer ? null : $officerId;
            $movementLog[$entryIndex]['receiving_officer_name'] = $officerName;
            $movementLog[$entryIndex]['receiving_office_code'] = $request->office_code;
            $movementLog[$entryIndex]['receiving_office_name'] = $request->office_name;
            $movementLog[$entryIndex]['manual_update'] = true;
            $movementLog[$entryIndex]['manual_update_by'] = Auth::id();
            $movementLog[$entryIndex]['manual_update_at'] = now()->toIso8601String();

            $tracker->movement_log = $movementLog;

            // Editing a log entry invalidates any previously printed log/complete sheet,
            // so reset the printed flag to force a fresh print.
            $tracker->printed = false;

            $entryStatus = strtolower((string) ($movementLog[$entryIndex]['status'] ?? ''));
            if ($entryStatus !== 'completed') {
                $tracker->current_office_code = $request->office_code;
                $tracker->current_office_name = $request->office_name;
            }

            if ($entryIndex === 0) {
                $tracker->receiving_office_code = $request->office_code;
                $tracker->receiving_office_name = $request->office_name;
                $tracker->receiving_officer_id = $isOtherOfficer ? null : $officerId;
                $tracker->receiving_officer_name = $officerName;
            }

            $tracker->save();
            DB::commit();

            $tracker->refresh();
            $this->decorateTracker($tracker);

            $officeChanged = strcasecmp((string) $previousOfficeCode, (string) $request->office_code) !== 0;
            $shouldNotify = !$isOtherOfficer && $officerId > 0 && (
                $previousOfficerId !== $officerId || $officeChanged
            );

            if ($shouldNotify) {
                $fileReference = $tracker->file_number
                    ?? $tracker->tracking_id
                    ?? ('File #' . $tracker->id);

                $actorName = auth()->user()->name ?? 'System';
                $notificationTitle = sprintf('Log %s updated for %s', $logId, $fileReference);
                $notificationBody = sprintf(
                    'You are now the receiving officer for log %s (%s). Office: %s.',
                    $logId,
                    $fileReference,
                    $request->office_name
                );

                $this->notifyReceivingOfficer($tracker, [
                    'receiving_officer_id' => $officerId,
                    'receiving_office_name' => $request->office_name,
                    'file_number' => $tracker->file_number,
                    'reason' => sprintf('Log corrected by %s', $actorName),
                    'notification_title' => $notificationTitle,
                    'notification_body' => $notificationBody,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $tracker,
                'message' => 'Log entry updated successfully'
            ]);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error updating log entry: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a single log entry from a tracker's movement_log.
     * DELETE /api/file-trackers/{id}/logs/{logId}
     */
    public function destroyLogEntry($id, $logId): JsonResponse
    {
        try {
            $tracker = FileTracker::find($id);

            if (!$tracker) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tracker not found'
                ], 404);
            }

            if (!$this->canEditTracker($tracker)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not allowed to modify this file tracker'
                ], 403);
            }

            $movementLog = is_array($tracker->movement_log)
                ? $tracker->movement_log
                : (json_decode((string) $tracker->movement_log, true) ?? []);

            $entryIndex = null;
            foreach ($movementLog as $index => $entry) {
                if (($entry['log_id'] ?? null) === $logId) {
                    $entryIndex = $index;
                    break;
                }
            }

            if ($entryIndex === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Log entry not found for this tracker'
                ], 404);
            }

            DB::beginTransaction();

            array_splice($movementLog, $entryIndex, 1);
            $tracker->movement_log = $movementLog;
            $tracker->total_offices = count($movementLog);

            // Recompute the tracker status from what remains, otherwise a deleted
            // out-movement leaves the tracker stuck ACTIVE and the file keeps showing as
            // IN_TRANSIT in Quick Search.
            $hasActiveMovement = collect($movementLog)
                ->contains(fn ($e) => is_array($e) && strtolower($e['status'] ?? '') === 'active');

            if (empty($movementLog)) {
                // No movements left: the log is empty, so delete the tracker entirely.
                // Clear any indexing snapshot that points at it so Quick Search falls
                // back to the file's home location instead of a dangling reference.
                $trackerId  = $tracker->id;
                $fileNumber = $tracker->file_number;

                try {
                    DB::connection('sqlsrv')->table('file_indexings')
                        ->where('file_tracker_id', $trackerId)
                        ->update([
                            'file_tracker_id'        => null,
                            'tracking_status'        => null,
                            'location_status_manual' => null,
                        ]);
                } catch (Exception $e) {
                    // Non-fatal: the resolver re-derives location even without this cleanup.
                    Log::warning('Could not clear file_indexings snapshot on tracker delete', ['tracker_id' => $trackerId, 'error' => $e->getMessage()]);
                }

                $tracker->delete();
                DB::commit();

                return response()->json([
                    'success'         => true,
                    'tracker_deleted' => true,
                    'file_number'     => $fileNumber,
                    'message'         => 'Last log entry removed — file tracker deleted.',
                ]);
            }

            // Restore current office pointers from the new latest entry.
            $latest = end($movementLog);
            $tracker->current_office_code = $latest['office_code'] ?? $tracker->current_office_code;
            $tracker->current_office_name = $latest['office_name'] ?? $tracker->current_office_name;
            // Out-movement remaining -> still ACTIVE; otherwise it has been logged back in.
            $tracker->status = $hasActiveMovement
                ? FileTracker::STATUS_ACTIVE
                : FileTracker::STATUS_COMPLETED;

            $tracker->save();
            DB::commit();

            $tracker->refresh();
            $this->decorateTracker($tracker);

            return response()->json([
                'success' => true,
                'data' => $tracker,
                'message' => 'Log entry deleted successfully'
            ]);

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return response()->json([
                'success' => false,
                'message' => 'Error deleting log entry: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard statistics
     * GET /api/file-trackers/dashboard
     */
    public function dashboard(): JsonResponse
    {
        try {
            $stats = [
                'total_trackers' => FileTracker::count(),
                'active_trackers' => FileTracker::active()->count(),
                'completed_trackers' => FileTracker::completed()->count(),
                'overdue_trackers' => FileTracker::overdue()->count(),
                'priority_breakdown' => [
                    'high' => FileTracker::byPriority('HIGH')->active()->count(),
                    'medium' => FileTracker::byPriority('MEDIUM')->active()->count(),
                    'low' => FileTracker::byPriority('LOW')->active()->count(),
                ],
                'recent_activity' => FileTracker::orderBy('updated_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($tracker) {
                        return [
                            'id' => $tracker->id,
                            'tracking_id' => $tracker->tracking_id,
                            'file_title' => $tracker->file_title,
                            'status' => $tracker->status,
                            'priority' => $tracker->priority,
                            'updated_at' => $tracker->updated_at
                        ];
                    })
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Dashboard statistics retrieved successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search file trackers - Quick Action
     * GET /api/file-trackers/search
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'nullable|string|max:255',
                'file_number' => 'nullable|string|max:255',
                'file_title' => 'nullable|string|max:255',
                'priority' => 'nullable|in:Low,Medium,High,Urgent',
                'status' => 'nullable|in:Active,Completed,On Hold,Cancelled'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = FileTracker::query();

            // General search query
            $searchTerm = trim((string) $request->input('query', ''));
            if ($searchTerm !== '') {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('tracking_id', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('file_number', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('file_title', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('notes', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Specific field searches
            if ($request->filled('file_number')) {
                $fileNumber = trim((string) $request->input('file_number'));
                $query->where('file_number', 'LIKE', "%{$fileNumber}%");
            }

            if ($request->filled('file_title')) {
                $fileTitle = trim((string) $request->input('file_title'));
                $query->where('file_title', 'LIKE', "%{$fileTitle}%");
            }

            if ($request->filled('priority')) {
                $query->where('priority', $request->input('priority'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            $results = $query->orderBy('updated_at', 'desc')
                           ->limit(50)
                           ->get()
                           ->map(function ($tracker) {
                               return [
                                   'id' => $tracker->id,
                                   'tracking_id' => $tracker->tracking_id,
                                   'file_number' => $tracker->file_number,
                                   'file_title' => $tracker->file_title,
                                   'priority' => $tracker->priority,
                                   'status' => $tracker->status,
                                   'current_office' => $tracker->current_office_name,
                                   'created_at' => $tracker->created_at,
                                   'updated_at' => $tracker->updated_at,
                                   'is_overdue' => $tracker->is_overdue,
                                   'days_until_deadline' => $tracker->days_until_deadline
                               ];
                           });

            return response()->json([
                'success' => true,
                'data' => $results,
                'total' => $results->count(),
                'message' => 'Search completed successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error performing search: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Track file status by file number or tracking ID - Quick Action
     * GET /api/file-trackers/track/{identifier}
     */
    public function track(Request $request, $identifier): JsonResponse
    {
        try {
            // Try to find by tracking ID first, then by file number
            $tracker = FileTracker::where('tracking_id', $identifier)
                                 ->orWhere('file_number', $identifier)
                                 ->orderByDesc('id')
                                 ->first();

            if (!$tracker) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tracker not found'
                ], 404);
            }

            $movementLog = is_array($tracker->movement_log)
                ? $tracker->movement_log
                : (json_decode((string) $tracker->movement_log, true) ?? []);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $tracker->id,
                    'tracking_id' => $tracker->tracking_id,
                    'file_number' => $tracker->file_number,
                    'file_title' => $tracker->file_title,
                    'file_type' => $tracker->file_type,
                    'priority' => $tracker->priority,
                    'status' => $tracker->status,
                    'current_office' => $tracker->current_office_name,
                    'current_office_code' => $tracker->current_office_code,
                    'department' => $tracker->department,
                    'created_at' => $tracker->created_at,
                    'updated_at' => $tracker->updated_at,
                    'deadline' => $tracker->deadline,
                    'is_overdue' => $tracker->is_overdue,
                    'days_until_deadline' => $tracker->days_until_deadline,
                    'timeline_status' => $tracker->timeline_status,
                    'request_purpose_name' => $tracker->request_purpose_name,
                    'completion_percentage' => $tracker->completion_percentage,
                    'movement_history' => $movementLog,
                    'prior_movements' => $this->getPriorMovements($tracker),
                    'notes' => $tracker->notes
                ],
                'message' => 'File tracker retrieved successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error tracking file: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function getPriorMovements(FileTracker $tracker): array
    {
        $fileNumber = trim((string) $tracker->file_number);
        if ($fileNumber === '') {
            return [];
        }

        $key = mb_strtoupper($fileNumber);
        $priorRows = FileTracker::query()
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$key])
            ->where('id', '<', $tracker->id)
            ->orderBy('id')
            ->get(['id', 'movement_log', 'receiving_officer_name', 'receiving_officer_id']);

        $priorMovements = [];
        foreach ($priorRows as $row) {
            $logEntries = is_array($row->movement_log)
                ? $row->movement_log
                : (json_decode((string) ($row->movement_log ?? '[]'), true) ?? []);

            if (!is_array($logEntries)) {
                continue;
            }

            foreach ($logEntries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $recvId = $entry['receiving_officer_id'] ?? null;
                $userId = $entry['user_id'] ?? null;
                if ($recvId !== null && $userId !== null && (int) $recvId === (int) $userId) {
                    $entry['receiving_officer_name'] = $row->receiving_officer_name ?: null;
                    $entry['receiving_officer_id'] = $row->receiving_officer_id;
                }

                $priorMovements[] = $entry;
            }
        }

        // Weave in related files from the same parcel-update / merger group (Change of Purpose,
        // Subdivision, Merger, Extension, Separation, Temporary File) so the timeline reads as one
        // continuous lineage: each decommissioned parent's history ends with a File Decommissioning
        // marker, each surviving child opens with a File Commissioning marker. Non-fatal.
        try {
            $groupEntries = app(\App\Services\FileMergerService::class)
                ->groupMovementEntries($fileNumber, [$fileNumber]);
            foreach ($groupEntries as $entry) {
                $priorMovements[] = $entry;
            }
        } catch (\Throwable $e) {
            // Fall back to the single-file history.
        }

        usort($priorMovements, function ($a, $b) {
            return $this->movementSortTimestamp($a) <=> $this->movementSortTimestamp($b);
        });

        return $priorMovements;
    }

    protected function movementSortTimestamp(array $entry): float
    {
        $inDate  = trim((string) ($entry['log_in_date'] ?? ''));
        $inTime  = trim((string) ($entry['log_in_time'] ?? ''));
        $outDate = trim((string) ($entry['log_out_date'] ?? ''));
        $outTime = trim((string) ($entry['log_out_time'] ?? ''));

        $candidates = [];
        if ($inDate !== '') {
            $candidates[] = $inDate . ' ' . ($inTime !== '' ? $inTime : '00:00');
        }
        if ($outDate !== '') {
            $candidates[] = $outDate . ' ' . ($outTime !== '' ? $outTime : '00:00');
        }
        if (!empty($entry['timestamp'])) {
            $candidates[] = (string) $entry['timestamp'];
        }

        foreach ($candidates as $candidate) {
            try {
                return (float) Carbon::parse($candidate)->timestamp;
            } catch (\Throwable $e) {
                continue;
            }
        }

        return PHP_FLOAT_MAX;
    }

    /**
     * Bulk operations - Quick Action
     * POST /api/file-trackers/bulk
     */
    public function bulk(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'operation' => 'required|in:move,priority,archive,delete',
                'tracker_ids' => 'required|array|min:1',
                'tracker_ids.*' => 'integer|exists:file_tracker,id',
                'data' => 'required|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $operation = $request->operation;
            $trackerIds = $request->tracker_ids;
            $data = $request->data;

            DB::beginTransaction();

            $affectedCount = 0;

            switch ($operation) {
                case 'move':
                    // Bulk move to office
                    if (!isset($data['office_code']) || !isset($data['office_name'])) {
                        throw new Exception('Office information required for move operation');
                    }

                    foreach ($trackerIds as $trackerId) {
                        $tracker = FileTracker::find($trackerId);
                        if ($tracker) {
                            $tracker->addMovementLog(
                                $data['office_code'],
                                $data['office_name'],
                                now()->format('H:i'),
                                now()->format('Y-m-d'),
                                $data['notes'] ?? 'Bulk move operation',
                                Auth::id(),
                                Auth::user()->name ?? 'System'
                            );

                            $tracker->current_office_code = $data['office_code'];
                            $tracker->current_office_name = $data['office_name'];
                            $tracker->save();
                            $affectedCount++;
                        }
                    }
                    break;

                case 'priority':
                    // Bulk priority update
                    if (!isset($data['priority']) || !in_array($data['priority'], ['Low', 'Medium', 'High', 'Urgent'])) {
                        throw new Exception('Valid priority required for priority operation');
                    }

                    $affectedCount = FileTracker::whereIn('id', $trackerIds)
                                               ->update(['priority' => $data['priority']]);
                    break;

                case 'archive':
                    // Bulk archive (set status to completed)
                    $affectedCount = FileTracker::whereIn('id', $trackerIds)
                                               ->update(['status' => 'Completed']);
                    break;

                case 'delete':
                    // Bulk delete
                    $affectedCount = FileTracker::whereIn('id', $trackerIds)->delete();
                    break;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'operation' => $operation,
                    'affected_count' => $affectedCount
                ],
                'message' => "Bulk {$operation} operation completed successfully. {$affectedCount} trackers affected."
            ]);

        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error performing bulk operation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export file trackers data - Quick Action
     * GET /api/file-trackers/export
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'format' => 'required|in:csv,excel,pdf',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'include_details' => 'boolean',
                'include_movement' => 'boolean',
                'include_office' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = FileTracker::query();

            // Apply date filters
            if ($request->from_date) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }

            if ($request->to_date) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            $trackers = $query->orderBy('created_at', 'desc')->get();

            // Prepare export data based on included options
            $exportData = $trackers->map(function ($tracker) use ($request) {
                $data = [
                    'Tracking ID' => $tracker->tracking_id,
                    'File Number' => $tracker->file_number,
                    'File Title' => $tracker->file_title,
                    'Priority' => $tracker->priority,
                    'Status' => $tracker->status,
                    'Created Date' => $tracker->created_at->format('Y-m-d H:i:s')
                ];

                if ($request->include_details) {
                    $data['File Type'] = $tracker->file_type;
                    $data['Notes'] = $tracker->notes;
                    $data['Deadline'] = $tracker->deadline;
                    $data['Is Overdue'] = $tracker->is_overdue ? 'Yes' : 'No';
                }

                if ($request->include_office) {
                    $data['Current Office'] = $tracker->current_office_name;
                    $data['Office Code'] = $tracker->current_office_code;
                    $data['Department'] = $tracker->department;
                }

                if ($request->include_movement) {
                    $movementLog = is_array($tracker->movement_log)
                        ? $tracker->movement_log
                        : (json_decode((string) $tracker->movement_log, true) ?? []);
                    $data['Movement Count'] = count($movementLog);
                    $data['Last Movement'] = count($movementLog) > 0 ? 
                        end($movementLog)['moved_at'] ?? 'N/A' : 'N/A';
                }

                return $data;
            });

            // In a real implementation, you would generate the actual file here
            // For now, return the data structure

            return response()->json([
                'success' => true,
                'data' => [
                    'format' => $request->input('format'),
                    'total_records' => $exportData->count(),
                    'export_data' => $exportData,
                    'generated_at' => now()->toISOString()
                ],
                'message' => 'Export data prepared successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error preparing export: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function decorateTracker(FileTracker $tracker): FileTracker
    {
        $tracker->setAttribute('is_overdue', $tracker->is_overdue);
        $tracker->setAttribute('days_until_deadline', $tracker->days_until_deadline);
        $tracker->setAttribute('completion_percentage', $tracker->completion_percentage);
        $tracker->setAttribute('current_movement', $tracker->getCurrentMovement());
        $tracker->setAttribute('can_edit', $this->canEditTracker($tracker));

        // Add workflow progress for KANGIS approval trackers
        if ($tracker->isKangis3Step()) {
            $tracker->setAttribute('workflow_progress', $tracker->getWorkflowProgress());
            $tracker->setAttribute('next_step', $tracker->getNextStepDefinition());
            $tracker->setAttribute('current_step', $tracker->getCurrentStepDefinition());
        }

        return $tracker;
    }

    protected function canEditTracker(FileTracker $tracker): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Robust check for super/supper admin across both standard 'type' and assigned roles.
        $roleStr = strtolower((string) ($user->assign_role ?? ''));
        $typeStr = strtolower((string) ($user->type ?? ''));
        
        $isElevatedAdmin = $typeStr === 'super admin' || $typeStr === 'supper admin'
            || str_contains($roleStr, 'super admin') || str_contains($roleStr, 'supper admin');

        // If User model method is available, use it for more precision (handles IDs/JSON/etc)
        if (!$isElevatedAdmin && method_exists($user, 'assignedRoleNames')) {
            $roleNames = $user->assignedRoleNames();
            $isElevatedAdmin = in_array('super admin', $roleNames) || in_array('supper admin', $roleNames);
        }

        if ($isElevatedAdmin) {
            return true;
        }

        return (int) $tracker->created_by === (int) $user->id;
    }

    /**
     * Resolve a receiving officer ID that may be a regular user ID or an ro_-prefixed other_receiving_officers ID.
     */
    protected function resolveReceivingOfficer($rawId, ?string $fallbackName = null): array
    {
        $isOther = is_string($rawId) && str_starts_with($rawId, 'ro_');

        if ($isOther) {
            $numericId = (int) substr($rawId, 3);
            $officer = OtherReceivingOfficer::on('sqlsrv')->find($numericId);
            $name = $officer ? trim($officer->full_name ?: ($officer->first_name . ' ' . $officer->last_name)) : null;
            if (!$name) {
                $name = trim((string) $fallbackName) ?: ('Other Officer #' . $numericId);
            }
            return ['numeric_id' => $numericId, 'name' => $name, 'is_other' => true];
        }

        $numericId = (int) $rawId;
        $user = User::on('sqlsrv')->find($numericId);
        $name = $user ? trim($user->name ?: ($user->first_name . ' ' . $user->last_name)) : null;
        if (!$name) {
            $name = trim((string) $fallbackName) ?: ('User #' . $numericId);
        }
        return ['numeric_id' => $numericId, 'name' => $name, 'is_other' => false];
    }

    protected function notifyReceivingOfficer(FileTracker $tracker, array $context = []): void
    {
        $officerId = (int) ($context['receiving_officer_id'] ?? $tracker->receiving_officer_id);

        if ($officerId <= 0) {
            return;
        }

        try {
            $officer = User::on('sqlsrv')->find($officerId) ?? User::find($officerId);

            if (!$officer) {
                return;
            }

            $fileNumber = $context['file_number'] ?? $tracker->file_number ?? null;
            $officeName = $context['receiving_office_name']
                ?? $tracker->receiving_office_name
                ?? $tracker->current_office_name;

            $title = $context['notification_title']
                ?? ($fileNumber
                    ? sprintf('File %s logged for you', $fileNumber)
                    : 'File tracker update');

            $bodyParts = array_filter([
                $fileNumber ? 'File No: ' . $fileNumber : null,
                $tracker->file_title ? 'Title: ' . $tracker->file_title : null,
                $officeName ? 'Office: ' . $officeName : null,
                $context['reason'] ?? $tracker->notes ?? null,
            ]);

            $body = $context['notification_body']
                ?? ($bodyParts ? implode(' • ', $bodyParts) : 'A new file tracker has been logged for you.');

            $data = [
                'file_number' => $fileNumber,
                'file_name' => $tracker->file_title,
                'office_name' => $officeName,
                'tracking_id' => $tracker->tracking_id,
                'reason' => $context['reason'] ?? $tracker->notes,
            ];

            $overrides = [
                'module' => 'file_tracking',
                'subject' => $title,
                'message' => $body,
                'name' => $officer->name,
                'enabled_email' => false,
                'enabled_sms' => false,
            ];

            $this->notificationService->create(
                $officer->id,
                'file_tracking.receiving',
                $title,
                $body,
                $data,
                $overrides
            );
        } catch (\Throwable $exception) {
            Log::warning('Unable to notify receiving officer about tracker update', [
                'tracker_id' => $tracker->id,
                'officer_id' => $officerId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function notifyWorkflowOwner(FileTracker $tracker, string $title, string $body, array $data = []): void
    {
        $ownerId = (int) ($tracker->created_by ?? 0);

        if ($ownerId <= 0) {
            return;
        }

        try {
            $owner = User::on('sqlsrv')->find($ownerId) ?? User::find($ownerId);

            if (!$owner) {
                return;
            }

            $payload = array_merge([
                'tracking_id' => $tracker->tracking_id,
                'file_number' => $tracker->file_number,
                'file_title' => $tracker->file_title,
                'status' => $tracker->status,
                'workflow_type' => $tracker->workflow_type,
            ], $data);

            $overrides = [
                'module' => 'file_tracking',
                'subject' => $title,
                'message' => $body,
                'name' => $owner->name,
                'enabled_email' => false,
                'enabled_sms' => false,
            ];

            $this->notificationService->create(
                $owner->id,
                'file_tracking.workflow',
                $title,
                $body,
                $payload,
                $overrides
            );
        } catch (\Throwable $exception) {
            Log::warning('Unable to notify workflow owner about tracker update', [
                'tracker_id' => $tracker->id,
                'owner_id' => $ownerId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function notifyKangisWorkflowTransition(FileTracker $tracker, array $workflowUpdate, array $context = []): void
    {
        $status = strtolower((string) ($workflowUpdate['status'] ?? $tracker->status));
        $fileReference = $tracker->file_number ?: $tracker->tracking_id;

        $title = null;
        $body = null;

        if ($status === 'recommended') {
            $title = sprintf('File %s recommended by Director GIS', $fileReference);
            $body = sprintf(
                'Tracking ID: %s • Recommendation completed and forwarded to Director General for approval.',
                $tracker->tracking_id
            );
        }

        if ($status === 'not_recommended') {
            $title = sprintf('File %s not recommended', $fileReference);
            $body = sprintf(
                'Tracking ID: %s • Recommendation was not approved and the file remains in KANGIS Registry.',
                $tracker->tracking_id
            );
        }

        if ($status === 'approved') {
            $queueDestination = $workflowUpdate['queue_destination_name'] ?? 'Department Queue';
            $title = sprintf('File %s approved by DG', $fileReference);
            $body = sprintf(
                'Tracking ID: %s • Approved and moved to %s for processing.',
                $tracker->tracking_id,
                $queueDestination
            );
        }

        if ($status === 'rejected') {
            $title = sprintf('File %s rejected by DG', $fileReference);
            $body = sprintf(
                'Tracking ID: %s • Approval was rejected and the file remains in KANGIS Registry.',
                $tracker->tracking_id
            );
        }

        if (!$title || !$body) {
            return;
        }

        $actorName = trim((string) ($context['actor_name'] ?? ''));
        if ($actorName !== '') {
            $body .= ' Action by: ' . $actorName . '.';
        }

        $reason = trim((string) ($context['reason'] ?? ''));
        if ($reason !== '') {
            $body .= ' Note: ' . $reason;
        }

        $this->notifyWorkflowOwner($tracker, $title, $body, [
            'workflow_status' => $status,
            'workflow_step' => $workflowUpdate['next_step'] ?? $tracker->workflow_step,
            'workflow_purpose' => $context['purpose'] ?? null,
            'workflow_decision' => $context['decision'] ?? null,
        ]);
    }

    /**
     * Auto-create a KANGIS checkout approval record when a file tracker
     * is created under the KANGIS module.
     */
    protected function createKangisCheckoutApproval(FileTracker $tracker): void
    {
        $user = Auth::user();
        $year = now()->year;

        $module = $tracker->module ?? 'kangis';
        $registryLabel = match ($module) {
            'sltr' => 'SLTR Registry',
            'st'   => 'ST Registry',
            default => 'KANGIS Registry',
        };

        $count = DB::connection('sqlsrv')
            ->table('kangis_checkout_approvals')
            ->whereYear('created_at', $year)
            ->count();
        $requestNo = sprintf('KCA/%d/%04d', $year, $count + 1);

        DB::connection('sqlsrv')->table('kangis_checkout_approvals')->insert([
            'request_no'            => $requestNo,
            'file_tracker_id'       => $tracker->id,
            'file_number'           => $tracker->file_number,
            'file_title'            => $tracker->file_title,
            'requested_by'          => $user->id ?? null,
            'requested_by_name'     => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'requested_office_code' => $tracker->origin_office_code,
            'requested_office_name' => $tracker->origin_office_name,
            'purpose'               => 'File checkout from ' . $registryLabel . ' — Tracker #' . $tracker->tracking_id,
            'status'                => 'pending',
            'module'                => $module,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    /**
     * Handle cross-module request approval / rejection.
     * POST /api/file-trackers/{id}/cross-module-action
     */
    public function crossModuleAction(Request $request, $id): JsonResponse
    {
        try {
            $tracker = FileTracker::find($id);

            if (!$tracker) {
                return response()->json(['success' => false, 'message' => 'File tracker not found'], 404);
            }

            if ($tracker->workflow_type !== FileTracker::WORKFLOW_CROSS_MODULE_REQUEST) {
                return response()->json(['success' => false, 'message' => 'Not a cross-module request tracker'], 422);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:approve,reject',
                'notes'  => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $action   = $request->input('action');
            $actorName = Auth::user()->name ?? 'System';
            $config    = is_array($tracker->workflow_config) ? $tracker->workflow_config : [];

            if ($action === 'approve') {
                $tracker->status       = 'cross_approved';
                $tracker->workflow_step = 2;
                $config['approved_by']   = $actorName;
                $config['approved_at']   = now()->toIso8601String();
                $config['approval_notes'] = $request->input('notes', '');
            } else {
                $tracker->status       = 'cross_rejected';
                $tracker->workflow_step = 2;
                $config['rejected_by']   = $actorName;
                $config['rejected_at']   = now()->toIso8601String();
                $config['rejection_notes'] = $request->input('notes', '');
            }

            $tracker->workflow_config = $config;
            $tracker->save();

            // Notify the tracker creator (sender)
            $statusLabel = $action === 'approve' ? 'approved' : 'rejected';
            $title = sprintf('Your file request has been %s', $statusLabel);
            $body  = sprintf(
                'Tracking ID %s — %s. %s',
                $tracker->tracking_id,
                $action === 'approve' ? 'You may now print the tracking sheet.' : 'The request was rejected.',
                $request->input('notes', '')
            );
            $this->notifyWorkflowOwner($tracker, $title, $body);

            $this->decorateTracker($tracker);

            return response()->json([
                'success' => true,
                'data'    => $tracker,
                'message' => sprintf('Request %s successfully.', $statusLabel),
            ]);

        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Resolve deterministic transitions for the updated KANGIS 4-step workflow.
     */
    protected function resolveKangisWorkflowTransition(FileTracker $tracker, Request $request, ?string $purpose): array
    {
        $currentStep = (int) ($tracker->workflow_step ?? 1);
        $decision = strtolower(trim((string) $request->input('decision', '')));

        if ($purpose === FileTracker::PURPOSE_SUBMISSION) {
            return [
                'status' => 'submitted',
                'next_step' => 2,
                'office_code' => FileTracker::OFFICE_DIRECTOR_GIS,
                'office_name' => 'Director GIS',
            ];
        }

        if ($purpose === FileTracker::PURPOSE_RECOMMENDATION) {
            if ($decision === '' || $decision === 'recommend') {
                return [
                    'status' => 'recommended',
                    'next_step' => 3,
                    'office_code' => FileTracker::OFFICE_DG_KANGIS,
                    'office_name' => 'Director General, KANGIS',
                ];
            }

            if ($decision === 'not_recommend') {
                return [
                    'status' => 'not_recommended',
                    'next_step' => 1,
                    'office_code' => FileTracker::OFFICE_KANGIS_REGISTRY,
                    'office_name' => 'KANGIS Registry',
                ];
            }

            throw new Exception('Invalid recommendation decision. Use recommend or not_recommend.');
        }

        if ($purpose === FileTracker::PURPOSE_APPROVAL) {
            if ($decision === '' || $decision === 'approve') {
                // Enforce workflow order: the DG cannot approve a file that the
                // Director GIS has not yet recommended. workflow_step only reaches
                // 3 after a positive recommendation (a not_recommend/reject resets
                // it to 1), so a step below 3 means the recommendation is missing.
                if ($currentStep < 3) {
                    throw new Exception('Cannot approve: this file must first be recommended by the Director GIS before the DG can approve it.');
                }

                return [
                    'status' => 'approved',
                    'next_step' => 4,
                    'office_code' => FileTracker::OFFICE_DEPARTMENT_QUEUE,
                    'office_name' => 'Department Queue',
                    'queue_destination_name' => 'Department Queue',
                ];
            }

            if ($decision === 'reject') {
                return [
                    'status' => 'rejected',
                    'next_step' => 1,
                    'office_code' => FileTracker::OFFICE_KANGIS_REGISTRY,
                    'office_name' => 'KANGIS Registry',
                ];
            }

            throw new Exception('Invalid approval decision. Use approve or reject.');
        }

        if ($purpose === FileTracker::PURPOSE_PROCESSING) {
            $department = trim((string) $request->input('department', ''));
            if ($department === '') {
                $department = trim((string) $request->input('office_name', ''));
            }

            if ($department === '') {
                throw new Exception('Department is required to start processing.');
            }

            return [
                'status' => 'in_processing',
                'next_step' => 4,
                'office_code' => FileTracker::OFFICE_DEPARTMENT_QUEUE,
                'office_name' => 'Department Queue',
                'current_department' => $department,
            ];
        }

        // Fallback: keep current state if purpose was not workflow-driven.
        return [
            'status' => (string) $tracker->status,
            'next_step' => $currentStep,
            'office_code' => (string) ($tracker->current_office_code ?? ''),
            'office_name' => (string) ($tracker->current_office_name ?? ''),
        ];
    }
}




