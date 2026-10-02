<?php

namespace App\Http\Controllers;

use App\Models\FileTracking;
use App\Models\FileIndexing;
use App\Models\Office;
use App\Models\User;
use App\Models\OtherReceivingOfficer;
use App\Services\FileTrackingAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Exception;
use RuntimeException;

class FileTrackingController extends Controller
{
    public function __construct(
        protected FileTrackingAssignmentService $assignmentService
    ) {
    }

    /**
     * List all tracked files with filters
     * GET /api/file-trackings
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = FileTracking::with(['fileIndexing', 'currentHandlerUser', 'assignedUser', 'assignedByUser', 'assignedOffice']);

            // Apply filters
            if ($request->has('status') && $request->status) {
                $query->byStatus($request->status);
            }

            if ($request->has('location') && $request->location) {
                $query->byLocation($request->location);
            }

            if ($request->has('handler') && $request->handler) {
                $query->byHandler($request->handler);
            }

            if ($request->has('overdue') && $request->overdue === 'true') {
                $query->overdue();
            }

            if ($request->has('active') && $request->active === 'true') {
                $query->active();
            }

            // Search by file number or title
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->whereHas('fileIndexing', function ($q) use ($search) {
                    $q->where('file_number', 'LIKE', "%{$search}%")
                        ->orWhere('file_title', 'LIKE', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $trackings = $query->paginate($perPage);

            // Add computed attributes
            foreach ($trackings as $tracking) {
                $tracking->setAttribute('is_overdue', $tracking->is_overdue);
                $tracking->setAttribute('days_until_due', $tracking->days_until_due);
            }

            return response()->json([
                'success' => true,
                'data' => $trackings,
                'message' => 'File trackings retrieved successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Error retrieving file trackings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving file trackings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Locate a tracking record by file number, tracking reference, or file indexing id.
     * GET /api/file-trackings/lookup
     */
    public function lookup(Request $request): JsonResponse
    {
        $fileNumber = trim((string) $request->get('file_number', ''));
        $trackingId = trim((string) $request->get('tracking_id', ''));
        $fileIndexingId = $request->get('file_indexing_id');

        if ($fileNumber === '' && $trackingId === '' && !$fileIndexingId) {
            return response()->json([
                'success' => false,
                'message' => 'Provide at least one lookup parameter (file_number, tracking_id, or file_indexing_id).',
            ], 422);
        }

        try {
            $query = FileTracking::with([
                'fileIndexing',
                'currentHandlerUser',
                'assignedUser',
                'assignedByUser',
                'assignedOffice',
            ]);

            if ($fileIndexingId) {
                $query->where('file_indexing_id', (int) $fileIndexingId);
            }

            if ($fileNumber !== '' || $trackingId !== '') {
                $query->where(function ($builder) use ($fileNumber, $trackingId) {
                    $hasCondition = false;

                    if ($fileNumber !== '') {
                        $builder->whereHas('fileIndexing', function ($sub) use ($fileNumber) {
                            $sub->whereRaw('UPPER(file_number) = ?', [mb_strtoupper($fileNumber)]);
                        });
                        $hasCondition = true;
                    }

                    if ($trackingId !== '') {
                        $method = $hasCondition ? 'orWhereRaw' : 'whereRaw';
                        $builder->{$method}('UPPER(qr_code) = ?', [mb_strtoupper($trackingId)]);
                    }
                });
            }

            $tracking = $query->orderByDesc('created_at')->first();

            if (!$tracking) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tracking record not found for the supplied reference.',
                ], 404);
            }

            $resource = $this->loadTrackingRelations($tracking);

            return response()->json([
                'success' => true,
                'data' => $resource,
                'message' => 'File tracking record located successfully',
            ]);

        } catch (Exception $e) {
            Log::error('Error looking up file tracking record', [
                'file_number' => $fileNumber,
                'tracking_id' => $trackingId,
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error locating file tracking record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Provide assignment metadata (offices and staff directory).
     * GET /api/file-trackings/assignment/options
     */
    public function assignmentOptions(): JsonResponse
    {
        try {
            $offices = Office::on('sqlsrv')
                ->active()
                ->orderBy('office_name')
                ->get(['id', 'office_code', 'office_name', 'office_abbreviation', 'department'])
                ->map(function (Office $office) {
                    return [
                        'id' => (int) $office->id,
                        'code' => $office->office_code,
                        'name' => $office->office_name,
                        'abbreviation' => $office->office_abbreviation,
                        'department' => $office->department,
                    ];
                })
                ->values();

            $users = User::on('sqlsrv')
                ->where(function ($query) {
                    $query->whereNull('is_active')->orWhere('is_active', 1);
                })
                ->where('staff_type_category', 'MLPP')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->limit(500)
                ->get([
                    'id',
                    'username',
                    'first_name',
                    'last_name',
                    'profile',
                    'passport_photo_path',
                ])
                ->map(function (User $user) {
                    $resolvedName = trim($user->name ?: ($user->first_name . ' ' . $user->last_name));

                    return [
                        'id' => (int) $user->id,
                        'name' => $resolvedName !== '' ? $resolvedName : 'User #' . $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'username' => $user->username,
                        // Shown beside the officer once they are picked, on both the
                        // web and mobile file-tracking screens.
                        'photo_url' => $user->profile_url,
                    ];
                })
                ->values();

            // Also load officers from receiving_officers table
            $receivingOfficers = OtherReceivingOfficer::on('sqlsrv')
                ->where('is_active', 1)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(function (OtherReceivingOfficer $officer) {
                    return [
                        'id' => 'ro_' . $officer->id,
                        'name' => $officer->name,
                        'first_name' => $officer->first_name,
                        'last_name' => $officer->last_name,
                        'username' => null,
                        'source' => 'receiving_officers',
                    ];
                })
                ->values();

            $allUsers = $users->merge($receivingOfficers)->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'offices' => $offices,
                    'users' => $allUsers,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Error retrieving assignment options', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load assignment directory',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Register a new file tracking entry
     * POST /api/file-trackings
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file_indexing_id' => 'required|integer|exists:file_indexings,id',
                'rfid_tag' => 'nullable|string|max:100|unique:file_trackings,rfid_tag',
                'qr_code' => 'nullable|string|max:100|unique:file_trackings,qr_code',
                'current_location' => 'nullable|string|max:255',
                'current_holder' => 'nullable|string|max:255',
                'current_handler' => 'nullable|string|max:255',
                'date_received' => 'nullable|date',
                'due_date' => 'nullable|date|after:date_received',
                'status' => 'required|string|in:active,checked_out,overdue,returned,lost,archived',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if file is already being tracked
            $existingTracking = FileTracking::where('file_indexing_id', $request->file_indexing_id)->first();
            if ($existingTracking) {
                return response()->json([
                    'success' => false,
                    'message' => 'This file is already being tracked',
                    'existing_tracking_id' => $existingTracking->id
                ], 409);
            }

            $tracking = FileTracking::create([
                'file_indexing_id' => $request->file_indexing_id,
                'rfid_tag' => $request->rfid_tag,
                'qr_code' => $request->qr_code,
                'current_location' => $request->current_location,
                'current_holder' => $request->current_holder,
                'current_handler' => $request->current_handler,
                'date_received' => $request->date_received ? Carbon::parse($request->date_received) : Carbon::now(),
                'due_date' => $request->due_date ? Carbon::parse($request->due_date) : null,
                'status' => $request->status,
            ]);

            $tracking->load(['fileIndexing', 'currentHandlerUser']);

            Log::info('File tracking created', [
                'tracking_id' => $tracking->id,
                'file_indexing_id' => $tracking->file_indexing_id,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $tracking,
                'message' => 'File tracking registered successfully'
            ], 201);

        } catch (Exception $e) {
            Log::error('Error creating file tracking', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating file tracking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * View detailed tracking info including movement history
     * GET /api/file-trackings/{id}
     */
    public function show($id): JsonResponse
    {
        try {
            $tracking = FileTracking::with(['fileIndexing', 'currentHandlerUser'])->find($id);

            if (!$tracking) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tracking not found'
                ], 404);
            }

            // Add computed attributes
            $tracking->setAttribute('is_overdue', $tracking->is_overdue);
            $tracking->setAttribute('days_until_due', $tracking->days_until_due);

            return response()->json([
                'success' => true,
                'data' => $tracking,
                'message' => 'File tracking retrieved successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Error retrieving file tracking', [
                'tracking_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving file tracking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update file status, location, or handler
     * PUT /api/file-trackings/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $tracking = FileTracking::find($id);

            if (!$tracking) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tracking not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'rfid_tag' => 'nullable|string|max:100|unique:file_trackings,rfid_tag,' . $id,
                'qr_code' => 'nullable|string|max:100|unique:file_trackings,qr_code,' . $id,
                'current_location' => 'nullable|string|max:255',
                'current_holder' => 'nullable|string|max:255',
                'current_handler' => 'nullable|string|max:255',
                'date_received' => 'nullable|date',
                'due_date' => 'nullable|date|after:date_received',
                'status' => 'nullable|string|in:active,checked_out,overdue,returned,lost,archived',
                'reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $reason = $request->get('reason', 'Manual update');

            // Track changes and update with movement history
            if ($request->has('current_location') && $request->current_location !== $tracking->current_location) {
                $tracking->updateLocation($request->current_location, $reason);
            }

            if ($request->has('current_handler') && $request->current_handler !== $tracking->current_handler) {
                $tracking->updateHandler($request->current_handler, $reason);
            }

            if ($request->has('status') && $request->status !== $tracking->status) {
                $tracking->updateStatus($request->status, $reason);
            }

            // Update other fields without movement history
            $fieldsToUpdate = ['rfid_tag', 'qr_code', 'current_holder', 'date_received', 'due_date'];
            foreach ($fieldsToUpdate as $field) {
                if ($request->has($field)) {
                    if (in_array($field, ['date_received', 'due_date']) && $request->$field) {
                        $tracking->$field = Carbon::parse($request->$field);
                    } else {
                        $tracking->$field = $request->$field;
                    }
                }
            }

            $tracking->save();
            $tracking->load(['fileIndexing', 'currentHandlerUser']);

            Log::info('File tracking updated', [
                'tracking_id' => $tracking->id,
                'updated_fields' => array_keys($request->all()),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $tracking,
                'message' => 'File tracking updated successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Error updating file tracking', [
                'tracking_id' => $id,
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating file tracking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign a file tracking record to an office and user
     * POST /api/file-trackings/{id}/assign
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $tracking = FileTracking::find($id);

        if (!$tracking) {
            return response()->json([
                'success' => false,
                'message' => 'File tracking not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'assigned_office_id' => 'required|integer|exists:sqlsrv.offices,id',
            'assigned_to' => 'required|integer|exists:sqlsrv.users,id',
            'reason' => 'nullable|string|max:500',
            'notification_title' => 'nullable|string|max:255',
            'notification_body' => 'nullable|string|max:1000',
            'send_email' => 'nullable|boolean',
            'send_sms' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $context = [
            'reason' => $request->input('reason'),
            'notification_title' => $request->input('notification_title'),
            'notification_body' => $request->input('notification_body'),
            'send_email' => $request->boolean('send_email'),
            'send_sms' => $request->boolean('send_sms'),
        ];

        try {
            $updatedTracking = $this->assignmentService->assign(
                $tracking,
                (int) $request->assigned_office_id,
                (int) $request->assigned_to,
                auth()->id(),
                $context
            );

            $trackingResource = $this->loadTrackingRelations($updatedTracking);

            Log::info('File tracking assigned', [
                'tracking_id' => $trackingResource->id,
                'assigned_office_id' => $request->assigned_office_id,
                'assigned_to' => $request->assigned_to,
                'assigned_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $trackingResource,
                'message' => 'File tracking assigned successfully',
            ]);

        } catch (RuntimeException $e) {
            Log::warning('File tracking assignment failed', [
                'tracking_id' => $tracking->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (Exception $e) {
            Log::error('Error assigning file tracking', [
                'tracking_id' => $tracking->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error assigning file tracking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Accept a file tracking assignment
     * POST /api/file-trackings/{id}/accept
     */
    public function acceptAssignment(Request $request, int $id): JsonResponse
    {
        $tracking = FileTracking::find($id);

        if (!$tracking) {
            return response()->json([
                'success' => false,
                'message' => 'File tracking not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer|exists:sqlsrv.users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = (int) ($request->input('user_id') ?? auth()->id());

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'User context is required to accept assignments',
            ], 401);
        }

        try {
            $updatedTracking = $this->assignmentService->accept($tracking, $userId);

            $trackingResource = $this->loadTrackingRelations($updatedTracking);

            Log::info('File tracking assignment accepted', [
                'tracking_id' => $trackingResource->id,
                'user_id' => $userId,
            ]);

            return response()->json([
                'success' => true,
                'data' => $trackingResource,
                'message' => 'Assignment accepted successfully',
            ]);

        } catch (RuntimeException $e) {
            Log::warning('File tracking assignment accept failed', [
                'tracking_id' => $tracking->id,
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (Exception $e) {
            Log::error('Error accepting file tracking assignment', [
                'tracking_id' => $tracking->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error accepting assignment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a file tracking assignment
     * POST /api/file-trackings/{id}/reject
     */
    public function rejectAssignment(Request $request, int $id): JsonResponse
    {
        $tracking = FileTracking::find($id);

        if (!$tracking) {
            return response()->json([
                'success' => false,
                'message' => 'File tracking not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer|exists:sqlsrv.users,id',
            'note' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = (int) ($request->input('user_id') ?? auth()->id());

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'User context is required to reject assignments',
            ], 401);
        }

        try {
            $updatedTracking = $this->assignmentService->reject($tracking, $userId, $request->input('note'));

            $trackingResource = $this->loadTrackingRelations($updatedTracking);

            Log::info('File tracking assignment rejected', [
                'tracking_id' => $trackingResource->id,
                'user_id' => $userId,
            ]);

            return response()->json([
                'success' => true,
                'data' => $trackingResource,
                'message' => 'Assignment rejected successfully',
            ]);

        } catch (RuntimeException $e) {
            Log::warning('File tracking assignment reject failed', [
                'tracking_id' => $tracking->id,
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (Exception $e) {
            Log::error('Error rejecting file tracking assignment', [
                'tracking_id' => $tracking->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error rejecting assignment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Append new movement entry to history
     * POST /api/file-trackings/{id}/move
     */
    public function addMovement(Request $request, $id): JsonResponse
    {
        try {
            $tracking = FileTracking::find($id);

            if (!$tracking) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tracking not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|string|max:100',
                'from_location' => 'nullable|string|max:255',
                'to_location' => 'nullable|string|max:255',
                'from_handler' => 'nullable|string|max:255',
                'to_handler' => 'nullable|string|max:255',
                'reason' => 'nullable|string|max:500',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $movementData = [
                'action' => $request->action,
                'from_location' => $request->from_location,
                'to_location' => $request->to_location,
                'from_handler' => $request->from_handler,
                'to_handler' => $request->to_handler,
                'reason' => $request->reason,
                'notes' => $request->notes,
            ];

            $tracking->addMovementEntry($movementData);

            // Update current location and handler if provided
            if ($request->to_location) {
                $tracking->current_location = $request->to_location;
            }
            if ($request->to_handler) {
                $tracking->current_handler = $request->to_handler;
            }
            $tracking->save();

            $tracking->load(['fileIndexing', 'currentHandlerUser']);

            Log::info('Movement added to file tracking', [
                'tracking_id' => $tracking->id,
                'action' => $request->action,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $tracking,
                'message' => 'Movement entry added successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Error adding movement to file tracking', [
                'tracking_id' => $id,
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error adding movement entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a file tracking entry
     * DELETE /api/file-trackings/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $tracking = FileTracking::find($id);

            if (!$tracking) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tracking not found'
                ], 404);
            }

            $tracking->delete();

            Log::info('File tracking deleted', [
                'tracking_id' => $id,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File tracking deleted successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Error deleting file tracking', [
                'tracking_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting file tracking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign RFID tag to a file
     * POST /api/rfid/register
     */
    public function registerRfid(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file_indexing_id' => 'required|integer|exists:file_indexings,id',
                'rfid_tag' => 'required|string|max:100|unique:file_trackings,rfid_tag',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find existing tracking or create new one
            $tracking = FileTracking::where('file_indexing_id', $request->file_indexing_id)->first();

            if ($tracking) {
                // Update existing tracking with RFID tag
                $tracking->rfid_tag = $request->rfid_tag;
                $tracking->addMovementEntry([
                    'action' => 'rfid_assigned',
                    'rfid_tag' => $request->rfid_tag,
                    'reason' => 'RFID tag assigned to existing tracking'
                ]);
                $tracking->save();
            } else {
                // Create new tracking with RFID tag
                $tracking = FileTracking::create([
                    'file_indexing_id' => $request->file_indexing_id,
                    'rfid_tag' => $request->rfid_tag,
                    'status' => 'active',
                    'date_received' => Carbon::now(),
                ]);
            }

            $tracking->load(['fileIndexing', 'currentHandlerUser']);

            Log::info('RFID tag registered', [
                'tracking_id' => $tracking->id,
                'rfid_tag' => $request->rfid_tag,
                'file_indexing_id' => $request->file_indexing_id,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $tracking,
                'message' => 'RFID tag registered successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Error registering RFID tag', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error registering RFID tag',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retrieve file tracking info by RFID tag
     * GET /api/rfid/scan/{tag}
     */
    public function scanRfid($tag): JsonResponse
    {
        try {
            $tracking = FileTracking::with(['fileIndexing', 'currentHandlerUser'])
                ->where('rfid_tag', $tag)
                ->first();

            if (!$tracking) {
                return response()->json([
                    'success' => false,
                    'message' => 'No file found with this RFID tag'
                ], 404);
            }

            // Add scan entry to movement history
            $tracking->addMovementEntry([
                'action' => 'rfid_scanned',
                'rfid_tag' => $tag,
                'scan_location' => request()->ip(),
                'reason' => 'RFID tag scanned'
            ]);

            // Add computed attributes
            $tracking->setAttribute('is_overdue', $tracking->is_overdue);
            $tracking->setAttribute('days_until_due', $tracking->days_until_due);

            Log::info('RFID tag scanned', [
                'tracking_id' => $tracking->id,
                'rfid_tag' => $tag,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $tracking,
                'message' => 'File tracking retrieved by RFID scan'
            ]);

        } catch (Exception $e) {
            Log::error('Error scanning RFID tag', [
                'rfid_tag' => $tag,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error scanning RFID tag',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate movement and overdue reports
     * GET /api/rfid/report
     */
    public function generateReport(Request $request): JsonResponse
    {
        try {
            $reportType = $request->get('type', 'summary');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            $report = [];

            switch ($reportType) {
                case 'overdue':
                    $report = $this->generateOverdueReport();
                    break;

                case 'movement':
                    $report = $this->generateMovementReport($startDate, $endDate);
                    break;

                case 'location':
                    $report = $this->generateLocationReport();
                    break;

                case 'handler':
                    $report = $this->generateHandlerReport();
                    break;

                default:
                    $report = $this->generateSummaryReport();
                    break;
            }

            Log::info('Report generated', [
                'report_type' => $reportType,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Report generated successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Error generating report', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error generating report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch update overdue files
     * POST /api/file-trackings/batch/overdue
     */
    public function batchUpdateOverdue(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'action' => 'required|string|in:mark_overdue,extend_due_date,return_files',
                'tracking_ids' => 'required|array',
                'tracking_ids.*' => 'integer|exists:file_trackings,id',
                'new_due_date' => 'nullable|date|required_if:action,extend_due_date',
                'reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $trackings = FileTracking::whereIn('id', $request->tracking_ids)->get();
            $updatedCount = 0;
            $reason = $request->get('reason', 'Batch operation');

            foreach ($trackings as $tracking) {
                switch ($request->action) {
                    case 'mark_overdue':
                        if ($tracking->status !== FileTracking::STATUS_OVERDUE) {
                            $tracking->updateStatus(FileTracking::STATUS_OVERDUE, $reason);
                            $updatedCount++;
                        }
                        break;

                    case 'extend_due_date':
                        $tracking->due_date = Carbon::parse($request->new_due_date);
                        $tracking->addMovementEntry([
                            'action' => 'due_date_extended',
                            'old_due_date' => $tracking->getOriginal('due_date'),
                            'new_due_date' => $request->new_due_date,
                            'reason' => $reason
                        ]);
                        $tracking->save();
                        $updatedCount++;
                        break;

                    case 'return_files':
                        if ($tracking->status !== FileTracking::STATUS_RETURNED) {
                            $tracking->updateStatus(FileTracking::STATUS_RETURNED, $reason);
                            $updatedCount++;
                        }
                        break;
                }
            }

            Log::info('Batch operation completed', [
                'action' => $request->action,
                'updated_count' => $updatedCount,
                'total_requested' => count($request->tracking_ids),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'updated_count' => $updatedCount,
                    'total_requested' => count($request->tracking_ids)
                ],
                'message' => "Batch operation completed. {$updatedCount} files updated."
            ]);

        } catch (Exception $e) {
            Log::error('Error in batch operation', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error in batch operation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function loadTrackingRelations(FileTracking $tracking): FileTracking
    {
        $tracking->load([
            'fileIndexing',
            'currentHandlerUser',
            'assignedUser',
            'assignedByUser',
            'assignedOffice',
        ]);

        $tracking->setAttribute('is_overdue', $tracking->is_overdue);
        $tracking->setAttribute('days_until_due', $tracking->days_until_due);

        return $tracking;
    }

    /**
     * Generate summary report
     */
    private function generateSummaryReport(): array
    {
        $totalFiles = FileTracking::count();
        $activeFiles = FileTracking::where('status', FileTracking::STATUS_ACTIVE)->count();
        $checkedOutFiles = FileTracking::where('status', FileTracking::STATUS_CHECKED_OUT)->count();
        $overdueFiles = FileTracking::overdue()->count();
        $returnedFiles = FileTracking::where('status', FileTracking::STATUS_RETURNED)->count();
        $lostFiles = FileTracking::where('status', FileTracking::STATUS_LOST)->count();

        return [
            'total_files' => $totalFiles,
            'active_files' => $activeFiles,
            'checked_out_files' => $checkedOutFiles,
            'overdue_files' => $overdueFiles,
            'returned_files' => $returnedFiles,
            'lost_files' => $lostFiles,
            'generated_at' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Generate overdue report
     */
    private function generateOverdueReport(): array
    {
        $overdueFiles = FileTracking::with(['fileIndexing', 'currentHandlerUser'])
            ->overdue()
            ->get()
            ->map(function ($tracking) {
                return [
                    'id' => $tracking->id,
                    'file_number' => $tracking->fileIndexing->file_number ?? 'N/A',
                    'file_title' => $tracking->fileIndexing->file_title ?? 'N/A',
                    'current_location' => $tracking->current_location,
                    'current_handler' => $tracking->current_handler,
                    'due_date' => $tracking->due_date,
                    'days_overdue' => abs($tracking->days_until_due),
                    'status' => $tracking->status
                ];
            });

        return [
            'overdue_files' => $overdueFiles,
            'total_overdue' => $overdueFiles->count(),
            'generated_at' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Generate movement report
     */
    private function generateMovementReport($startDate = null, $endDate = null): array
    {
        $query = FileTracking::with(['fileIndexing']);

        if ($startDate) {
            $query->where('updated_at', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $query->where('updated_at', '<=', Carbon::parse($endDate));
        }

        $trackings = $query->get();
        $movements = [];

        foreach ($trackings as $tracking) {
            if ($tracking->movement_history) {
                foreach ($tracking->movement_history as $movement) {
                    $movementDate = Carbon::parse($movement['timestamp']);

                    if ($startDate && $movementDate->lt(Carbon::parse($startDate))) {
                        continue;
                    }
                    if ($endDate && $movementDate->gt(Carbon::parse($endDate))) {
                        continue;
                    }

                    $movements[] = array_merge($movement, [
                        'tracking_id' => $tracking->id,
                        'file_number' => $tracking->fileIndexing->file_number ?? 'N/A',
                        'file_title' => $tracking->fileIndexing->file_title ?? 'N/A'
                    ]);
                }
            }
        }

        // Sort by timestamp descending
        usort($movements, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return [
            'movements' => $movements,
            'total_movements' => count($movements),
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'generated_at' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Generate location report
     */
    private function generateLocationReport(): array
    {
        $locationStats = FileTracking::selectRaw('current_location, COUNT(*) as file_count')
            ->whereNotNull('current_location')
            ->groupBy('current_location')
            ->orderBy('file_count', 'desc')
            ->get();

        return [
            'location_statistics' => $locationStats,
            'total_locations' => $locationStats->count(),
            'generated_at' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Generate handler report
     */
    private function generateHandlerReport(): array
    {
        $handlerStats = FileTracking::selectRaw('current_handler, COUNT(*) as file_count')
            ->whereNotNull('current_handler')
            ->groupBy('current_handler')
            ->orderBy('file_count', 'desc')
            ->get();

        return [
            'handler_statistics' => $handlerStats,
            'total_handlers' => $handlerStats->count(),
            'generated_at' => Carbon::now()->toISOString()
        ];
    }
}
