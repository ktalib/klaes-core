<?php

namespace App\Http\Controllers;

use App\Models\FileIndexing;
use App\Models\FileSearchRequest;
use App\Models\FileTracker;
use App\Models\RequestPurpose;
use App\Models\User;
use App\Mail\FileSearchRequestIssued;
use App\Models\OtherReceivingOfficer;
use App\Models\Notification;
use App\Services\EBulkSmsService;
use App\Services\BulkSmsNigeriaService;
use App\Services\FileCommissioningTrackingService;
use App\Services\FileLocationResolver;
use App\Services\UserNotificationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateFileTrackerController extends Controller
{
    /**
     * The "Submitted Request" predicate — an officer asked for this file. The
     * In-transit tab is its exact negation, so the two request-type tabs can
     * never drift apart or double-count a tracker. Mirrors
     * FileTrackerDashboardApiController::requestedFlagSql() so the File Log
     * Table and the Commissioner Dashboard count the same set.
     */
    protected const SUBMITTED_REQUEST_TYPE_SQL = "(UPPER(ISNULL(file_request_type, '')) = 'SUBMITTED')";

    protected array $rackShelfCache = [];

    protected array $indexingCreatedAtCache = [];

    protected array $indexingTitleCache = [];

    /**
     * Per-request map of normalized (UPPER+trim) file number => the highest-id
     * file_indexings row {id, created_at}. Primed once per results page so
     * getFileIndexingCreatedAt() reads from memory instead of scanning the
     * 130k-row file_indexings table once per row. Null means "not primed".
     */
    protected ?array $indexingRowCache = null;

    protected array $fileTrackerMovementHistoryCache = [];

    /**
     * Set of normalized (UPPER+trim) file numbers that actually exist in
     * file_tracker or file_indexings. Primed once per results page so the
     * mother/temp counterpart lookups can skip the (expensive) location
     * resolver for the vast majority of files that have no counterpart.
     * A null value means "not primed" — callers then resolve on demand.
     */
    protected ?array $relatedFileExistsCache = null;

    public function __construct(
        protected UserNotificationService $notificationService
    ) {
    }

    /**
     * Display the create file tracker page
     */
    public function index()
    {
        $PageTitle = 'Create File Tracker';
        $PageDescription = 'Create and manage file trackers for document workflow management';

        // Fetch data for dropdowns
        $registries = DB::connection('sqlsrv')->table('physical_registries')->where('is_active', 1)->get();
        $departments = DB::connection('sqlsrv')
            ->table('offices')
            ->where('is_active', 1)
            ->whereNotNull('department')
            ->select('department')
            ->distinct()
            ->orderBy('department')
            ->get();
        $offices = DB::connection('sqlsrv')->table('offices')->where('is_active', 1)->get();
        $requestPurposes = RequestPurpose::active()->orderBy('name')->get();
        $receivingOfficers = DB::connection('sqlsrv')
            ->table('users')
            ->where('is_active', 1)
            ->where('staff_type_category', 'MLPP')
            ->select('id', 'first_name', 'last_name', 'department_id', 'profile', 'passport_photo_path')
            ->get()
            // Read through the query builder, so the User model's profile_url accessor
            // is not available — resolve the photo URL for each option here instead.
            ->map(function ($officer) {
                $officer->photo_url = \App\Support\UserPhoto::url($officer->profile, $officer->passport_photo_path);
                return $officer;
            });

        $user = Auth::user();
        $currentUserPayload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'type' => $user->type,
            'assign_role' => $user->assign_role,
            'roles' => $user->assignedRoleNames(),
        ];

        // Assignment permissions placeholder (if needed by existing blade code)
        $assignmentPermissionsPayload = [
            'can_assign' => true,
        ];

        $module = request()->get('url', '');

        // For digital_request module, resolve the user's department and its offices
        // so the Receiving Office shows a department-scoped dropdown.
        $userOffice             = null;
        $userDepartmentName     = null;
        $userDepartmentOffices  = collect();
        if (in_array(strtolower($module), ['digital_request', 'digital-request'])) {
            if ($user->department_id) {
                $userDepartmentName = DB::connection('sqlsrv')
                    ->table('departments')
                    ->where('id', $user->department_id)
                    ->value('name');
                if ($userDepartmentName) {
                    $userDepartmentOffices = DB::connection('sqlsrv')
                        ->table('offices')
                        ->where('department', $userDepartmentName)
                        ->where('is_active', 1)
                        ->orderBy('office_name')
                        ->get();
                    // Keep a single default office for the footer info strip
                    $userOffice = $userDepartmentOffices->first();
                }
            }
        }

        // Department directors ("DCIV Director", "Land Director", …) plus any
        // named individuals added through "Add New Director".
        $requesterDirectors = app(\App\Services\RequesterDirectorService::class)->optionsForDropdown();

        return view('create_file_tracker_page.index', compact(
            'PageTitle', 'PageDescription', 'registries', 'departments', 'offices',
            'requestPurposes', 'receivingOfficers', 'currentUserPayload', 'assignmentPermissionsPayload',
            'module', 'userOffice', 'userDepartmentName', 'userDepartmentOffices', 'requesterDirectors'
        ));
    }

    /**
     * Store a new file tracker
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file_number' => 'nullable|string|max:255',
                'file_title' => 'required|string|max:255',
                'file_type' => 'nullable|string|max:100',
                'priority' => 'required|in:LOW,MEDIUM,HIGH',
                'status' => 'required|in:Log-in,Canceled',
                'department' => 'required|string|max:100', // This is still used as "Origin Office" in some contexts? Or should we remove if replaced? Left for now.
                'destination' => 'required|string|max:255', // New Destination from Departments
                'request_purpose_id' => 'nullable|integer|exists:sqlsrv.request_purposes,id',
                'request_purpose_other' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'deadline' => 'nullable|date',
                'timeline_days' => 'nullable|integer|min:0|max:365',
                'movement_log' => 'required|array',
                'movement_log.*.office_code' => 'required|string',
                'movement_log.*.office_name' => 'required|string',
                'movement_log.*.log_in_time' => 'nullable|string',
                'movement_log.*.log_in_date' => 'nullable|date',
                'movement_log.*.log_out_time' => 'nullable|string',
                'movement_log.*.log_out_date' => 'nullable|date',
                'movement_log.*.notes' => 'nullable|string',
                'notes' => 'nullable|string',
                'requester_director_id' => 'nullable|integer',
                'requester_director_first_name' => 'nullable|string|max:255',
                'requester_director_last_name' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors()
                    ], 422);
                }

                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput()
                    ->with('error', 'Please fix the validation errors and try again.');
            }

            DB::beginTransaction();

            // Use the preview tracking ID built on the client (base + registry code),
            // so the saved ID always matches what was shown to the user.
            // Fall back to a fresh server-generated ID when none is provided.
            $proposedTrackingId = trim((string) $request->input('proposed_tracking_id', ''));
            $registryCode       = $request->input('origin_registry_code') ?: null;
            // Temporary file — the TMP code makes the tracker a standalone file for
            // tracking purposes only, so it never collides with the parent file's tracker.
            $isTemporaryFile    = filter_var($request->input('is_temporary_file'), FILTER_VALIDATE_BOOLEAN);

            if ($proposedTrackingId !== '' && !FileTracker::where('tracking_id', $proposedTrackingId)->exists()) {
                $trackingId = $proposedTrackingId;
            } else {
                $trackingId = FileTracker::generateTrackingId($registryCode);
                if ($isTemporaryFile) {
                    $trackingId .= '-TMP';
                }
            }

            $rawOfficerId = $request->input('receiving_officer_id');
            $isReceivingOfficerTable = is_string($rawOfficerId) && str_starts_with($rawOfficerId, 'ro_');
            $receivingOfficerId = $isReceivingOfficerTable ? null : (int) $rawOfficerId;
            $receivingOfficeCode = $request->input('receiving_office_code');
            $receivingOfficeName = $request->input('receiving_office_name');

            // If the officer comes from receiving_officers table, resolve the name
            if ($isReceivingOfficerTable) {
                $roId = (int) str_replace('ro_', '', $rawOfficerId);
                $roRecord = OtherReceivingOfficer::on('sqlsrv')->find($roId);
                if ($roRecord && !$receivingOfficeName) {
                    $receivingOfficeName = $roRecord->name;
                }
            }

            // Non-system officers auto-accept; system users go through pending acceptance
            $assignmentStatus = $isReceivingOfficerTable
                ? FileTracker::ASSIGNMENT_ACCEPTED
                : ($receivingOfficerId ? FileTracker::ASSIGNMENT_PENDING : FileTracker::ASSIGNMENT_ACCEPTED);

            $linkedIndexing = $this->findLinkedFileIndexing($request->input('file_number'));
            $isKangisNewTracker = $this->shouldUseKangisNewWorkflow(
                $request->input('file_number'),
                $request->input('workflow_type'),
                $linkedIndexing
            );

            $requestedModule = strtolower(trim((string) $request->input('module', '')));
            if ($requestedModule === 'new_kangis' && !$isKangisNewTracker) {
                $message = 'Only New KANGIS files (KN followed by digits) can be created in this module.';
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                    ], 422);
                }

                return redirect()->back()
                    ->withInput()
                    ->with('error', $message);
            }

            $resolvedModule = $requestedModule !== ''
                ? $requestedModule
                : ($isKangisNewTracker ? 'new_kangis' : null);

            if ($resolvedModule === 'new_kangis' && !$isKangisNewTracker) {
                $resolvedModule = null;
            }

            // Determine if this is a KANGIS approval workflow.
            // Only honour whitelisted workflow_type values from the request to avoid injection.
            $requestedWorkflowType = $request->input('workflow_type');
            $workflowType = in_array($requestedWorkflowType, [FileTracker::WORKFLOW_KANGIS_3STEP, FileTracker::WORKFLOW_KANGIS_APPROVAL], true)
                ? $requestedWorkflowType
                : null;

            // The KANGIS module ALWAYS uses the 4-step approval workflow — enforce server-side
            // even when the frontend omits workflow_type (toggle is locked ON in UI).
            if ($workflowType === null && $resolvedModule === 'kangis') {
                $workflowType = FileTracker::WORKFLOW_KANGIS_APPROVAL;
            }

            $workflowStep = null;
            $workflowConfig = null;
            $workflowStatus = $request->status ?: FileTracker::STATUS_ACTIVE;

            if (in_array($workflowType, [FileTracker::WORKFLOW_KANGIS_3STEP, FileTracker::WORKFLOW_KANGIS_APPROVAL], true)) {
                $workflowType = FileTracker::WORKFLOW_KANGIS_APPROVAL;
                // Tracker creation at registry is treated as submission complete.
                $workflowStep = 2;
                $workflowStatus = 'submitted';
                $originalDestName = $request->input('final_destination_name') ?: $request->destination;
                $workflowConfig = [
                    'destination_office_code'          => $request->input('final_destination_code'),
                    'destination_office_name'          => $originalDestName,
                    'original_destination_office_name' => $originalDestName,
                ];
            } elseif ($isKangisNewTracker) {
                $workflowType = FileTracker::WORKFLOW_KANGIS_NEW;
                $workflowStep = 2;
            }

            // Classification-only backfill: a KN-prefixed legacy KANGIS file (e.g. "KNML 6794")
            // always belongs to the kangis module so it stays visible in the KANGIS view,
            // even when this create flow did not pass module=kangis. This intentionally does
            // NOT touch $resolvedModule above, so it never forces the KANGIS approval workflow
            // for files arriving from non-KANGIS entry points.
            $storedModule = $resolvedModule;
            if ($storedModule === null && $this->isLegacyKangisFileNumber($request->file_number)) {
                $storedModule = 'kangis';
            }

            $requestPurpose = $request->filled('request_purpose_id')
                ? RequestPurpose::find($request->input('request_purpose_id'))
                : null;
            $requestPurposeOther = trim((string) $request->input('request_purpose_other', ''));
            $requestPurposeName = $requestPurpose?->name ?: ($requestPurposeOther !== '' ? $requestPurposeOther : null);

            // Timeline (Days) is the source of truth for the return window and is entered
            // by hand — the Request Purpose no longer supplies a default, since every
            // purpose carries the same turnaround and auto-filling it hid the real figure.
            // A date-only Expected Return Date means "by the end of that day": parsed
            // literally it's midnight, which would read as overdue from the first second
            // of the due date. setTime(23,59,59) keeps the whole due date "on time" —
            // note endOfDay() must NOT be used here, as its .999999 microseconds round
            // UP to the next midnight in SQL Server's `datetime` (~3.33ms precision),
            // silently granting an extra day.
            $timelineDays = $request->filled('timeline_days') ? (int) $request->input('timeline_days') : null;
            $deadline = $request->deadline
                ? \Carbon\Carbon::parse($request->deadline)->setTime(23, 59, 59)
                : ($timelineDays !== null ? now()->addDays($timelineDays)->setTime(23, 59, 59) : null);

            $rd = app(\App\Services\RequesterDirectorService::class)->resolve(
                $request->filled('requester_director_id') ? (int) $request->requester_director_id : null,
                $request->requester_director_first_name,
                $request->requester_director_last_name,
                $request->department
            );
            $requesterDirectorId = $rd?->id;
            $requesterDirectorName = $rd?->full_name;

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
                'destination' => $request->destination,
                'request_purpose_id' => $requestPurpose?->id,
                'request_purpose_name' => $requestPurposeName,
                'requester_director_id' => $requesterDirectorId,
                'requester_director_name' => $requesterDirectorName,
                'description' => $request->description,
                'status' => $workflowStatus,
                'date_created' => now(),
                'date_requested' => now(),
                'deadline' => $deadline,
                'timeline_days' => $timelineDays,
                'total_offices' => count($request->movement_log),
                'notes' => $request->notes,
                'receiving_office_code' => $receivingOfficeCode,
                'receiving_office_name' => $receivingOfficeName,
                'receiving_officer_id' => $receivingOfficerId ?: null,
                'receiving_officer_name' => $request->input('receiving_officer_name') ?: $receivingOfficeName,
                'assignment_status' => $assignmentStatus,
                'assignment_accepted_at' => $isReceivingOfficerTable ? now() : null,
                'module' => $storedModule,
                'workflow_type' => $workflowType,
                'workflow_step' => $workflowStep,
                'workflow_config' => $workflowConfig,
                'module_meta' => $linkedIndexing ? $this->buildTrackerModuleMeta($linkedIndexing, $request->input('file_number')) : null,
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
                    'status' => $index === 0 ? 'active' : 'pending'
                ];

                if ($receivingOfficerId && $index === 0) {
                    $processedEntry['status'] = 'pending_acceptance';
                    $processedEntry['log_in_time'] = null;
                    $processedEntry['log_in_date'] = null;
                    $processedEntry['receiving_officer_id'] = $receivingOfficerId;
                    $processedEntry['receiving_officer_name'] = $request->input('receiving_officer_name');
                } elseif ($isReceivingOfficerTable && $index === 0) {
                    $processedEntry['status'] = 'active';
                    $processedEntry['receiving_officer_name'] = $receivingOfficeName;
                }

                $processedLog[] = $processedEntry;
            }

            $tracker->movement_log = $processedLog;

            // Set current office from first log entry
            if (!empty($processedLog)) {
                $tracker->current_office_code = $processedLog[0]['office_code'];
                $tracker->current_office_name = $processedLog[0]['office_name'];
                $tracker->completed_offices = 1;
            }

            if ($tracker->isKangis3Step()) {
                $tracker->current_office_code = FileTracker::OFFICE_DIRECTOR_GIS;
                $tracker->current_office_name = 'Director GIS';
                $tracker->receiving_office_code = FileTracker::OFFICE_DIRECTOR_GIS;
                $tracker->receiving_office_name = 'Director GIS';
            }

            if ($tracker->isKangisNewFile() && empty($tracker->workflow_step)) {
                $tracker->workflow_step = 2;
            }

            // A file logged out after a Quick Search request was asked for by an officer,
            // so it is a Submitted Request, not a file simply in transit.
            if ($this->answersQuickSearchRequest($tracker)) {
                $tracker->file_request_type = 'SUBMITTED';
            }

            $tracker->save();

            $smsResult = ['sent' => false, 'message' => null];
            if ($tracker->isKangisNewFile()) {
                $smsResult = $this->sendNewKangisTrackerSms(
                    $tracker,
                    $linkedIndexing,
                    $receivingOfficeName ?: $request->destination ?: $tracker->current_office_name
                );
            }

            DB::commit();

            /*
             | Tell the requesting officer their file is on its way to them.
             |
             | AFTER the commit, unlike the KANGIS tracker SMS a few lines above
             | it, which sends inside the transaction and would tell an applicant
             | about a movement that then rolled back. That one is left alone --
             | it predates this and changing when it fires is a separate call --
             | but the new message does not repeat the mistake.
             */
            $this->sendFileLoggedSms($tracker);

            // Logging the file resolves any open "Found" SCB request for it — mark them
            // acted so they drop out of the SCB Feedback queue. This covers logging via
            // the Quick Search result card, which doesn't hit the front-desk-acted endpoint.
            $loggedFileNumber = trim((string) $tracker->file_number);
            if ($loggedFileNumber !== '') {
                FileSearchRequest::where('status', FileSearchRequest::STATUS_FOUND)
                    ->whereNull('front_desk_acted_at')
                    ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper($loggedFileNumber)])
                    ->update([
                        'front_desk_acted_at' => now(),
                        'front_desk_acted_by' => Auth::id(),
                    ]);
            }

            // ── Digital Request module: auto-create approval record + notify ──
            if ($resolvedModule === 'digital_request') {
                try {
                    $user = Auth::user();

                    // Resolve source office from user's department
                    $deptName   = DB::connection('sqlsrv')->table('departments')->where('id', $user->department_id)->value('name');
                    $srcOffice  = $deptName ? \App\Models\Office::active()->where('department', $deptName)->first() : null;

                    $senderName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->name ?? 'Unknown');

                    $digitalReq = \App\Models\DigitalFileRequest::create([
                        'request_no'              => \App\Models\DigitalFileRequest::generateRequestNo(),
                        'file_no'                 => $tracker->file_number,
                        'file_title'              => $tracker->file_title,
                        'requester_user_id'       => $user->id,
                        'sending_officer'         => $senderName,
                        'receiving_officer'       => $request->input('receiving_officer_name') ?: $receivingOfficeName,
                        'source_office_id'        => $srcOffice?->id,
                        'source_office_name'      => $srcOffice?->office_name ?? $tracker->origin_office_name,
                        'destination_office_name' => $tracker->receiving_office_name ?? $tracker->destination,
                        'current_file_location'   => $tracker->current_office_name,
                        'request_status'          => \App\Models\DigitalFileRequest::STATUS_PENDING,
                        'remarks'                 => $tracker->notes,
                        'requested_at'            => now(),
                    ]);

                    // Notify the receiving officer with approve/reject actions in the bell
                    if ($receivingOfficerId) {
                        $this->notificationService->create(
                            $receivingOfficerId,
                            'digital_request',
                            "Digital File Request: {$digitalReq->request_no}",
                            "{$senderName} is requesting file {$tracker->file_number} ({$tracker->file_title}) to be sent to {$tracker->receiving_office_name}.",
                            [
                                'request_id'    => $digitalReq->id,
                                'request_no'    => $digitalReq->request_no,
                                'file_number'   => $tracker->file_number,
                                'office_name'   => $tracker->receiving_office_name,
                            ],
                            ['module' => 'digital_request']
                        );
                    }

                } catch (\Throwable $e) {
                    Log::warning('DigitalFileRequest auto-create failed', ['tracker_id' => $tracker->id, 'error' => $e->getMessage()]);
                }
            }

            // Only notify system users (not receiving_officers table entries)
            if ($receivingOfficerId && !$isReceivingOfficerTable) {
                $this->notifyReceivingOfficer($tracker, [
                    'receiving_officer_id' => $receivingOfficerId,
                    'receiving_office_name' => $request->input('receiving_office_name'),
                    'file_number' => $request->input('file_number'),
                    'reason' => $request->input('notes'),
                ]);
            }

            if ($request->expectsJson()) {
                // Add computed attributes for API response
                $tracker->setAttribute('is_overdue', $tracker->is_overdue);
                $tracker->setAttribute('days_until_deadline', $tracker->days_until_deadline);
                $tracker->setAttribute('completion_percentage', $tracker->completion_percentage);
                $tracker->setAttribute('current_movement', $tracker->getCurrentMovement());
                $tracker->setAttribute('rack_shelf_location', $this->getRackShelfLocation($tracker->file_number));

                return response()->json([
                    'success' => true,
                    'data' => $tracker,
                    'message' => 'File tracker created successfully',
                    'sms_sent' => $smsResult['sent'],
                    'sms_message' => $smsResult['message']
                ], 201);
            }

            return redirect()->route('create-file-tracker.index')
                ->with('success', 'File tracker created successfully with ID: ' . $trackingId);

        } catch (Exception $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error creating file tracker: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error creating file tracker: ' . $e->getMessage());
        }
    }

    /**
     * Get list of file trackers for the current user
     */
    public function list(Request $request)
    {
        try {
            $query = FileTracker::query();
            $user = Auth::user();
            $userId = $user ? $user->id : null;
            $moduleFilter = strtolower(trim((string) $request->input('module', '')));

            // SQL Server-safe normalized expressions (trim + lowercase) for resilient filtering.
            $normalizedModuleExpr = "LOWER(LTRIM(RTRIM(ISNULL(module, ''))))";
            $normalizedWorkflowExpr = "LOWER(LTRIM(RTRIM(ISNULL(workflow_type, ''))))";
            $normalizedFileNoExpr = "UPPER(LTRIM(RTRIM(ISNULL(file_number, ''))))";

            $applyModuleScope = function ($builder) use ($moduleFilter) {
                $this->applyTrackerModuleScope($builder, $moduleFilter);
            };

            // Module filter (e.g. kangis, new_kangis, dgis, dg)
            $applyModuleScope($query);

            // DG/DGIS sub-tab filter (logs / track-new / request-land).
            // - logs        : Track Existing — exclude New KANGIS (KN-prefixed) AND Cross-Registry Requests
            // - track-new   : New KANGIS files only (KN-prefixed numeric file numbers OR new_kangis workflow)
            // - request-land: Cross-Registry Requests only
            $tabFilter = strtolower(trim((string) $request->input('tab', '')));
            if (in_array($moduleFilter, ['dgis', 'dg'], true) && $tabFilter !== '') {
                if ($tabFilter === 'request-land') {
                    $query->whereRaw("{$normalizedWorkflowExpr} = ?", [FileTracker::WORKFLOW_CROSS_MODULE_REQUEST]);
                } elseif ($tabFilter === 'track-new') {
                    $query->where(function ($q) use ($normalizedWorkflowExpr, $normalizedModuleExpr, $normalizedFileNoExpr) {
                        $q->whereRaw("{$normalizedWorkflowExpr} <> ?", [FileTracker::WORKFLOW_CROSS_MODULE_REQUEST])
                          ->where(function ($qq) use ($normalizedWorkflowExpr, $normalizedModuleExpr, $normalizedFileNoExpr) {
                              $qq->whereRaw("{$normalizedModuleExpr} = ?", ['new_kangis'])
                                 ->orWhereRaw("{$normalizedWorkflowExpr} = ?", [FileTracker::WORKFLOW_KANGIS_NEW])
                                 ->orWhere(function ($kn) use ($normalizedFileNoExpr) {
                                      $kn->whereRaw("{$normalizedFileNoExpr} LIKE 'KN%'")
                                         ->whereRaw("LEN({$normalizedFileNoExpr}) > 2")
                                         ->whereRaw("PATINDEX('%[^0-9]%', SUBSTRING({$normalizedFileNoExpr}, 3, 8000)) = 0");
                                 });
                          });
                    });
                } else { // 'logs' (default existing-file view)
                    $query->whereRaw("{$normalizedWorkflowExpr} <> ?", [FileTracker::WORKFLOW_CROSS_MODULE_REQUEST])
                          ->where(function ($q) use ($normalizedFileNoExpr) {
                              // Exclude KN-prefixed New KANGIS files
                              $q->whereRaw("{$normalizedFileNoExpr} NOT LIKE 'KN%'")
                                ->orWhereRaw("PATINDEX('%[^0-9]%', SUBSTRING({$normalizedFileNoExpr}, 3, 8000)) <> 0");
                          });
                }
            }

            // Apply filters
            if ($request->has('status') && $request->status) {
                $status = $request->status;
                if ($status === 'my-files') {
                    $query->where('receiving_officer_id', $userId)
                          ->where('status', '!=', FileTracker::STATUS_COMPLETED);
                } elseif ($status === 'awaiting') {
                    $query->where('receiving_officer_id', $userId)
                          ->where('assignment_status', FileTracker::ASSIGNMENT_PENDING);
                } elseif ($status === 'other-handlers') {
                    $query->where('receiving_officer_id', '!=', $userId)
                          ->where('status', '!=', FileTracker::STATUS_COMPLETED);
                } elseif ($status === 'completed') {
                    $query->where('status', FileTracker::STATUS_COMPLETED);
                } elseif ($status === 'not-completed') {
                    // Active tab on the File Log Table — everything except files that
                    // have been logged back in (COMPLETED).
                    $query->where('status', '!=', FileTracker::STATUS_COMPLETED);
                } elseif ($status !== 'all') {
                    $query->where('status', $status);
                }
            }

            if ($request->has('priority') && $request->priority && $request->priority !== 'all') {
                $query->where('priority', $request->priority);
            }

            // File Request Type tabs: In-transit / Submitted Request. The two tabs
            // are exact complements, matching the Commissioner Dashboard: SUBMITTED
            // means an officer asked for the file, and EVERYTHING else (MANUAL,
            // SYSTEM, the legacy "In-Transit" literal, or an unclassified/NULL row)
            // is a file that is simply moving. Filtering on the literal 'MANUAL'
            // used to drop ~98% of the in-transit set on the floor.
            $fileRequestType = strtoupper(trim((string) $request->input('file_request_type', '')));
            if ($fileRequestType === 'SUBMITTED') {
                $query->whereRaw(self::SUBMITTED_REQUEST_TYPE_SQL);
            } elseif (in_array($fileRequestType, ['MANUAL', 'IN_TRANSIT', 'IN-TRANSIT'], true)) {
                $this->applyInTransitScope($query);
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('tracking_id', 'LIKE', "%{$search}%")
                        ->orWhere('file_number', 'LIKE', "%{$search}%")
                        ->orWhere('file_title', 'LIKE', "%{$search}%");
                });
            }

            // Collapse to one row per file number, keeping only the most recent
            // tracker within the given scope (see the main-query collapse below for
            // the full rationale). Shared so the File Log Table sub-tab badges count
            // the SAME unit — files, not raw tracker records — as the table total.
            //   $mode === 'active'    : newest active (non-completed) tracker per file
            //   $mode === 'completed' : newest completed tracker per file
            //   $mode === 'any'       : newest tracker per file, any status
            $applyCollapse = function ($builder, string $mode) {
                return $builder->where(function ($outer) use ($mode) {
                    $outer->whereNull('file_number')
                        ->orWhereRaw("LTRIM(RTRIM(file_number)) = ''")
                        ->orWhereNotExists(function ($sub) use ($mode) {
                            $sub->selectRaw('1')
                                ->from('file_tracker as ft2')
                                ->whereColumn('ft2.file_number', 'file_tracker.file_number')
                                ->whereColumn('ft2.id', '>', 'file_tracker.id');

                            if ($mode === 'completed') {
                                $sub->where('ft2.status', FileTracker::STATUS_COMPLETED);
                            } elseif ($mode === 'active') {
                                $sub->where('ft2.status', '!=', FileTracker::STATUS_COMPLETED);
                            }
                        });
                });
            };

            // Cheap equivalent of counting the collapsed rows above, WITHOUT the
            // correlated NOT EXISTS (which is slow layered on the non-sargable
            // module scan). The collapse keeps exactly one row — the highest id —
            // per non-empty file number, and keeps every empty/null-numbered row
            // as-is, so the collapsed count is:
            //   COUNT(DISTINCT non-empty file_number)  +  COUNT(empty/null rows).
            // The passed builder must already carry the same module/status scope as
            // the rows being collapsed. Mathematically identical to $applyCollapse.
            $collapsedCount = function ($builder): int {
                $nonEmpty = (clone $builder)
                    ->whereNotNull('file_number')
                    ->whereRaw("LTRIM(RTRIM(file_number)) <> ''")
                    ->distinct()
                    ->count('file_number');

                $empty = (clone $builder)
                    ->where(function ($q) {
                        $q->whereNull('file_number')
                          ->orWhereRaw("LTRIM(RTRIM(file_number)) = ''");
                    })
                    ->count();

                return $nonEmpty + $empty;
            };

            // Global stats for the dashboard matching the UI cards
            $statsBase = FileTracker::query();
            $applyModuleScope($statsBase);
            $stats = [
                'my_files' => (clone $statsBase)->where('receiving_officer_id', $userId)
                                ->where('status', '!=', FileTracker::STATUS_COMPLETED)->count(),
                'awaiting' => (clone $statsBase)->where('receiving_officer_id', $userId)
                                ->where('assignment_status', FileTracker::ASSIGNMENT_PENDING)->count(),
                'others' => (clone $statsBase)->where('receiving_officer_id', '!=', $userId)
                                ->where('status', '!=', FileTracker::STATUS_COMPLETED)->count(),
                // Completed / Active sub-tab badges label the File Log Table, so they
                // count collapsed files (one card per file number) to match its
                // "N files found" total — not raw tracker records.
                'completed' => $collapsedCount(
                                (clone $statsBase)->where('status', FileTracker::STATUS_COMPLETED)
                              ),
                'active' => $collapsedCount(
                                (clone $statsBase)->where('status', '!=', FileTracker::STATUS_COMPLETED)
                              ),
                // File Request Type tab counts. They label the File Log Table's
                // Active view, so they count the same unit it does — collapsed
                // files, not raw tracker rows — over the non-completed set, and
                // they split it the same way the Commissioner Dashboard does
                // (Submitted vs. its exact negation).
                'in_transit' => $collapsedCount(
                                (clone $statsBase)
                                    ->where('status', '!=', FileTracker::STATUS_COMPLETED)
                                    ->where(fn ($q) => $this->applyInTransitScope($q))
                              ),
                'submitted' => $collapsedCount(
                                (clone $statsBase)
                                    ->where('status', '!=', FileTracker::STATUS_COMPLETED)
                                    ->whereRaw(self::SUBMITTED_REQUEST_TYPE_SQL)
                              ),
            ];

            // DIIT files are active by definition (in process at the File Commissioning
            // Office), so they count towards the Active badge — otherwise the badge
            // disagrees with the "N files found" total of the list it labels. They are
            // never Completed, and they count as In-transit, so In-transit + Submitted
            // Request adds up to Active.
            if ($moduleFilter === '') {
                $stats['diit'] = app(FileCommissioningTrackingService::class)->untrackedQuery()->count();
                $stats['active'] += $stats['diit'];
                $stats['in_transit'] += $stats['diit'];
            }

            // Extra new_kangis dashboard metrics:
            //   - tracked_today      : trackers created today
            //   - total_tracked      : total trackers in this module
            //   - unique_requesters  : distinct originating users (created_by)
            if (in_array($moduleFilter, ['new_kangis', 'kangis', 'dg', 'dgis'], true)) {
                // For the DG view, the activity KPIs must reflect the SAME union as the
                // DG File Streams (KANGIS legacy + KANGIS New + Cross-Registry Requests),
                // otherwise Total Files Tracked diverges from the streams sum.
                if ($moduleFilter === 'dg') {
                    $dgUnionFilter = function ($query) use ($normalizedModuleExpr, $normalizedWorkflowExpr, $normalizedFileNoExpr) {
                        $query->where(function ($outer) use ($normalizedModuleExpr, $normalizedWorkflowExpr, $normalizedFileNoExpr) {
                            // Stream 1: KANGIS legacy (module = kangis AND not KN-prefix-numeric)
                            $outer->orWhere(function ($q) use ($normalizedModuleExpr, $normalizedFileNoExpr) {
                                $q->whereRaw("{$normalizedModuleExpr} = ?", ['kangis'])
                                    ->where(function ($qq) use ($normalizedFileNoExpr) {
                                        $qq->whereRaw("{$normalizedFileNoExpr} NOT LIKE 'KN%'")
                                            ->orWhereRaw("PATINDEX('%[^0-9]%', SUBSTRING({$normalizedFileNoExpr}, 3, 8000)) <> 0");
                                    });
                            });
                            // Stream 2: KANGIS New (KN-prefix numeric AND new_kangis module/workflow)
                            $outer->orWhere(function ($q) use ($normalizedModuleExpr, $normalizedWorkflowExpr, $normalizedFileNoExpr) {
                                $q->whereRaw("{$normalizedFileNoExpr} LIKE 'KN%'")
                                    ->whereRaw("LEN({$normalizedFileNoExpr}) > 2")
                                    ->whereRaw("PATINDEX('%[^0-9]%', SUBSTRING({$normalizedFileNoExpr}, 3, 8000)) = 0")
                                    ->where(function ($typedQuery) use ($normalizedModuleExpr, $normalizedWorkflowExpr) {
                                        $typedQuery->whereRaw("{$normalizedWorkflowExpr} = ?", [FileTracker::WORKFLOW_KANGIS_NEW])
                                            ->orWhereRaw("{$normalizedModuleExpr} = ?", ['new_kangis']);
                                    });
                            });
                            // Stream 3: Cross-Registry Requests
                            $outer->orWhereRaw("{$normalizedWorkflowExpr} = ?", [FileTracker::WORKFLOW_CROSS_MODULE_REQUEST]);
                        });
                    };

                    $stats['tracked_today']     = FileTracker::query()->where($dgUnionFilter)
                                                    ->whereDate('created_at', now()->toDateString())->count();
                    $stats['total_tracked']     = FileTracker::query()->where($dgUnionFilter)->count();
                    $stats['unique_requesters'] = FileTracker::query()->where($dgUnionFilter)
                                                    ->whereNotNull('created_by')
                                                    ->distinct('created_by')
                                                    ->count('created_by');
                } else {
                    $stats['tracked_today']     = (clone $statsBase)->whereDate('created_at', now()->toDateString())->count();
                    $stats['total_tracked']     = (clone $statsBase)->count();
                    $stats['unique_requesters'] = (clone $statsBase)
                        ->whereNotNull('created_by')
                        ->distinct('created_by')
                        ->count('created_by');
                }
            }

            // DG dashboard breakdown by registry/source: KANGIS (legacy), KANGIS New (KN-prefixed),
            // and Cross-Registry Requests. These give the Director General a quick glance across
            // the three streams of files reaching their desk.
            // Also exposed for DGIS so their tab badges (logs / track-new / request-land) match.
            if (in_array($moduleFilter, ['dg', 'dgis'], true)) {
                $dgKangisBase = FileTracker::query();
                $dgKangisBase->whereRaw("{$normalizedModuleExpr} = ?", ['kangis']);
                // Legacy KANGIS count must EXCLUDE cross-registry requests so that the
                // File Log Manager tab badge does not double-count items that belong to
                // the Request Land File tab.
                $dgKangisBase->where(function ($q) use ($normalizedWorkflowExpr) {
                    $q->whereRaw("{$normalizedWorkflowExpr} <> ?", [FileTracker::WORKFLOW_CROSS_MODULE_REQUEST])
                        ->orWhereNull('workflow_type');
                });

                $stats['kangis_legacy_count'] = (clone $dgKangisBase)
                    ->where(function ($q) use ($normalizedFileNoExpr) {
                        $q->whereRaw("{$normalizedFileNoExpr} NOT LIKE 'KN%'")
                            ->orWhereRaw("PATINDEX('%[^0-9]%', SUBSTRING({$normalizedFileNoExpr}, 3, 8000)) <> 0");
                    })
                    ->count();

                $stats['kangis_new_count'] = FileTracker::query()
                    ->where(function ($q) use ($normalizedModuleExpr, $normalizedWorkflowExpr, $normalizedFileNoExpr) {
                        $q->whereRaw("{$normalizedFileNoExpr} LIKE 'KN%'")
                            ->whereRaw("LEN({$normalizedFileNoExpr}) > 2")
                            ->whereRaw("PATINDEX('%[^0-9]%', SUBSTRING({$normalizedFileNoExpr}, 3, 8000)) = 0")
                            ->where(function ($typedQuery) use ($normalizedModuleExpr, $normalizedWorkflowExpr) {
                                $typedQuery->whereRaw("{$normalizedWorkflowExpr} = ?", [FileTracker::WORKFLOW_KANGIS_NEW])
                                    ->orWhereRaw("{$normalizedModuleExpr} = ?", ['new_kangis']);
                            });
                    })
                    ->count();

                $stats['cross_registry_count'] = FileTracker::query()
                    ->whereRaw("{$normalizedWorkflowExpr} = ?", [FileTracker::WORKFLOW_CROSS_MODULE_REQUEST])
                    ->count();
            }

            // Date range filter
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->get('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->get('date_to'));
            }

            // Collapse to one card per file: keep only the most recent tracker per file
            // number within the current status scope. Earlier cycles of the same file are
            // already merged into the surviving card as read-only "prior movements", so
            // rendering the older trackers as their own cards would just duplicate that
            // history (and leave empty/stale cards). Uses an indexed correlated existence
            // check (file_number is indexed) so it stays fast on large datasets — a
            // GROUP BY / window collapse re-runs the non-sargable PATINDEX filters and
            // times out. Trackers without a file number can't be grouped, so each is kept.
            $statusFilter = $request->input('status');
            $collapseMode = null;
            if ($statusFilter === 'completed') {
                $collapseMode = 'completed';
            } elseif ($statusFilter === 'not-completed') {
                $collapseMode = 'active';
            } elseif (!$request->filled('status') || $statusFilter === 'all') {
                $collapseMode = 'any';
            }

            if ($collapseMode !== null) {
                $applyCollapse($query, $collapseMode);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = strtolower($request->get('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

            $perPage = (int) $request->get('per_page', 20);
            $perPage = max(1, min($perPage, 500));

            $requestedPage = (int) $request->get('page', 1);
            $page = max(1, $requestedPage);

            // Default In-process In-transit Tracking — commissioned files that have
            // never been logged out have no tracker row of their own, so they are
            // listed from the commissioning register alongside the real trackers.
            $diitQuery = $this->diitListQuery($request, $moduleFilter);

            if ($diitQuery === null) {
                $query->orderBy($sortBy, $sortOrder);

                // Get total filtered count for pagination UI
                $totalFiltered = $query->count();
                $totalPages = ceil($totalFiltered / $perPage);

                $results = $query
                    ->skip(($page - 1) * $perPage)
                    ->take($perPage)
                    ->get();
            } else {
                $totalFiltered = $query->count() + (clone $diitQuery)->count();
                $totalPages = ceil($totalFiltered / $perPage);

                $results = $this->paginateTrackersWithDiit(
                    $query,
                    $diitQuery,
                    $sortBy,
                    $sortOrder,
                    ($page - 1) * $perPage,
                    $perPage
                );
            }

            // Bulk pre-load the movement history for every file number on this page so
            // decorateTrackerForResponse() resolves prior_movements from cache instead of
            // running one query per row (N+1).
            $this->primeMovementHistoryCache($results->pluck('file_number'));

            // Bulk pre-load which mother/temp counterparts exist so the per-row
            // counterpart location strip skips the heavy location resolver for the
            // (common) files that have no counterpart — the dominant cost of this
            // endpoint before priming.
            $this->primeRelatedLocationCache($results->pluck('file_number'));

            // Bulk pre-load the file_indexings created_at ("home location" timestamp)
            // for every file number on this page in one indexed query instead of a
            // 130k-row scan per row.
            $this->primeIndexingCreatedAtCache($results->pluck('file_number'));

            // Bulk pre-load the commissioning register so every card on the page can
            // carry its default "File Commissioning" line (and the location resolver
            // can answer DIIT questions) without a query per row.
            app(FileCommissioningTrackingService::class)->prime($results->pluck('file_number'));

            // Same idea for the holder photos printed on the Tracking Sheet: one query
            // for the page instead of one per row.
            \App\Support\UserPhoto::prime($results->pluck('receiving_officer_id'));

            // And for the applicant photograph shown on each log card: two queries for the
            // whole page rather than two per row.
            app(\App\Services\FilePassportService::class)->prime($results->pluck('file_number'));

            $collection = $results->map(function ($tracker) {
                return $this->decorateTrackerForResponse($tracker);
            });

            return response()->json([
                'success' => true,
                'data' => $collection,
                'stats' => $stats,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $totalPages,
                    'total_items' => $totalFiltered,
                    'has_more' => $page < $totalPages,
                    'next_page' => $page < $totalPages ? $page + 1 : null,
                    'prev_page' => $page > 1 ? $page - 1 : null,
                ],
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
     * Iterate several traversables as one stream, without loading any of them
     * into memory — the exports read a database cursor followed by a generator.
     */
    protected function chain(iterable ...$sources): \Generator
    {
        foreach ($sources as $source) {
            foreach ($source as $item) {
                yield $item;
            }
        }
    }

    /**
     * Scope a FileTracker query to one module view. Shared by the File Log Table
     * and its CSV/PDF exports so an export holds exactly the files the view lists.
     */
    protected function applyTrackerModuleScope($builder, string $moduleFilter): void
    {
        $normalizedModuleExpr = "LOWER(LTRIM(RTRIM(ISNULL(module, ''))))";
        $normalizedWorkflowExpr = "LOWER(LTRIM(RTRIM(ISNULL(workflow_type, ''))))";
        $normalizedFileNoExpr = "UPPER(LTRIM(RTRIM(ISNULL(file_number, ''))))";

        if ($moduleFilter === '') {
            // General /create-file-tracker (no url=kangis / no url=new_kangis):
            // this is the LAND file log — it must show land files only. Exclude
            // the KANGIS / New KANGIS modules AND, by file-number prefix, any file
            // that belongs to another registry (KANGIS legacy/new, SLTR, ST,
            // DCIV, Survey/GKN). SIT files are Land files and stay visible here.
            // The prefix guard is the ground truth: it also
            // catches older records that carry a NULL/empty module and would
            // otherwise leak into the Land view.
            $builder->where(function ($excludeQuery) use ($normalizedModuleExpr, $normalizedWorkflowExpr, $normalizedFileNoExpr) {
                $excludeQuery->whereRaw("{$normalizedModuleExpr} NOT IN (?, ?)", ['kangis', 'new_kangis'])
                    ->whereRaw("{$normalizedWorkflowExpr} NOT IN (?, ?, ?)", [
                        FileTracker::WORKFLOW_KANGIS_NEW,
                        FileTracker::WORKFLOW_KANGIS_APPROVAL,
                        FileTracker::WORKFLOW_KANGIS_3STEP,
                    ])
                    ->whereRaw("{$normalizedFileNoExpr} NOT LIKE 'KN%'")
                    ->whereRaw("{$normalizedFileNoExpr} NOT LIKE 'MLKN%'")
                    ->whereRaw("{$normalizedFileNoExpr} NOT LIKE 'SLTR%'")
                    ->whereRaw("{$normalizedFileNoExpr} NOT LIKE 'ST-%'")
                    ->whereRaw("{$normalizedFileNoExpr} NOT LIKE 'ST/%'")
                    ->whereRaw("{$normalizedFileNoExpr} NOT LIKE 'LPCC%'")
                    ->whereRaw("{$normalizedFileNoExpr} NOT LIKE 'DCIV%'")
                    ->whereRaw("{$normalizedFileNoExpr} NOT LIKE 'GKN%'")
                    ->whereRaw("{$normalizedFileNoExpr} NOT LIKE 'LPKN%'");
            });
            return;
        }

        // dgis and dg are approval views into legacy kangis workflow trackers.
        if (in_array($moduleFilter, ['dgis', 'dg'], true)) {
            $builder->whereRaw("{$normalizedModuleExpr} = ?", ['kangis']);
            return;
        }

        if ($moduleFilter === 'new_kangis') {
            // Strictly show New KANGIS trackers only:
            // KN + digits file numbers that are explicitly tagged by module/workflow.
            $builder->where(function ($moduleQuery) use ($normalizedModuleExpr, $normalizedWorkflowExpr, $normalizedFileNoExpr) {
                $moduleQuery->whereRaw("{$normalizedFileNoExpr} LIKE 'KN%'")
                    ->whereRaw("LEN({$normalizedFileNoExpr}) > 2")
                    ->whereRaw("PATINDEX('%[^0-9]%', SUBSTRING({$normalizedFileNoExpr}, 3, 8000)) = 0")
                    ->where(function ($typedQuery) use ($normalizedModuleExpr, $normalizedWorkflowExpr) {
                        $typedQuery->whereRaw("{$normalizedWorkflowExpr} = ?", [FileTracker::WORKFLOW_KANGIS_NEW])
                            ->orWhereRaw("{$normalizedModuleExpr} = ?", ['new_kangis']);
                    });
            });
            return;
        }

        $builder->whereRaw("{$normalizedModuleExpr} = ?", [$moduleFilter]);
    }

    /**
     * The commissioned-but-never-tracked files an export should include, under the
     * export's own date/module filters. Null when the export is scoped to a module
     * (mls_file_no is Land-only, so a module export has no DIIT rows).
     */
    protected function diitExportQuery(?string $dateFrom, ?string $dateTo, string $module, ?string $requestType = null): ?\Illuminate\Database\Query\Builder
    {
        // A commissioning counts as In-transit (nobody asked for the file), so it is
        // left out of a Submitted Request export only — the same rule the list applies.
        if ($module !== '' || $requestType === 'Submitted Request') {
            return null;
        }

        $query = app(FileCommissioningTrackingService::class)->untrackedQuery();
        $commissionedAt = $this->diitCommissionedAtExpr();

        if ($dateFrom) {
            $query->whereRaw("CAST({$commissionedAt} AS date) >= ?", [$dateFrom]);
        }
        if ($dateTo) {
            $query->whereRaw("CAST({$commissionedAt} AS date) <= ?", [$dateTo]);
        }

        return $query;
    }

    /**
     * The same rows as FileTracker instances, streamed one at a time so an export
     * of thousands of commissioned files stays flat in memory. Ordered ascending to
     * match the exports — oldest-first from the From date.
     */
    protected function diitExportTrackers(?string $dateFrom, ?string $dateTo, string $module, ?string $requestType = null): \Generator
    {
        $query = $this->diitExportQuery($dateFrom, $dateTo, $module, $requestType);

        if ($query === null) {
            return;
        }

        $service = app(FileCommissioningTrackingService::class);

        foreach ($query->orderByRaw($this->diitCommissionedAtExpr() . ' ASC')->cursor() as $row) {
            yield $service->syntheticTracker($service->hydrate($row));
        }
    }

    /**
     * SQL Server expression for the moment a file was commissioned — the stored
     * commissioning date/time, falling back to the register row's created_at.
     * Mirrors FileCommissioningTrackingService::commissionedAt() so the list sorts
     * on exactly the date the DIIT line shows.
     */
    protected function diitCommissionedAtExpr(): string
    {
        return "ISNULL(TRY_CONVERT(datetime2, CONVERT(varchar(10), mls_file_no.commissioning_date, 23)"
            . " + ' ' + LEFT(CONVERT(varchar(16), ISNULL(mls_file_no.commissioning_time, '00:00:00')), 8)),"
            . " mls_file_no.created_at)";
    }

    /**
     * The commissioning-register rows that should be listed as DIIT cards for this
     * request, or null when this view does not show them.
     *
     * DIIT rows only appear on the Land file log: mls_file_no holds Land/MLS file
     * numbers, so the KANGIS / New KANGIS / DG / DGIS views are untouched. They are
     * also skipped for the tabs they cannot belong to — Completed (a DIIT file has
     * never been logged back in), the officer tabs (no receiving officer), a
     * priority filter (no priority) and the Submitted Request tab (nobody asked for
     * the file). They are listed under In-transit.
     */
    protected function diitListQuery(Request $request, string $moduleFilter): ?\Illuminate\Database\Query\Builder
    {
        if ($moduleFilter !== '') {
            return null;
        }

        $status = (string) $request->input('status', '');
        if (!in_array($status, ['', 'all', 'not-completed'], true)) {
            return null;
        }

        $priority = (string) $request->input('priority', '');
        if ($priority !== '' && $priority !== 'all') {
            return null;
        }

        if (strtoupper(trim((string) $request->input('file_request_type', ''))) === 'SUBMITTED') {
            return null;
        }

        $query = app(FileCommissioningTrackingService::class)->untrackedQuery();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_file_number', 'LIKE', "%{$search}%")
                    ->orWhere('file_name', 'LIKE', "%{$search}%")
                    ->orWhere('tracking_id', 'LIKE', "%{$search}%");
            });
        }

        // The list's date range filters on when tracking started, which for a DIIT
        // row is the commissioning date.
        $commissionedAt = $this->diitCommissionedAtExpr();
        if ($request->filled('date_from')) {
            $query->whereRaw("CAST({$commissionedAt} AS date) >= ?", [$request->get('date_from')]);
        }
        if ($request->filled('date_to')) {
            $query->whereRaw("CAST({$commissionedAt} AS date) <= ?", [$request->get('date_to')]);
        }

        return $query;
    }

    /**
     * Page through the two sources as one ordered list.
     *
     * Real trackers and DIIT rows interleave by date, so they cannot be paged
     * one after the other. Union just the identifiers + the sort key (cheap), let
     * SQL Server order and slice that, then load only the page's rows from each
     * source and rebuild the collection in the union's order. Both kinds come back
     * as FileTracker instances, so decorateTrackerForResponse() and the front end
     * stay unaware of the difference.
     */
    protected function paginateTrackersWithDiit(
        $trackerQuery,
        \Illuminate\Database\Query\Builder $diitQuery,
        string $sortBy,
        string $sortOrder,
        int $skip,
        int $take
    ) {
        $commissioningService = app(FileCommissioningTrackingService::class);

        // Only columns both sources can express are sortable across the union.
        $sortable = ['created_at', 'date_created', 'file_number', 'priority', 'status'];
        $sortBy = in_array($sortBy, $sortable, true) ? $sortBy : 'created_at';

        $commissionedAt = $this->diitCommissionedAtExpr();
        $diitSortExpr = match ($sortBy) {
            'file_number' => 'mls_file_no.full_file_number',
            'priority'    => "'" . FileTracker::PRIORITY_MEDIUM . "'",
            'status'      => "'" . FileTracker::STATUS_ACTIVE . "'",
            default       => $commissionedAt,
        };

        $trackerKeys = (clone $trackerQuery)->toBase()->select([
            DB::raw("'T' as src"),
            'file_tracker.id as row_id',
            DB::raw("file_tracker.{$sortBy} as sort_key"),
        ]);

        $diitKeys = (clone $diitQuery)->select([
            DB::raw("'D' as src"),
            'mls_file_no.id as row_id',
            DB::raw("{$diitSortExpr} as sort_key"),
        ]);

        $keys = DB::connection('sqlsrv')->query()
            ->fromSub($trackerKeys->unionAll($diitKeys), 'u')
            ->orderBy('sort_key', $sortOrder)
            ->orderBy('row_id', $sortOrder)
            ->skip($skip)
            ->take($take)
            ->get();

        $trackerIds = $keys->where('src', 'T')->pluck('row_id')->all();
        $diitIds    = $keys->where('src', 'D')->pluck('row_id')->all();

        $trackers = empty($trackerIds)
            ? collect()
            : FileTracker::whereIn('id', $trackerIds)->get()->keyBy('id');

        $diitRows = empty($diitIds)
            ? collect()
            : $commissioningService->baseQuery()->whereIn('id', $diitIds)->get()->keyBy('id');

        return $keys->map(function ($key) use ($trackers, $diitRows, $commissioningService) {
            if ($key->src === 'T') {
                return $trackers->get($key->row_id);
            }

            $row = $diitRows->get($key->row_id);

            return $row ? $commissioningService->syntheticTracker($commissioningService->hydrate($row)) : null;
        })->filter()->values();
    }

    /**
     * Whether this new tracker is the log-out answering a Quick Search request: the
     * file has a request the SCB marked FOUND, and no tracker has been created for it
     * since that response. The front desk may already have marked the request acted
     * (the front-desk-acted endpoint) before logging the file, so that is not a
     * condition. A SYSTEM tracker (file-range bookkeeping) is never a request.
     */
    protected function answersQuickSearchRequest(FileTracker $tracker): bool
    {
        $fileNumber = strtoupper(trim((string) $tracker->file_number));
        if ($fileNumber === '' || strtoupper((string) $tracker->file_request_type) === 'SYSTEM') {
            return false;
        }

        return FileSearchRequest::where('status', FileSearchRequest::STATUS_FOUND)
            ->whereNotNull('responded_at')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$fileNumber])
            ->whereNotExists(function ($q) use ($tracker) {
                $q->selectRaw('1')
                    ->from('file_tracker')
                    ->whereRaw('UPPER(LTRIM(RTRIM(file_tracker.file_number))) = UPPER(LTRIM(RTRIM(file_search_requests.file_number)))')
                    ->whereColumn('file_tracker.created_at', '>=', 'file_search_requests.responded_at')
                    ->when($tracker->id, fn ($w) => $w->where('file_tracker.id', '<>', $tracker->id));
            })
            ->exists();
    }

    /**
     * The In-transit side of the request-type split: not a submitted request, and —
     * while active — not a file that also has an active submitted request. Such a file
     * counts as a Submitted Request only, so the two tabs never both list it.
     */
    protected function applyInTransitScope($builder): void
    {
        $builder->whereRaw('NOT ' . self::SUBMITTED_REQUEST_TYPE_SQL)
            ->where(function ($q) {
                $q->where('file_tracker.status', FileTracker::STATUS_COMPLETED)
                    ->orWhereNotExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('file_tracker as ft_sub')
                            ->whereColumn('ft_sub.file_number', 'file_tracker.file_number')
                            ->where('ft_sub.status', '!=', FileTracker::STATUS_COMPLETED)
                            ->whereRaw("UPPER(ISNULL(ft_sub.file_request_type, '')) = 'SUBMITTED'");
                    });
            });
    }

    /**
     * Narrow an export to the Request Type picked in the Export menu, using the same
     * split as the list's In-transit / Submitted Request tabs. Returns the label of the
     * type applied, or null for All.
     */
    protected function applyExportRequestType($query, string $type): ?string
    {
        $type = strtoupper(trim($type));

        if ($type === 'SUBMITTED') {
            $query->whereRaw(self::SUBMITTED_REQUEST_TYPE_SQL);
            return 'Submitted Request';
        }

        if (in_array($type, ['MANUAL', 'IN_TRANSIT', 'IN-TRANSIT'], true)) {
            $this->applyInTransitScope($query);
            return 'In-transit';
        }

        return null;
    }

    public function exportCsv(Request $request)
    {
        ini_set('memory_limit', '256M');
        set_time_limit(300);

        $dateFrom = $request->get('date_from');
        $dateTo   = $request->get('date_to');
        $module   = strtolower(trim((string) $request->get('module', '')));

        $query = FileTracker::select([
            'id', 'file_number', 'file_title', 'priority', 'status',
            'origin_office_name', 'current_office_name', 'receiving_officer_name',
            'movement_log', 'created_at', 'workflow_config', 'department',
        ]);

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        $this->applyTrackerModuleScope($query, $module);
        $requestType = $this->applyExportRequestType($query, (string) $request->get('file_request_type', ''));

        // cursor() streams one row at a time — memory stays constant regardless of record count.
        // Ascending: the report reads forward from the From date the user picked, so row 1
        // is the oldest file in the period rather than the newest.
        $cursor = $query->orderBy('created_at')->cursor();
        $diitCursor = $this->diitExportTrackers($dateFrom, $dateTo, $module, $requestType);

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="file-trackers-' . ($dateFrom ?? 'all') . '-to-' . ($dateTo ?? 'all') . '.csv"',
        ];

        $callback = function () use ($cursor, $diitCursor) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

            // The report is printed in full caps, headers and values alike.
            $upper = fn (array $cells) => array_map(fn ($v) => mb_strtoupper((string) $v, 'UTF-8'), $cells);

            fputcsv($handle, $upper([
                '#', 'File Number', 'File Title', 'Priority',
                'Origin Registry', 'Destination', 'Receiving Officer',
                'Log In', 'Log Out', 'Status',
            ]));

            $row = 0;
            // Real trackers first, then the commissioned files whose only line is the
            // default commissioning one (DIIT) — the same two sources the File Log
            // Table lists, so an export is not missing half the files on screen.
            foreach ($this->chain($cursor, $diitCursor) as $tracker) {
                $row++;
                $log      = is_array($tracker->movement_log) ? $tracker->movement_log : json_decode($tracker->movement_log ?? '[]', true);
                $first    = $log[0] ?? [];

                $logInDate  = $first['log_in_date']  ?? null;
                $logInTime  = $first['log_in_time']  ?? null;
                $logOutDate = $first['log_out_date'] ?? null;
                $logOutTime = $first['log_out_time'] ?? null;

                $logIn  = $logInDate  ? $logInDate  . ($logInTime  ? ' ' . substr($logInTime,  0, 5) : '') : '';
                $logOut = $logOutDate ? $logOutDate . ($logOutTime ? ' ' . substr($logOutTime, 0, 5) : '') : '';

                $status = strtolower($tracker->status ?? '');
                if ($status === 'canceled') {
                    $label = 'Canceled';
                } elseif (!empty($logOutDate)) {
                    $label = 'Log-Out';
                } else {
                    $label = 'In-Transit';
                }

                fputcsv($handle, $upper([
                    $row,
                    $tracker->file_number        ?? '',
                    $tracker->file_title         ?? '',
                    strtoupper($tracker->priority ?? 'LOW'),
                    $tracker->origin_office_name  ?? '',
                    $this->exportDestination($tracker) ?? '',
                    $tracker->receiving_officer_name ?? '',
                    $logIn,
                    $logOut,
                    $label,
                ]));
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        set_time_limit(300);

        $dateFrom = $request->get('date_from');
        $dateTo   = $request->get('date_to');
        $module   = strtolower(trim((string) $request->get('module', '')));

        $query = FileTracker::select([
            'id', 'file_number', 'file_title', 'priority', 'status',
            'origin_office_name', 'current_office_name', 'receiving_officer_name',
            'movement_log', 'created_at', 'workflow_config', 'department',
        ]);

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        $this->applyTrackerModuleScope($query, $module);
        $requestType = $this->applyExportRequestType($query, (string) $request->get('file_request_type', ''));

        $diitQuery   = $this->diitExportQuery($dateFrom, $dateTo, $module, $requestType);
        $totalCount  = (clone $query)->count() + ($diitQuery ? $diitQuery->count() : 0);
        $generated   = now()->format('d M Y H:i');
        $moduleLabel = $module ? strtoupper($module) : 'ALL MODULES';
        $periodLabel = ($dateFrom ?? 'All dates') . ' — ' . ($dateTo ?? 'present')
            . ($requestType ? ' &nbsp;|&nbsp; Request Type: ' . $requestType : '');

        // Stream the report as HTML directly — the browser renders it progressively
        // and the user prints to PDF via the browser's native print dialog.
        // This avoids DomPDF's in-memory DOM which exhausts RAM on large datasets.
        // Ascending, like exportCsv() — the report starts at the From date, not the latest file.
        $cursor = $query->orderBy('created_at')->cursor();
        $diitCursor = $this->diitExportTrackers($dateFrom, $dateTo, $module, $requestType);

        return response()->stream(
            function () use ($cursor, $diitCursor, $totalCount, $generated, $moduleLabel, $periodLabel) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                echo $this->exportPdfHeader($moduleLabel, $periodLabel, $totalCount, $generated);
                flush();

                $row = 0;
                // Real trackers, then commissioned files carrying only the default
                // commissioning line — see exportCsv() for the rationale.
                foreach ($this->chain($cursor, $diitCursor) as $tracker) {
                    $row++;
                    echo $this->exportPdfRow($row, $tracker);
                    if ($row % 150 === 0) {
                        flush();
                    }
                }

                echo $this->exportPdfFooter($generated, $row);
                flush();
            },
            200,
            [
                'Content-Type'      => 'text/html; charset=UTF-8',
                'X-Accel-Buffering' => 'no',
                'Cache-Control'     => 'no-cache, no-store, must-revalidate',
            ]
        );
    }

    /**
     * Destination column for the exports. A KANGIS approval-workflow file parks in the
     * "Department Queue" placeholder office after DG approval; its real destination
     * lives on workflow_config, the same place the File Log Table reads it from.
     */
    private function exportDestination($tracker): ?string
    {
        $queue = 'Department Queue';
        $office = $tracker->current_office_name;

        if (trim((string) $office) !== $queue) {
            return $office;
        }

        $config = (array) ($tracker->workflow_config ?? []);
        foreach ([$config['original_destination_office_name'] ?? null, $config['destination_office_name'] ?? null, $tracker->department ?? null] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && $candidate !== $queue) {
                return $candidate;
            }
        }

        return $office;
    }

    private function exportPdfHeader(string $moduleLabel, string $periodLabel, int $totalCount, string $generated): string
    {
        $total = number_format($totalCount);
        $logo1 = asset('assets/logo/ministry1.jpg');
        // The KANGIS views (KANGIS, New KANGIS and the DG / DGIS approval views into
        // them) carry the KANGIS logo, in the header and as the watermark, in place
        // of the ministry seal the other departments use.
        $isKangis = in_array($moduleLabel, ['KANGIS', 'NEW_KANGIS', 'DG', 'DGIS'], true);
        $logo2 = $isKangis
            ? asset('assets/logo/kangis.jpg')
            : asset('assets/logo/ministry2.jpeg');
        $watermark = $logo2;
        // No module is the main /create-file-tracker view, which is the Land file log.
        $dept  = ($moduleLabel !== 'ALL MODULES')
            ? strtoupper($moduleLabel) . ' Department'
            : 'Land Department';

        // KANGIS is its own agency: its reports carry its name alone, in place of the
        // state / ministry / department lines the other departments print.
        $titleBlock = $isKangis
            ? '<div class="h1">Kano State Geographic Information Service</div>'
            : '<div class="h1">Kano State Government</div>'
                . '<div class="h2">Ministry of Land and Physical Planning</div>'
                . '<div class="h3">' . htmlspecialchars($dept, ENT_QUOTES, 'UTF-8') . '</div>';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>File Tracking Export</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Arial,Helvetica,sans-serif;font-size:9pt;color:#1e293b;background:#fff}
  .toolbar{background:#1e293b;color:#fff;padding:10px 20px;display:flex;align-items:center;
           justify-content:space-between;position:sticky;top:0;z-index:100;gap:12px}
  .toolbar .status{font-size:12px;flex:1}
  .toolbar .progress{height:4px;background:#334155;flex:1;border-radius:2px;overflow:hidden}
  .toolbar .progress-bar{height:100%;background:#22c55e;width:0%;transition:width .3s}
  .toolbar button{background:#166534;color:#fff;border:none;padding:7px 20px;border-radius:4px;
                  cursor:pointer;font-size:13px;font-weight:bold;white-space:nowrap}
  .toolbar button:hover{background:#15803d}
  .report-wrap{padding:14px 18px}
  .hdr-table{width:100%;border-collapse:collapse;margin-bottom:10px}
  .hdr-table td{padding:0;vertical-align:middle}
  .hdr-logo{width:88px;text-align:center}
  .hdr-logo img{width:76px;height:76px;object-fit:contain}
  .hdr-text{text-align:center}
  .hdr-text .h1{font-size:14pt;font-weight:bold;text-transform:uppercase;color:#000}
  .hdr-text .h2{font-size:11pt;font-weight:bold;margin-top:3px;color:#000}
  .hdr-text .h3{font-size:9.5pt;font-weight:bold;margin-top:2px;color:#000}
  .hdr-text .meta{font-size:8pt;color:#64748b;margin-top:5px}
  hr.div{border:none;border-top:2px solid #1a1a1a;margin:8px 0 10px}
  table.data{width:100%;border-collapse:collapse;text-transform:uppercase}
  table.data thead tr{background:#166534;color:#fff}
  table.data thead th{padding:6px 7px;text-align:left;font-size:8pt;font-weight:700;white-space:nowrap}
  table.data tbody tr:nth-child(even){background:#f0fdf4}
  table.data tbody tr:nth-child(odd){background:#fff}
  table.data tbody td{padding:4px 6px;border-bottom:1px solid #e2e8f0;font-size:8pt;vertical-align:top}
  .footer-row{margin-top:12px;border-top:1px solid #cbd5e1;padding-top:5px;
              display:flex;justify-content:space-between;font-size:7.5pt;color:#64748b}
  @media print{
    .toolbar{display:none!important}
    .report-wrap{padding:0}
    table.data thead{display:table-header-group}
    table.data tbody tr{page-break-inside:avoid}
  }
  /* Fixed, so the browser repeats it on every printed page. It sits above the
     table at low opacity because the striped rows would otherwise cover it. */
  .watermark{position:fixed;top:50%;left:50%;width:360px;transform:translate(-50%,-50%);
             opacity:.07;z-index:50;pointer-events:none}
  @page{size:A4 landscape;margin:10mm}
</style>
</head>
<body>
<img class="watermark" src="{$watermark}" alt="">
<div class="toolbar">
  <span class="status" id="load-status">Loading &mdash; {$total} records&hellip;</span>
  <div class="progress"><div class="progress-bar" id="pbar"></div></div>
  <button id="print-btn" onclick="window.print()" style="display:none">&#128438;&nbsp;Print / Save as PDF</button>
</div>
<div class="report-wrap">
  <table class="hdr-table">
    <tr>
      <td class="hdr-logo"><img src="{$logo1}" alt="Coat of Arms"></td>
      <td class="hdr-text">
        {$titleBlock}
        <div class="meta">Generated: {$generated} &nbsp;|&nbsp; Period: {$periodLabel} &nbsp;|&nbsp; Total Records: {$total}</div>
      </td>
      <td class="hdr-logo"><img src="{$logo2}" alt="Ministry Seal"></td>
    </tr>
  </table>
  <hr class="div">
  <table class="data">
    <thead>
      <tr>
        <th>#</th><th>File Number</th><th>File Title</th>
        <th>Origin Registry</th><th>Destination</th><th>Receiving Officer</th>
        <th>Log In</th><th>Log Out</th>
      </tr>
    </thead>
    <tbody>
HTML;
    }

    private function exportPdfRow(int $row, $tracker): string
    {
        $priority   = strtolower($tracker->priority ?? '');
        $badgeClass = $priority === 'high' ? 'badge-high' : ($priority === 'medium' ? 'badge-medium' : 'badge-low');

        $log      = is_array($tracker->movement_log) ? $tracker->movement_log : json_decode($tracker->movement_log ?? '[]', true);
        $firstLog = $log[0] ?? [];

        $logInDate  = $firstLog['log_in_date']  ?? null;
        $logInTime  = $firstLog['log_in_time']  ?? null;
        $logOutDate = $firstLog['log_out_date'] ?? null;
        $logOutTime = $firstLog['log_out_time'] ?? null;

        $logIn  = $logInDate  ? $logInDate  . ($logInTime  ? ' ' . substr($logInTime,  0, 5) : '') : '&mdash;';
        $logOut = $logOutDate ? $logOutDate . ($logOutTime ? ' ' . substr($logOutTime, 0, 5) : '') : '&mdash;';

        $trackerStatus = strtolower($tracker->status ?? '');
        if ($trackerStatus === 'canceled') {
            $logStatus = 'Canceled'; $statusBg = '#fee2e2'; $statusColor = '#b91c1c';
        } elseif (!empty($logOutDate)) {
            $logStatus = 'Log-Out';  $statusBg = '#f1f5f9'; $statusColor = '#475569';
        } else {
            $logStatus = 'In-Transit'; $statusBg = '#fef9c3'; $statusColor = '#92400e';
        }

        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        return "<tr>"
            . "<td>{$row}</td>"
            . "<td>{$e($tracker->file_number ?? '—')}</td>"
            . "<td>{$e($tracker->file_title ?? '—')}</td>"
            . "<td>{$e($tracker->origin_office_name ?? '—')}</td>"
            . "<td>{$e($this->exportDestination($tracker) ?? '—')}</td>"
            . "<td>{$e($tracker->receiving_officer_name ?? '—')}</td>"
            . "<td>{$logIn}</td>"
            . "<td>{$logOut}</td>"
            . "</tr>\n";
    }

    private function exportPdfFooter(string $generated, int $loadedCount): string
    {
        $loaded = number_format($loadedCount);
        return <<<HTML
    </tbody>
  </table>
  <div class="footer-row">
    <span>Kano State Ministry of Land &amp; Physical Planning &mdash; File Tracker System</span>
    <span>Printed: {$generated}</span>
  </div>
</div>
<script>
  (function () {
    var btn    = document.getElementById('print-btn');
    var status = document.getElementById('load-status');
    var pbar   = document.getElementById('pbar');
    status.textContent = '{$loaded} records loaded — ready to print';
    if (pbar) pbar.style.width = '100%';
    if (btn)  btn.style.display = 'inline-block';
    setTimeout(function () { window.print(); }, 700);
  })();
</script>
</body>
</html>
HTML;
    }

    protected function findLinkedFileIndexing(?string $fileNumber): ?FileIndexing
    {
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '') {
            return null;
        }

        // Temporary file number support: "RES-2026-1(T)" should match
        // the base "RES-2026-1" row in file_indexings.
        $stripped = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $fileNumber);
        $candidates = [$fileNumber];
        if ($stripped !== null && $stripped !== '' && $stripped !== $fileNumber) {
            $candidates[] = $stripped;
        }

        $variants = [];
        foreach ($candidates as $candidate) {
            $variants[] = $candidate;
            $variants[] = strtoupper($candidate);
            $variants[] = strtolower($candidate);
        }
        $variants = array_values(array_unique(array_filter($variants)));

        return FileIndexing::on('sqlsrv')
            ->where(function ($query) use ($variants) {
                $query->whereIn('file_number', $variants)
                    ->orWhereIn('new_kangis_file_no', $variants)
                    ->orWhereIn('kangis_file_no', $variants)
                    ->orWhereIn('mls_file_no', $variants)
                    ->orWhereIn('st_file_no', $variants);
            })
            ->orderByDesc('id')
            ->first();
    }

    protected function shouldUseKangisNewWorkflow(?string $fileNumber, ?string $workflowType, ?FileIndexing $linkedIndexing): bool
    {
        if (in_array($workflowType, [FileTracker::WORKFLOW_KANGIS_3STEP, FileTracker::WORKFLOW_KANGIS_APPROVAL], true)) {
            return false;
        }

        if ($this->isValidNewKangisFileNumber($fileNumber)) {
            return true;
        }

        if (!$linkedIndexing) {
            return false;
        }

        if ($this->isValidNewKangisFileNumber($linkedIndexing->new_kangis_file_no ?? null)) {
            return true;
        }

        return $this->isValidNewKangisFileNumber($linkedIndexing->file_number ?? null);
    }

    protected function buildTrackerModuleMeta(FileIndexing $linkedIndexing, ?string $fallbackFileNumber = null): string
    {
        $resolvedNewKangisFileNo = null;
        foreach ([$linkedIndexing->new_kangis_file_no ?? null, $fallbackFileNumber, $linkedIndexing->file_number ?? null] as $candidate) {
            if ($this->isValidNewKangisFileNumber($candidate)) {
                $resolvedNewKangisFileNo = strtoupper(trim((string) $candidate));
                break;
            }
        }

        return json_encode([
            'file_indexing_id' => $linkedIndexing->id,
            'mls_file_no' => $linkedIndexing->mls_file_no ?: null,
            'kangis_file_no' => $linkedIndexing->kangis_file_no ?: null,
            'new_kangis_file_no' => $resolvedNewKangisFileNo,
        ]);
    }

    protected function isValidNewKangisFileNumber(?string $value): bool
    {
        $normalized = strtoupper(trim((string) $value));
        return $normalized !== '' && (bool) preg_match('/^KN\d+$/', $normalized);
    }

    /**
     * Legacy KANGIS file numbers are KN-prefixed but are NOT the pure "KN + digits"
     * New KANGIS pattern (e.g. "KNML 6794"). Such files belong to the kangis module
     * even when the creating flow forgot to pass module=kangis, so we use this to
     * backfill the stored module and keep them visible in the KANGIS view.
     */
    protected function isLegacyKangisFileNumber(?string $value): bool
    {
        $normalized = strtoupper(trim((string) $value));

        return $normalized !== ''
            && str_starts_with($normalized, 'KN')
            && strlen($normalized) > 2
            && !$this->isValidNewKangisFileNumber($normalized);
    }

    /**
     * "Your File Request has been logged to your office" -- to the requester.
     *
     * Sent when the file is actually logged OUT to them, which is a different
     * moment from the request being raised, and the one that starts the return
     * clock. Everything the message quotes is tracker-level and was written a few
     * lines earlier: the receiving office, the purpose, and the timeline.
     *
     * The recipient is the File Requester named on the tracker, whose user id the
     * form supplies. When that names somebody outside the system -- the tracker
     * allows a free-text officer -- there is no number to use and the dispatcher
     * records a skipped row saying so.
     *
     * THE CLOCK: [DateTime] is rendered on the Lagos clock by the dispatcher, not
     * on config('app.timezone'), which is UTC here. A message telling an officer
     * their file was logged out an hour before it was is worse than none.
     */
    protected function sendFileLoggedSms(FileTracker $tracker): void
    {
        try {
            $key = \App\Models\SmsSetting::KEY_FILE_REQUEST_LOGGED;
            $dispatcher = app(\App\Services\Sms\KlaesSmsDispatcher::class);

            if (!$dispatcher->enabled($key)) {
                return;
            }

            $phone = null;

            if (!empty($tracker->receiving_officer_id)) {
                $phone = DB::connection('sqlsrv')->table('users')
                    ->where('id', $tracker->receiving_officer_id)
                    ->value('phone_number');
            }

            $dispatcher->queue($key, $phone, [
                'FileNo(s)' => $tracker->file_number,
                'Requester Office' => $tracker->receiving_office_name
                    ?: ($tracker->destination ?: $tracker->department),
                'DateTime' => $this->smsMoment($tracker->date_requested ?? $tracker->date_created),
                'Purpose' => $tracker->request_purpose_name ?: 'official use',
                'Timeline' => $this->smsTimeline($tracker),
            ], [
                'dedupe_key' => $key . ':' . $tracker->id,
                'file_number' => $tracker->file_number,
                'subject_type' => 'file_tracker',
                'subject_id' => $tracker->id,
                'event_at' => $tracker->date_requested ?? $tracker->date_created,
                'phone_confirmed' => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('CreateFileTrackerController: file logged SMS not queued', [
                'tracker_id' => $tracker->id ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /** A moment as the office clock shows it, for an SMS body. */
    protected function smsMoment($moment): string
    {
        $timezone = config('klaes_sms.timezone', 'Africa/Lagos');
        $format = config('klaes_sms.datetime_format', 'd/m/Y h:i A');

        try {
            return \Carbon\Carbon::parse($moment ?: now())
                ->setTimezone($timezone)
                ->format($format);
        } catch (\Throwable $e) {
            return \Carbon\Carbon::now($timezone)->format($format);
        }
    }

    /**
     * How long the requester has the file for, in words.
     *
     * timeline_days is the explicit "Timeline (Days)" field; a tracker created
     * from a purpose instead carries only a deadline, so that is counted back
     * into days rather than left blank.
     */
    protected function smsTimeline(FileTracker $tracker): string
    {
        $days = $tracker->timeline_days;

        if (!$days && !empty($tracker->deadline)) {
            try {
                $days = \Carbon\Carbon::parse($tracker->date_created ?: now())
                    ->startOfDay()
                    ->diffInDays(\Carbon\Carbon::parse($tracker->deadline)->startOfDay());
            } catch (\Throwable $e) {
                $days = null;
            }
        }

        if (!$days) {
            return 'the agreed period';
        }

        return $days . ' ' . ((int) $days === 1 ? 'day' : 'days');
    }

    /**
     * "Your File Request has been received" -- to the officer who raised it.
     *
     * Goes to a member of STAFF, not the public: the wording says "your request"
     * and "let us know if this was not placed by you", which only makes sense to
     * the person who pressed the button. users.phone_number is therefore the
     * source, and it is the one phone column in this database with no
     * shared-number problem.
     *
     * One request is one file number -- file_search_requests.file_number is a
     * single string, and Quick Search raises them one at a time -- so the
     * [FileNo(s)] token renders one number. The plural is in the Ministry's
     * wording, not in the data.
     */
    protected function sendFileRequestReceivedSms(FileSearchRequest $fr, $user): void
    {
        try {
            $key = \App\Models\SmsSetting::KEY_FILE_REQUEST_RECEIVED;
            $dispatcher = app(\App\Services\Sms\KlaesSmsDispatcher::class);

            if (!$dispatcher->enabled($key)) {
                return;
            }

            $phone = $user->phone_number ?? null;

            $dispatcher->queue($key, $phone, [
                'FileNo(s)' => $fr->file_number,
            ], [
                // Keyed on the request, not the file: a second request for the
                // same file is a real second request and deserves its own
                // acknowledgement.
                'dedupe_key' => $key . ':' . $fr->id,
                'file_number' => $fr->file_number,
                'subject_type' => 'file_search_requests',
                'subject_id' => $fr->id,
                // Staff numbers are personal, so the shared-number guard has
                // nothing to protect against here.
                'phone_confirmed' => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('CreateFileTrackerController: file request SMS not queued', [
                'request_id' => $fr->id ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function sendNewKangisTrackerSms(FileTracker $tracker, ?FileIndexing $linkedIndexing, ?string $officeName): array
    {
        $phone = $linkedIndexing?->phone;
        if (!$phone) {
            return ['sent' => false, 'message' => 'Successful, but no phone number was found on the linked indexing record.'];
        }

        try {
            $message = sprintf(
                'Dear Applicant, your KANGIS file %s has moved to %s. - KANGIS Registry.',
                $tracker->file_number,
                $officeName ?: ($tracker->current_office_name ?: 'the next office')
            );

            $sent = app(EBulkSmsService::class)->send($phone, $message);
            $provider = 'ebulksms';

            // Fallback: if the primary provider failed (no API key, network error,
            // or non-success response), try BulkSMS Nigeria.
            if (!$sent) {
                Log::info('CreateFileTrackerController: EBulkSMS failed, attempting BulkSMS Nigeria fallback', [
                    'tracker_id'  => $tracker->id,
                    'file_number' => $tracker->file_number,
                ]);
                $sent = app(BulkSmsNigeriaService::class)->send($phone, $message);
                $provider = $sent ? 'bulksmsnigeria' : 'none';
            }

            return [
                'sent' => $sent,
                'provider' => $provider,
                'message' => $sent
                    ? 'SMS sent successfully via ' . $provider . '.'
                    : 'Successful, but SMS did not send via either provider.',
            ];
        } catch (\Throwable $exception) {
            Log::warning('CreateFileTrackerController: SMS failed during tracker create', [
                'tracker_id' => $tracker->id,
                'file_number' => $tracker->file_number,
                'error' => $exception->getMessage(),
            ]);

            return ['sent' => false, 'message' => 'Successful, but SMS failed to send.'];
        }
    }

    /**
     * Search for a tracker by tracking ID or file number, including archive lookup.
     */
    public function search(Request $request)
    {
        $query = trim((string) $request->get('query', ''));

        if ($query === '') {
            return response()->json([
                'success' => false,
                'message' => 'Please provide a tracking ID or file number to search.'
            ], 422);
        }

        $needle = mb_strtoupper($query);

        $tracker = FileTracker::where(function ($query) use ($needle) {
            $query->whereRaw('UPPER(tracking_id) = ?', [$needle])
                ->orWhereRaw('UPPER(tracking_id) LIKE ?', [$needle . '%'])
                ->orWhereRaw('UPPER(file_number) = ?', [$needle])
                ->orWhereRaw('UPPER(file_title) = ?', [$needle]);
        })
            ->orderByDesc('id')
            ->first();

        if ($tracker) {
            $formatted = $this->decorateTrackerForResponse($tracker);

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'tracked',
                    'tracker' => $formatted,
                ],
                'message' => 'File tracker located.'
            ]);
        }

        $archiveRecord = DB::connection('sqlsrv')
            ->table('fileNumber')
            ->select([
                'id',
                'tracking_id',
                'st_file_no',
                'FileName',
                'location',
                'kangisFileNo',
                'mlsfNo',
                'NewKANGISFileNo',
                'temp_fileno',
                'application_id',
                'type',
                'is_decommissioned',
                'decommissioning_reason',
                'created_at',
            ])
            ->where(function ($q) use ($needle) {
                $q->whereRaw('UPPER(tracking_id) = ?', [$needle])
                    ->orWhereRaw('UPPER(tracking_id) LIKE ?', [$needle . '%'])
                    ->orWhereRaw('UPPER(st_file_no) = ?', [$needle])
                    ->orWhereRaw('UPPER(kangisFileNo) = ?', [$needle])
                    ->orWhereRaw('UPPER(mlsfNo) = ?', [$needle])
                    ->orWhereRaw('UPPER(NewKANGISFileNo) = ?', [$needle])
                    ->orWhereRaw('UPPER(temp_fileno) = ?', [$needle])
                    ->orWhereRaw('UPPER(FileName) = ?', [$needle]);
            })
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            // Exclude decommissioned/superseded files so the archive fallback does not
            // surface a file that has been replaced by a land transaction.
            ->where(function ($q) {
                $q->whereNull('is_decommissioned')->orWhere('is_decommissioned', 0);
            })
            ->orderByDesc('id')
            ->first();

        if ($archiveRecord) {
            $archiveFileNumber = $archiveRecord->st_file_no
                ?? $archiveRecord->kangisFileNo
                ?? $archiveRecord->mlsfNo
                ?? $archiveRecord->NewKANGISFileNo
                ?? $archiveRecord->temp_fileno;

            $archiveFileIndexing = null;
            if ($archiveFileNumber) {
                $archiveFileIndexing = FileIndexing::query()
                    ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [mb_strtoupper(trim((string) $archiveFileNumber))])
                    ->withCount('pagetypings')
                    ->first();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'archive',
                    'record' => [
                        'id' => $archiveRecord->id,
                        'file_number' => $archiveFileNumber,
                        'tracking_id' => $archiveRecord->tracking_id,
                        'file_name' => $this->getFileIndexingTitle($archiveFileNumber) ?? $archiveRecord->FileName,
                        'location' => $archiveRecord->location,
                        'application_id' => $archiveRecord->application_id,
                        'type' => $archiveRecord->type,
                        'is_decommissioned' => (bool) $archiveRecord->is_decommissioned,
                        'decommissioning_reason' => $archiveRecord->decommissioning_reason,
                        'created_at' => $archiveRecord->created_at,
                        'num_pages' => $archiveFileIndexing?->pagetypings_count,
                        'in_digital_archive' => $archiveFileIndexing ? true : false,
                        'rack_shelf_location' => $this->getRackShelfLocation($archiveFileNumber),
                    ],
                ],
                'message' => 'File located in archive records (fileNumber).'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No tracker or archive entry found for the supplied reference.'
        ], 404);
    }

    /**
     * Quick Search & File Location — standalone page.
     */
    public function quickSearch()
    {
        $PageTitle = 'Quick Search & File Location';
        $PageDescription = 'Instantly locate a file and its current status.';

        // Cascade data for the Requester section (mirrors the Create File Tracker form):
        // Requester Office (Departments) → Requester Office → Requester Officer.
        $receivingOfficers = DB::connection('sqlsrv')
            ->table('users')
            ->where('is_active', 1)
            ->where('staff_type_category', 'MLPP')
            ->select('id', 'first_name', 'last_name', 'department_id', 'rank', 'profile', 'passport_photo_path')
            ->orderBy('first_name')
            ->get()
            ->map(function ($officer) {
                $officer->photo_url = \App\Support\UserPhoto::url($officer->profile, $officer->passport_photo_path);
                return $officer;
            });

        $offices = DB::connection('sqlsrv')
            ->table('offices')
            ->where('is_active', 1)
            ->select('id', 'office_code', 'office_name', 'department')
            ->orderBy('office_name')
            ->get();

        $departments = DB::connection('sqlsrv')
            ->table('offices')
            ->where('is_active', 1)
            ->whereNotNull('department')
            ->select('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        // Map department NAME → id (officers are linked to departments.id) so the
        // officer dropdown can be filtered by the chosen department.
        $departmentIds = DB::connection('sqlsrv')
            ->table('departments')
            ->select('id', 'name')
            ->get();

        // Origin registries (+ short codes) for the File Search request — mirrors the
        // Registry selector on Create File Tracker and the mobile File Search.
        $registries = DB::connection('sqlsrv')
            ->table('physical_registries')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['name', 'registry_code']);

        // Request Purpose + its default turnaround — captured here so it can be
        // backfilled straight into the Create File Tracker form on "Log File".
        $requestPurposes = RequestPurpose::active()->orderBy('name')->get(['id', 'name', 'turnaround_days']);

        $requesterDirectors = app(\App\Services\RequesterDirectorService::class)->optionsForDropdown();

        return view('create_file_tracker_page.quick_search', compact(
            'PageTitle', 'PageDescription', 'receivingOfficers', 'offices', 'departments', 'departmentIds', 'registries', 'requestPurposes', 'requesterDirectors'
        ));
    }

    /**
     * Resolve a file number to a definitive location + status + next action.
     * Always returns an outcome (never a 404) and writes the snapshot through
     * to the matching file_indexings row.
     */
    public function quickSearchResolve(Request $request, FileLocationResolver $resolver)
    {
        $fileNumber = trim((string) $request->get('query', $request->get('file_number', '')));

        if ($fileNumber === '') {
            return response()->json([
                'success' => false,
                'message' => 'Please provide a file number to search.',
            ], 422);
        }

        $result = $resolver->resolve($fileNumber);
        $resolver->persist($result);

        $data = $this->presentLocationResult($result, true);

        // Surface any existing active request for this file so the Quick Search card can
        // warn about a duplicate the moment the file is selected — rather than waiting for
        // the user to fill the requester form and click Send to discover it.
        $existing = FileSearchRequest::activeForFile($fileNumber)
            ->with('requester:id,first_name,last_name')
            ->orderByDesc('id')
            ->first();
        if ($existing) {
            $data['existing_request'] = $this->frDuplicatePayload($existing);
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => 'File location resolved.',
        ]);
    }

    /**
     * Manually set / override a file's location status from the Quick Search
     * interface. Persists onto the matching file_indexings row and marks it as
     * a manual override so the resolver stops re-deriving it.
     * POST /create-file-tracker/quick-search/update-status
     */
    public function updateLocationStatus(Request $request, FileLocationResolver $resolver)
    {
        $allowed = [
            FileLocationResolver::STATUS_IN_TRANSIT,
            FileLocationResolver::STATUS_IN_ARCHIVE,
            FileLocationResolver::STATUS_IN_POOL,
            FileLocationResolver::STATUS_NOT_FOUND,
            FileLocationResolver::STATUS_REFER,
            FileLocationResolver::STATUS_MISSING_FILE,
        ];

        $validator = Validator::make($request->all(), [
            'file_number'      => 'required|string|max:255',
            'status'           => ['required', 'string', Rule::in($allowed)],
            'current_location' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $result   = $resolver->resolve($request->file_number);
        $indexing = $result['indexing'];

        if (!$indexing) {
            return response()->json([
                'success' => false,
                'message' => 'No indexed file record found for this file number, so the status cannot be stored.',
            ], 404);
        }

        $indexing->forceFill([
            'tracking_status'        => $request->status,
            'current_location'       => $request->input('current_location') ?: $indexing->current_location,
            'location_status_manual' => now(),
        ])->save();

        // Return the freshly resolved (now manual) outcome for the UI.
        $fresh = $resolver->resolve($request->file_number);

        return response()->json([
            'success' => true,
            'data'    => $this->presentLocationResult($fresh, true),
            'message' => 'File location status updated.',
        ]);
    }

    /**
     * Re-direct a duplicate file (one registered in the duplicate_fileno table) to
     * the Director Land (Land Department) instead of raising a blind File Search
     * Request to the SCB. Creates a redirected Digital File Request addressed to the
     * active user whose rank / work station is "Director Land" and notifies them.
     * POST /create-file-tracker/quick-search/redirect-director-land
     */
    public function redirectToDirectorLand(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_number' => 'required|string|max:255',
            'file_title'  => 'nullable|string|max:255',
            // Which physical file the front desk picked, when the searched number
            // is registered in both file_indexings and duplicate_fileno. Sent as a
            // pair or not at all — an id without its table names nothing.
            'selected_record_id'     => 'nullable|integer|required_with:selected_record_source',
            'selected_record_source' => [
                'nullable',
                'required_with:selected_record_id',
                Rule::in([FileLocationResolver::SOURCE_INDEXED, FileLocationResolver::SOURCE_DUPLICATE]),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $fileNumber = trim((string) $request->input('file_number'));
        $fileTitle  = $request->input('file_title');
        $selectedId     = $request->input('selected_record_id');
        $selectedSource = $request->input('selected_record_source');

        // Recipient: the active user whose rank (or work station) is "Director Land".
        $director = User::where('is_active', 1)
            ->where(function ($q) {
                $q->where('rank', 'Director Land')->orWhere('work_station', 'Director Land');
            })
            ->first();

        if (! $director) {
            return response()->json([
                'success' => false,
                'message' => 'No active user with the rank “Director Land” was found. Set a Director Land in user management first.',
            ], 422);
        }

        $directorName = trim(($director->first_name ?? '') . ' ' . ($director->last_name ?? ''));
        $user = Auth::user();

        // Don't raise a second open re-direct for the SAME PHYSICAL FILE. When a
        // record was selected the guard keys on that record, not on the file
        // number: the whole point of the selection is that two different files
        // share one number, and a pending request for one of them must not block
        // a request for the other — the requester may already be holding it.
        $existing = \App\Models\DigitalFileRequest::where('file_no', $fileNumber)
            ->where('is_redirected', true)
            ->where('receiving_officer', $directorName)
            ->where('request_status', \App\Models\DigitalFileRequest::STATUS_PENDING)
            ->when($selectedId !== null, fn ($q) => $q
                ->where('selected_record_id', $selectedId)
                ->where('selected_record_source', $selectedSource))
            ->first();

        if ($existing) {
            return response()->json([
                'success'    => true,
                'request_no' => $existing->request_no,
                'message'    => "Already re-directed to Director Land ({$existing->request_no}).",
            ]);
        }

        $req = \App\Models\DigitalFileRequest::create([
            'request_no'              => \App\Models\DigitalFileRequest::generateRequestNo(),
            'request_type'            => \App\Models\DigitalFileRequest::TYPE_PHYSICAL,
            'file_no'                 => $fileNumber,
            'file_title'              => $fileTitle,
            'selected_record_id'      => $selectedId,
            'selected_record_source'  => $selectedSource,
            'requester_user_id'       => $user->id,
            'sending_officer'         => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'receiving_officer'       => $directorName,
            'destination_office_name' => 'Land Department',
            'current_file_location'   => 'Land Department',
            'is_redirected'           => true,
            'request_status'          => \App\Models\DigitalFileRequest::STATUS_PENDING,
            'remarks'                 => $selectedId !== null
                ? sprintf(
                    'Duplicate file — re-directed to Director Land for resolution. Selected record: %s #%d.',
                    $selectedSource === FileLocationResolver::SOURCE_DUPLICATE ? 'Duplicate File' : 'Indexed File',
                    $selectedId
                )
                : 'Duplicate file — re-directed to Director Land for resolution.',
            'requested_at'            => now(),
        ]);

        // Notify the Director Land in-app.
        $this->notificationService->create(
            $director->id,
            'digital_request',
            "Duplicate File Re-directed: {$req->request_no}",
            "{$req->sending_officer} re-directed duplicate file {$fileNumber} to you (Director Land) for resolution.",
            ['request_id' => $req->id, 'file_no' => $fileNumber],
            ['module' => 'digital_request']
        );

        return response()->json([
            'success'    => true,
            'request_no' => $req->request_no,
            'message'    => "Re-directed to Director Land ({$directorName}).",
        ]);
    }

    /**
     * Re-direct a file under DCIV investigation (registry = DCIV Registry, or
     * flagged dciv_status=1 via master_dciv_links) to the DCIV Director instead of
     * raising a File Search Request to the SCB. Mirrors redirectToDirectorLand().
     * POST /create-file-tracker/quick-search/redirect-dciv-director
     */
    public function redirectToDcivDirector(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_number' => 'required|string|max:255',
            'file_title'  => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $fileNumber = trim((string) $request->input('file_number'));
        $fileTitle  = $request->input('file_title');

        // Recipient: the active user whose rank (or work station) is "DCIV Director".
        $director = User::where('is_active', 1)
            ->where(function ($q) {
                $q->where('rank', 'DCIV Director')->orWhere('work_station', 'DCIV Director');
            })
            ->first();

        if (! $director) {
            return response()->json([
                'success' => false,
                'message' => 'No active user with the rank “DCIV Director” was found. Set a DCIV Director in user management first.',
            ], 422);
        }

        $directorName = trim(($director->first_name ?? '') . ' ' . ($director->last_name ?? ''));
        $user = Auth::user();

        // Don't raise a second open re-direct for the same file to the DCIV Director.
        $existing = \App\Models\DigitalFileRequest::where('file_no', $fileNumber)
            ->where('is_redirected', true)
            ->where('receiving_officer', $directorName)
            ->where('request_status', \App\Models\DigitalFileRequest::STATUS_PENDING)
            ->first();

        if ($existing) {
            return response()->json([
                'success'    => true,
                'request_no' => $existing->request_no,
                'message'    => "Already re-directed to DCIV Director ({$existing->request_no}).",
            ]);
        }

        $req = \App\Models\DigitalFileRequest::create([
            'request_no'              => \App\Models\DigitalFileRequest::generateRequestNo(),
            'request_type'            => \App\Models\DigitalFileRequest::TYPE_PHYSICAL,
            'file_no'                 => $fileNumber,
            'file_title'              => $fileTitle,
            'requester_user_id'       => $user->id,
            'sending_officer'         => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'receiving_officer'       => $directorName,
            'destination_office_name' => 'DCIV Registry',
            'current_file_location'   => 'DCIV Registry',
            'is_redirected'           => true,
            'request_status'          => \App\Models\DigitalFileRequest::STATUS_PENDING,
            'remarks'                 => 'File under DCIV investigation — re-directed to DCIV Director for resolution.',
            'requested_at'            => now(),
        ]);

        // Notify the DCIV Director in-app.
        $this->notificationService->create(
            $director->id,
            'digital_request',
            "File Re-directed: {$req->request_no}",
            "{$req->sending_officer} re-directed file {$fileNumber} to you (DCIV Director) for resolution.",
            ['request_id' => $req->id, 'file_no' => $fileNumber],
            ['module' => 'digital_request']
        );

        return response()->json([
            'success'    => true,
            'request_no' => $req->request_no,
            'message'    => "Re-directed to DCIV Director ({$directorName}).",
        ]);
    }

    /**
     * SCB Feedback queue: File Search Requests the current Front Desk user raised
     * that the SCB has responded to (Found / Not Found) but the Front Desk has NOT
     * acted on yet. Once the Front Desk logs/refers the file it leaves this queue
     * and lives only in the File Request Log.
     * GET /create-file-tracker/quick-search/scb-feedback
     */
    /**
     * Map a Quick Search module context (?url=kangis | sltr | cadastral | st | …)
     * to a registry-name keyword so the page can be scoped to that registry's files.
     * The FileSearchRequest.registry column stores the physical_registries name
     * ("KANGIS Registry", "SLTR Registry", "Registry 1 - Cadastral", "ST Registry", …),
     * so a LIKE on the keyword matches every registry belonging to that module.
     * Returns null for an unscoped (general) context.
     */
    protected function registryKeywordForModule($module): ?string
    {
        return match (strtolower(trim((string) $module))) {
            'kangis', 'new_kangis' => 'KANGIS',
            'sltr'                 => 'SLTR',
            'cadastral'            => 'Cadastral',
            'st'                   => 'ST Registry',
            'dciv'                 => 'DCIV',
            'survey'               => 'Survey',
            default                => null,
        };
    }

    public function scbFeedback(Request $request)
    {
        // Optionally scope the queue to a single registry (?url=kangis|sltr|cadastral|…).
        $registryKw = $this->registryKeywordForModule($request->get('url'));

        // Full SCB Feedback queue — every responded request awaiting front-desk action
        // (not scoped to the current user, so the shared front desk sees them all).
        $rows = FileSearchRequest::whereIn('status', [FileSearchRequest::STATUS_FOUND, FileSearchRequest::STATUS_NOT_FOUND])
            ->when($registryKw, fn ($q) => $q->where('registry', 'like', '%' . $registryKw . '%'))
            ->whereNull('front_desk_acted_at')
            // A "Found" request is done once the file is logged in response to it: i.e. a
            // tracker for the same file was created at/after the request was responded to.
            // (NOT_FOUND requests lead to a refer slip, not logging, so they stay.)
            ->where(function ($q) {
                $q->where('status', '!=', FileSearchRequest::STATUS_FOUND)
                    ->orWhereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('file_tracker')
                            ->whereRaw('UPPER(LTRIM(RTRIM(file_tracker.file_number))) = UPPER(LTRIM(RTRIM(file_search_requests.file_number)))')
                            ->whereColumn('file_tracker.created_at', '>=', 'file_search_requests.responded_at');
                    });
            })
            ->orderByDesc('responded_at')
            ->limit(50)
            ->get();

        // Blind request marked "Found for Indexing" should automatically flip to
        // "Found (Indexed)" once a matching indexing row exists.
        $this->promoteBlindFoundIndexingRows($rows);

        $locType = fn ($rs) => match ($rs) {
            FileLocationResolver::STATUS_IN_POOL       => 'Pool Office',
            FileLocationResolver::STATUS_PENDING_FILE  => 'Blind / Not Indexed',
            FileLocationResolver::STATUS_BLIND_REQUEST_SENT => 'Blind / Not Indexed',
            FileLocationResolver::STATUS_BLIND_FOUND_FOR_INDEXING => 'Ready Indexing',
            FileLocationResolver::STATUS_BLIND_FOUND_INDEXED => 'Blind / Indexed',
            default                                    => 'Archive',
        };

        $blindStatuses = $this->blindResolvedStatuses();

        return response()->json([
            'success' => true,
            'data' => $rows->map(function ($r) use ($locType, $blindStatuses) {
                $found = $r->status === FileSearchRequest::STATUS_FOUND;
                $isDfr = ($r->source ?? null) === FileSearchRequest::SOURCE_DFR;
                $isBlind = ! $isDfr && in_array($r->resolved_status, $blindStatuses, true);
                $isReadyIndexing = $found && $r->resolved_status === FileLocationResolver::STATUS_BLIND_FOUND_FOR_INDEXING;
                $isFoundIndexed = $found && $r->resolved_status === FileLocationResolver::STATUS_BLIND_FOUND_INDEXED;
                $canLog = $found && ! $isReadyIndexing;

                return [
                    'id'               => $r->id,
                    'request_no'       => $r->request_no,
                    'file_number'      => $r->file_number,
                    'file_title'       => $r->file_title,
                    'location_type'    => $locType($r->resolved_status),
                    'current_location' => $r->current_location,
                    'scb_response'     => $found
                        ? ($isReadyIndexing ? 'Found for Indexing' : ($isFoundIndexed ? 'Found (Indexed)' : 'Found'))
                        : 'Not Found',
                    'not_found_type'   => $found ? null : $r->not_found_type,
                    'found'            => $found,
                    'can_log'          => $canLog,
                    'ready_indexing'   => $isReadyIndexing,
                    'not_found'        => ! $found,
                    'request_type'     => $isDfr ? 'DFR' : ($isBlind ? 'Blind Request' : 'Open Request'),
                    'is_dfr'           => $isDfr,
                    'is_blind'         => $isBlind,
                    'is_ofs'           => (bool) $r->is_ofs,
                    'ofs_rank'         => $r->ofs_rank,
                    'note'             => $r->feedback_note,
                    'receiving_officer'=> $r->receiving_officer,
                    'requester_office' => $r->requester_office,
                    'requester_department' => $r->requester_department,
                    'registry'         => $r->registry,
                    'registry_code'    => $r->registry_code,
                    'request_purpose_id'   => $r->request_purpose_id,
                    'request_purpose_name' => $r->request_purpose_name,
                    'expected_return_date' => optional($r->expected_return_date)?->format('Y-m-d'),
                    'requested_at'     => optional($r->created_at)->format('Y-m-d g:i A'),
                    'responded_at'     => optional($r->responded_at)->format('Y-m-d g:i A'),
                    // Raw timestamps (epoch ms) so the client can sort reliably.
                    'responded_ts'     => optional($r->responded_at)->valueOf(),
                    'requested_ts'     => optional($r->created_at)->valueOf(),
                ];
            }),
        ]);
    }

    /**
     * Front Desk acts on an SCB-responded request (after logging or referring the
     * file). Marks it acted so it drops out of the SCB Feedback queue.
     * POST /create-file-tracker/quick-search/file-request/{id}/front-desk-acted
     */
    public function markFrontDeskActed($id)
    {
        // Shared front-desk queue: any front-desk user may act on a responded request.
        $fr = FileSearchRequest::find($id);
        if (! $fr) {
            return response()->json(['success' => false, 'message' => 'File request not found.'], 404);
        }

        $fr->forceFill([
            'front_desk_acted_at' => now(),
            'front_desk_acted_by' => Auth::id(),
        ])->save();

        return response()->json(['success' => true]);
    }

    /**
     * Revert (undo) an SCB Found/Not-Found response from the Front Desk — e.g. when the
     * SCB tapped it by accident — putting the request back into the open queue. Only
     * allowed while the Front Desk has not yet acted on it.
     * POST /create-file-tracker/quick-search/file-request/{id}/revert
     */
    public function revertScbResponse($id, FileLocationResolver $resolver)
    {
        if (! Auth::user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Not authorized — Super Admins only.'], 403);
        }

        $fr = FileSearchRequest::find($id);
        if (! $fr) {
            return response()->json(['success' => false, 'message' => 'File request not found.'], 404);
        }

        if (! in_array($fr->status, [FileSearchRequest::STATUS_FOUND, FileSearchRequest::STATUS_NOT_FOUND], true)) {
            return response()->json(['success' => false, 'message' => 'Only a Found / Not-Found response can be reverted.'], 422);
        }

        if ($fr->front_desk_acted_at !== null) {
            return response()->json(['success' => false, 'message' => 'This request has already been acted on — it can no longer be reverted.'], 422);
        }

        // Remove the missing-file report auto-created by a Not Found (Missing)
        // response, so future requests stop diverting to the Original Registry.
        \App\Models\MissingFile::where('request_no', $fr->request_no)
            ->where('status', '!=', \App\Models\MissingFile::STATUS_FOUND)
            ->delete();

        // Send the request back to the open queue (clear the response fields).
        $fr->forceFill([
            'status'        => FileSearchRequest::STATUS_PENDING,
            'feedback_note' => null,
            'responded_by'  => null,
            'responded_at'  => null,
        ])->save();

        // Undo the SCB outcome stamped on the matching file_indexings row so Quick Search
        // stops showing the (accidental) Found / Not-Found outcome.
        $resolved = $resolver->resolve($fr->file_number);
        if ($indexing = $resolved['indexing']) {
            $indexing->forceFill([
                'tracking_status'        => null,
                'location_status_manual' => null,
            ])->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Response reverted — the request is back in the open queue.',
        ]);
    }

    /**
     * Delete a File Search Request. Super Admins only.
     * DELETE /create-file-tracker/quick-search/file-request/{id}
     */
    public function deleteFileRequest($id)
    {
        if (! Auth::user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Not authorized — Super Admins only.'], 403);
        }

        $fr = FileSearchRequest::find($id);
        if (! $fr) {
            return response()->json(['success' => false, 'message' => 'File request not found.'], 404);
        }

        $fr->delete();

        return response()->json(['success' => true, 'message' => 'File request deleted.']);
    }

    /**
     * File Request Log: every File Search Request the current Front Desk user has
     * raised — including those still awaiting an SCB response. Powers the web
     * Quick Search "File Request Log" panel. Optional ?status= filter.
     * GET /create-file-tracker/quick-search/file-request-log
     */
    public function fileRequestLog(Request $request)
    {
        $statusFilter = $request->get('status'); // PENDING | FOUND | NOT_FOUND | MISSING | BLIND

        // Optional date-range filter (on request created_at). Applies to the history
        // list and every chip / report count except the fixed "Requests Today" tile.
        $from = $request->get('from');
        $to   = $request->get('to');
        $dateScope = function ($q) use ($from, $to) {
            if ($from) { $q->whereDate('created_at', '>=', $from); }
            if ($to)   { $q->whereDate('created_at', '<=', $to); }
        };

        // Optionally scope the whole log + report to a single registry
        // (?url=kangis|sltr|cadastral|…) so each registry sees only its own files.
        $registryKw = $this->registryKeywordForModule($request->get('url'));
        $registryScope = function ($q) use ($registryKw) {
            if ($registryKw) { $q->where('registry', 'like', '%' . $registryKw . '%'); }
        };

        // "Missing" = a NOT_FOUND on a blind / not-indexed file (the file has no
        // archive record, so it is genuinely unaccounted for). "Not Found" = a
        // NOT_FOUND on an indexed file (SCB searched a known location and it wasn't there).
        $blindStatuses = $this->blindResolvedStatuses();

        // Indexed (non-blind) resolved_status filter — NULL counts as indexed.
        $indexedScope = function ($q) use ($blindStatuses) {
            $q->whereNotIn('resolved_status', $blindStatuses)
              ->orWhereNull('resolved_status');
        };

        // Full File Search History — everyone's requests (no per-user scoping).
        $query = FileSearchRequest::query()
            ->with(['responder:id,first_name,last_name', 'requester:id,first_name,last_name'])
            ->where($dateScope)
            ->where($registryScope);

        // Free-text search (file no, title, request no, requester, office). Applied
        // server-side so the panel can find matches beyond the 100 most-recent rows
        // returned below — client-side filtering alone would miss older requests.
        $search = trim((string) $request->get('q'));
        if ($search !== '') {
            $query->where(function ($w) use ($search) {
                $like = '%' . $search . '%';
                $w->where('file_number', 'like', $like)
                  ->orWhere('file_title', 'like', $like)
                  ->orWhere('request_no', 'like', $like)
                  ->orWhere('receiving_officer', 'like', $like)
                  ->orWhere('requester_office', 'like', $like)
                  ->orWhere('requester_department', 'like', $like)
                  ->orWhere('current_location', 'like', $like);
            });
        }

        if ($statusFilter === 'PENDING') {
            $query->whereIn('status', [FileSearchRequest::STATUS_PENDING, FileSearchRequest::STATUS_SEARCHING]);
        } elseif ($statusFilter === 'MISSING') {
            $query->where('status', FileSearchRequest::STATUS_NOT_FOUND)
                  ->whereIn('resolved_status', $blindStatuses);
        } elseif ($statusFilter === 'NOT_FOUND') {
            $query->where('status', FileSearchRequest::STATUS_NOT_FOUND)->where($indexedScope);
        } elseif ($statusFilter === 'BLIND') {
            $query->where(function ($q) {
                $q->whereNull('source')->orWhere('source', '!=', FileSearchRequest::SOURCE_DFR);
            });
        } elseif ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $rows = $query->orderByDesc('id')->limit(100)->get();
        $this->promoteBlindFoundIndexingRows($rows);

        $locType = fn ($rs) => match ($rs) {
            FileLocationResolver::STATUS_IN_POOL       => 'Pool Office',
            FileLocationResolver::STATUS_PENDING_FILE  => 'Blind / Not Indexed',
            FileLocationResolver::STATUS_BLIND_REQUEST_SENT => 'Blind / Not Indexed',
            FileLocationResolver::STATUS_BLIND_FOUND_FOR_INDEXING => 'Ready Indexing',
            FileLocationResolver::STATUS_BLIND_FOUND_INDEXED => 'Blind / Indexed',
            default                                    => 'Archive',
        };

        // Counts for the filter chips — scoped to the same date range + registry as the list.
        $counts       = FileSearchRequest::query()->where($dateScope)->where($registryScope)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $pendingCount = (int) ($counts[FileSearchRequest::STATUS_PENDING] ?? 0)
                      + (int) ($counts[FileSearchRequest::STATUS_SEARCHING] ?? 0);

        $missingCount = (int) FileSearchRequest::where($dateScope)->where($registryScope)
            ->where('status', FileSearchRequest::STATUS_NOT_FOUND)
            ->whereIn('resolved_status', $blindStatuses)->count();
        $notFoundCount = (int) FileSearchRequest::where($dateScope)->where($registryScope)
            ->where('status', FileSearchRequest::STATUS_NOT_FOUND)
            ->where($indexedScope)->count();
        $blindOpenCount = (int) FileSearchRequest::where($dateScope)->where($registryScope)->where(function ($q) {
            $q->whereNull('source')->orWhere('source', '!=', FileSearchRequest::SOURCE_DFR);
        })->count();

        // ── Reporting summary ──
        $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('M j, Y') : null;
        $rangeLabel = ($from || $to)
            ? trim(($fmt($from) ?? '…') . ' – ' . ($fmt($to) ?? '…'))
            : null;

        // "Requests Today" tile — the desk's activity today, independent of the
        // date-range filter. Found / Not Found = files the SCB *resolved today* (by
        // responded_at), so a file raised on an earlier day but found this morning is
        // counted. Awaiting = files *raised today* that have no SCB response yet.
        // The header is the sum of the three, so the breakdown always adds up (a file
        // raised today and found today shows once, under Found).
        $today = now()->toDateString();

        $todayFound = (int) FileSearchRequest::query()->where($registryScope)
            ->where('status', FileSearchRequest::STATUS_FOUND)
            ->whereDate('responded_at', $today)->count();

        $todayNotFound = (int) FileSearchRequest::query()->where($registryScope)
            ->where('status', FileSearchRequest::STATUS_NOT_FOUND)
            ->whereDate('responded_at', $today)->count();

        $todayAwaiting = (int) FileSearchRequest::query()->where($registryScope)
            ->whereIn('status', [FileSearchRequest::STATUS_PENDING, FileSearchRequest::STATUS_SEARCHING])
            ->whereDate('created_at', $today)->count();

        $submittedToday = $todayFound + $todayNotFound + $todayAwaiting;

        $report = [
            'date'            => now()->format('M j, Y'),
            'range_label'     => $rangeLabel,
            // a. Activity today = files resolved today + files still awaiting from today
            'submitted_today' => $submittedToday,
            // a.1 Breakdown of today's activity — Found / Not Found by response date,
            //     Awaiting by request date. These three always sum to submitted_today.
            'today_found'     => $todayFound,
            'today_not_found' => $todayNotFound,
            'today_awaiting'  => $todayAwaiting,
            // b. Blind / Open requests (everything raised from Quick Search — i.e. not a DFR)
            'blind_open'      => $blindOpenCount,
            // c. Found
            'found'           => (int) ($counts[FileSearchRequest::STATUS_FOUND] ?? 0),
            // d. Not Found (indexed file, SCB couldn't locate it)
            'not_found'       => $notFoundCount,
            // e. Missing (blind / not-indexed file, not located)
            'missing'         => $missingCount,
            // f. Awaiting (still pending / searching — no SCB response yet)
            'awaiting'        => $pendingCount,
        ];

        return response()->json([
            'success' => true,
            'report'  => $report,
            'counts'  => [
                'all'        => (int) $counts->sum(),
                'pending'    => $pendingCount,
                'found'      => (int) ($counts[FileSearchRequest::STATUS_FOUND] ?? 0),
                'not_found'  => $notFoundCount,
                'missing'    => $missingCount,
                'blind_open' => $blindOpenCount,
            ],
            'data' => $rows->map(function ($r) use ($locType, $blindStatuses) {
                $resp      = $r->responder;
                $reqUser   = $r->requester;
                $isPending = in_array($r->status, [FileSearchRequest::STATUS_PENDING, FileSearchRequest::STATUS_SEARCHING], true);
                $isDfr     = ($r->source ?? null) === FileSearchRequest::SOURCE_DFR;
                $isBlind   = ! $isDfr && in_array($r->resolved_status, $blindStatuses, true);

                return [
                    'id'               => $r->id,
                    'request_no'       => $r->request_no,
                    'file_number'      => $r->file_number,
                    'file_title'       => $r->file_title,
                    'request_type'     => $isDfr ? 'DFR' : ($isBlind ? 'Blind Request' : 'Open Request'),
                    'is_blind'         => $isBlind,
                    'is_dfr'           => $isDfr,
                    'location_type'    => $locType($r->resolved_status),
                    'current_location' => $r->current_location,
                    'status'           => $r->status,
                    'is_pending'       => $isPending,
                    'found'            => $r->status === FileSearchRequest::STATUS_FOUND,
                    'not_found'        => $r->status === FileSearchRequest::STATUS_NOT_FOUND,
                    'scb_response'     => $isPending
                        ? 'Awaiting'
                        : ($r->status === FileSearchRequest::STATUS_FOUND
                            ? ($r->resolved_status === FileLocationResolver::STATUS_BLIND_FOUND_FOR_INDEXING
                                ? 'Found for Indexing'
                                : ($r->resolved_status === FileLocationResolver::STATUS_BLIND_FOUND_INDEXED ? 'Found (Indexed)' : 'Found'))
                            : 'Not Found'),
                    'not_found_type'   => $r->status === FileSearchRequest::STATUS_NOT_FOUND ? $r->not_found_type : null,
                    'front_desk_acted' => ! is_null($r->front_desk_acted_at),
                    'note'             => $r->feedback_note,
                    'responder'        => $resp ? trim($resp->first_name . ' ' . $resp->last_name) : null,
                    'requested_by'     => $reqUser ? trim($reqUser->first_name . ' ' . $reqUser->last_name) : null,
                    'receiving_officer'=> $r->receiving_officer,
                    'requester_office' => $r->requester_office,
                    'requester_department' => $r->requester_department,
                    'registry'         => $r->registry,
                    'registry_code'    => $r->registry_code,
                    'request_purpose_id'   => $r->request_purpose_id,
                    'request_purpose_name' => $r->request_purpose_name,
                    'expected_return_date' => optional($r->expected_return_date)?->format('Y-m-d'),
                    'requested_at'     => optional($r->created_at)->format('Y-m-d g:i A'),
                    'responded_at'     => optional($r->responded_at)->format('Y-m-d g:i A'),
                ];
            }),
        ]);
    }

    /**
     * Resolved-status values considered part of the blind request workflow.
     */
    protected function blindResolvedStatuses(): array
    {
        return [
            FileLocationResolver::STATUS_PENDING_FILE,
            FileLocationResolver::STATUS_BLIND_REQUEST_SENT,
            FileLocationResolver::STATUS_BLIND_FOUND_INDEXED,
            FileLocationResolver::STATUS_BLIND_FOUND_FOR_INDEXING,
        ];
    }

    /**
     * Auto-promote blind "Found for Indexing" rows once indexing exists.
     */
    protected function promoteBlindFoundIndexingRows($rows): void
    {
        foreach ($rows as $row) {
            if (! $row instanceof FileSearchRequest) {
                continue;
            }

            if ($row->status !== FileSearchRequest::STATUS_FOUND
                || $row->resolved_status !== FileLocationResolver::STATUS_BLIND_FOUND_FOR_INDEXING) {
                continue;
            }

            $exists = FileIndexing::on('sqlsrv')
                ->where(function ($q) use ($row) {
                    $q->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(LTRIM(RTRIM(?)))', [$row->file_number])
                      ->orWhereRaw('UPPER(LTRIM(RTRIM(temp_file_no))) = UPPER(LTRIM(RTRIM(?)))', [$row->file_number]);
                })
                ->exists();

            if (! $exists) {
                continue;
            }

            $row->forceFill([
                'resolved_status' => FileLocationResolver::STATUS_BLIND_FOUND_INDEXED,
            ])->save();

            // Keep this in-memory row aligned with the DB update for immediate payload use.
            $row->resolved_status = FileLocationResolver::STATUS_BLIND_FOUND_INDEXED;
        }
    }

    /**
     * Shape a FileLocationResolver result for JSON (strip Eloquent models).
     */
    protected function presentLocationResult(array $result, bool $withCandidates = false): array
    {
        /** @var \App\Models\FileTracker|null $tracker */
        $tracker = $result['tracker'] ?? null;
        /** @var \App\Models\FileIndexing|null $indexing */
        $indexing = $result['indexing'] ?? null;

        // A duplicate number can have both indexed and duplicate_fileno records.
        // Keep the indexed holder/title in the Duplicate File panel as well, so
        // the web picker and the mobile File Search account for every physical
        // file sharing the number.
        $displayTitle = $tracker->file_title ?? $indexing->file_title ?? null;
        $duplicateCandidates = $withCandidates
            ? app(FileLocationResolver::class)->duplicateCandidates($result)
            : [];
        $duplicateFlag = $result['duplicate_flag'] ?? null;
        if (is_array($duplicateFlag) && $duplicateCandidates) {
            $indexedEntries = collect();
            if ($indexing && trim((string) $displayTitle) !== '') {
                $indexedEntries->push([
                    'file_number' => trim((string) $result['file_number']),
                    'file_title'  => trim((string) $displayTitle),
                ]);
            }
            $indexedEntries = $indexedEntries
                ->merge(collect($duplicateCandidates)
                    ->filter(fn ($candidate) => ($candidate['source'] ?? null) === FileLocationResolver::SOURCE_INDEXED)
                    ->map(fn ($candidate) => [
                        'file_number' => trim((string) ($candidate['file_number'] ?? '')),
                        'file_title'  => trim((string) ($candidate['holder'] ?? '')) ?: null,
                    ]))
                ->filter(fn ($entry) => $entry['file_number'] !== '' && $entry['file_title'] !== null)
                ->values();

            if ($indexedEntries->isNotEmpty()) {
                $duplicateFlag['entries'] = $indexedEntries
                    ->merge(collect($duplicateFlag['entries'] ?? []))
                    ->unique(fn ($entry) => strtoupper($entry['file_number']) . '|' . strtoupper((string) ($entry['file_title'] ?? '')))
                    ->values()
                    ->all();
            }
        }

        // When the file is in transit (logged out), surface the logout date + time.
        // This is exactly the timestamp the "Duration with holder" clock runs from, so
        // it must come from the resolver's held_since — which reads the current movement's
        // accepted_at / log_in / log_out in that order. Deriving it separately here let the
        // two rows disagree: a movement with only a log_out fell through to the tracker's
        // updated_at, so any unrelated correction to the row (a re-typed officer name, a
        // department fix) re-dated the logout to the moment of that edit while the duration
        // kept counting from the real one. Never fall back to updated_at.
        $loggedOutAt = null;
        if ($result['status'] === FileLocationResolver::STATUS_IN_TRANSIT && $tracker) {
            $loggedOutAt = $result['held_since'] ?? null;
        }

        // If a File Search Request is already open for this file, surface when it was sent.
        $openFr = \App\Models\FileSearchRequest::openForFile($result['file_number'])
            ->orderByDesc('created_at')
            ->first();

        // The most recent FR the SCB responded to as Found — surfaced on the "File
        // Found" panel so the front desk can see who requested it, when the request
        // was sent, when it was found, and which SCB monitor responded.
        $foundFr = \App\Models\FileSearchRequest::where('file_number', $result['file_number'])
            ->where('status', FileSearchRequest::STATUS_FOUND)
            ->with('responder:id,first_name,last_name')
            ->orderByDesc('responded_at')
            ->first();

        // Likewise, the most recent FR the SCB responded to as Not Found — surfaced on
        // the "File Not Found" panel (who requested it, when, who searched, and whether
        // the file is Missing or the search is still Pending).
        $notFoundFr = \App\Models\FileSearchRequest::where('file_number', $result['file_number'])
            ->where('status', FileSearchRequest::STATUS_NOT_FOUND)
            ->with('responder:id,first_name,last_name')
            ->orderByDesc('responded_at')
            ->first();

        // The receiving officer's department (from the offices table), so the In-Transit
        // card can show "Receiving Officer: X (Dept) · currently holding the file."
        $receivingDepartment = null;
        if ($tracker && ($tracker->receiving_office_code || $tracker->receiving_office_name)) {
            $receivingDepartment = \App\Models\Office::query()
                ->when($tracker->receiving_office_code, fn ($q) => $q->where('office_code', $tracker->receiving_office_code))
                ->when(! $tracker->receiving_office_code, fn ($q) => $q->where('office_name', $tracker->receiving_office_name))
                ->value('department');
        }

        $dcivInfo = app(FileLocationResolver::class)->dcivInfoFor($result['file_number'], $indexing, $result['registry'] ?? null);

        return [
            'logged_out_at'    => $loggedOutAt,
            'fr_sent_at'       => optional($openFr?->created_at)?->format('Y-m-d g:i A'),
            'fr_request_no'    => $openFr?->request_no,
            // Details of the FR that was responded to as Found (for the "File Found" panel).
            'fr_found'         => $foundFr ? [
                'request_no'           => $foundFr->request_no,
                'requested_at'         => optional($foundFr->created_at)?->format('Y-m-d g:i A'),
                'responded_at'         => optional($foundFr->responded_at)?->format('Y-m-d g:i A'),
                'responder'            => $foundFr->responder
                    ? trim($foundFr->responder->first_name . ' ' . $foundFr->responder->last_name)
                    : null,
                'receiving_officer'    => $foundFr->receiving_officer,
                'requester_office'     => $foundFr->requester_office,
                'requester_department' => $foundFr->requester_department,
                'registry'             => $foundFr->registry,
                'request_purpose_id'   => $foundFr->request_purpose_id,
                'request_purpose_name' => $foundFr->request_purpose_name,
                'expected_return_date' => optional($foundFr->expected_return_date)?->format('Y-m-d'),
            ] : null,
            // Details of the FR that was responded to as Not Found (for the "File Not Found" panel).
            'fr_not_found'     => $notFoundFr ? [
                'request_no'           => $notFoundFr->request_no,
                'requested_at'         => optional($notFoundFr->created_at)?->format('Y-m-d g:i A'),
                'responded_at'         => optional($notFoundFr->responded_at)?->format('Y-m-d g:i A'),
                'responder'            => $notFoundFr->responder
                    ? trim($notFoundFr->responder->first_name . ' ' . $notFoundFr->responder->last_name)
                    : null,
                'receiving_officer'    => $notFoundFr->receiving_officer,
                'requester_office'     => $notFoundFr->requester_office,
                'requester_department' => $notFoundFr->requester_department,
                'registry'             => $notFoundFr->registry,
                'not_found_type'       => $notFoundFr->not_found_type,
                'note'                 => $notFoundFr->feedback_note,
            ] : null,
            'file_number'      => $result['file_number'],
            // Related counterpart file number (KANGIS ↔ Land). When a KANGIS file
            // is searched and it carries a related land file — or vice versa — the
            // UI renders "KANGIS FileNo (Land FileNo)". Null when there is no pair.
            'linked_file_number' => app(FileLocationResolver::class)->linkedFileNumber($result['file_number'], $indexing),
            // All related numbers, not just the first counterpart. This belongs in the
            // Quick Search file history directly under the file number.
            'related_files' => $withCandidates
                ? app(FileLocationResolver::class)->relatedFilesWithTitles($indexing, $result['file_number'])
                : [],
            'status'           => $result['status'],
            'registry'         => $result['registry'],
            'zone'             => $result['zone'],
            'current_location' => $result['current_location'],
            'rack_shelf'       => $result['rack_shelf'],
            // How long the file has been with the current holder (IN_TRANSIT only).
            'held_since'           => $result['held_since'] ?? null,
            'days_with_holder'     => $result['days_with_holder'] ?? null,
            'duration_with_holder' => $result['duration_with_holder'] ?? null,
            'next_action'      => $result['next_action'],
            'slip_variant'     => $result['slip_variant'],
            'can_send_fr'      => (bool) ($result['can_send_fr'] ?? false),
            'can_log'          => (bool) ($result['can_log'] ?? false),
            'is_blind'         => (bool) ($result['is_blind'] ?? false),
            // Flag set when this file number exists in the missing_files table.
            // When true, the "Send Blind Request to SCB Monitor" button changes to
            // "Send Blind Request to the Original Registry" and saves without SCB.
            'is_missing_file'  => (bool) ($result['is_missing_file'] ?? false),
            // True when a FileIndexing row exists for this file number. Combined with
            // is_missing_file above, this flags a stale missing_files report — the file
            // has since been indexed (i.e. physically returned and filed), so the UI can
            // surface a note under the Missing File badge instead of treating it as still
            // missing.
            'is_indexed'       => (bool) $indexing,
            // Temporary "(T)" file — resolved standalone (never against its stripped
            // base number); the UI shows an "Is Temporary File" badge.
            'is_temp_file'     => (bool) ($result['is_temp_file'] ?? false),
            // In-transit files can be re-directed straight to the office currently
            // holding them (the last receiving officer) instead of going to the SCB.
            'can_redirect'     => $result['status'] === FileLocationResolver::STATUS_IN_TRANSIT,
            'manual'           => (bool) ($result['manual'] ?? false),
            // Duplicate-registry flag (CofO collected/ready, duplicate, temp, W/C/R) when
            // the file number is registered in duplicate_fileno — null otherwise.
            'duplicate_flag'   => $duplicateFlag,
            // Every physical file registered under this number, but ONLY when the
            // number is in BOTH file_indexings and duplicate_fileno. Non-empty means
            // the front desk must pick the exact file before the request can go out:
            // the number alone no longer identifies one file, and the user may
            // already be holding one of the two. Empty = the existing workflow.
            // Only the Quick Search paths ask for these: decorateTrackerForResponse()
            // calls this presenter once per row of a paginated tracker list and reads
            // none of them, so computing them there would be a per-row N+1 (see the
            // File Log Table timeout this presenter already caused once).
            'duplicate_candidates' => $duplicateCandidates,
            'file_tracker_id'  => $result['file_tracker_id'],
            'file_title'       => $displayTitle,
            'receiving_officer_name' => $tracker->receiving_officer_name ?? null,
            // Photo of the officer physically holding the file. Behind $withCandidates
            // because only the Quick Search paths render it — the paginated tracker list
            // calls this presenter per row and would turn it into an N+1.
            'receiving_officer_photo' => $withCandidates
                ? \App\Support\UserPhoto::forIdOrName(
                    $tracker->receiving_officer_id ?? null,
                    $tracker->receiving_officer_name ?? null
                )
                : null,
            // The APPLICANT's photograph for this file (not the officer's above), so the
            // Quick Search panel shows whose file is being pulled. Behind $withCandidates
            // for the same reason as the officer photo: the paginated tracker list calls
            // this presenter per row and primes its own passports in bulk instead.
            'passport_url' => $withCandidates
                ? (app(\App\Services\FilePassportService::class)->resolve($result['file_number'])['url'] ?? null)
                : null,
            'receiving_department'   => $receivingDepartment,
            'tracking_id'      => $tracker->tracking_id ?? null,
            // DCIV investigation flag: true when this file is either flagged
            // dciv_status=1 (a related file linked to a DCIV master) OR is itself the
            // DCIV master record (own registry is the DCIV Registry, or it has related
            // files linked to it). See FileLocationResolver::dcivInfoFor().
            'dciv_status'      => (int) $dcivInfo['status'],
            'dciv_fileno'      => $dcivInfo['fileno'],
            'dciv_reason'      => $dcivInfo['reason'],
            // The reverse case: the searched number IS a DCIV master file itself —
            // every related file linked to it via master_dciv_links, so the front
            // desk can see what it is investigating.
            'dciv_related_files' => $dcivInfo['related_files'],
            // Ownership history — the chronological holder chain from the cross-table
            // property timeline (file_history/CofO/pra/deeds), rendered as a timeline.
            // Only surfaced for indexed files: a file with no indexing row (Pending /
            // Not Indexed) brings no transactions.
            'holder_history'   => $indexing
                ? app(\App\Services\TimelineWeightingService::class)->holderHistory($result['file_number'])
                : [],
            // Root of Title / Original Holder / Current Holder — resolved from the
            // transaction chain, NOT from the raw indexing columns (client spec
            // 2026-08-20 §12). The resolver falls back to those columns itself
            // when the file carries no transactions at all, so an unindexed or
            // dealing-free file still shows what it always showed.
            'title_holders'    => $indexing
                ? app(\App\Services\TitleHolderResolver::class)
                    ->resolveForDisplay($result['file_number'], null, $indexing)
                : null,
            'original_holder'  => $indexing?->formattedHolder('original_holder'),
            'current_holder'   => $indexing?->formattedHolder('current_holder'),
            'bill_balance'     => \App\Models\BillBalance::summaryForFile($result['file_number']),
            // Indexing bills (Bill Balance + Grant Rent amounts captured during File Indexing).
            'indexing_bills'   => \App\Models\FileIndexingBill::amountsForFile($result['file_number']),
        ];
    }

    /**
     * Create a File Request (FR) from web Quick Search and route it to the
     * SCB Monitors (the mobile-only file searchers) via in-app notification
     * + email.
     * POST /create-file-tracker/file-request
     */
    public function sendFileRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_number'       => 'required|string|max:255',
            'file_title'        => 'nullable|string|max:255',
            'current_location'  => 'nullable|string|max:255',
            'resolved_status'   => 'nullable|string|max:50',
            'receiving_officer' => 'nullable|string|max:255',
            'requester_office'  => 'nullable|string|max:255',
            'requester_department' => 'nullable|string|max:255',
            'registry'          => 'nullable|string|max:255',
            'registry_code'     => 'nullable|string|max:20',
            'update_existing_id' => 'nullable|integer',
            'request_purpose_id' => 'nullable|integer|exists:sqlsrv.request_purposes,id',
            'request_purpose_other' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();

            $requestPurposeId = $request->input('request_purpose_id');
            $requestPurposeOther = trim((string) $request->input('request_purpose_other', ''));
            $requestPurposeName = $requestPurposeId
                ? optional(RequestPurpose::find($requestPurposeId))->name
                : ($requestPurposeOther !== '' ? $requestPurposeOther : null);
            // No expected_return_date is captured when a request is raised. The return
            // clock starts when the file is logged out on Create File Tracker, so that
            // days spent searching don't eat into the file's turnaround.

            // Update-existing mode: the user chose to refresh the requester details on
            // an existing active request rather than raise a duplicate. No new row and no
            // fresh SCB notification — just update who is asking and re-prioritise.
            if ($updateId = $request->input('update_existing_id')) {
                $existing = FileSearchRequest::activeForFile($request->file_number)
                    ->where('id', $updateId)
                    ->first();

                if (! $existing) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The original request could not be found — it may have already been actioned or removed.',
                    ], 404);
                }

                $receivingOfficer = $request->input('receiving_officer');
                $officerRank      = $this->resolveOfficerRank($receivingOfficer);
                $isOfs   = $user && $user->isOfs();
                $ofsRank = $isOfs ? $user->ofsRank() : null;

                $existing->update([
                    'receiving_officer'    => $receivingOfficer,
                    'requester_office'     => $request->input('requester_office'),
                    'requester_department' => $request->input('requester_department'),
                    'registry'             => $request->input('registry'),
                    'registry_code'        => $request->input('registry_code'),
                    'is_ofs'               => $isOfs,
                    'ofs_rank'             => $ofsRank,
                    'priority'             => FileSearchRequest::priorityFor($isOfs ? $ofsRank : ($officerRank ?: $receivingOfficer)),
                    'request_purpose_id'   => $requestPurposeId,
                    'request_purpose_name' => $requestPurposeName,
                ]);

                return response()->json([
                    'success' => true,
                    'updated' => true,
                    'data'    => ['request_no' => $existing->request_no, 'id' => $existing->id],
                    'message' => 'Requester details updated on the existing request.',
                ], 200);
            }

            // Duplicate guard: if the file already has an active request to SCB (open, or
            // responded but not yet front-desk-acted), prompt the user — who raised it —
            // instead of silently creating another row. They then update the requester
            // details on that existing request rather than duplicating it.
            $existing = FileSearchRequest::activeForFile($request->file_number)
                ->with('requester:id,first_name,last_name')
                ->orderByDesc('id')
                ->first();

            if ($existing) {
                return response()->json([
                    'success'   => false,
                    'duplicate' => true,
                    'message'   => 'This file has already been requested and sent to SCB.',
                    'existing'  => $this->frDuplicatePayload($existing),
                ], 200);
            }

            // Missing-file shortcut: if the file number is recorded in the missing_files
            // table (meaning SCB has already searched for it and it was not found), the
            // request should be saved as "Refer to Original Registry" without notifying
            // SCB monitors. The front-end button already reads "Send Blind Request to the
            // Original Registry" in this case; we just skip the SCB notification here.
            $isMissingFile = false;
            if (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasTable('missing_files')) {
                $isMissingFile = \App\Models\MissingFile::where('file_number', $request->file_number)
                    ->where('status', '!=', \App\Models\MissingFile::STATUS_FOUND)
                    ->exists();
            }

            if ($isMissingFile) {
                $receivingOfficer = $request->input('receiving_officer');
                $officerRank      = $this->resolveOfficerRank($receivingOfficer);
                $isOfs   = $user && $user->isOfs();
                $ofsRank = $isOfs ? $user->ofsRank() : null;

                // Saved directly as a closed-out NOT_FOUND (Missing) request: the SCB has
                // already searched this file, so it must not re-enter the SCB Open inbox,
                // the Awaiting count, or the SCB Feedback queue. Stamping the response and
                // front-desk action up-front sends it straight to File Search History.
                $fr = FileSearchRequest::create([
                    'request_no'        => FileSearchRequest::generateRequestNo(),
                    'file_number'       => $request->file_number,
                    'file_title'        => $request->file_title,
                    'requester_user_id' => $user->id ?? null,
                    'status'            => FileSearchRequest::STATUS_NOT_FOUND,
                    'not_found_type'    => FileSearchRequest::NOT_FOUND_MISSING,
                    'feedback_note'     => 'File is on the missing files list — referred to the Original Registry.',
                    'responded_at'      => now(),
                    'front_desk_acted_at' => now(),
                    'front_desk_acted_by' => $user->id ?? null,
                    'resolved_status'   => FileLocationResolver::STATUS_REFER,
                    'current_location'  => $request->current_location,
                    'receiving_officer' => $receivingOfficer,
                    'requester_office'  => $request->input('requester_office'),
                    'requester_department' => $request->input('requester_department'),
                    'registry'          => $request->input('registry'),
                    'registry_code'     => $request->input('registry_code'),
                    'is_ofs'            => $isOfs,
                    'ofs_rank'          => $ofsRank,
                    'priority'          => FileSearchRequest::priorityFor($isOfs ? $ofsRank : ($officerRank ?: $receivingOfficer)),
                ]);

                return response()->json([
                    'success' => true,
                    'data'    => ['request_no' => $fr->request_no, 'id' => $fr->id],
                    'message' => 'Blind request saved — file is in the missing list. Refer to Original Registry.',
                ], 201);
            }

            // Seniority is taken from the chosen Receiving Officer (the requester); fall
            // back to the officer name when their rank can't be resolved.
            $receivingOfficer = $request->input('receiving_officer');
            $officerRank      = $this->resolveOfficerRank($receivingOfficer);

            // OFS: when a ranked officer raises the request themselves, seniority comes
            // from their own rank and the request is flagged so it stands out and floats
            // to the top of the SCB queue.
            $isOfs   = $user && $user->isOfs();
            $ofsRank = $isOfs ? $user->ofsRank() : null;

            $fr = FileSearchRequest::create([
                'request_no'        => FileSearchRequest::generateRequestNo(),
                'file_number'       => $request->file_number,
                'file_title'        => $request->file_title,
                'requester_user_id' => $user->id ?? null,
                'status'            => FileSearchRequest::STATUS_PENDING,
                'resolved_status'   => $request->input('resolved_status', FileLocationResolver::STATUS_IN_POOL),
                'current_location'  => $request->current_location,
                'receiving_officer' => $receivingOfficer,
                'requester_office'  => $request->input('requester_office'),
                'requester_department' => $request->input('requester_department'),
                'registry'          => $request->input('registry'),
                'registry_code'     => $request->input('registry_code'),
                'is_ofs'            => $isOfs,
                'ofs_rank'          => $ofsRank,
                'request_purpose_id'   => $requestPurposeId,
                'request_purpose_name' => $requestPurposeName,
                'priority'          => FileSearchRequest::priorityFor($isOfs ? $ofsRank : ($officerRank ?: $receivingOfficer)),
            ]);

            $requesterName = $user->name ?? 'A registry user';
            $this->notifyScbMonitors($fr, $requesterName);

            /*
             | Acknowledge the request to the officer who raised it.
             |
             | Only on this branch. The two earlier returns -- updating an open
             | request, and the missing-file shortcut that is stamped NOT_FOUND
             | without ever reaching the SCB -- must stay silent, because the
             | message promises the request has been "forwarded to our SCB
             | Monitor for onward retrieval" and on those paths it has not.
             */
            $this->sendFileRequestReceivedSms($fr, $user);

            return response()->json([
                'success' => true,
                'data'    => ['request_no' => $fr->request_no, 'id' => $fr->id],
                'message' => 'File Request sent to SCB Monitors.',
            ], 201);
        } catch (Exception $e) {
            Log::error('sendFileRequest failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Could not send the File Request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve a receiving officer's rank from their "First Last" name, so seniority can
     * be derived. Returns null when no matching active user / rank is found.
     */
    protected function resolveOfficerRank(?string $officerName): ?string
    {
        $officerName = trim((string) $officerName);
        if ($officerName === '') {
            return null;
        }

        $rank = DB::connection('sqlsrv')
            ->table('users')
            ->where('is_active', 1)
            ->whereRaw("LTRIM(RTRIM(CONCAT(first_name, ' ', last_name))) = ?", [$officerName])
            ->value('rank');

        return $rank ?: null;
    }

    /** Compact summary of an existing open FSR, for the duplicate-request prompt. */
    protected function frDuplicatePayload(FileSearchRequest $fr): array
    {
        $req = $fr->relationLoaded('requester') ? $fr->requester : $fr->requester()->first();
        $createdBy = $req ? trim($req->first_name . ' ' . $req->last_name) : '';

        // "Requested by" is the selected Requester Officer — not the user account that
        // raised the request. Fall back to office/department, then the creating user.
        $requesterName = $fr->receiving_officer
            ?: ($fr->requester_office ?: ($fr->requester_department ?: ($createdBy ?: '—')));

        return [
            'id'                   => $fr->id,
            'request_no'           => $fr->request_no,
            'requester_name'       => $requesterName,
            'receiving_officer'    => $fr->receiving_officer,
            'requester_office'     => $fr->requester_office,
            'requester_department' => $fr->requester_department,
            'created_by'           => $createdBy ?: '—',
            'status'               => $fr->status,
            'requested_at'         => optional($fr->created_at)->format('Y-m-d g:i A'),
        ];
    }

    /**
     * GET /create-file-tracker/file-requesters?file_number=
     * Open File Search Requests for a file, ordered by requester seniority so the
     * front desk honors the most senior at the log step. Read-only — never alters
     * the requests. The top (most senior) row is flagged is_top.
     */
    public function fileRequesters(Request $request)
    {
        $fileNumber = trim((string) $request->get('file_number', ''));
        if ($fileNumber === '') {
            return response()->json(['success' => true, 'data' => []]);
        }

        $rows = FileSearchRequest::openForFile($fileNumber)
            ->with('requester:id,first_name,last_name')
            ->orderByDesc('priority')
            ->orderBy('created_at')
            ->get();

        $data = $rows->values()->map(function (FileSearchRequest $fr, int $i) {
            $req = $fr->requester;
            return [
                'request_no'        => $fr->request_no,
                'requester_name'    => $req ? trim($req->first_name . ' ' . $req->last_name) : '—',
                'receiving_officer' => $fr->receiving_officer,
                'priority'          => (int) $fr->priority,
                'source'            => $fr->source,
                'status'            => $fr->status,
                'requested_at'      => optional($fr->created_at)->format('Y-m-d g:i A'),
                'is_top'            => $i === 0,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Notify every SCB Monitor (in-app + email) about a new File Request.
     */
    protected function notifyScbMonitors(FileSearchRequest $fr, string $requesterName): void
    {
        // SCB Monitors are users flagged with fr_permissions = 'SCB'
        // (set via the Digital File Request Permissions section on the user form).
        $monitors = User::where('fr_permissions', 'SCB')
            ->where(function ($q) {
                $q->whereNull('is_active')->orWhere('is_active', 1);
            })
            ->get();

        foreach ($monitors as $monitor) {
            try {
                $this->notificationService->create(
                    $monitor->id,
                    'file_search_request',
                    "File Request: {$fr->request_no}",
                    "{$requesterName} requested a physical search for file {$fr->file_number}" .
                        ($fr->current_location ? " (expected at {$fr->current_location})." : '.'),
                    [
                        'request_id'   => $fr->id,
                        'request_no'   => $fr->request_no,
                        'file_number'  => $fr->file_number,
                        'location'     => $fr->current_location,
                    ],
                    ['module' => 'file_search_request', 'enabled_email' => true]
                );

                if (!empty($monitor->email)) {
                    \Illuminate\Support\Facades\Mail::to($monitor->email)
                        ->send(new FileSearchRequestIssued($fr, $requesterName));
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to notify SCB Monitor of File Request', [
                    'fr_id' => $fr->id, 'monitor_id' => $monitor->id, 'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get dashboard statistics for file trackers
     */
    public function dashboard()
    {
        try {
            $userId = Auth::id();
            $isAdmin = Auth::user()->can('view_all_file_trackers');

            // Base query - filter by user if not admin
            $baseQuery = FileTracker::query();

            $stats = [
                'total_trackers' => (clone $baseQuery)->count(),
                'active_trackers' => (clone $baseQuery)->where('status', FileTracker::STATUS_ACTIVE)->count(),
                'completed_trackers' => (clone $baseQuery)->where('status', FileTracker::STATUS_COMPLETED)->count(),
                'overdue_trackers' => (clone $baseQuery)->where('deadline', '<', now())
                    ->where('status', FileTracker::STATUS_ACTIVE)->count(),
                'priority_breakdown' => [
                    'high' => (clone $baseQuery)->where('priority', 'HIGH')
                        ->where('status', FileTracker::STATUS_ACTIVE)->count(),
                    'medium' => (clone $baseQuery)->where('priority', 'MEDIUM')
                        ->where('status', FileTracker::STATUS_ACTIVE)->count(),
                    'low' => (clone $baseQuery)->where('priority', 'LOW')
                        ->where('status', FileTracker::STATUS_ACTIVE)->count(),
                ],
                'recent_activity' => (clone $baseQuery)->orderBy('updated_at', 'desc')
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
     * Add computed attributes used by the front-end for consistent responses.
     */
    protected function decorateTrackerForResponse(FileTracker $tracker): FileTracker
    {
        // Trigger accessor evaluation so the properties are included in JSON responses.
        $tracker->setAttribute('is_overdue', $tracker->is_overdue);
        $tracker->setAttribute('days_until_deadline', $tracker->days_until_deadline);
        $tracker->setAttribute('timeline_status', $tracker->timeline_status);
        $tracker->setAttribute('completion_percentage', $tracker->completion_percentage);
        $tracker->setAttribute('current_movement', $tracker->getCurrentMovement());

        $tracker->setAttribute('rack_shelf_location', $this->getRackShelfLocation($tracker->file_number));
        // Passport photo of the officer currently holding the file — printed on the
        // File Tracking Sheet beside the ministry header. UserPhoto memoises per
        // request, so the same officer across many rows costs one query.
        $tracker->setAttribute('receiving_officer_photo', \App\Support\UserPhoto::forIdOrName(
            $tracker->receiving_officer_id,
            $tracker->receiving_officer_name
        ));

        // Movement entries from EARLIER tracking cycles of the same file. The front-end
        // renders these read-only between the Archive home row and this tracker's own
        // rows so a file that is re-tracked after being logged back into the registry
        // shows one continuous timeline instead of a fresh, disconnected log.
        // The DIIT "File Commissioning" line is prepended here, so a commissioned
        // file's timeline always opens where the file actually began.
        $priorMovements = $this->withCommissioningLine($tracker);
        $tracker->setAttribute('prior_movements', $priorMovements);

        // The KANGIS sheet takes its holder from the latest movement entry when the
        // tracker-level officer is blank, so those officers need photos too, and the
        // log table draws a face beside every Receiving Officer cell — including the
        // read-only prior-cycle rows, whose officers are not in this tracker's own log.
        // One primed query covers both lists; ids with no photo are dropped.
        $logOfficerIds = collect($tracker->movement_log ?: [])
            ->merge($priorMovements)
            ->pluck('receiving_officer_id')
            ->filter()
            ->unique();
        \App\Support\UserPhoto::prime($logOfficerIds);
        $tracker->setAttribute('officer_photos', $logOfficerIds
            ->mapWithKeys(fn ($id) => [(string) $id => \App\Support\UserPhoto::forId($id)])
            ->filter()
            ->all());

        // The APPLICANT's photograph for this file — not the officer's above. Shown on the
        // log card and the movement timeline so a clerk pulling the file can see whose it
        // is. Null is ordinary: corporate files carry no passport, and neither do files
        // commissioned before the photograph was captured. prime() makes this free on the
        // list; a single-record screen pays two queries.
        $trackerPassport = app(\App\Services\FilePassportService::class)->resolve($tracker->file_number);
        $tracker->setAttribute('passport_url', $trackerPassport['url'] ?? null);

        // Sync and override file_title with the correct title from file_indexings
        $indexingTitle = $this->getFileIndexingTitle($tracker->file_number);
        if ($indexingTitle !== null) {
            $tracker->file_title = $indexingTitle;
            $tracker->setAttribute('file_title', $indexingTitle);
        }

        // Original file-indexing created_at — used as the "home location" row's
        // Date & Time / Log In on the Movement History sheet.
        $tracker->setAttribute('file_indexing_created_at', $this->getFileIndexingCreatedAt($tracker->file_number));

        // DIIT flags for the front end:
        //   is_commissioned — the file was commissioned through KLAES, so its history
        //                     opens with the File Commissioning line and the synthetic
        //                     "Registry / Archive" home row is dropped.
        //   is_diit         — this whole card is the default commissioning line (no
        //                     tracker row exists yet), so the id-based actions are hidden.
        $commissioning = app(FileCommissioningTrackingService::class)->infoFor($tracker->file_number);
        $tracker->setAttribute('is_commissioned', $commissioning !== null);
        $tracker->setAttribute('is_diit', (bool) $tracker->getAttribute('is_diit'));
        $tracker->setAttribute('commissioned_at', $commissioning ? $commissioning['commissioned_at']->toIso8601String() : null);
        $tracker->setAttribute('commissioned_by', $commissioning['commissioned_by'] ?? null);

        // Add workflow progress if this is a 3-step tracker
        if ($tracker->isKangis3Step()) {
            $tracker->setAttribute('workflow_progress', $tracker->getWorkflowProgress());
            $tracker->setAttribute('next_step', $tracker->getNextStepDefinition());
        }

        // Temporary "(T)" files are tracked as standalone records, so the Tracking
        // Sheet needs to separately surface where the file's counterpart currently
        // sits — logged out with a holder (IN_TRANSIT) or sitting in the Archive.
        // Both directions: a "(T)" sheet shows the mother (base) file, and a mother
        // sheet shows its temporary "(T)" file when one exists.
        $tracker->setAttribute('mother_file_location', $this->getMotherFileLocation($tracker->file_number));
        $tracker->setAttribute('temp_file_location', $this->getTempFileLocation($tracker->file_number));

        // Fetch current resolver location data
        try {
            $locationResult = app(FileLocationResolver::class)->resolve($tracker->file_number);
            $locationData = $this->presentLocationResult($locationResult);
            $tracker->setAttribute('logged_out_at', $locationData['logged_out_at'] ?? null);
            $tracker->setAttribute('duration_with_holder', $locationData['duration_with_holder'] ?? null);
            $tracker->setAttribute('registry', $locationData['registry'] ?? null);
            $tracker->setAttribute('receiving_department', $locationData['receiving_department'] ?? null);
        } catch (\Throwable $e) {
            \Log::warning('Unable to resolve location data for decoration', [
                'file_number' => $tracker->file_number,
                'error' => $e->getMessage()
            ]);
        }

        return $tracker;
    }

    /**
     * For a temporary "(T)" file number, resolve the current location of the
     * stripped base ("mother") file — only when it is logged out (IN_TRANSIT)
     * or sitting in the Archive. Returns null for non-temp files or when the
     * mother file has no resolvable location.
     */
    protected function getMotherFileLocation(?string $fileNumber): ?array
    {
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '' || !preg_match('/\(\s*T\s*\)\s*$/i', $fileNumber)) {
            return null;
        }

        $motherFileNumber = trim(preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $fileNumber));
        if ($motherFileNumber === '' || !$this->relatedFileWorthResolving($motherFileNumber)) {
            return null;
        }

        return $this->resolveRelatedFileLocation($motherFileNumber);
    }

    /**
     * Reciprocal of getMotherFileLocation(): for a mother (non-temp) file number,
     * resolve the current location of its temporary "(T)" counterpart — only when
     * that temp file actually exists and is logged out (IN_TRANSIT) or in the
     * Archive. A never-indexed "(T)" number resolves to PENDING_FILE and is
     * filtered out, so mother sheets without a temp file show nothing extra.
     */
    protected function getTempFileLocation(?string $fileNumber): ?array
    {
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '' || preg_match('/\(\s*T\s*\)\s*$/i', $fileNumber)) {
            return null;
        }

        // Temp numbers appear both with and without a space before the "(T)".
        // Only hit the (expensive) location resolver for a variant that actually
        // exists — the page-level existence cache filters out the common case of
        // a mother file that has no temporary counterpart at all.
        $result = null;
        if ($this->relatedFileWorthResolving($fileNumber . '(T)')) {
            $result = $this->resolveRelatedFileLocation($fileNumber . '(T)');
        }
        if ($result === null && $this->relatedFileWorthResolving($fileNumber . ' (T)')) {
            $result = $this->resolveRelatedFileLocation($fileNumber . ' (T)');
        }

        return $result;
    }

    /**
     * Resolve a counterpart file number (mother or temp) into the compact payload
     * the Tracking Sheet strip renders: home archive details (always shown as the
     * first line) plus, when logged out, the current location / holder / duration.
     */
    protected function resolveRelatedFileLocation(string $relatedFileNumber): ?array
    {
        try {
            $resolver = app(FileLocationResolver::class);
            $result   = $resolver->resolve($relatedFileNumber);
        } catch (\Throwable $e) {
            return null;
        }

        if (!in_array($result['status'], [FileLocationResolver::STATUS_IN_TRANSIT, FileLocationResolver::STATUS_IN_ARCHIVE], true)) {
            return null;
        }

        // Receiving officer (holder) while the file is logged out — taken from the
        // active movement, falling back to the tracker header.
        $holder = null;
        $relatedTracker = $result['tracker'] ?? null;
        if ($relatedTracker instanceof FileTracker) {
            $movement = $relatedTracker->getCurrentMovement();
            $holder = (is_array($movement) ? trim((string) ($movement['receiving_officer_name'] ?? '')) : '')
                ?: trim((string) ($relatedTracker->receiving_officer_name ?? ''));
            $holder = $holder !== '' ? $holder : null;
        }

        // Home archive details — registry + assigned rack/shelf. This is where the
        // file belongs (and returns to) even while it is logged out.
        $range     = $resolver->matchRange($relatedFileNumber);
        $registry  = ($range['registry'] ?? null) ?: ($result['registry'] ?? null);
        $rackShelf = $result['rack_shelf'] ?? null;
        $homeParts = array_filter([$registry, $rackShelf ? 'Rack/Shelf ' . $rackShelf : null]);

        return [
            'file_number'      => $relatedFileNumber,
            'status'           => $result['status'],
            'current_location' => $result['current_location'],
            'holder'           => $holder,
            'registry'         => $registry,
            'rack_shelf'       => $rackShelf,
            'home_location'    => $homeParts ? implode(' — ', $homeParts) : null,
            'held_since'       => $result['held_since'] ?? null,
            'duration_with_holder' => $result['duration_with_holder'] ?? null,
        ];
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
                'assignment_status' => $tracker->assignment_status,
                'file_tracker_id' => $tracker->id,
                'tracker_type' => 'file_tracker',
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

    protected function markNotificationAsRead(?int $notificationId, int $userId): void
    {
        if (!$notificationId) {
            return;
        }

        $notification = Notification::query()
            ->where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if ($notification) {
            $notification->markAsRead();
        }
    }

    protected function notifyTrackerCreatorOutcome(FileTracker $tracker, string $outcome, ?string $note = null): void
    {
        $creatorId = (int) $tracker->created_by;

        if ($creatorId <= 0) {
            return;
        }

        $creator = User::on('sqlsrv')->find($creatorId) ?? User::find($creatorId);
        if (!$creator) {
            return;
        }

        $title = $outcome === 'accepted'
            ? 'File received by officer'
            : 'File rejected by officer';

        $bodyParts = array_filter([
            $tracker->file_number ? 'File No: ' . $tracker->file_number : null,
            $tracker->receiving_office_name ? 'Office: ' . $tracker->receiving_office_name : null,
            $note ? 'Note: ' . $note : null,
        ]);

        $body = $bodyParts ? implode(' | ', $bodyParts) : 'Assignment update logged.';

        $this->notificationService->create(
            $creatorId,
            "file_tracking.assignment.{$outcome}",
            $title,
            $body,
            [
                'file_tracker_id' => $tracker->id,
                'file_number' => $tracker->file_number,
                'file_name' => $tracker->file_title,
                'assignment_status' => $tracker->assignment_status,
                'note' => $note,
            ],
            [
                'module' => 'file_tracking',
                'subject' => $title,
                'message' => $body,
                'name' => $creator->name,
            ]
        );
    }

    /**
     * Quickly create a lightweight receiving officer so users can continue logging trackers.
     */
    public function storeReceivingOfficer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'office_name' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide both first name and last name.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $firstName = trim($request->input('first_name'));
            $lastName = trim($request->input('last_name'));

            $officer = OtherReceivingOfficer::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone_number' => trim($request->input('phone_number')) ?: null,
                'email' => trim($request->input('email')) ?: null,
                'office_name' => trim($request->input('office_name')) ?: null,
                'designation' => trim($request->input('designation')) ?: null,
                'notes' => trim($request->input('notes')) ?: null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'is_active' => 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Receiving officer created successfully.',
                'data' => [
                    'id' => 'ro_' . $officer->id,
                    'first_name' => $officer->first_name,
                    'last_name' => $officer->last_name,
                    'name' => $officer->name,
                    'source' => 'receiving_officers',
                ],
            ], 201);
        } catch (Exception $exception) {
            Log::error('Unable to create receiving officer', [
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create receiving officer right now. Please try again.',
            ], 500);
        }
    }

    /**
     * Store Other Receiving Officer (external, not a system user)
     */
    public function storeOtherReceivingOfficer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'office_name' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide both first name and last name.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $firstName = trim($request->input('first_name'));
            $lastName = trim($request->input('last_name'));

            $otherOfficer = OtherReceivingOfficer::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone_number' => trim($request->input('phone_number')) ?: null,
                'email' => trim($request->input('email')) ?: null,
                'office_name' => trim($request->input('office_name')) ?: null,
                'designation' => trim($request->input('designation')) ?: null,
                'notes' => trim($request->input('notes')) ?: null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'is_active' => 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Other receiving officer created successfully.',
                'data' => [
                    'id' => 'other_' . $otherOfficer->id, // Prefix with 'other_' to distinguish from system users
                    'first_name' => $otherOfficer->first_name,
                    'last_name' => $otherOfficer->last_name,
                    'name' => $otherOfficer->name,
                    'office_name' => $otherOfficer->office_name,
                    'designation' => $otherOfficer->designation,
                    'type' => 'other',
                ],
                'meta' => [
                    'is_external' => true,
                    'table' => 'other_receiving_officers',
                ],
            ], 201);
        } catch (Exception $exception) {
            Log::error('Unable to create other receiving officer', [
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create other receiving officer right now. Please try again.',
            ], 500);
        }
    }

    public function shelfLocation(Request $request){
        $fileNumber = trim((string) $request->query('file_number', ''));
        if ($fileNumber === '') {
            return response()->json([
                'success' => false,
                'message' => 'File number is required.',
            ], 422);
        }

        $location = $this->getRackShelfLocation($fileNumber);

        return response()->json([
            'success' => true,
            'data' => [
                'file_number' => $fileNumber,
                'shelf_location' => $location,
                'rack_shelf_location' => $location,
            ],
            'message' => $location
                ? 'Rack/Shelf location resolved successfully.'
                : 'No rack/shelf information was found for this file number.',
        ]);
    }

    public function requestPreview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_number' => 'required|string|max:255',
            'tracker_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $fileNumber = trim((string) $request->query('file_number', ''));
        $trackerId = $request->query('tracker_id');

        $linkedIndexing = $this->findLinkedFileIndexing($fileNumber);
        $folderPath = $this->resolveRequestPreviewDirectory($fileNumber);
        $folderFiles = $folderPath ? $this->listRequestPreviewFiles($folderPath) : [];
        $isApproved = $this->canViewAllRequestPreviewFiles($fileNumber, $trackerId ? (int) $trackerId : null);

        $filesPayload = [];
        foreach ($folderFiles as $index => $entry) {
            $isFirst = $index === 0;
            $canOpen = $isApproved || $isFirst;

            $filesPayload[] = [
                'name' => $entry['name'],
                'size_bytes' => $entry['size_bytes'],
                'modified_at' => $entry['modified_at'],
                'is_first' => $isFirst,
                'can_open' => $canOpen,
                'open_url' => $canOpen
                    ? route('create-file-tracker.request-preview-file', [
                        'file_number' => $fileNumber,
                        'file_name' => $entry['name'],
                        'tracker_id' => $trackerId,
                    ])
                    : null,
            ];
        }

        $propertyPayload = $linkedIndexing ? [
            'id' => $linkedIndexing->id,
            'file_number' => $linkedIndexing->file_number,
            'file_name' => $linkedIndexing->file_name,
            'file_title' => $linkedIndexing->file_title,
            'mls_file_no' => $linkedIndexing->mls_file_no,
            'kangis_file_no' => $linkedIndexing->kangis_file_no,
            'new_kangis_file_no' => $linkedIndexing->new_kangis_file_no,
            'tracking_id' => $linkedIndexing->tracking_id,
            'reg_no' => $linkedIndexing->reg_no,
            'land_use' => $linkedIndexing->land_use,
            'applicant_name' => $linkedIndexing->applicant_name,
            'owner_name' => $linkedIndexing->owner_name,
            'plot_no' => $linkedIndexing->plot_no,
            'block_no' => $linkedIndexing->block_no,
            'district' => $linkedIndexing->district,
            'lga' => $linkedIndexing->lga,
            'location' => $linkedIndexing->location,
            'phone' => $linkedIndexing->phone,
            'created_at' => $linkedIndexing->created_at,
        ] : null;

        return response()->json([
            'success' => true,
            'data' => [
                'file_number' => $fileNumber,
                'property' => $propertyPayload,
                'folder' => [
                    'base_path' => $folderPath,
                    'exists' => $folderPath !== null,
                    'file_count' => count($filesPayload),
                    'files' => $filesPayload,
                ],
                'can_view_all_files' => $isApproved,
            ],
        ]);
    }

    public function requestPreviewFile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_number' => 'required|string|max:255',
            'file_name' => 'required|string|max:255',
            'tracker_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            abort(422, 'Invalid request parameters.');
        }

        $fileNumber = trim((string) $request->query('file_number', ''));
        $requestedName = trim((string) $request->query('file_name', ''));
        $trackerId = $request->query('tracker_id');

        $folderPath = $this->resolveRequestPreviewDirectory($fileNumber);
        if (!$folderPath) {
            abort(404, 'Folder not found.');
        }

        $entries = $this->listRequestPreviewFiles($folderPath);
        if (empty($entries)) {
            abort(404, 'No files available.');
        }

        $matched = null;
        foreach ($entries as $entry) {
            if (strcasecmp($entry['name'], $requestedName) === 0) {
                $matched = $entry;
                break;
            }
        }

        if (!$matched) {
            abort(404, 'File not found.');
        }

        $isApproved = $this->canViewAllRequestPreviewFiles($fileNumber, $trackerId ? (int) $trackerId : null);
        $firstFileName = $entries[0]['name'] ?? null;

        if (!$isApproved && (!$firstFileName || strcasecmp($matched['name'], $firstFileName) !== 0)) {
            abort(403, 'Only the first file is available until recommendation and approval are completed.');
        }

        if (!is_file($matched['path'])) {
            abort(404, 'File is not available on disk.');
        }

        $extension = strtolower(pathinfo($matched['path'], PATHINFO_EXTENSION));
        $mimeMap = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'pdf'  => 'application/pdf',
            'tif'  => 'image/tiff',
            'tiff' => 'image/tiff',
        ];
        $mimeType = $mimeMap[$extension] ?? (@mime_content_type($matched['path']) ?: 'application/octet-stream');

        return response()->file($matched['path'], [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'inline; filename="' . rawurlencode(basename($matched['path'])) . '"',
            'Cache-Control'       => 'private, max-age=300',
        ]);
    }

    protected function resolveRequestPreviewDirectory(string $fileNumber): ?string
    {
        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            return null;
        }

        $root = 'C:\\demo';
        $directPath = $root . DIRECTORY_SEPARATOR . $fileNumber . DIRECTORY_SEPARATOR . 'A4';

        if (is_dir($directPath)) {
            return $directPath;
        }

        $sanitized = Str::of($fileNumber)
            ->replace(['/', '\\\\', ':', '*', '?', '"', '<', '>', '|'], '-')
            ->trim()
            ->toString();

        $fallbackPath = $root . DIRECTORY_SEPARATOR . $sanitized . DIRECTORY_SEPARATOR . 'A4';

        if (is_dir($fallbackPath)) {
            return $fallbackPath;
        }

        return null;
    }

    protected function listRequestPreviewFiles(string $folderPath): array
    {
        $rawEntries = @scandir($folderPath) ?: [];
        $files = [];

        foreach ($rawEntries as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $fullPath = $folderPath . DIRECTORY_SEPARATOR . $name;
            if (!is_file($fullPath)) {
                continue;
            }

            $files[] = [
                'name' => $name,
                'path' => $fullPath,
                'size_bytes' => @filesize($fullPath) ?: 0,
                'modified_at' => @filemtime($fullPath) ?: null,
            ];
        }

        usort($files, function (array $a, array $b): int {
            return strnatcasecmp($a['name'], $b['name']);
        });

        return $files;
    }

    protected function canViewAllRequestPreviewFiles(string $fileNumber, ?int $trackerId = null): bool
    {
        $approvedStatuses = ['cross_approved', 'approved', FileTracker::STATUS_COMPLETED];

        if ($trackerId) {
            $tracker = FileTracker::find($trackerId);
            if ($tracker) {
                return $tracker->workflow_type === FileTracker::WORKFLOW_CROSS_MODULE_REQUEST
                    && in_array((string) $tracker->status, $approvedStatuses, true);
            }
        }

        $needle = strtoupper(trim($fileNumber));
        if ($needle === '') {
            return false;
        }

        $latest = FileTracker::query()
            ->where('workflow_type', FileTracker::WORKFLOW_CROSS_MODULE_REQUEST)
            ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(file_number, '')))) = ?", [$needle])
            ->orderByDesc('id')
            ->first();

        if (!$latest) {
            return false;
        }

        return in_array((string) $latest->status, $approvedStatuses, true);
    }

    protected function getRackShelfLocation(?string $fileNumber): ?string
    {
        $normalized = trim((string) $fileNumber);
        if ($normalized === '') {
            return null;
        }

        $key = mb_strtoupper($normalized);
        if (array_key_exists($key, $this->rackShelfCache)) {
            return $this->rackShelfCache[$key];
        }

        try {
            $location = DB::connection('sqlsrv')
                ->table('print_label_batch_items')
                ->whereRaw('UPPER(file_number) = ?', [$key])
                ->orderByDesc('id')
                ->value('shelf_location');

            $location = $location !== null ? trim((string) $location) : null;
        } catch (Exception $exception) {
            Log::warning('Unable to fetch rack/shelf location', [
                'file_number' => $fileNumber,
                'error' => $exception->getMessage(),
            ]);
            $location = null;
        }

        $this->rackShelfCache[$key] = $location;

        return $location;
    }

    /**
     * The file_indexings lookup variants for a file number: the number itself
     * plus, for a temporary "(T)" file, its stripped base number — both
     * upper-cased for cache keying.
     */
    protected function indexingVariantsFor(string $normalized): array
    {
        $key = mb_strtoupper($normalized);
        $stripped = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $normalized);

        return array_values(array_unique(array_filter([$key, mb_strtoupper((string) $stripped)])));
    }

    /**
     * Bulk-load the latest file_indexings {id, created_at} for every file number
     * (and its stripped "(T)" base) on a results page in one indexed query, so
     * getFileIndexingCreatedAt() never scans the 130k-row file_indexings table
     * per row (N+1).
     */
    protected function primeIndexingCreatedAtCache($fileNumbers): void
    {
        $variants = collect($fileNumbers)
            ->flatMap(fn ($value) => $this->indexingVariantsFor(trim((string) $value)))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values();

        // Mark the cache primed so the getter uses the fast path even when the
        // page has no matching indexing rows (empty result is a valid answer).
        if ($this->indexingRowCache === null) {
            $this->indexingRowCache = [];
        }

        if ($variants->isEmpty()) {
            return;
        }

        try {
            $rows = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->whereIn('file_number', $variants->all())
                ->orderBy('id')
                ->get(['id', 'file_number', 'created_at', 'file_title']);
        } catch (Exception $exception) {
            Log::warning('Unable to prime file indexing cache', [
                'error' => $exception->getMessage(),
            ]);
            return;
        }

        // Ascending id order means the last row written per key is the highest id.
        foreach ($rows as $row) {
            $this->indexingRowCache[mb_strtoupper(trim((string) $row->file_number))] = [
                'id' => $row->id,
                'created_at' => $row->created_at,
                'file_title' => $row->file_title !== null ? trim((string) $row->file_title) : null,
            ];
        }
    }

    /**
     * Resolve the original file_title of the matching file_indexings
     * row for a file number. Returns a string (or null).
     */
    protected function getFileIndexingTitle(?string $fileNumber): ?string
    {
        $normalized = trim((string) $fileNumber);
        if ($normalized === '') {
            return null;
        }

        $key = mb_strtoupper($normalized);
        if (array_key_exists($key, $this->indexingTitleCache)) {
            return $this->indexingTitleCache[$key];
        }

        $variants = $this->indexingVariantsFor($normalized);

        // Fast path: served from the page-level prime (no query).
        if ($this->indexingRowCache !== null) {
            $best = null;
            foreach ($variants as $variant) {
                $row = $this->indexingRowCache[$variant] ?? null;
                if ($row && ($best === null || $row['id'] > $best['id'])) {
                    $best = $row;
                }
            }
            $title = $best && isset($best['file_title'])
                ? $best['file_title']
                : null;
            $this->indexingTitleCache[$key] = $title;
            return $title;
        }

        try {
            $title = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->whereIn('file_number', $variants)
                ->orderByDesc('id')
                ->value('file_title');

            $title = $title !== null ? trim((string) $title) : null;
        } catch (Exception $exception) {
            Log::warning('Unable to fetch file indexing file_title', [
                'file_number' => $fileNumber,
                'error' => $exception->getMessage(),
            ]);
            $title = null;
        }

        $this->indexingTitleCache[$key] = $title;

        return $title;
    }

    /**
     * Resolve the original created_at timestamp of the matching file_indexings
     * row for a file number. Returns an ISO-8601 string (or null) so the front-end
     * can render it as the Movement History "home location" Date & Time / Log In.
     */
    protected function getFileIndexingCreatedAt(?string $fileNumber): ?string
    {
        $normalized = trim((string) $fileNumber);
        if ($normalized === '') {
            return null;
        }

        $key = mb_strtoupper($normalized);
        if (array_key_exists($key, $this->indexingCreatedAtCache)) {
            return $this->indexingCreatedAtCache[$key];
        }

        // Temporary file numbers like "RES-2026-1(T)" fall back to their base
        // number, so the created_at can come from either indexing row — take the
        // most recently indexed (highest id) among the variants.
        $variants = $this->indexingVariantsFor($normalized);

        // Fast path: served from the page-level prime (no query).
        if ($this->indexingRowCache !== null) {
            $best = null;
            foreach ($variants as $variant) {
                $row = $this->indexingRowCache[$variant] ?? null;
                if ($row && ($best === null || $row['id'] > $best['id'])) {
                    $best = $row;
                }
            }
            $createdAt = $best && $best['created_at']
                ? \Illuminate\Support\Carbon::parse($best['created_at'])->toIso8601String()
                : null;
            $this->indexingCreatedAtCache[$key] = $createdAt;
            return $createdAt;
        }

        try {
            // Plain equality (no UPPER wrapping) so the file_number index seeks —
            // the CI collation already matches case-insensitively.
            $createdAt = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->whereIn('file_number', $variants)
                ->orderByDesc('id')
                ->value('created_at');

            $createdAt = $createdAt
                ? \Illuminate\Support\Carbon::parse($createdAt)->toIso8601String()
                : null;
        } catch (Exception $exception) {
            Log::warning('Unable to fetch file indexing created_at', [
                'file_number' => $fileNumber,
                'error' => $exception->getMessage(),
            ]);
            $createdAt = null;
        }

        $this->indexingCreatedAtCache[$key] = $createdAt;

        return $createdAt;
    }

    /**
     * Collect movement-log entries from all EARLIER trackers (lower id) that share
     * this tracker's file number, in chronological order. This lets the front-end
     * render a continuous movement timeline: when a file is logged back into the
     * registry (completed) and later re-tracked to a new office, the new tracker's
     * card shows the prior cycle (logout → completed/return) above its own rows.
     */
    /**
     * Bulk-load the full tracker history for a set of file numbers into the per-request
     * cache so getPriorMovements() avoids an N+1 query when decorating a page of results.
     */
    protected function primeMovementHistoryCache($fileNumbers): void
    {
        $keys = collect($fileNumbers)
            ->map(fn ($value) => mb_strtoupper(trim((string) $value)))
            ->filter(fn ($value) => $value !== '')
            ->reject(fn ($value) => array_key_exists($value, $this->fileTrackerMovementHistoryCache))
            ->unique()
            ->values();

        if ($keys->isEmpty()) {
            return;
        }

        $grouped = FileTracker::query()
            ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(file_number)))'), $keys->all())
            ->orderBy('id')
            ->get(['id', 'movement_log', 'receiving_officer_name', 'receiving_officer_id', 'file_number'])
            ->groupBy(fn ($row) => mb_strtoupper(trim((string) $row->file_number)));

        foreach ($keys as $key) {
            // Always set the key (even to an empty collection) so getPriorMovements()
            // treats it as primed and never re-queries.
            $this->fileTrackerMovementHistoryCache[$key] = $grouped->get($key, collect());
        }
    }

    /**
     * Build the candidate counterpart file numbers for a single file:
     *   - a temporary "(T)" file  -> its stripped mother number
     *   - a mother (non-temp) file -> its "X(T)" and "X (T)" temp variants
     * Mirrors the candidates that getMotherFileLocation()/getTempFileLocation()
     * would otherwise hand straight to the location resolver.
     */
    protected function relatedCandidatesFor(?string $fileNumber): array
    {
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '') {
            return [];
        }

        if (preg_match('/\(\s*T\s*\)\s*$/i', $fileNumber)) {
            $mother = trim(preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $fileNumber));
            return $mother === '' ? [] : [$mother];
        }

        return [$fileNumber . '(T)', $fileNumber . ' (T)'];
    }

    /**
     * Bulk-load, for a page of file numbers, which of their mother/temp
     * counterparts actually exist in file_tracker or file_indexings. The
     * counterpart location strip only ever renders for files that exist in one
     * of those tables (IN_TRANSIT lives in file_tracker, IN_ARCHIVE in
     * file_indexings), so priming this set lets getMotherFileLocation()/
     * getTempFileLocation() skip the ~22-query location resolver for the vast
     * majority of rows that have no counterpart — turning a per-row N+1 into two
     * batched existence queries for the whole page.
     */
    protected function primeRelatedLocationCache($fileNumbers): void
    {
        $candidates = collect($fileNumbers)
            ->flatMap(fn ($value) => $this->relatedCandidatesFor($value))
            ->map(fn ($value) => mb_strtoupper(trim((string) $value)))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values();

        $this->relatedFileExistsCache = [];

        if ($candidates->isEmpty()) {
            return;
        }

        $exists = [];
        foreach (['file_tracker', 'file_indexings'] as $table) {
            try {
                // Plain equality (no UPPER/TRIM wrapping) so the existing
                // file_number index does a seek: the SQL_Latin1_General_CP1_CI_AS
                // collation already matches case-insensitively and ignores trailing
                // spaces, so the candidate set matches regardless of stored casing.
                $found = DB::connection('sqlsrv')
                    ->table($table)
                    ->whereIn('file_number', $candidates->all())
                    ->pluck('file_number');
                foreach ($found as $value) {
                    $exists[mb_strtoupper(trim((string) $value))] = true;
                }
            } catch (Exception $exception) {
                // A missing table or transient error must not break the listing —
                // fall back to on-demand resolution for the affected rows.
                Log::warning('Unable to prime related-file existence cache', [
                    'table' => $table,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->relatedFileExistsCache = $exists;
    }

    /**
     * True when the given counterpart file number is worth resolving — i.e. the
     * page-level existence cache was not primed (resolve on demand), or the cache
     * confirms the file exists in file_tracker/file_indexings.
     */
    protected function relatedFileWorthResolving(string $candidate): bool
    {
        if ($this->relatedFileExistsCache === null) {
            return true;
        }

        return isset($this->relatedFileExistsCache[mb_strtoupper(trim($candidate))]);
    }

    /**
     * Related files in the selected file's parcel-update / merger group (Change of Purpose,
     * Subdivision, Merger, Extension, Separation, Temporary File). Powers the small "Related
     * Files" panel on the File Details form so a clerk logging a child file (or logging one out)
     * can see every file in the lineage — e.g. all source files that fed a merger.
     */
    public function mergerGroup(Request $request)
    {
        $fileNumber = trim((string) $request->query('file_number', ''));
        if ($fileNumber === '') {
            return response()->json(['in_group' => false, 'members' => []]);
        }

        $group = app(\App\Services\FileMergerService::class)->resolveGroup($fileNumber);
        if (empty($group)) {
            return response()->json(['in_group' => false, 'members' => []]);
        }

        $selfNorm = mb_strtoupper($fileNumber);
        $fmt = function ($date) {
            if (empty($date)) {
                return null;
            }
            try {
                return \Illuminate\Support\Carbon::parse($date)->format('M j, Y');
            } catch (\Throwable $e) {
                return null;
            }
        };

        // Which related files have actually been through file tracking (were logged out at some
        // point). Decommissioned parents that were never tracked carry no movement, so the panel
        // has no reason to list them — only files with real tracking activity are shown.
        $memberNorms = array_map(fn ($m) => mb_strtoupper(trim((string) $m['file_number'])), $group);
        $activeSet = [];
        foreach (FileTracker::query()
            ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(file_number)))'), $memberNorms)
            ->get(['file_number', 'movement_log']) as $t) {
            $log = is_array($t->movement_log) ? $t->movement_log : json_decode((string) ($t->movement_log ?? '[]'), true);
            if (is_array($log) && count($log) > 0) {
                $activeSet[mb_strtoupper(trim((string) $t->file_number))] = true;
            }
        }

        $members = array_map(function ($m) use ($selfNorm, $fmt, $activeSet) {
            $norm = mb_strtoupper(trim((string) $m['file_number']));
            return [
                'file_number' => $m['file_number'],
                'role' => $m['role'],
                'file_title' => $m['file_title'],
                'location' => $m['location'],
                'date_commissioned' => $fmt($m['date_commissioned'] ?? null),
                'date_decommissioned' => $fmt($m['date_decommissioned'] ?? null),
                'is_self' => $norm === $selfNorm,
                'logged_out' => isset($activeSet[$norm]),
            ];
        }, $group);

        return response()->json([
            'in_group' => true,
            'merger_id' => $group[0]['merger_id'] ?? null,
            'parent_count' => count(array_filter($group, fn ($m) => ($m['role'] ?? '') === 'parent')),
            'child_count' => count(array_filter($group, fn ($m) => ($m['role'] ?? '') !== 'parent')),
            'members' => $members,
        ]);
    }

    protected function getPriorMovements(FileTracker $tracker): array
    {
        $fileNumber = trim((string) $tracker->file_number);
        if ($fileNumber === '') {
            return [];
        }

        $key = mb_strtoupper($fileNumber);
        if (!array_key_exists($key, $this->fileTrackerMovementHistoryCache)) {
            $this->fileTrackerMovementHistoryCache[$key] = FileTracker::query()
                ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$key])
                ->orderBy('id')
                ->get(['id', 'movement_log', 'receiving_officer_name', 'receiving_officer_id']);
        }

        $priorMovements = [];
        foreach ($this->fileTrackerMovementHistoryCache[$key] as $row) {
            // Only entries from trackers created before this one.
            if ((int) $row->id >= (int) $tracker->id) {
                continue;
            }

            $log = is_array($row->movement_log)
                ? $row->movement_log
                : json_decode((string) ($row->movement_log ?? '[]'), true);

            if (is_array($log)) {
                foreach ($log as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }

                    // When a file is logged back into the registry, the entry records the
                    // performing clerk as its receiving officer (receiving_officer_id ==
                    // user_id) — that's effectively the "created by", not the cycle's
                    // receiving officer. Surface the parent tracker's receiving officer
                    // instead so the Completed/return row shows the correct name.
                    $recvId = $entry['receiving_officer_id'] ?? null;
                    $userId = $entry['user_id'] ?? null;
                    if ($recvId !== null && $userId !== null && (int) $recvId === (int) $userId) {
                        $entry['receiving_officer_name'] = $row->receiving_officer_name ?: null;
                        $entry['receiving_officer_id']   = $row->receiving_officer_id;
                    }

                    $priorMovements[] = $entry;
                }
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

        // Order the merged history chronologically so the timeline reads correctly
        // across cycles (e.g. a log-out at 15:17 must precede the completed/return at
        // 15:45). Per-tracker array order can place a later "Completed" entry before
        // an earlier log-out, so we sort by each entry's representative event time.
        usort($priorMovements, function ($a, $b) {
            return $this->movementSortTimestamp($a) <=> $this->movementSortTimestamp($b);
        });

        return $priorMovements;
    }

    /**
     * Prior movements with the Default In-process In-transit Tracking line in front.
     *
     * Every file commissioned through KLAES starts life at the File Commissioning
     * Office, so that is the first line of its history — above any log it already
     * has. The line is PINNED first rather than left to the chronological sort: a
     * legacy file recommissioned into KLAES can carry movements older than its
     * commissioning date, and the line must still open the timeline.
     *
     * Skipped for a synthetic DIIT card, whose own movement_log is that line.
     *
     * @return array<int, array<string,mixed>>
     */
    protected function withCommissioningLine(FileTracker $tracker): array
    {
        $priorMovements = $this->getPriorMovements($tracker);

        if ($tracker->getAttribute('is_diit')) {
            return $priorMovements;
        }

        $service = app(FileCommissioningTrackingService::class);
        $commissioning = $service->infoFor($tracker->file_number);

        if ($commissioning === null) {
            return $priorMovements;
        }

        // The file left the commissioning office when it was first logged anywhere,
        // which closes the line; with no movement at all it is still in process there.
        $ownLog = is_array($tracker->movement_log) ? $tracker->movement_log : [];

        // A file commissioned with a next destination already carries the line as a
        // real logged movement — do not show it twice.
        if ($service->logHasCommissioningLine($priorMovements, $ownLog)) {
            return $priorMovements;
        }
        $timestamps = [];
        foreach (array_merge($priorMovements, $ownLog) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $stamp = $this->movementSortTimestamp($entry);
            if ($stamp < PHP_FLOAT_MAX) {
                $timestamps[] = $stamp;
            }
        }

        $closedAt = empty($timestamps)
            ? null
            : \Carbon\Carbon::createFromTimestamp((int) min($timestamps));

        array_unshift($priorMovements, $service->movementEntry($commissioning, $closedAt));

        return $priorMovements;
    }

    /**
     * Best-effort sortable unix timestamp for a movement-log entry. Prefers the
     * arrival (log-in) time, then the departure (log-out) time, then the entry's
     * creation timestamp. Unparseable entries sort last (stable).
     */
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
                return (float) \Illuminate\Support\Carbon::parse($candidate)->timestamp;
            } catch (\Throwable $e) {
                continue;
            }
        }

        return PHP_FLOAT_MAX;
    }

    public function acceptAssignment(Request $request, $id)
    {
        $tracker = FileTracker::find($id);
        $user = $request->user();

        if (!$tracker) {
            return response()->json([
                'success' => false,
                'message' => 'File tracker not found.',
            ], 404);
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        // Permission Check:
        // Normally, only the receiving officer can accept.
        // For "Quick Acceptance" (Receiver is beside me), we allow the person who
        // initiated the movement (the sender) to accept it on their behalf.
        $movementLog = $tracker->movement_log ?: [];
        $pendingEntry = null;
        for ($index = count($movementLog) - 1; $index >= 0; $index--) {
            if (strtolower((string) ($movementLog[$index]['status'] ?? '')) === 'pending_acceptance') {
                $pendingEntry = $movementLog[$index];
                break;
            }
        }

        // Temporary override: allow any authenticated user to accept pending assignments.
        // TODO: restore strict receiving-officer/initiator check when workflow policy is finalized.

        $notificationId = $request->input('notification_id');

        DB::beginTransaction();

        try {
            $movementLog = $tracker->movement_log ?: [];
            $updated = false;

            for ($index = count($movementLog) - 1; $index >= 0; $index--) {
                $status = strtolower((string) ($movementLog[$index]['status'] ?? ''));
                if ($status === 'pending_acceptance') {
                    $movementLog[$index]['log_in_time'] = now()->format('H:i');
                    $movementLog[$index]['log_in_date'] = now()->format('Y-m-d');
                    $movementLog[$index]['status'] = 'active';
                    $movementLog[$index]['accepted_by'] = $user->id;
                    $movementLog[$index]['accepted_by_name'] = $user->name ?? ($user->email ?? 'User');
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No pending movement found to accept.',
                ], 422);
            }

            $tracker->movement_log = $movementLog;
            $tracker->assignment_status = FileTracker::ASSIGNMENT_ACCEPTED;
            $tracker->assignment_accepted_at = now();
            $tracker->current_office_code = $tracker->receiving_office_code;
            $tracker->current_office_name = $tracker->receiving_office_name;
            $tracker->current_holder = $user->name ?? ($user->email ?? 'User');
            $tracker->current_handler = $tracker->current_holder;
            $tracker->save();

            $tracker->refresh();
            DB::commit();

            if ($notificationId) {
                $this->markNotificationAsRead((int) $notificationId, $user->id);
            }

            $this->notifyTrackerCreatorOutcome($tracker, 'accepted');
            $tracker = $this->decorateTrackerForResponse($tracker);

            return response()->json([
                'success' => true,
                'message' => 'File accepted successfully.',
                'data' => $tracker,
            ]);
        } catch (Exception $exception) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    public function rejectAssignment(Request $request, $id)
    {
        $tracker = FileTracker::find($id);
        $user = $request->user();

        if (!$tracker) {
            return response()->json([
                'success' => false,
                'message' => 'File tracker not found.',
            ], 404);
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        if ((int) $tracker->receiving_officer_id !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to reject this tracker.',
            ], 403);
        }

        $note = trim((string) $request->input('note', ''));

        $notificationId = $request->input('notification_id');

        DB::beginTransaction();

        try {
            $movementLog = $tracker->movement_log ?: [];
            $updated = false;

            for ($index = count($movementLog) - 1; $index >= 0; $index--) {
                $status = strtolower((string) ($movementLog[$index]['status'] ?? ''));
                if ($status === 'pending_acceptance') {
                    $movementLog[$index]['status'] = 'rejected';
                    if ($note !== '') {
                        $movementLog[$index]['reject_note'] = $note;
                    }
                    $movementLog[$index]['rejected_at'] = now()->toIso8601String();
                    $movementLog[$index]['rejected_by'] = $user->id;
                    $movementLog[$index]['rejected_by_name'] = $user->name ?? ($user->email ?? 'User');
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No pending movement found to reject.',
                ], 422);
            }

            $tracker->movement_log = $movementLog;
            $tracker->assignment_status = FileTracker::ASSIGNMENT_REJECTED;
            $tracker->assignment_accepted_at = null;
            $tracker->save();
            $tracker->refresh();

            DB::commit();

            if ($notificationId) {
                $this->markNotificationAsRead((int) $notificationId, $user->id);
            }

            $this->notifyTrackerCreatorOutcome($tracker, 'rejected', $note ?: null);
            $tracker = $this->decorateTrackerForResponse($tracker);

            return response()->json([
                'success' => true,
                'message' => 'Assignment rejected successfully.',
                'data' => $tracker,
            ]);
        } catch (Exception $exception) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // KANGIS Registry Checkout Approval
    // ──────────────────────────────────────────────────────────────

    /**
     * List checkout approval requests.
     * status filter: all | pending | approved | rejected
     */
    public function kangisCheckoutList(Request $request)
    {
        try {
            $status  = $request->input('status', 'all');
            $search  = trim((string) $request->input('search', ''));
            $module  = trim((string) $request->input('module', ''));

            $query = DB::connection('sqlsrv')->table('kangis_checkout_approvals')
                ->orderByDesc('created_at');

            if ($module !== '') {
                $query->where('module', $module);
            }

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $needle = '%' . $search . '%';
                    $q->where('file_number', 'like', $needle)
                      ->orWhere('file_title', 'like', $needle)
                      ->orWhere('requested_by_name', 'like', $needle)
                      ->orWhere('request_no', 'like', $needle);
                });
            }

            $rows = $query->get();

            return response()->json([
                'success' => true,
                'data'    => $rows,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit a new checkout request.
     */
    public function kangisCheckoutRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_number'          => 'nullable|string|max:120',
            'file_title'           => 'nullable|string|max:255',
            'purpose'              => 'required|string|max:1000',
            'expected_return_date' => 'nullable|date',
            'office_code'          => 'nullable|string|max:60',
            'office_name'          => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please fix the validation errors.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();

            // Generate sequential request_no: KCA/YYYY/0001
            $year  = now()->year;
            $count = DB::connection('sqlsrv')
                ->table('kangis_checkout_approvals')
                ->whereYear('created_at', $year)
                ->count();
            $requestNo = sprintf('KCA/%d/%04d', $year, $count + 1);

            $id = DB::connection('sqlsrv')->table('kangis_checkout_approvals')->insertGetId([
                'request_no'           => $requestNo,
                'file_number'          => trim((string) $request->input('file_number', '')),
                'file_title'           => trim((string) $request->input('file_title', '')),
                'requested_by'         => $user->id,
                'requested_by_name'    => trim($user->first_name . ' ' . $user->last_name),
                'requested_office_code'=> $request->input('office_code'),
                'requested_office_name'=> $request->input('office_name'),
                'purpose'              => $request->input('purpose'),
                'expected_return_date' => $request->input('expected_return_date'),
                'status'               => 'pending',
                'module'               => $request->input('module'),
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            $record = DB::connection('sqlsrv')->table('kangis_checkout_approvals')->find($id);

            return response()->json([
                'success' => true,
                'message' => "Checkout request {$requestNo} submitted. Awaiting registry approval.",
                'data'    => $record,
            ], 201);
        } catch (Exception $e) {
            Log::error('kangisCheckoutRequest failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Unable to submit request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve a checkout request and generate an Acknowledgement Number.
     */
    public function kangisCheckoutApprove(Request $request, $id)
    {
        $user = Auth::user();

        $record = DB::connection('sqlsrv')->table('kangis_checkout_approvals')->find($id);

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Request not found.'], 404);
        }

        if ($record->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been ' . $record->status . '.',
            ], 422);
        }

        try {
            // Generate acknowledgement number: ACK/YYYY/NNNN
            $year  = now()->year;
            $count = DB::connection('sqlsrv')
                ->table('kangis_checkout_approvals')
                ->where('status', 'approved')
                ->whereYear('approved_at', $year)
                ->count();
            $ackNo = sprintf('ACK/%d/%04d', $year, $count + 1);

            $approverDesignation = trim((string) ($user->designation ?? ''));
            if ($approverDesignation === '') {
                $approverDesignation = trim((string) ($user->rank ?? ''));
            }
            if ($approverDesignation === '') {
                $approverDesignation = trim((string) ($user->user_type ?? ''));
            }
            if ($approverDesignation === '') {
                $approverDesignation = trim((string) ($user->type ?? ''));
            }
            if ($approverDesignation === '') {
                $approverDesignation = 'Registry Officer';
            }

            DB::connection('sqlsrv')->table('kangis_checkout_approvals')
                ->where('id', $id)
                ->update([
                    'status'                  => 'approved',
                    'approved_by'             => $user->id,
                    'approved_by_name'        => trim($user->first_name . ' ' . $user->last_name),
                    'approved_by_designation' => $approverDesignation,
                    'approved_at'             => now(),
                    'acknowledgement_no'      => $ackNo,
                    'updated_at'              => now(),
                ]);

            $record = DB::connection('sqlsrv')->table('kangis_checkout_approvals')->find($id);

            return response()->json([
                'success' => true,
                'message' => "Request approved. Acknowledgement No: {$ackNo}",
                'data'    => $record,
            ]);
        } catch (Exception $e) {
            Log::error('kangisCheckoutApprove failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Approval failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a checkout request.
     */
    public function kangisCheckoutReject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Rejection reason is required.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        $record = DB::connection('sqlsrv')->table('kangis_checkout_approvals')->find($id);

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Request not found.'], 404);
        }

        if ($record->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been ' . $record->status . '.',
            ], 422);
        }

        try {
            DB::connection('sqlsrv')->table('kangis_checkout_approvals')
                ->where('id', $id)
                ->update([
                    'status'           => 'rejected',
                    'rejected_by'      => $user->id,
                    'rejected_by_name' => trim($user->first_name . ' ' . $user->last_name),
                    'rejected_at'      => now(),
                    'rejection_reason' => $request->input('rejection_reason'),
                    'updated_at'       => now(),
                ]);

            $record = DB::connection('sqlsrv')->table('kangis_checkout_approvals')->find($id);

            return response()->json([
                'success' => true,
                'message' => 'Checkout request rejected.',
                'data'    => $record,
            ]);
        } catch (Exception $e) {
            Log::error('kangisCheckoutReject failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Rejection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // KANGIS Approval Workflow Endpoints
    // ──────────────────────────────────────────────────────────────

    /**
     * Get the workflow progress and next step suggestion for a tracker.
     */
    public function workflowProgress($id)
    {
        $tracker = FileTracker::find($id);

        if (!$tracker) {
            return response()->json(['success' => false, 'message' => 'Tracker not found.'], 404);
        }

        if (!$tracker->isKangis3Step()) {
            return response()->json([
                'success' => false,
                'message' => 'This tracker does not use the KANGIS approval workflow.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'workflow_type' => $tracker->workflow_type,
                'workflow_step' => $tracker->workflow_step,
                'progress' => $tracker->getWorkflowProgress(),
                'next_step' => $tracker->getNextStepDefinition(),
                'current_step' => $tracker->getCurrentStepDefinition(),
                'workflow_config' => $tracker->workflow_config,
            ],
        ]);
    }

    /**
     * Return the KANGIS approval workflow definition (static reference data).
     */
    public function workflowDefinition()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'type' => FileTracker::WORKFLOW_KANGIS_APPROVAL,
                'steps' => FileTracker::KANGIS_4STEP_DEFINITION,
                'purposes' => [
                    FileTracker::PURPOSE_SUBMISSION => 'Submission',
                    FileTracker::PURPOSE_RECOMMENDATION => 'Recommendation',
                    FileTracker::PURPOSE_APPROVAL => 'Approval',
                    FileTracker::PURPOSE_PROCESSING => 'Processing',
                ],
            ],
        ]);
    }

    /**
     * Mark a file tracker as printed
     */
    public function markAsPrinted(Request $request, $id)
    {
        $tracker = FileTracker::find($id);

        if (!$tracker) {
            $tracker = FileTracker::where('tracking_id', $id)->first();
        }

        if (!$tracker) {
            return response()->json([
                'success' => false,
                'message' => 'Tracker not found.'
            ], 404);
        }

        try {
            $oldData = ['printed' => $tracker->printed];
            $tracker->update(['printed' => true]);

            // Log action in AuditLog via AuditService
            try {
                $auditService = app(\App\Services\AuditService::class);
                $auditService->logAction(
                    'UPDATED',
                    'FileTracker',
                    $tracker->id,
                    $oldData,
                    ['printed' => true],
                    "Marked file tracker {$tracker->tracking_id} as printed"
                );
            } catch (\Exception $ae) {
                Log::warning('CreateFileTrackerController markAsPrinted audit logging failed: ' . $ae->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'File request sheet printed.',
                'data' => $this->decorateTrackerForResponse($tracker),
            ]);
        } catch (\Exception $e) {
            Log::error('markAsPrinted failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark printed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the dynamic datadriven file request sheet for a tracker.
     */
    public function requestSheet($id)
    {
        $tracker = FileTracker::find($id);

        if (!$tracker) {
            $tracker = FileTracker::where('tracking_id', $id)->first();
        }

        if (!$tracker) {
            abort(404, 'File Tracker not found.');
        }

        // Fetch officer's rank from User table if receiving_officer_id is present
        $officerRank = '—';
        $officer = null;
        if (!empty($tracker->receiving_officer_id)) {
            $officer = \App\Models\User::find($tracker->receiving_officer_id);
            if ($officer && !empty($officer->rank)) {
                $officerRank = $officer->rank;
            }
        }

        // The requester's passport photo, printed beside their details on the sheet.
        // Falls back to a name lookup for older rows that stored no officer id.
        $officerPhoto = $officer
            ? $officer->profile_url
            : \App\Support\UserPhoto::forName($tracker->receiving_officer_name ?? null);

        return view('create_file_tracker_page.file_request_sheet', compact('tracker', 'officerRank', 'officerPhoto'));
    }

    /**
     * Update the Manual File Request Sheet details for a tracker.
     * Only the fields shown on the printed sheet are updatable here; the file
     * number stays read-only so the tracker keeps pointing at the same file.
     * POST /create-file-tracker/{id}/request-sheet
     */
    public function updateRequestSheet(Request $request, $id)
    {
        $tracker = FileTracker::find($id);

        if (!$tracker) {
            $tracker = FileTracker::where('tracking_id', $id)->first();
        }

        if (!$tracker) {
            return response()->json([
                'success' => false,
                'message' => 'File Tracker not found.'
            ], 404);
        }

        $validated = $request->validate([
            'file_request_type' => 'nullable|string|in:MANUAL,SUBMITTED',
            'file_title' => 'nullable|string|max:500',
            'priority' => 'nullable|string|in:LOW,MEDIUM,HIGH',
            'origin_office_code' => 'nullable|string|max:50',
            'origin_office_name' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'receiving_office_code' => 'nullable|string|max:50',
            'receiving_office_name' => 'nullable|string|max:255',
            'receiving_officer_id' => 'nullable|string|max:50',
            'receiving_officer_name' => 'nullable|string|max:255',
            'date_requested' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $oldData = $tracker->only(array_keys($validated));

            $tracker->update($validated);

            // Log action in AuditLog via AuditService
            try {
                $auditService = app(\App\Services\AuditService::class);
                $auditService->logAction(
                    'UPDATED',
                    'FileTracker',
                    $tracker->id,
                    $oldData,
                    $validated,
                    "Updated manual file request sheet for tracker {$tracker->tracking_id}"
                );
            } catch (\Exception $ae) {
                Log::warning('CreateFileTrackerController updateRequestSheet audit logging failed: ' . $ae->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'File request sheet updated.',
                'data' => $this->decorateTrackerForResponse($tracker->fresh()),
            ]);
        } catch (\Exception $e) {
            Log::error('updateRequestSheet failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update request sheet: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Render a location slip directly from a file number for the outcomes that
     * have no tracker (Archive Tracking Sheet, Missing File Confirmation, or
     * Refer to Original Registry). In-Transit keeps using requestSheet().
     * GET /create-file-tracker/slip?file_number=XXX&variant=missing
     */
    public function slipFromFileNumber(Request $request, FileLocationResolver $resolver)
    {
        $fileNumber = trim((string) $request->get('file_number', ''));
        if ($fileNumber === '') {
            abort(404, 'A file number is required.');
        }

        $result  = $resolver->resolve($fileNumber);
        $variant = $request->get('variant', $result['slip_variant'] ?? 'tracking_sheet');

        $headings = [
            'tracking_sheet'        => 'KLAES FILE TRACKING SHEET',
            'tracking_confirmation' => 'KLAES FILE TRACKING REQUEST SHEET',
            'missing'               => 'KLAES MISSING FILE CONFIRMATION SLIP',
            'refer_registry'        => 'KLAES — REFER TO ORIGINAL REGISTRY',
        ];

        $heading   = $headings[$variant] ?? $headings['tracking_sheet'];
        $fileTitle = $result['indexing']->file_title ?? ($result['tracker']->file_title ?? '—');
        $reason    = trim((string) $request->get('reason', ''));

        return view('create_file_tracker_page.location_slip', [
            'variant'     => $variant,
            'heading'     => $heading,
            'fileNumber'  => $fileNumber,
            'fileTitle'   => $fileTitle,
            'registry'    => $result['registry'] ?? '—',
            'location'    => $result['current_location'] ?? '—',
            'statusLabel' => $result['status'],
            'nextAction'  => $result['next_action'],
            'reason'      => $reason,
        ]);
    }

    /**
     * Check whether a file number is currently logged out.
     * A file is "logged out" when every movement log entry either has log_out_date set
     * or is not in 'active' status — meaning no office currently holds it.
     * GET /create-file-tracker/check-logout-status?file_number=XXX
     */
    public function checkFileLogoutStatus(\Illuminate\Http\Request $request)
    {
        $fileNumber = trim((string) $request->get('file_number', ''));
        $currentModule = strtolower(trim((string) $request->get('module', '')));

        if ($fileNumber === '') {
            return response()->json(['is_logged_out' => false]);
        }

        $existingTrackers = FileTracker::where('file_number', $fileNumber)
            ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(status,'')))) NOT IN ('COMPLETED', 'CANCELLED')")
            ->get();

        foreach ($existingTrackers as $existing) {
            // Only a tracker in the SAME module blocks logging this file out here.
            // A file checked out under a different module/department (e.g. Cadastral)
            // is tracked independently and must not flag this module's registry.
            if (strtolower(trim((string) ($existing->module ?? ''))) !== $currentModule) {
                continue;
            }

            $log = $existing->movement_log ?? [];
            if (empty($log)) {
                continue;
            }

            $entries = collect($log);

            // File is "checked in" only when an active entry has no log_out_date yet.
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
}

