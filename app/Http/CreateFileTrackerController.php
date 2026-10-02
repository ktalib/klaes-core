<?php

namespace App\Http\Controllers;

use App\Models\FileIndexing;
use App\Models\FileTracker;
use App\Models\User;
use App\Models\OtherReceivingOfficer;
use App\Models\Notification;
use App\Services\EBulkSmsService;
use App\Services\UserNotificationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CreateFileTrackerController extends Controller
{
    protected array $rackShelfCache = [];

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
        $receivingOfficers = DB::connection('sqlsrv')
            ->table('users')
            ->where('is_active', 1)
            ->where('staff_type_category', 'MLPP')
            ->select('id', 'first_name', 'last_name', 'department_id')
            ->get();

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

        return view('create_file_tracker_page.index', compact('PageTitle', 'PageDescription', 'registries', 'departments', 'offices', 'receivingOfficers', 'currentUserPayload', 'assignmentPermissionsPayload', 'module'));
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
                'description' => 'nullable|string',
                'deadline' => 'nullable|date',
                'movement_log' => 'required|array',
                'movement_log.*.office_code' => 'required|string',
                'movement_log.*.office_name' => 'required|string',
                'movement_log.*.log_in_time' => 'nullable|string',
                'movement_log.*.log_in_date' => 'nullable|date',
                'movement_log.*.log_out_time' => 'nullable|string',
                'movement_log.*.log_out_date' => 'nullable|date',
                'movement_log.*.notes' => 'nullable|string',
                'notes' => 'nullable|string'
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

            // Generate tracking ID
            $trackingId = FileTracker::generateTrackingId();

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
                'description' => $request->description,
                'status' => $workflowStatus,
                'date_created' => now(),
                'deadline' => $request->deadline,
                'total_offices' => count($request->movement_log),
                'notes' => $request->notes,
                'receiving_office_code' => $receivingOfficeCode,
                'receiving_office_name' => $receivingOfficeName,
                'receiving_officer_id' => $receivingOfficerId ?: null,
                'receiving_officer_name' => $request->input('receiving_officer_name') ?: $receivingOfficeName,
                'assignment_status' => $assignmentStatus,
                'assignment_accepted_at' => $isReceivingOfficerTable ? now() : null,
                'module' => $resolvedModule,
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

            $applyModuleScope = function ($builder) use ($moduleFilter, $normalizedModuleExpr, $normalizedWorkflowExpr, $normalizedFileNoExpr) {
                if ($moduleFilter === '') {
                    // General /create-file-tracker (no url=kangis / no url=new_kangis):
                    // exclude trackers that belong to the KANGIS / New KANGIS modules so
                    // their files don't leak into the generic file tracker view.
                    $builder->where(function ($excludeQuery) use ($normalizedModuleExpr, $normalizedWorkflowExpr, $normalizedFileNoExpr) {
                        $excludeQuery->whereRaw("{$normalizedModuleExpr} NOT IN (?, ?)", ['kangis', 'new_kangis'])
                            ->whereRaw("{$normalizedWorkflowExpr} NOT IN (?, ?, ?)", [
                                FileTracker::WORKFLOW_KANGIS_NEW,
                                FileTracker::WORKFLOW_KANGIS_APPROVAL,
                                FileTracker::WORKFLOW_KANGIS_3STEP,
                            ])
                            // Belt & braces: KN-prefixed file numbers are always New KANGIS.
                            ->where(function ($knGuard) use ($normalizedFileNoExpr) {
                                $knGuard->whereRaw("{$normalizedFileNoExpr} NOT LIKE 'KN%'")
                                    ->orWhereRaw("PATINDEX('%[^0-9]%', SUBSTRING({$normalizedFileNoExpr}, 3, 8000)) <> 0");
                            });
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
            };

            // Module filter (e.g. kangis, new_kangis, dgis, dg)
            $applyModuleScope($query);

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
                } elseif ($status !== 'all') {
                    $query->where('status', $status);
                }
            }

            if ($request->has('priority') && $request->priority && $request->priority !== 'all') {
                $query->where('priority', $request->priority);
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('tracking_id', 'LIKE', "%{$search}%")
                        ->orWhere('file_number', 'LIKE', "%{$search}%")
                        ->orWhere('file_title', 'LIKE', "%{$search}%");
                });
            }

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
                'completed' => (clone $statsBase)->where('status', FileTracker::STATUS_COMPLETED)->count(),
            ];

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = (int) $request->get('per_page', 20);
            $perPage = max(1, min($perPage, 500));

            $requestedPage = (int) $request->get('page', 1);
            $page = max(1, $requestedPage);

            // Get total filtered count for pagination UI
            $totalFiltered = $query->count();
            $totalPages = ceil($totalFiltered / $perPage);

            $results = $query
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

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
                    ->orWhereIn('mls_file_no', $variants);
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

            return [
                'sent' => $sent,
                'message' => $sent
                    ? 'SMS sent successfully.'
                    : 'Successful, but SMS did not send — no valid API key.',
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
                ->orWhereRaw('UPPER(file_number) = ?', [$needle])
                ->orWhereRaw('UPPER(file_title) = ?', [$needle]);
        })
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
            ->orderByDesc('id')
            ->first();

        if ($archiveRecord) {
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'archive',
                    'record' => [
                        'id' => $archiveRecord->id,
                        'file_number' => $archiveRecord->st_file_no
                            ?? $archiveRecord->kangisFileNo
                            ?? $archiveRecord->mlsfNo
                            ?? $archiveRecord->NewKANGISFileNo
                            ?? $archiveRecord->temp_fileno,
                        'tracking_id' => $archiveRecord->tracking_id,
                        'file_name' => $archiveRecord->FileName,
                        'location' => $archiveRecord->location,
                        'application_id' => $archiveRecord->application_id,
                        'type' => $archiveRecord->type,
                        'is_decommissioned' => (bool) $archiveRecord->is_decommissioned,
                        'decommissioning_reason' => $archiveRecord->decommissioning_reason,
                        'created_at' => $archiveRecord->created_at,
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
        $tracker->setAttribute('completion_percentage', $tracker->completion_percentage);
        $tracker->setAttribute('current_movement', $tracker->getCurrentMovement());

        $tracker->setAttribute('rack_shelf_location', $this->getRackShelfLocation($tracker->file_number));

        // Add workflow progress if this is a 3-step tracker
        if ($tracker->isKangis3Step()) {
            $tracker->setAttribute('workflow_progress', $tracker->getWorkflowProgress());
            $tracker->setAttribute('next_step', $tracker->getNextStepDefinition());
        }

        return $tracker;
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
}
