<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * UserActivityLog Model - Phase 2 Implementation
 *
 * Tracks user activity: login/logout times, session duration, heartbeat (last_seen_at),
 * device/browser/platform info, environment (TEST|PRO), related file number linkage,
 * and status (Online|Offline|Idle) with helper scopes and statistics utilities.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $ip_address
 * @property string|null $device
 * @property string|null $browser
 * @property string|null $platform
 * @property Carbon|null $login_time
 * @property Carbon|null $logout_time
 * @property int|null $duration_minutes
 * @property string $status
 * @property Carbon|null $last_seen_at
 * @property string|null $related_file_number
 * @property string $test_control
 * @property Carbon|null $indexed_at
 * @property bool $is_online
 * @property string|null $activity_type
 * @property string|null $session_id
 * @property string|null $user_agent
 * @property string|null $device_type
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UserActivityLog extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'user_activity_logs';
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'ip_address',
        'device',
        'browser',
        'platform',
        'login_time',
        'logout_time',
        'duration_minutes',
        'status',
        'last_seen_at',
        'related_file_number',
        'test_control',
        'indexed_at',
        'is_online',
        'activity_type',
        'activity_description',
        'session_id',
        'user_agent',
        'device_type',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'duration_minutes' => 'integer',
        'login_time' => 'datetime',
        'logout_time' => 'datetime',
        'last_seen_at' => 'datetime',
        'indexed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_online' => 'boolean',
    ];

    // Relationship
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // ===================== Scopes: Status =====================
    public function scopeOnline(Builder $query): Builder
    {
        $freshThreshold = Carbon::now()->subMinutes(5);

        return $query
            ->where('status', 'Online')
            ->where('is_online', true)
            ->where(function ($subQuery) use ($freshThreshold) {
                $subQuery->where(function ($recentSeen) use ($freshThreshold) {
                        $recentSeen->whereNotNull('last_seen_at')
                            ->where('last_seen_at', '>=', $freshThreshold);
                    })
                    ->orWhere(function ($recentLogin) use ($freshThreshold) {
                        $recentLogin->whereNull('last_seen_at')
                            ->whereNotNull('login_time')
                            ->where('login_time', '>=', $freshThreshold);
                    });
            });
    }

    public function scopeOffline(Builder $query): Builder
    {
        return $query->where('status', 'Offline');
    }

    public function scopeIdle(Builder $query): Builder
    {
        return $query->where('status', 'Idle');
    }

    // ===================== Scopes: User & Date =====================
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByDateRange(Builder $query, $from, $to): Builder
    {
        $from = $from instanceof Carbon ? $from : Carbon::parse($from);
        $to   = $to instanceof Carbon ? $to : Carbon::parse($to);
        return $query->whereBetween('login_time', [$from, $to]);
    }

    // ===================== Scopes: Environment =====================
    public function scopeTestData(Builder $query): Builder
    {
        return $query->where('test_control', 'TEST');
    }

    public function scopeProduction(Builder $query): Builder
    {
        return $query->where('test_control', 'PRO');
    }

    public function scopeStale(Builder $query, int $minutes = 30): Builder
    {
        $staleTime = Carbon::now()->subMinutes($minutes);
        return $query->where('last_seen_at', '<', $staleTime);
    }

    public function scopeRecent(Builder $query, $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ===================== Status Helpers =====================
    public function isOnline(): bool
    {
        return $this->status === 'Online';
    }

    public function isOffline(): bool
    {
        return $this->status === 'Offline';
    }

    public function isIdle(): bool
    {
        return $this->status === 'Idle';
    }

    public function isStale(int $minutes = 30): bool
    {
        if (!$this->last_seen_at) return false;
        return $this->last_seen_at->isBefore(Carbon::now()->subMinutes($minutes));
    }

    public function hasTimedOut(int $minutes = 120): bool
    {
        if (!$this->logout_time) return false;
        return $this->logout_time->isBefore(Carbon::now()->subMinutes($minutes));
    }

    // ===================== Session Management =====================
    public function recordHeartbeat(): bool
    {
        return $this->update([
            'last_seen_at' => Carbon::now(),
            'status' => 'Online',
            'is_online' => true,
        ]);
    }

    public function markIdle(): bool
    {
        return $this->update([
            'status' => 'Idle',
            'is_online' => true,
        ]);
    }

    public function markOffline(): bool
    {
        $logoutTime = Carbon::now();
        $durationMinutes = $this->login_time
            ? $this->login_time->diffInMinutes($logoutTime)
            : null;

        return $this->update([
            'status' => 'Offline',
            'logout_time' => $logoutTime,
            'duration_minutes' => $durationMinutes,
            'is_online' => false,
        ]);
    }

    public function updateDeviceInfo(string $device, string $browser, string $platform): bool
    {
        return $this->update([
            'device' => $device,
            'browser' => $browser,
            'platform' => $platform,
        ]);
    }

    public function linkFileNumber(string $fileNumber): bool
    {
        return $this->update(['related_file_number' => $fileNumber]);
    }

    public function unlinkFileNumber(): bool
    {
        return $this->update(['related_file_number' => null]);
    }

    // ===================== Accessors =====================
    public function getFormattedLogoutTimeAttribute(): string
    {
        return $this->logout_time ? $this->logout_time->format('Y-m-d H:i:s') : '-';
    }

    public function getFormattedLoginTimeAttribute(): string
    {
        return $this->login_time ? $this->login_time->format('Y-m-d H:i:s') : '-';
    }

    public function getSessionDurationAttribute(): string
    {
        if (!$this->login_time) return '-';
        $end = $this->logout_time ?: now();
        $mins = $this->login_time->diffInMinutes($end);
        if ($mins < 60) return $mins . ' min';
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        return "{$h}h {$m}m";
    }

    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration_minutes) return 'N/A';
        $hours = intdiv($this->duration_minutes, 60);
        $minutes = $this->duration_minutes % 60;
        return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
    }

    public function getDeviceDisplayAttribute(): string
    {
        return trim(($this->device ?? '-') . ' • ' . ($this->browser ?? '-'));
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Online' => '#10b981',
            'Idle' => '#f59e0b',
            'Offline' => '#ef4444',
            default => '#6b7280',
        };
    }

    // Backwards compatibility badge (HTML)
    public function getOnlineStatusBadgeAttribute(): string
    {
        if ($this->is_online) {
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"><span class="w-2 h-2 bg-green-400 rounded-full mr-1"></span>Online</span>';
        }
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"><span class="w-2 h-2 bg-gray-400 rounded-full mr-1"></span>Offline</span>';
    }

    // ===================== Static Helpers =====================
    public static function getLatestForUser(int $userId)
    {
        return static::byUser($userId)->latest('login_time')->first();
    }

    public static function getCurrentSessionForUser(int $userId)
    {
        return static::byUser($userId)
            ->whereIn('status', ['Online', 'Idle'])
            ->latest('login_time')
            ->first();
    }

    public static function getDurationStatsForUser(int $userId, Carbon $from = null, Carbon $to = null)
    {
        $query = static::byUser($userId)
            ->offline()
            ->whereNotNull('duration_minutes');

        if ($from && $to) {
            $query->byDateRange($from, $to);
        }

        return $query->selectRaw("
            COUNT(*) as session_count,
            AVG(duration_minutes) as avg_duration,
            MIN(duration_minutes) as min_duration,
            MAX(duration_minutes) as max_duration,
            SUM(duration_minutes) as total_duration
        ")->first();
    }

    public static function countOnlineUsers(): int
    {
        return static::online()->count();
    }

    public static function getOnlineUsers()
    {
        return static::online()
            ->with('user')
            ->orderBy('last_seen_at', 'DESC')
            ->get();
    }

    public static function getStaleForCleanup(int $timeoutMinutes = 120)
    {
        return static::whereIn('status', ['Online', 'Idle'])
            ->stale($timeoutMinutes)
            ->get();
    }

    public static function cleanupTestData(int $daysOld = 7): int
    {
        $cutoff = Carbon::now()->subDays($daysOld);
        return static::testData()
            ->where('login_time', '<', $cutoff)
            ->delete();
    }

    public static function cleanupArchivedLogs(int $daysOld = 90): int
    {
        $cutoff = Carbon::now()->subDays($daysOld);
        return static::offline()
            ->where('logout_time', '<', $cutoff)
            ->delete();
    }

    public static function logActivity($userId, $activityType = 'login', $additionalData = [])
    {
        $userAgent = request()->header('User-Agent');
        $ipAddress = request()->ip();
        $deviceInfo = self::parseUserAgent($userAgent);

        $data = array_merge([
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'device_type' => $deviceInfo['device'],
            'browser' => $deviceInfo['browser'],
            'platform' => $deviceInfo['platform'],
            'session_id' => session()->getId(),
            'activity_type' => $activityType,
            'is_online' => $activityType === 'login',
            'status' => $activityType === 'login' ? 'Online' : 'Offline',
        ], $additionalData);

        if ($activityType === 'login') {
            $data['login_time'] = now();
            // mark previous sessions offline
            self::where('user_id', $userId)
                ->where('is_online', true)
                ->update(['is_online' => false, 'logout_time' => now(), 'status' => 'Offline']);
        } elseif ($activityType === 'logout') {
            $data['logout_time'] = now();
            $data['is_online'] = false;
            $data['status'] = 'Offline';
        }

        return self::create($data);
    }

    // ===================== Statistics =====================
    public static function getActivityStats($days = 30)
    {
        $startDate = now()->subDays($days);
        return [
            'total_sessions' => self::where('created_at', '>=', $startDate)->count(),
            'unique_users' => self::where('created_at', '>=', $startDate)->distinct('user_id')->count(),
            'online_users' => self::online()->count(),
            'avg_session_duration' => self::getAverageSessionDuration($days),
            'top_browsers' => self::getTopBrowsers($days),
            'top_platforms' => self::getTopPlatforms($days),
        ];
    }

    private static function getAverageSessionDuration($days = 30)
    {
        $sessions = self::where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('login_time')
            ->whereNotNull('logout_time')
            ->get();

        if ($sessions->isEmpty()) return 0;

        $totalMinutes = $sessions->sum(fn($s) => $s->login_time->diffInMinutes($s->logout_time));
        return round($totalMinutes / $sessions->count(), 2);
    }

    private static function getTopBrowsers($days = 30)
    {
        return self::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('browser, COUNT(*) as count')
            ->groupBy('browser')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
    }

    private static function getTopPlatforms($days = 30)
    {
        return self::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('platform, COUNT(*) as count')
            ->groupBy('platform')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
    }

    // ===================== User-Agent Parsing =====================
    private static function parseUserAgent($userAgent): array
    {
        $device = 'desktop';
        $browser = 'Unknown';
        $platform = 'Unknown';

        if (preg_match('/mobile|android|iphone|ipad|phone/i', $userAgent)) {
            $device = 'mobile';
        } elseif (preg_match('/tablet|ipad/i', $userAgent)) {
            $device = 'tablet';
        }

        if (preg_match('/chrome/i', $userAgent)) $browser = 'Chrome';
        elseif (preg_match('/firefox/i', $userAgent)) $browser = 'Firefox';
        elseif (preg_match('/safari/i', $userAgent)) $browser = 'Safari';
        elseif (preg_match('/edge/i', $userAgent)) $browser = 'Edge';
        elseif (preg_match('/opera/i', $userAgent)) $browser = 'Opera';

        if (preg_match('/windows/i', $userAgent)) $platform = 'Windows';
        elseif (preg_match('/macintosh|mac os x/i', $userAgent)) $platform = 'macOS';
        elseif (preg_match('/linux/i', $userAgent)) $platform = 'Linux';
        elseif (preg_match('/android/i', $userAgent)) $platform = 'Android';
        elseif (preg_match('/ios|iphone|ipad/i', $userAgent)) $platform = 'iOS';

        return [
            'device' => $device,
            'browser' => $browser,
            'platform' => $platform,
        ];
    }
}
