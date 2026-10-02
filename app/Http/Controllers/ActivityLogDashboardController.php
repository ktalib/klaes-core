<?php

namespace App\Http\Controllers;

use App\Models\UserActivityLog;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * ActivityLogDashboardController
 *
 * Handles user activity log dashboard with statistics, charts,
 * real-time updates, and activity visualization.
 */
class ActivityLogDashboardController extends Controller
{
    /**
     * Service instance
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
     * Display the activity log dashboard
     *
     * GET /activity-logs/dashboard
     *
     * Shows:
     * - Online user count (real-time)
     * - Total sessions today
     * - Average session duration
     * - Activity timeline graph
     * - Top browsers/platforms
     * - Recent login activity
     */
    public function index(Request $request)
    {
        try {
            // Authorization - admin and those with permission
            if (!Auth::user()->isAdmin() && !Auth::user()->can('view-activity-logs')) {
                return abort(403, 'Unauthorized');
            }

            // Get date range from request (default last 7 days)
            $days = (int)$request->input('days', 7);
            $startDate = Carbon::now()->subDays($days);

            // Fetch statistics
            $stats = $this->getDashboardStatistics($days);

            // Fetch chart data
            $chartData = $this->getChartData($startDate);

            // Fetch recent activity
            $recentActivity = UserActivityLog::with('user:id,name,email')
                ->where('login_time', '>=', $startDate)
                ->orderBy('login_time', 'desc')
                ->limit(20)
                ->get();

            // Browser statistics
            $browserStats = DB::connection('sqlsrv')
                ->table('user_activity_logs')
                ->where('login_time', '>=', $startDate)
                ->where('browser', '!=', null)
                ->groupBy('browser')
                ->selectRaw('browser, COUNT(*) as count')
                ->orderByRaw('count DESC')
                ->limit(10)
                ->get();

            // Platform statistics
            $platformStats = DB::connection('sqlsrv')
                ->table('user_activity_logs')
                ->where('login_time', '>=', $startDate)
                ->where('platform', '!=', null)
                ->groupBy('platform')
                ->selectRaw('platform, COUNT(*) as count')
                ->orderByRaw('count DESC')
                ->limit(10)
                ->get();

            // Device statistics
            $deviceStats = DB::connection('sqlsrv')
                ->table('user_activity_logs')
                ->where('login_time', '>=', $startDate)
                ->where('device', '!=', null)
                ->groupBy('device')
                ->selectRaw('device, COUNT(*) as count')
                ->orderByRaw('count DESC')
                ->limit(10)
                ->get();

            // Hour-by-hour activity
            $hourlyActivity = DB::connection('sqlsrv')
                ->table('user_activity_logs')
                ->where('login_time', '>=', $startDate)
                ->selectRaw('DATEPART(hour, login_time) as hour, COUNT(*) as count')
                ->groupByRaw('DATEPART(hour, login_time)')
                ->orderByRaw('hour ASC')
                ->get();

            return view('activity.dashboard', [
                'stats' => $stats,
                'chartData' => $chartData,
                'recentActivity' => $recentActivity,
                'browserStats' => $browserStats,
                'platformStats' => $platformStats,
                'deviceStats' => $deviceStats,
                'hourlyActivity' => $hourlyActivity,
                'days' => $days,
                'startDate' => $startDate,
            ]);

        } catch (\Exception $e) {
            Log::error("Error loading dashboard: {$e->getMessage()}");
            return abort(500, 'Failed to load dashboard');
        }
    }

