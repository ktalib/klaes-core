<?php

namespace App\Http\Controllers;

use App\Models\UserActivityLog;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ActivityLogController
 * 
 * Handles user activity log operations including viewing, filtering,
 * exporting, and management of activity records.
 */
class ActivityLogController extends Controller
{
    /**
     * Service instance for business logic
     */
    protected $activityLogService;

    /**
     * Constructor
     */
    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
        $this->middleware('auth');
    }

    /**
     * Display a listing of activity logs
     * 
     * GET /activity-logs
     * GET /api/activity-logs
     * 
     * Query Parameters:
     *   - page: int (default 1)
     *   - per_page: int (default 50, max 500)
     *   - status: string (online|offline|idle|stale)
     *   - user_id: int (filter by user)
     *   - date_from: Y-m-d
     *   - date_to: Y-m-d
     *   - search: string (searches username, email, ip_address)
     *   - sort: string (field name with optional - prefix for descending)
     */
    public function index(Request $request)
    {
        // Authorization check - only super admin and those with permission
        if (!Auth::user()->isAdmin() && !Auth::user()->can('view-activity-logs')) {
            return $this->respondForbidden('Unauthorized to view activity logs');
        }

        // Validate parameters
        $perPage = min((int)$request->input('per_page', 50), 500);
        $page = max((int)$request->input('page', 1), 1);

        try {
            $query = UserActivityLog::with('user:id,name,email');

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filter by user
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }

            // Filter by date range
            if ($request->filled('date_from')) {
                $query->where('login_time', '>=', $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->where('logout_time', '<=', $request->input('date_to') . ' 23:59:59');
            }

            // Search
            if ($request->filled('search')) {
                $search = '%' . $request->input('search') . '%';
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', $search)
                      ->orWhere('email', 'like', $search);
                })->orWhere('ip_address', 'like', $search);
            }

            // Sort
            $sort = $request->input('sort', '-login_time');
            if ($sort) {
                $descending = str_starts_with($sort, '-');
                $column = ltrim($sort, '-');

                // Validate column to prevent SQL injection
                $allowed = ['login_time', 'logout_time', 'duration_minutes', 'status', 'user_id', 'created_at'];
                if (in_array($column, $allowed)) {
                    $query->orderBy($column, $descending ? 'desc' : 'asc');
                }
            }

            // Paginate results
            $logs = $query->paginate($perPage, ['*'], 'page', $page);

            // Format response based on request type
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $logs->items(),
                    'pagination' => [
                        'total' => $logs->total(),
                        'per_page' => $logs->perPage(),
                        'current_page' => $logs->currentPage(),
                        'last_page' => $logs->lastPage(),
                        'from' => $logs->firstItem(),
                        'to' => $logs->lastItem(),
                    ]
                ]);
            }

            // Return blade view
            return view('activity.index', [
                'logs' => $logs,
                'statuses' => ['online' => 'Online', 'offline' => 'Offline', 'idle' => 'Idle', 'stale' => 'Stale'],
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching activity logs: {$e->getMessage()}");
            return $this->respondError('Failed to fetch activity logs');
        }
    }

    /**
     * Display a specific activity log entry
     * 
     * GET /activity-logs/{id}
     */
    public function show($id)
    {
        try {
            $log = UserActivityLog::with('user:id,name,email')->findOrFail($id);

            // Authorization - user can only view their own logs, admins can view all
            if (Auth::id() !== $log->user_id && !Auth::user()->isAdmin()) {
                return $this->respondForbidden('Unauthorized');
            }

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $log
                ]);
            }

            return view('activity.show', ['log' => $log]);

        } catch (\Exception $e) {
            Log::error("Error fetching activity log {$id}: {$e->getMessage()}");
            return $this->respondNotFound('Activity log not found');
        }
    }

    /**
     * Delete a specific activity log entry
     * 
     * DELETE /activity-logs/{id}
     */
    public function destroy($id)
    {
        // Only super admin can delete logs
        if (!Auth::user()->isAdmin()) {
            return $this->respondForbidden('Only administrators can delete activity logs');
        }

        try {
            $log = UserActivityLog::findOrFail($id);
            $log->delete();

            Log::info("Activity log {$id} deleted by user " . Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Activity log deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error deleting activity log {$id}: {$e->getMessage()}");
            return $this->respondError('Failed to delete activity log');
        }
    }

    /**
     * Bulk delete activity log entries
     * 
     * POST /api/activity-logs/bulk-delete
     * 
     * Request body:
     * {
     *   "ids": [1, 2, 3],
     *   "status": "offline",  // Optional - delete all with this status
     *   "date_before": "2025-01-01",  // Optional - delete before date
     *   "test_only": true  // Optional - only delete test records
     * }
     */
    public function bulkDelete(Request $request)
    {
        // Only super admin
        if (!Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can perform bulk operations'
            ], 403);
        }

        try {
            $deleted = 0;

            // Delete by IDs
            if ($request->filled('ids')) {
                $ids = array_filter(array_map('intval', $request->input('ids', [])));
                if (!empty($ids)) {
                    $deleted += UserActivityLog::whereIn('id', $ids)->delete();
                }
            }

            // Delete by status and date
            if ($request->filled('status') && $request->filled('date_before')) {
                $query = UserActivityLog::where('status', $request->input('status'))
                    ->where('logout_time', '<=', $request->input('date_before'));

                if ($request->boolean('test_only')) {
                    $query->where('test_control', 'TEST');
                }

                $deleted += $query->delete();
            }

            Log::info("Bulk deleted {$deleted} activity log entries by user " . Auth::id());

            return response()->json([
                'success' => true,
                'message' => "Deleted {$deleted} activity log entries",
                'data' => ['deleted_count' => $deleted]
            ]);

        } catch (\Exception $e) {
            Log::error("Error in bulk delete: {$e->getMessage()}");
            return $this->respondError('Bulk delete operation failed');
        }
    }

    /**
     * Export activity logs to CSV
     * 
     * GET /api/activity-logs/export
     * 
     * Query Parameters:
     *   - format: csv|json (default csv)
     *   - status: string (optional filter)
     *   - date_from: Y-m-d (optional)
     *   - date_to: Y-m-d (optional)
     */
    public function export(Request $request)
    {
        // Authorization
        if (!Auth::user()->isAdmin() && !Auth::user()->can('export-activity-logs')) {
            return $this->respondForbidden('Unauthorized to export activity logs');
        }

        try {
            $query = UserActivityLog::with('user:id,name,email');

            // Apply same filters as index
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('date_from')) {
                $query->where('login_time', '>=', $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->where('logout_time', '<=', $request->input('date_to') . ' 23:59:59');
            }

            $logs = $query->orderBy('login_time', 'desc')->get();

            // JSON export
            if ($request->input('format') === 'json') {
                return response()->json([
                    'success' => true,
                    'data' => $logs,
                    'exported_at' => now(),
                    'total_records' => count($logs)
                ]);
            }

            // CSV export
            $filename = 'activity_logs_' . now()->format('Y-m-d_His') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function () use ($logs) {
                $file = fopen('php://output', 'w');

                // CSV headers
                fputcsv($file, [
                    'ID',
                    'User',
                    'Email',
                    'Login Time',
                    'Logout Time',
                    'Duration (minutes)',
                    'IP Address',
                    'Device',
                    'Browser',
                    'Platform',
                    'Status',
                    'File Number',
                    'Test/Production'
                ]);

                // CSV rows
                foreach ($logs as $log) {
                    fputcsv($file, [
                        $log->id,
                        $log->user->name ?? 'Unknown',
                        $log->user->email ?? 'Unknown',
                        $log->login_time,
                        $log->logout_time,
                        $log->duration_minutes,
                        $log->ip_address,
                        $log->device,
                        $log->browser,
                        $log->platform,
                        $log->status,
                        $log->related_file_number,
                        $log->test_control
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error("Error exporting activity logs: {$e->getMessage()}");
            return $this->respondError('Failed to export activity logs');
        }
    }

    /**
     * Trigger cleanup operation
     * 
     * POST /api/activity-logs/cleanup
     * 
     * Request body:
     * {
     *   "test_data_days": 7,
     *   "archive_days": 90,
     *   "batch_size": 1000
     * }
     */
    public function cleanup(Request $request)
    {
        // Only super admin
        if (!Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can trigger cleanup'
            ], 403);
        }

        try {
            $testDataDays = (int)$request->input('test_data_days', 7);
            $archiveDays = (int)$request->input('archive_days', 90);
            $batchSize = (int)$request->input('batch_size', 1000);

            // Call service cleanup
            $result = $this->activityLogService->performCleanup(
                $testDataDays,
                $archiveDays,
                $batchSize
            );

            Log::info("Activity log cleanup performed by user " . Auth::id(), $result);

            return response()->json([
                'success' => true,
                'message' => 'Cleanup operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error("Error during cleanup: {$e->getMessage()}");
            return $this->respondError('Cleanup operation failed');
        }
    }

    /**
     * Get/Update user activity log settings
     * 
     * GET /api/activity-logs/settings - Get current user's settings
     * POST /api/activity-logs/settings - Update settings
     */
    public function settings(Request $request)
    {
        $user = Auth::user();

        try {
            if ($request->isMethod('post')) {
                // Validate
                $request->validate([
                    'timezone' => 'nullable|string|timezone',
                    'notes' => 'nullable|string|max:1000'
                ]);

                // Update settings
                $this->activityLogService->updateSettings(
                    $user,
                    $request->only(['timezone', 'notes'])
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Settings updated successfully'
                ]);
            }

            // GET - Return settings
            $settings = $this->activityLogService->getSettings($user);

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            Log::error("Error managing activity log settings: {$e->getMessage()}");
            return $this->respondError('Failed to manage settings');
        }
    }

    /**
     * Helper method to respond with error
     */
    private function respondError(string $message, int $code = 400): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], $code);
    }

    /**
     * Helper method to respond with forbidden
     */
    private function respondForbidden(string $message = 'Forbidden'): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 403);
    }

    /**
     * Helper method to respond with not found
     */
    private function respondNotFound(string $message = 'Not Found'): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 404);
    }
}
