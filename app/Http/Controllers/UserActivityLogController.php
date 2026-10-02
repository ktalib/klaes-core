<?php

namespace App\Http\Controllers;

use App\Models\UserActivityLog;
use App\Models\UserActivityLogSetting;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

class UserActivityLogController extends Controller
{
    /**
     * Display a listing of user activity logs
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            return $this->getActivityLogsData($request);
        }

        $stats = UserActivityLog::getActivityStats();
        $onlineUsers = UserActivityLog::with('user')
            ->online()
            ->orderByDesc('last_seen_at')
            ->orderByDesc('login_time')
            ->limit(10)
            ->get();

        return view('user_activity_logs.index', compact('stats', 'onlineUsers'));
    }

    /**
     * Get activity logs data for DataTables
     */
    private function getActivityLogsData(Request $request)
    {
        $query = UserActivityLog::with('user')
            ->select([
                'user_activity_logs.*',
                'users.first_name',
                'users.last_name',
                'users.email'
            ])
            ->leftJoin('users', 'user_activity_logs.user_id', '=', 'users.id');

        // Apply filters
        if ($request->filled('user_id')) {
            $query->where('user_activity_logs.user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $statusFilter = strtolower($request->status);

            if ($statusFilter === 'online') {
                $query->online();
            } elseif ($statusFilter === 'offline') {
                $query->offline();
            } elseif ($statusFilter === 'idle') {
                $query->idle();
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('user_activity_logs.created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('user_activity_logs.created_at', '<=', $request->date_to);
        }

        if ($request->filled('device_type')) {
            $query->where('device_type', $request->device_type);
        }

        return DataTables::of($query)
            ->addColumn('user_name', function ($log) {
                if ($log->user) {
                    return $log->user->first_name . ' ' . $log->user->last_name;
                }
                return 'Unknown User';
            })
            ->addColumn('user_email', function ($log) {
                return $log->user ? $log->user->email : 'N/A';
            })
            ->addColumn('online_status', function ($log) {
                return $log->online_status_badge;
            })
            ->addColumn('session_duration', function ($log) {
                return $log->session_duration;
            })
            ->addColumn('device_info', function ($log) {
                $deviceIcon = $this->getDeviceIcon($log->device_type);
                return '<div class="flex items-center space-x-2">
                            <i class="' . $deviceIcon . ' text-gray-500"></i>
                            <div>
                                <div class="text-sm font-medium">' . ucfirst($log->device_type) . '</div>
                                <div class="text-xs text-gray-500">' . $log->browser . ' on ' . $log->platform . '</div>
                            </div>
                        </div>';
            })
            ->addColumn('login_time', function ($log) {
                return $log->login_time ? $log->login_time->format('Y-m-d H:i:s') : '-';
            })
            ->addColumn('logout_time', function ($log) {
                return $log->logout_time ? $log->logout_time->format('Y-m-d H:i:s') : '-';
            })
            ->addColumn('actions', function ($log) {
                $actions = '<div class="flex space-x-2">';
                $actions .= '<button onclick="viewActivityDetails(' . $log->id . ')" class="text-blue-600 hover:text-blue-900" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>';
                
                if (auth()->user()->can('delete logged history')) {
                    // Check if user is online - only show logout button for online users
                    if ($log->is_online) {
                        $actions .= '<button onclick="logoutUser(' . $log->user_id . ')" 
                                        class="logout-user-btn text-orange-600 hover:text-orange-900 cursor-pointer" 
                                        title="Logout User"
                                        data-user-id="' . $log->user_id . '"
                                        data-user-status="online">
                                        <i class="fas fa-sign-out-alt"></i>
                                    </button>';
                    } else {
                        // Show disabled logout button for offline users
                        $actions .= '<button class="logout-user-btn-disabled text-gray-400 cursor-not-allowed opacity-50" 
                                        title="User is already offline"
                                        disabled>
                                        <i class="fas fa-sign-out-alt"></i>
                                    </button>';
                    }
                }
                $actions .= '</div>';
                
                return $actions;
            })
            ->rawColumns(['online_status', 'device_info', 'actions'])
            ->make(true);
    }

    /**
     * Get device icon based on device type
     */
    private function getDeviceIcon($deviceType)
    {
        switch ($deviceType) {
            case 'mobile':
                return 'fas fa-mobile-alt';
            case 'tablet':
                return 'fas fa-tablet-alt';
            default:
                return 'fas fa-desktop';
        }
    }

    /**
     * Show activity details
     */
    public function show($id)
    {
        $activity = UserActivityLog::with('user')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $activity->id,
                'user_name' => $activity->user ? $activity->user->name : 'Unknown User',
                'user_email' => $activity->user ? $activity->user->email : 'N/A',
                'ip_address' => $activity->ip_address,
                'user_agent' => $activity->user_agent,
                'device_type' => ucfirst($activity->device_type),
                'browser' => $activity->browser,
                'platform' => $activity->platform,
                'login_time' => $activity->formatted_login_time,
                'logout_time' => $activity->formatted_logout_time,
                'session_duration' => $activity->session_duration,
                'is_online' => $activity->is_online,
                'session_id' => $activity->session_id,
                'location' => $activity->location ?: 'Unknown',
                'activity_type' => ucfirst($activity->activity_type),
                'activity_description' => $activity->activity_description,
                'created_at' => $activity->created_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * Delete activity log
     */
    public function destroy($id)
    {
        try {
            $activity = UserActivityLog::findOrFail($id);
            $activity->delete();

            return response()->json([
                'success' => true,
                'message' => 'Activity log deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting activity log: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout user by admin
     */
    public function logoutUser(Request $request, $userId)
    {
        try {
            // Validate userId parameter
            if (empty($userId) || $userId === 'undefined' || $userId === 'null' || !is_numeric($userId)) {
                \Log::error('Invalid user ID provided for logout', [
                    'user_id' => $userId,
                    'admin_user' => auth()->id(),
                    'request_data' => $request->all()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid user ID provided'
                ], 400);
            }

            // Convert to integer for safety
            $userId = (int) $userId;

            // Log the request for debugging
            \Log::info('Logout user request', [
                'user_id' => $userId,
                'admin_user' => auth()->id(),
                'request_data' => $request->all()
            ]);

            // Check if user has permission to logout users
            if (!auth()->user()->can('delete logged history')) {
                \Log::warning('Unauthorized logout attempt', [
                    'admin_user' => auth()->id(),
                    'target_user' => $userId
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            // Find the user
            $user = User::find($userId);
            if (!$user) {
                \Log::error('User not found for logout', ['user_id' => $userId]);
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Get user name safely
            $userName = $user->name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->email ?? 'Unknown User');
            
            $description = 'Logged out by admin';
            $closedSessions = ActivityLogService::forceLogoutUser($userId, $description);

            return response()->json([
                'success' => true,
                'message' => $closedSessions > 0
                    ? 'User ' . $userName . ' has been logged out successfully.'
                    : 'User ' . $userName . ' has no active sessions.',
                'sessions_closed' => $closedSessions,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error logging out user', [
                'user_id' => $userId ?? 'undefined',
                'admin_user' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error logging out user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activity statistics
     */
    public function getStats(Request $request)
    {
        $days = $request->get('days', 30);
        $stats = UserActivityLog::getActivityStats($days);
        
        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get online users
     */
    public function getOnlineUsers()
    {
        $onlineUsers = UserActivityLog::with('user')
            ->online()
            ->orderByDesc('last_seen_at')
            ->orderByDesc('login_time')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_id' => $log->user_id, // Add the missing user_id field
                    'user_name' => $log->user ? $log->user->name : 'Unknown User',
                    'user_email' => $log->user ? $log->user->email : 'N/A',
                    'login_time' => $log->login_time ? $log->login_time->format('Y-m-d H:i:s') : '-',
                    'last_seen_at' => $log->last_seen_at ? $log->last_seen_at->format('Y-m-d H:i:s') : null,
                    'ip_address' => $log->ip_address,
                    'device_type' => $log->device_type ? strtolower($log->device_type) : 'unknown',
                    'browser' => $log->browser,
                    'platform' => $log->platform,
                    'status' => $log->status,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $onlineUsers
        ]);
    }

    /**
     * Get activity chart data
     */
    public function getChartData(Request $request)
    {
        $days = $request->get('days', 7);
        $startDate = now()->subDays($days);

        // Daily login counts
        $dailyLogins = UserActivityLog::where('activity_type', 'login')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('CONVERT(date, created_at) as [date], COUNT(*) as count')
            ->groupByRaw('CONVERT(date, created_at)')
            ->orderByRaw('CONVERT(date, created_at)')
            ->get();

        // Browser distribution
        $browserStats = UserActivityLog::where('created_at', '>=', $startDate)
            ->selectRaw('browser, COUNT(*) as count')
            ->groupBy('browser')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        // Device type distribution
        $deviceStats = UserActivityLog::where('created_at', '>=', $startDate)
            ->selectRaw('device_type, COUNT(*) as count')
            ->groupBy('device_type')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'daily_logins' => $dailyLogins,
                'browser_stats' => $browserStats,
                'device_stats' => $deviceStats,
            ]
        ]);
    }

    /**
     * Export activity logs
     */
    public function export(Request $request)
    {
        $query = UserActivityLog::with('user');

        // Apply filters
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $activities = $query->orderBy('created_at', 'desc')->get();

        $filename = 'user_activity_logs_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($activities) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'ID',
                'User Name',
                'Email',
                'IP Address',
                'Device Type',
                'Browser',
                'Platform',
                'Login Time',
                'Logout Time',
                'Session Duration',
                'Status',
                'Activity Type',
                'Created At'
            ]);

            // CSV data
            foreach ($activities as $activity) {
                fputcsv($file, [
                    $activity->id,
                    $activity->user ? $activity->user->name : 'Unknown User',
                    $activity->user ? $activity->user->email : 'N/A',
                    $activity->ip_address,
                    ucfirst($activity->device_type),
                    $activity->browser,
                    $activity->platform,
                    $activity->formatted_login_time,
                    $activity->formatted_logout_time,
                    $activity->session_duration,
                    $activity->is_online ? 'Online' : 'Offline',
                    ucfirst($activity->activity_type),
                    $activity->created_at->format('Y-m-d H:i:s')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Bulk delete activity logs
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:user_activity_logs,id'
        ]);

        try {
            UserActivityLog::whereIn('id', $request->ids)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Selected activity logs deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting activity logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean old activity logs
     */
    public function cleanOldLogs(Request $request)
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:365'
        ]);

        try {
            $cutoffDate = now()->subDays($request->days);
            $deletedCount = UserActivityLog::where('created_at', '<', $cutoffDate)->delete();

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$deletedCount} old activity logs."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error cleaning old logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user activity log settings
     */
    public function getSettings(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $settings = UserActivityLogSetting::getUserSettings($userId);

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save user activity log settings
     */
    public function saveSettings(Request $request)
    {
        try {
            $validator = UserActivityLogSetting::validateSettings($request->all());
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = $request->user()->id;
            $settings = UserActivityLogSetting::saveSettings($validator->validated(), $userId);

            return response()->json([
                'success' => true,
                'message' => 'Settings saved successfully',
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get global settings (admin only)
     */
    public function getGlobalSettings(Request $request)
    {
        try {
            // Check if user has admin permissions
            if (!$request->user()->can('manage logged history')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $settings = UserActivityLogSetting::getGlobalSettings();

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading global settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save global settings (admin only)
     */
    public function saveGlobalSettings(Request $request)
    {
        try {
            // Check if user has admin permissions
            if (!$request->user()->can('manage logged history')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $validator = UserActivityLogSetting::validateSettings($request->all());
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $settings = UserActivityLogSetting::saveGlobalSettings($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Global settings saved successfully',
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving global settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cleanup status and settings
     */
    public function getCleanupStatus(Request $request)
    {
        try {
            $cleanupSettings = UserActivityLogSetting::getCleanupSettings();

            return response()->json([
                'success' => true,
                'data' => $cleanupSettings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading cleanup status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run automatic cleanup based on settings
     */
    public function runAutomaticCleanup(Request $request)
    {
        try {
            if (!UserActivityLogSetting::shouldRunCleanup()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cleanup not due yet'
                ]);
            }

            $settings = UserActivityLogSetting::getGlobalSettings();
            $cutoffDate = now()->subDays($settings['retention_days']);
            $deletedCount = UserActivityLog::where('created_at', '<', $cutoffDate)->delete();

            UserActivityLogSetting::markCleanupCompleted();

            return response()->json([
                'success' => true,
                'message' => "Automatic cleanup completed. Deleted {$deletedCount} old activity logs.",
                'deleted_count' => $deletedCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error running automatic cleanup: ' . $e->getMessage()
            ], 500);
        }
    }
}