    /**
     * Get dashboard statistics
     *
     * Returns key metrics for the dashboard
     */
    public function getDashboardStatistics(int $days = 7): array
    {
        try {
            $startDate = Carbon::now()->subDays($days);

            // Online users (current)
            $onlineCount = $this->activityLogService->getOnlineUserCount();

            // Total sessions in period
            $totalSessions = UserActivityLog::where('login_time', '>=', $startDate)->count();

            // Unique users in period
            $uniqueUsers = DB::connection('sqlsrv')
                ->table('user_activity_logs')
                ->where('login_time', '>=', $startDate)
                ->distinct('user_id')
                ->count('user_id');

            // Average session duration
            $avgDuration = DB::connection('sqlsrv')
                ->table('user_activity_logs')
                ->where('login_time', '>=', $startDate)
                ->where('duration_minutes', '!=', null)
                ->avg('duration_minutes') ?? 0;

            // Max session duration
            $maxDuration = DB::connection('sqlsrv')
                ->table('user_activity_logs')
                ->where('login_time', '>=', $startDate)
                ->max('duration_minutes') ?? 0;

            // Idle users count
            $idleCount = UserActivityLog::idle()->count();

            // Stale users count (offline but recently active)
            $staleCount = UserActivityLog::stale()->count();

            // Peak hour (hour with most logins)
            $peakHour = DB::connection('sqlsrv')
                ->selectOne(
                    "SELECT TOP 1 DATEPART(hour, login_time) as hour, COUNT(*) as count 
                     FROM user_activity_logs 
                     WHERE login_time >= ? 
                     GROUP BY DATEPART(hour, login_time) 
                     ORDER BY count DESC",
                    [$startDate]
                );

            return [
                'online_count' => $onlineCount,
                'total_sessions' => $totalSessions,
                'unique_users' => $uniqueUsers,
                'avg_duration' => round($avgDuration, 2),
                'max_duration' => $maxDuration,
                'idle_count' => $idleCount,
                'stale_count' => $staleCount,
                'peak_hour' => $peakHour ? $peakHour->hour : 'N/A',
            ];

        } catch (\Exception $e) {
            Log::error("Error calculating statistics: {$e->getMessage()}");
            return [
                'online_count' => 0,
                'total_sessions' => 0,
                'unique_users' => 0,
                'avg_duration' => 0,
                'max_duration' => 0,
                'idle_count' => 0,
                'stale_count' => 0,
                'peak_hour' => 'N/A',
            ];
        }
    }

    /**
     * Get chart data for timeline visualization
     */
    public function getChartData(Carbon $startDate): array
    {
        try {
            // Daily activity
            $dailyActivity = DB::connection('sqlsrv')
                ->table('user_activity_logs')
                ->where('login_time', '>=', $startDate)
                ->selectRaw('CAST(login_time as DATE) as date, COUNT(*) as count')
                ->groupByRaw('CAST(login_time as DATE)')
                ->orderByRaw('date ASC')
                ->get();

            $dates = [];
            $counts = [];

            foreach ($dailyActivity as $activity) {
                $dates[] = Carbon::parse($activity->date)->format('M d');
                $counts[] = $activity->count;
            }

            return [
                'labels' => $dates,
                'data' => $counts,
            ];

        } catch (\Exception $e) {
            Log::error("Error getting chart data: {$e->getMessage()}");
            return [
                'labels' => [],
                'data' => [],
            ];
        }
    }

