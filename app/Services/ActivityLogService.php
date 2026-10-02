<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\UserActivityLogSetting;
use App\Services\Payroll\RateService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;

/**
 * ActivityLogService
 * 
 * Manages user activity tracking, session lifecycle, and cleanup operations.
 * Handles login/logout recording, heartbeat updates, idle detection, and data cleanup.
 */
class ActivityLogService
{
    /**
     * A sign-in a person performed in a browser, as opposed to one a script caused.
     *
     * tinker, artisan, queue workers and the scheduler all reach Auth::login() and
     * fire the same Login event a human sign-in fires. Their request is the one
     * Laravel's SetRequestForConsole bootstrapper builds — User-Agent "Symfony",
     * IP 127.0.0.1 — which is how 56 of 66 "Sign-in recorded" cards were minted
     * without anybody signing in, each one closing the user's live session on its
     * way past. Request::create() sub-requests (DuplexCommitService) carry that
     * same agent inside a real HTTP request, hence the second test rather than
     * runningInConsole() on its own.
     */
    public static function isInteractiveSignIn(): bool
    {
        if (app()->runningInConsole()) {
            return false;
        }

        $agent = trim((string) request()->header('User-Agent'));

        return $agent !== '' && $agent !== 'Symfony';
    }

    /**
     * Record user login activity
     *
     * Creates a new activity log entry when user logs in.
     * Marks any previous sessions as offline.
     */
    public static function recordLogin(User $user, string $ipAddress = null, string $userAgent = null): ?UserActivityLog
    {
        try {
            $userAgentString = $userAgent ?? request()->header('User-Agent') ?? 'Unknown';

            // Get device info from user agent
            $deviceInfo = self::parseUserAgent($userAgentString);
            
            // Mark previous sessions as offline (close any open/idle sessions before starting a new one)
            $now = Carbon::now();
            UserActivityLog::byUser($user->id)
                ->whereIn('status', ['Online', 'Idle'])
                ->update([
                    'status' => 'Offline',
                    'is_online' => false,
                    'logout_time' => $now,
                    'activity_type' => 'logout',
                    'activity_description' => 'Session closed due to new login',
                    // Use application time consistently to avoid UTC drift
                    'duration_minutes' => DB::raw("DATEDIFF(minute, login_time, '" . $now->format('Y-m-d H:i:s') . "')"),
                ]);

            // Create new login record
            $activityLog = UserActivityLog::create([
                'user_id' => $user->id,
                'ip_address' => $ipAddress ?? request()->ip(),
                'device' => self::resolveDeviceCode($deviceInfo['device']),
                'browser' => $deviceInfo['browser'],
                'platform' => $deviceInfo['platform'],
                'login_time' => $now,
                'status' => 'Online',
                'is_online' => true,
                'last_seen_at' => $now,
                'session_id' => session()->getId(),
                'activity_type' => 'login',
                'activity_description' => 'User logged in',
                'user_agent' => $userAgentString,
                'device_type' => $deviceInfo['device'],
                'test_control' => self::getTestControl(),
            ]);

            // Clear online users cache
            self::flushOnlineCaches();

            // The RESOLVED values, not the parameters. This line used to print the
            // unset $ipAddress, so every sign-in read "logged in from" with a null
            // ip — and the user agent, the one field that says whether a browser or
            // a script caused it, was not logged at all.
            Log::info("User {$user->id} logged in from {$activityLog->ip_address}", [
                'user_id' => $user->id,
                'ip_address' => $activityLog->ip_address,
                'device' => $deviceInfo['device'],
                'browser' => $activityLog->browser,
                'user_agent' => $userAgentString,
            ]);

            return $activityLog;

        } catch (\Exception $e) {
            Log::error("Failed to record login for user {$user->id}: {$e->getMessage()}");
            return null;
        }
    }