    /**
     * Get real-time online user count via AJAX
     *
     * GET /api/activity/online-count
     */
    public function getOnlineCount()
    {
        try {
            $count = $this->activityLogService->getOnlineUserCount();

            return response()->json([
                'success' => true,
                'data' => [
                    'online_count' => $count,
                    'timestamp' => now()->toIso8601String(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting online count: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch online count'
            ], 500);
        }
    }

    /**
     * Get list of currently online users via AJAX
     *
     * GET /api/activity/online-users
     */
    public function getOnlineUsers()
    {
        try {
            $onlineUsers = $this->activityLogService->getOnlineUsers();

            return response()->json([
                'success' => true,
                'data' => [
                    'users' => $onlineUsers->map(function ($log) {
                        return [
                            'id' => $log->user_id,
                            'name' => $log->user->name ?? 'Unknown',
                            'email' => $log->user->email ?? 'N/A',
                            'device' => $log->device,
                            'browser' => $log->browser,
                            'ip_address' => $log->ip_address,
                            'last_seen_at' => $log->last_seen_at?->diffForHumans(),
                            'login_time' => $log->login_time?->format('Y-m-d H:i:s'),
                        ];
                    }),
                    'total' => $onlineUsers->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting online users: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch online users'
            ], 500);
        }
    }

    /**
     * Get dashboard statistics via AJAX for real-time updates
     *
     * GET /api/activity/dashboard-stats
     */
    public function getDashboardStats(Request $request)
    {
        try {
            $days = (int)$request->input('days', 7);
            $stats = $this->getDashboardStatistics($days);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'timestamp' => now()->toIso8601String(),
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting dashboard stats: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard statistics'
            ], 500);
        }
    }

    /**
     * Get activity timeline data for chart
     *
     * GET /api/activity/timeline
     *
     * Query Parameters:
     *   - days: int (default 7)
     */
    public function getTimeline(Request $request)
    {
        try {
            $days = (int)$request->input('days', 7);
            $startDate = Carbon::now()->subDays($days);

            $chartData = $this->getChartData($startDate);

            return response()->json([
                'success' => true,
                'data' => $chartData,
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting timeline: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch timeline data'
            ], 500);
        }
    }

    /**
     * Get browser statistics via AJAX
     *
     * GET /api/activity/browser-stats
     *
     * Query Parameters:
     *   - days: int (default 7)
     */
    public function getBrowserStats(Request $request)
    {
        try {
            $days = (int)$request->input('days', 7);
            $startDate = Carbon::now()->subDays($days);

            $browserStats = DB::connection('sqlsrv')
                ->table('user_activity_logs')
                ->where('login_time', '>=', $startDate)
                ->where('browser', '!=', null)
                ->groupBy('browser')
                ->selectRaw('browser, COUNT(*) as count')
                ->orderByRaw('count DESC')
                ->limit(10)
                ->get();

            $labels = $browserStats->pluck('browser')->toArray();
            $data = $browserStats->pluck('count')->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => $labels,
                    'data' => $data,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting browser stats: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch browser statistics'
            ], 500);
        }
    }

    /**
     * Get platform statistics via AJAX
     *
     * GET /api/activity/platform-stats
     *
     * Query Parameters:
     *   - days: int (default 7)
     */
    public function getPlatformStats(Request $request)
    {
        try {
            $days = (int)$request->input('days', 7);
            $startDate = Carbon::now()->subDays($days);

            $platformStats = DB::connection('sqlsrv')
                ->table('user_activity_logs')
                ->where('login_time', '>=', $startDate)
                ->where('platform', '!=', null)
                ->groupBy('platform')
                ->selectRaw('platform, COUNT(*) as count')
                ->orderByRaw('count DESC')
                ->limit(10)
                ->get();

            $labels = $platformStats->pluck('platform')->toArray();
            $data = $platformStats->pluck('count')->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => $labels,
                    'data' => $data,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting platform stats: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch platform statistics'
            ], 500);
        }
    }

    /**
     * Get device statistics via AJAX
     *
     * GET /api/activity/device-stats
     *
     * Query Parameters:
     *   - days: int (default 7)
     */
    public function getDeviceStats(Request $request)
    {
        try {
            $days = (int)$request->input('days', 7);
            $startDate = Carbon::now()->subDays($days);

            $deviceStats = DB::connection('sqlsrv')
                ->table('user_activity_logs')
                ->where('login_time', '>=', $startDate)
                ->where('device', '!=', null)
                ->groupBy('device')
                ->selectRaw('device, COUNT(*) as count')
                ->orderByRaw('count DESC')
                ->limit(10)
                ->get();

            $labels = $deviceStats->pluck('device')->toArray();
            $data = $deviceStats->pluck('count')->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => $labels,
                    'data' => $data,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting device stats: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch device statistics'
            ], 500);
        }
    }

    /**
     * Heartbeat endpoint - keep session alive
     *
     * POST /api/activity/heartbeat
     *
     * Request Body (optional):
     * {
     *   "file_number": "ST-RES-2025-001"
     * }
     */
    public function recordHeartbeat(Request $request)
    {
        try {
            $user = Auth::user();
            $fileNumber = $request->input('file_number');

            // Call service to update heartbeat
            $this->activityLogService->updateHeartbeat($user, $fileNumber);

            return response()->json([
                'success' => true,
                'message' => 'Heartbeat recorded',
                'timestamp' => now()->toIso8601String(),
            ]);

        } catch (\Exception $e) {
            Log::warning("Heartbeat recording failed: {$e->getMessage()}");
            // Don't fail - just log warning
            return response()->json([
                'success' => false,
                'message' => 'Failed to record heartbeat'
            ], 500);
        }
    }
}