    /** Keep the login audit row aligned with Laravel's regenerated session ID. */
    public static function syncSessionIdAfterRegeneration(User $user, ?string $previousSessionId): void
    {
        $previousSessionId = (string) $previousSessionId;
        $currentSessionId = (string) session()->getId();

        if ($previousSessionId === '' || $currentSessionId === '' || $previousSessionId === $currentSessionId) {
            return;
        }

        UserActivityLog::byUser($user->id)
            ->where('session_id', $previousSessionId)
            ->where('is_online', true)
            ->whereIn('status', ['Online', 'Idle'])
            ->latest('id')
            ->first()?->update(['session_id' => $currentSessionId]);
    }

    /**
     * Record user logout activity
     * 
     * Marks the user's current session as offline and calculates duration.
     */
    public static function recordLogout(User $user, string $reason = 'manual'): bool
    {
        try {
            $session = UserActivityLog::getCurrentSessionForUser($user->id);

            if (!$session) {
                Log::warning("No active session found for user {$user->id} during logout");
                return false;
            }

            $logoutTime = Carbon::now();
            $durationMinutes = $session->login_time->diffInMinutes($logoutTime);

            $session->update([
                'status' => 'Offline',
                'logout_time' => $logoutTime,
                'duration_minutes' => $durationMinutes,
            ]);

            // Clear online users cache
            self::flushOnlineCaches();

            Log::info("User {$user->id} ({$user->email}) logged out", [
                'user_id' => $user->id,
                'duration_minutes' => $durationMinutes,
                'reason' => $reason,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to record logout for user {$user->id}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Update user's last activity timestamp (heartbeat)
     * 
     * Called via AJAX at regular intervals (30-60 seconds) to keep session alive.
     */
    public static function updateHeartbeat(User $user, string $relatedFileNumber = null): bool
    {
        try {
            $session = UserActivityLog::getCurrentSessionForUser($user->id);

            if (!$session) {
                // No active session, create one
                return self::recordLogin($user) !== null;
            }

            // Update heartbeat
            $updates = [
                'last_seen_at' => Carbon::now(),
                'status' => 'Online',
                'is_online' => true,
            ];

            if ($relatedFileNumber) {
                $updates['related_file_number'] = $relatedFileNumber;
            }

            $updated = $session->update($updates);

            if ($updated) {
                self::flushOnlineCaches();
            }

            return $updated;

        } catch (\Exception $e) {
            Log::error("Failed to update heartbeat for user {$user->id}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Detect and mark idle sessions
     * 
     * Sessions with no heartbeat for 30+ minutes are marked as idle.
     */
    public static function detectIdleUsers(int $idleMinutes = 30): int
    {
        try {
            $staleTime = Carbon::now()->subMinutes($idleMinutes);

            $updatedCount = UserActivityLog::online()
                ->where('last_seen_at', '<', $staleTime)
                ->update([
                    'status' => 'Idle',
                ]);

            if ($updatedCount > 0) {
                Log::info("Marked {$updatedCount} sessions as idle");
                self::flushOnlineCaches();
            }

            return $updatedCount;

        } catch (\Exception $e) {
            Log::error("Failed to detect idle users: {$e->getMessage()}");
            return 0;
        }
    }

    /**
     * Detect and mark stale sessions for cleanup
     * 
     * Sessions with no heartbeat for 120+ minutes are marked offline.
     */
    public static function detectStaleUsers(int $timeoutMinutes = 120): int
    {
        try {
            $staleTime = Carbon::now()->subMinutes($timeoutMinutes);

            $updatedCount = UserActivityLog::whereIn('status', ['Online', 'Idle'])
                ->where('last_seen_at', '<', $staleTime)
                ->update([
                    'status' => 'Offline',
                    'logout_time' => Carbon::now(),
                    'duration_minutes' => DB::raw('DATEDIFF(minute, login_time, GETUTCDATE())'),
                ]);

            if ($updatedCount > 0) {
                Log::info("Marked {$updatedCount} stale sessions as offline");
                self::flushOnlineCaches();
            }

            return $updatedCount;

        } catch (\Exception $e) {
            Log::error("Failed to detect stale users: {$e->getMessage()}");
            return 0;
        }
    }

    /**
     * Perform comprehensive cleanup of old activity logs
     * 
     * - Cleans up test data (7+ days old)
     * - Archives old production logs (90+ days old)
     * - Fixes orphaned entries
     */
    public static function performCleanup(
        int $testDataDays = 7,
        int $archiveDays = 90,
        int $batchSize = 1000
    ): array {
        try {
            $results = [
                'test_data_deleted' => 0,
                'archived_logs_deleted' => 0,
                'orphaned_deleted' => 0,
                'errors' => [],
            ];

            // Clean up old test data
            try {
                $testCutoff = Carbon::now()->subDays($testDataDays);
                $testDeleted = UserActivityLog::testData()
                    ->where('login_time', '<', $testCutoff)
                    ->delete();

                $results['test_data_deleted'] = $testDeleted;
                Log::info("Cleanup: Deleted {$testDeleted} old test data records");

            } catch (\Exception $e) {
                $results['errors'][] = "Test data cleanup failed: {$e->getMessage()}";
                Log::error("Test data cleanup error: {$e->getMessage()}");
            }

            // Archive old production logs
            try {
                $archiveCutoff = Carbon::now()->subDays($archiveDays);
                $archiveDeleted = UserActivityLog::production()
                    ->offline()
                    ->where('logout_time', '<', $archiveCutoff)
                    ->delete();

                $results['archived_logs_deleted'] = $archiveDeleted;
                Log::info("Cleanup: Deleted {$archiveDeleted} old archived logs");

            } catch (\Exception $e) {
                $results['errors'][] = "Archive cleanup failed: {$e->getMessage()}";
                Log::error("Archive cleanup error: {$e->getMessage()}");
            }

            // Clean up orphaned entries (sessions from deleted users)
            try {
                $orphanedDeleted = DB::connection('sqlsrv')
                    ->table('user_activity_logs as log')
                    ->leftJoin('users as u', 'log.user_id', '=', 'u.id')
                    ->whereNull('u.id')
                    ->delete();

                $results['orphaned_deleted'] = $orphanedDeleted;
                Log::info("Cleanup: Deleted {$orphanedDeleted} orphaned records");

            } catch (\Exception $e) {
                $results['errors'][] = "Orphaned cleanup failed: {$e->getMessage()}";
                Log::error("Orphaned cleanup error: {$e->getMessage()}");
            }

            Log::info("Activity log cleanup completed", $results);
            return $results;

        } catch (\Exception $e) {
            Log::error("Cleanup operation failed: {$e->getMessage()}");
            return [
                'test_data_deleted' => 0,
                'archived_logs_deleted' => 0,
                'orphaned_deleted' => 0,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Get count of currently online users
     */
    public static function getOnlineUserCount(): int
    {
        return Cache::remember('activity_logs_online_count', 60, function () {
            return UserActivityLog::online()->count();
        });
    }

    /**
     * Get list of currently online users
     * 
     * Cache TTL: 2 minutes
     */
    public static function getOnlineUsers()
    {
        return Cache::remember('activity_logs_online_users', 120, function () {
            return UserActivityLog::getOnlineUsers();
        });
    }

    /**
     * Get idle users for display/notification
     */
    public static function getIdleUsers()
    {
        return UserActivityLog::idle()
            ->with('user')
            ->orderBy('last_seen_at', 'DESC')
            ->get();
    }

    /**
     * Clear cached online user data to keep counts in sync
     */
    public static function flushOnlineCaches(): void
    {
        Cache::forget('activity_logs_online_users');
        Cache::forget('activity_logs_online_count');
    }

    /**
     * Automatically logout sessions that have exceeded configured work hours.
     */
    public static function autoLogoutExpiredSessions(?Carbon $now = null, bool $dryRun = false, ?int $limit = null): array
    {
        $settings = config('attendance.auto_logout', []);
        if (!($settings['enabled'] ?? false)) {
            return [
                'processed' => 0,
                'auto_logged_out' => 0,
                'sessions_removed' => 0,
                'skipped' => [],
                'dry_run' => $dryRun,
                'timestamp' => Carbon::now()->toDateTimeString(),
                'note' => 'auto_logout_disabled',
            ];
        }

        $now = $now ? $now->copy() : Carbon::now(config('app.timezone'));
        $graceMinutes = max(0, (int) ($settings['grace_minutes'] ?? 0));
        $chunkSize = max(1, (int) ($settings['chunk_size'] ?? 100));
        if ($limit !== null) {
            $chunkSize = min($chunkSize, max(1, $limit));
        }

        $rateService = app(RateService::class);

        $processed = 0;
        $autoLoggedOut = 0;
        $sessionsRemoved = 0;
        $skipped = [];
        $cacheInvalidated = false;

        $query = UserActivityLog::query()
            ->where('is_online', true)
            ->whereIn('status', ['Online', 'Idle'])
            ->whereNotNull('login_time')
            ->with(['user' => function ($builder) {
                $builder->select(
                    'id',
                    'first_name',
                    'last_name',
                    'is_active',
                    'man_hours_per_day',
                    'shift_code',
                    'auto_deactivate',
                    'staff_type_category',
                    // Needed by the auto-logout attendance SMS below; a column
                    // left out of this list reads as null, not as a lazy load.
                    'phone_number'
                );
            }])
            ->orderBy('id');

        $query->chunkById($chunkSize, function ($sessions) use (
            $rateService,
            $now,
            $graceMinutes,
            $settings,
            $dryRun,
            $limit,
            &$processed,
            &$autoLoggedOut,
            &$sessionsRemoved,
            &$skipped,
            &$cacheInvalidated
        ) {
            foreach ($sessions as $session) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }

                $processed++;

                $user = $session->user;
                if (!$user) {
                    $skipped[] = ['session_id' => $session->id, 'reason' => 'missing_user'];
                    continue;
                }

                if ((int) ($user->is_active ?? 0) !== 1) {
                    $skipped[] = ['session_id' => $session->id, 'user_id' => $user->id, 'reason' => 'user_inactive'];
                    continue;
                }

                if ($user->auto_deactivate === false) {
                    $skipped[] = ['session_id' => $session->id, 'user_id' => $user->id, 'reason' => 'auto_deactivate_disabled'];
                    continue;
                }

                if (!$session->login_time) {
                    $skipped[] = ['session_id' => $session->id, 'user_id' => $user->id, 'reason' => 'missing_login_time'];
                    continue;
                }

                $evaluation = self::evaluateAutoLogoutWindow($session, $user, $rateService, $now, $graceMinutes, $settings);

                if (!$evaluation['should_logout']) {
                    continue;
                }

                if ($dryRun) {
                    $autoLoggedOut++;
                    continue;
                }

                $result = self::finalizeAutoLogout($session, $evaluation['logout_time'], $evaluation['description']);
                if ($result['updated']) {
                    $autoLoggedOut++;
                    $sessionsRemoved += $result['sessions_removed'];
                    $cacheInvalidated = true;

                    // DELIBERATELY SILENT.
                    //
                    // This path used to send both the sign-out SMS and a
                    // "session closed automatically" bell entry. Neither goes
                    // out any more. Auto-logout fires off login_time plus the
                    // shift allowance and never looks at whether the person is
                    // doing anything, so the notice landed on staff who were
                    // still at their desks mid-task — which is what made it
                    // worth removing rather than rewording.
                    //
                    // Announcing a sign-out is now the manual path's job alone
                    // (App\Listeners\LogUserLogout). The attendance row and the
                    // session teardown above are unchanged: the record is still
                    // written, it is only the message about it that stops.
                } else {
                    $skipped[] = ['session_id' => $session->id, 'user_id' => $user->id, 'reason' => 'optimistic_lock_failed'];
                }
            }
        });

        if ($cacheInvalidated) {
            self::flushOnlineCaches();
        }

        return [
            'processed' => $processed,
            'auto_logged_out' => $autoLoggedOut,
            'sessions_removed' => $sessionsRemoved,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
            'timestamp' => $now->toDateTimeString(),
        ];
    }

    /**
     * Force logout all active sessions for the supplied user.
     */
    public static function forceLogoutUser(int $userId, string $description = 'Forced logout by administrator'): int
    {
        $sessions = UserActivityLog::query()
            ->where('user_id', $userId)
            ->where('is_online', true)
            ->whereIn('status', ['Online', 'Idle'])
            ->get();

        if ($sessions->isEmpty()) {
            return 0;
        }

        $now = Carbon::now(config('app.timezone'));
        $count = 0;
        $sessionsRemoved = 0;

        foreach ($sessions as $session) {
            $result = self::finalizeAutoLogout($session, $now->copy(), $description);
            if ($result['updated']) {
                $count++;
                $sessionsRemoved += $result['sessions_removed'];
            }
        }

        if ($count > 0) {
            self::flushOnlineCaches();

            // Also silent, by the same decision that quietened auto-logout:
            // only a sign-out the user performed themselves is announced. The
            // administrator who ended the session has this log line, and the
            // user sees the sign-out reflected on their next sign-in.
            Log::info('Forced logout completed', [
                'user_id' => $userId,
                'sessions_closed' => $count,
                'sessions_removed' => $sessionsRemoved,
                'description' => $description,
            ]);
        }

        return $count;
    }

    /**
     * Determine whether a session should be auto-logged out.
     */
    private static function evaluateAutoLogoutWindow(
        UserActivityLog $session,
        User $user,
        RateService $rateService,
        Carbon $now,
        int $graceMinutes,
        array $settings
    ): array {
        $loginAt = $session->login_time instanceof Carbon
            ? $session->login_time->copy()
            : Carbon::parse($session->login_time, config('app.timezone'));

        $profile = $rateService->resolveShiftProfile($user, $loginAt);
        $shiftHours = $profile['hours'] ?? ($user->man_hours_per_day ?? 0);
        $shiftHours = max(1, (int) $shiftHours);
        $baseEnd = $loginAt->copy()->addMinutes($shiftHours * 60);
        $reason = 'shift_hours';

        $shiftEnd = self::resolveShiftEnd($user, $loginAt);
        if ($shiftEnd) {
            if ($shiftEnd->greaterThan($loginAt) && $shiftEnd->lessThan($baseEnd)) {
                $baseEnd = $shiftEnd->copy();
                $reason = 'shift_schedule';
            } elseif ($shiftEnd->lessThanOrEqualTo($loginAt)) {
                $baseEnd = $loginAt->copy()->addMinutes(1);
                $reason = 'shift_schedule_elapsed';
            }
        }

        $maxEnd = $baseEnd->copy();
        if (($settings['track_overtime'] ?? true) && ($profile['allow_overtime'] ?? false)) {
            if ($profile['overtime_cap'] !== null) {
                $overtimeMinutes = max(0, (int) round($profile['overtime_cap'] * 60));
                if ($overtimeMinutes > 0) {
                    $maxEnd->addMinutes($overtimeMinutes);
                    $reason = 'overtime_cap';
                }
            } else {
                $extension = max(0, (int) ($settings['overtime_extension_minutes'] ?? 120));
                if ($extension > 0) {
                    $maxEnd->addMinutes($extension);
                    $reason = 'overtime_extension';
                }
            }
        }

        $cutoff = $maxEnd->copy()->addMinutes($graceMinutes);
        if ($now->lessThan($cutoff)) {
            return [
                'should_logout' => false,
                'logout_time' => null,
                'description' => $reason,
            ];
        }

        $logoutTime = $maxEnd->lessThan($now) ? $maxEnd->copy() : $now->copy();
        if ($logoutTime->lessThanOrEqualTo($loginAt)) {
            $logoutTime = $loginAt->copy()->addMinutes(1);
        }

        return [
            'should_logout' => true,
            'logout_time' => $logoutTime,
            'description' => 'Auto logout enforced (' . $reason . ')',
        ];
    }

    /**
     * Persist the auto logout update and remove backing session payloads.
     */
    private static function finalizeAutoLogout(UserActivityLog $session, Carbon $logoutTime, string $description): array
    {
        $loginAt = $session->login_time instanceof Carbon
            ? $session->login_time->copy()
            : Carbon::parse($session->login_time, config('app.timezone'));

        if ($logoutTime->lessThanOrEqualTo($loginAt)) {
            $logoutTime = $loginAt->copy()->addMinutes(1);
        }

        $duration = max(1, $loginAt->diffInMinutes($logoutTime));

        $updated = UserActivityLog::query()
            ->where('id', $session->id)
            ->where('is_online', true)
            ->update([
                'status' => 'Offline',
                'is_online' => false,
                'logout_time' => $logoutTime,
                'duration_minutes' => $duration,
                'last_seen_at' => $logoutTime,
                'activity_type' => 'logout',
                'activity_description' => $description,
            ]);

        if ($updated) {
            $removed = self::deleteSessionRecord($session->session_id, $session->user_id);

            Log::info('Auto logout applied', [
                'session_id' => $session->id,
                'user_id' => $session->user_id,
                'logout_time' => $logoutTime->toDateTimeString(),
                'duration_minutes' => $duration,
                'description' => $description,
                'sessions_removed' => $removed,
            ]);

            return ['updated' => true, 'sessions_removed' => $removed];
        }

        return ['updated' => false, 'sessions_removed' => 0];
    }

    /**
     * Resolve configured shift end for the user relative to the login date.
     */
    private static function resolveShiftEnd(User $user, Carbon $loginAt): ?Carbon
    {
        $code = $user->shift_code ?: 'full_day';
        $definition = config('attendance.shifts.' . $code);
        if (!$definition || empty($definition['end'])) {
            return null;
        }

        $timezone = config('app.timezone');

        try {
            $end = Carbon::createFromFormat(
                'Y-m-d H:i',
                $loginAt->format('Y-m-d') . ' ' . $definition['end'],
                $timezone
            );
        } catch (\Throwable $exception) {
            Log::warning('Failed to parse shift end', [
                'user_id' => $user->id,
                'shift_code' => $code,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (!empty($definition['overnight'])) {
            $loginTime = $loginAt->format('H:i');
            if ($loginTime < $definition['end']) {
                return $end;
            }

            if ($end->lessThanOrEqualTo($loginAt)) {
                $end->addDay();
            }

            return $end;
        }

        return $end;
    }

    /**
     * Delete persisted session payloads for the supplied session identifier.
     */
    private static function deleteSessionRecord(?string $sessionId, ?int $userId = null): int
    {
        if (!$sessionId && !$userId) {
            return 0;
        }

        $table = config('session.table', 'sessions');
        $connections = [];

        $sessionDriver = config('session.driver');
        if ($sessionDriver === 'database') {
            $configuredConnection = config('session.connection');
            if ($configuredConnection) {
                $connections[] = $configuredConnection;
            } else {
                $connections[] = config('database.default');
            }
        }

        $connections[] = 'sqlsrv';
        $connections[] = null;

        $connections = array_values(array_unique(array_filter($connections, static function ($value) {
            return $value !== '';
        })));

        $removed = 0;

        foreach ($connections as $connection) {
            try {
                $builder = $connection
                    ? DB::connection($connection)->table($table)
                    : DB::table($table);

                if ($sessionId) {
                    $builder->where('id', $sessionId);
                }

                if ($userId) {
                    $builder->where('user_id', $userId);
                }

                $deleted = $builder->delete();
                $removed += $deleted;
            } catch (\Throwable $exception) {
                Log::debug('Unable to delete session payload during auto logout', [
                    'connection' => $connection,
                    'session_id' => $sessionId,
                    'user_id' => $userId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $removed;
    }

    /**
     * Get activity statistics for dashboard
     */
    public static function getActivityStatistics(int $days = 30): array
    {
        return UserActivityLog::getActivityStats($days);
    }

    /**
     * Parse user agent string for device/browser/platform info
     * 
     * Uses Jenssegers\Agent package for parsing
     */
    private static function parseUserAgent(string $userAgent): array
    {
        if (!class_exists(Agent::class)) {
            return self::fallbackParseUserAgent($userAgent);
        }

        try {
            $agent = new Agent();
            $agent->setUserAgent($userAgent);

            return [
                'device' => $agent->isMobile() ? 'mobile' : ($agent->isTablet() ? 'tablet' : 'desktop'),
                'browser' => $agent->browser() ?? 'Unknown',
                'platform' => $agent->platform() ?? 'Unknown',
            ];

        } catch (\Exception $e) {
            Log::warning("Failed to parse user agent: {$e->getMessage()}");

            // Fallback to regex parsing
            return self::fallbackParseUserAgent($userAgent);
        }
    }

    /**
     * Fallback user agent parser using regex
     */
    private static function fallbackParseUserAgent(string $userAgent): array
    {
        $device = 'desktop';
        $browser = 'Unknown';
        $platform = 'Unknown';

        // Device detection
        if (preg_match('/mobile|android|iphone|ipad|phone/i', $userAgent)) {
            $device = 'mobile';
        } elseif (preg_match('/tablet|ipad/i', $userAgent)) {
            $device = 'tablet';
        }

        // Browser detection
        if (preg_match('/chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/firefox/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/safari/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/edge/i', $userAgent)) {
            $browser = 'Edge';
        }

        // Platform detection
        if (preg_match('/windows/i', $userAgent)) {
            $platform = 'Windows';
        } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
            $platform = 'macOS';
        } elseif (preg_match('/linux/i', $userAgent)) {
            $platform = 'Linux';
        } elseif (preg_match('/android/i', $userAgent)) {
            $platform = 'Android';
        } elseif (preg_match('/ios|iphone|ipad/i', $userAgent)) {
            $platform = 'iOS';
        }

        return [
            'device' => $device,
            'browser' => $browser,
            'platform' => $platform,
        ];
    }

    /**
     * Map device labels to numeric codes expected by legacy schema.
     */
    private static function resolveDeviceCode(?string $device): int
    {
        return match (strtolower($device ?? '')) {
            'mobile' => 2,
            'tablet' => 3,
            'desktop' => 1,
            default => 0,
        };
    }

    /**
     * Get test control flag (TEST or PRO based on environment)
     */
    private static function getTestControl(): string
    {
        // Check if running tests or in test environment
        if (app()->environment(['testing', 'test']) || 
            config('app.debug') && app()->environment('local')) {
            return 'TEST';
        }

        return 'PRO';
    }

    /**
     * Link activity session to a file number
     */
    public static function linkFileNumber(User $user, string $fileNumber): bool
    {
        try {
            $session = UserActivityLog::getCurrentSessionForUser($user->id);

            if (!$session) {
                return false;
            }

            return $session->linkFileNumber($fileNumber);

        } catch (\Exception $e) {
            Log::error("Failed to link file number for user {$user->id}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Unlink activity session from file number
     */
    public static function unlinkFileNumber(User $user): bool
    {
        try {
            $session = UserActivityLog::getCurrentSessionForUser($user->id);

            if (!$session) {
                return false;
            }

            return $session->unlinkFileNumber();

        } catch (\Exception $e) {
            Log::error("Failed to unlink file number for user {$user->id}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get or create user activity log settings
     */
    public static function getSettings(User $user): UserActivityLogSetting
    {
        return UserActivityLogSetting::firstOrCreateDefaults($user->id);
    }

    /**
     * Update user activity log settings
     */
    public static function updateSettings(User $user, array $data): UserActivityLogSetting
    {
        $settings = self::getSettings($user);

        if (isset($data['timezone'])) {
            $settings->setTimezone($data['timezone']);
        }

        if (isset($data['notes'])) {
            $settings->setNotes($data['notes']);
        }

        return $settings;
    }
}
