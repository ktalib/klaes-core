# User Activity Log System - Code Patterns & Examples

**Date**: November 10, 2025  
**Version**: 1.0  
**Purpose**: Reference patterns for consistent implementation

---

## Database Query Patterns

### Pattern 1: Get Online Users with Stale Detection
```php
// app/Services/ActivityLogService.php

public function getOnlineUsers($excludeStale = true)
{
    $query = UserActivityLog::where('status', 'Online')
        ->select(['id', 'user_id', 'ip_address', 'device', 'browser', 'login_time', 'last_seen_at'])
        ->with('user:id,name,email');

    if ($excludeStale) {
        // Exclude sessions with no heartbeat for 5 minutes
        $query->where('last_seen_at', '>', now()->subMinutes(5));
    }

    return $query->orderBy('login_time', 'desc')
        ->get()
        ->map(fn($log) => $this->formatUserForDisplay($log));
}

private function formatUserForDisplay(UserActivityLog $log)
{
    return [
        'id' => $log->id,
        'user_id' => $log->user_id,
        'user_name' => $log->user->name,
        'user_email' => $log->user->email,
        'ip_address' => $log->ip_address,
        'device_type' => $log->device,
        'browser' => $log->browser,
        'login_time' => $log->login_time->format('Y-m-d H:i:s'),
        'online_duration' => $this->formatDuration($log->login_time, now()),
        'last_seen_at' => $log->last_seen_at?->format('Y-m-d H:i:s'),
    ];
}
```

### Pattern 2: Filter Activity Logs with Pagination
```php
// app/Services/ActivityLogService.php

public function getFilteredLogs(array $filters, int $perPage = 50)
{
    $query = UserActivityLog::query();

    // Filter by user
    if (!empty($filters['user_id'])) {
        $query->where('user_id', $filters['user_id']);
    }

    // Filter by status
    if (!empty($filters['status'])) {
        $query->where('status', $filters['status']);
    }

    // Filter by device type
    if (!empty($filters['device_type'])) {
        $query->where('device', $filters['device_type']);
    }

    // Filter by browser
    if (!empty($filters['browser'])) {
        $query->where('browser', $filters['browser']);
    }

    // Filter by date range
    if (!empty($filters['date_from'])) {
        $query->whereDate('login_time', '>=', $filters['date_from']);
    }
    if (!empty($filters['date_to'])) {
        $query->whereDate('login_time', '<=', $filters['date_to']);
    }

    // Apply environment filter (exclude TEST in production)
    if (config('app.env') === 'production') {
        $query->where('test_control', '!=', 'TEST');
    }

    return $query->with('user:id,name,email')
        ->orderBy('login_time', 'desc')
        ->paginate($perPage);
}
```

### Pattern 3: Detect Stale Sessions
```php
// app/Services/ActivityLogService.php

public function detectIdleUsers(int $thresholdMinutes = 30): Collection
{
    return UserActivityLog::where('status', 'Online')
        ->where('last_seen_at', '<=', now()->subMinutes($thresholdMinutes))
        ->get()
        ->each(function ($log) {
            $log->update(['status' => 'Idle']);
            
            Log::info('User marked as idle', [
                'user_id' => $log->user_id,
                'inactive_minutes' => now()->diffInMinutes($log->last_seen_at),
            ]);
        });
}

public function markStaleSessionsAsOffline(int $staleMinutes = 120): int
{
    $staleThreshold = now()->subMinutes($staleMinutes);
    
    $updated = UserActivityLog::where('status', '!=', 'Offline')
        ->where('last_seen_at', '<=', $staleThreshold)
        ->whereNull('logout_time')
        ->update([
            'status' => 'Offline',
            'logout_time' => $staleThreshold,
            'duration_minutes' => DB::raw(
                'DATEDIFF(minute, login_time, logout_time)'
            ),
        ]);

    Log::info('Stale sessions marked offline', ['count' => $updated]);
    return $updated;
}
```

### Pattern 4: Calculate Session Duration
```php
// In Model Accessor
public function getDurationMinutesAttribute()
{
    if (is_null($this->logout_time)) {
        // Active session: calculate from login to now
        return now()->diffInMinutes($this->login_time);
    }

    // Completed session: use pre-calculated value or calculate
    return $this->attributes['duration_minutes'] 
        ?? $this->logout_time->diffInMinutes($this->login_time);
}

// Formatted duration for display
public function getFormattedDurationAttribute(): string
{
    $minutes = $this->duration_minutes;
    
    if ($minutes < 60) {
        return "{$minutes}m";
    }
    
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    return $mins > 0 ? "{$hours}h {$mins}m" : "{$hours}h";
}
```

### Pattern 5: Cleanup Old Logs
```php
// app/Services/ActivityLogService.php

public function performCleanup(int $retentionDays, ?string $environment = null): array
{
    $threshold = now()->subDays($retentionDays);
    
    $query = UserActivityLog::where('logout_time', '<', $threshold);

    // Optional: limit to specific environment
    if ($environment) {
        $query->where('test_control', $environment);
    }

    $count = $query->count();
    
    // Log before deletion for audit
    Log::info('Activity log cleanup started', [
        'retention_days' => $retentionDays,
        'environment' => $environment,
        'records_to_delete' => $count,
        'threshold_date' => $threshold->format('Y-m-d'),
    ]);

    // Perform deletion in chunks to avoid memory issues
    $deleted = 0;
    $query->each(function ($log) use (&$deleted) {
        $log->delete();
        $deleted++;
    }, 1000); // Process 1000 at a time

    Log::info('Activity log cleanup completed', [
        'records_deleted' => $deleted,
        'execution_time' => now()->diffInSeconds(now()),
    ]);

    return [
        'success' => true,
        'deleted' => $deleted,
        'message' => "Deleted {$deleted} activity logs older than {$retentionDays} days"
    ];
}
```

---

## Eloquent Model Patterns

### Pattern 6: UserActivityLog Model with Scopes
```php
// app/Models/UserActivityLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'login_time',
        'logout_time',
        'ip_address',
        'device',
        'browser',
        'platform',
        'status',
        'duration_minutes',
        'last_seen_at',
        'related_file_number',
        'test_control',
        'indexed_at',
    ];

    protected $casts = [
        'login_time' => 'datetime',
        'logout_time' => 'datetime',
        'last_seen_at' => 'datetime',
        'indexed_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeOnline($query)
    {
        return $query->where('status', 'Online');
    }

    public function scopeOffline($query)
    {
        return $query->where('status', 'Offline');
    }

    public function scopeIdle($query)
    {
        return $query->where('status', 'Idle');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('login_time', [$from, $to]);
    }

    public function scopeTestData($query)
    {
        return $query->where('test_control', 'TEST');
    }

    public function scopeProduction($query)
    {
        return $query->where('test_control', 'PRO');
    }

    public function scopeStale($query, $minutes = 30)
    {
        return $query->where('last_seen_at', '<=', now()->subMinutes($minutes));
    }

    // Status check methods
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

    public function isActive(): bool
    {
        return $this->status !== 'Offline';
    }
}
```

### Pattern 7: UserActivityLogSetting Model
```php
// app/Models/UserActivityLogSetting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLogSetting extends Model
{
    protected $fillable = [
        'user_id',
        'auto_cleanup_enabled',
        'auto_refresh_enabled',
        'retention_days',
        'refresh_interval',
        'timezone',
        'notes',
    ];

    protected $casts = [
        'auto_cleanup_enabled' => 'boolean',
        'auto_refresh_enabled' => 'boolean',
        'retention_days' => 'integer',
        'refresh_interval' => 'integer',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Accessors
    public function getTimezoneAttribute($value)
    {
        return $value ?? config('app.timezone');
    }

    // Static methods for defaults
    public static function getDefaults(): array
    {
        return [
            'auto_cleanup_enabled' => true,
            'auto_refresh_enabled' => true,
            'retention_days' => 90,
            'refresh_interval' => 30,
            'timezone' => config('app.timezone'),
        ];
    }

    public static function firstOrCreateDefaults($userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            array_merge(self::getDefaults(), ['user_id' => $userId])
        );
    }
}
```

---

## Service Layer Patterns

### Pattern 8: Activity Session Recording
```php
// app/Services/ActivityLogService.php

public function recordLogin(User $user, Request $request): UserActivityLog
{
    $log = UserActivityLog::create([
        'user_id' => $user->id,
        'login_time' => now(),
        'ip_address' => $request->ip(),
        'device' => $this->detectDevice($request),
        'browser' => $this->detectBrowser($request),
        'platform' => $this->detectPlatform($request),
        'status' => 'Online',
        'last_seen_at' => now(),
        'test_control' => config('app.env') === 'production' ? 'PRO' : 'TEST',
    ]);

    Cache::forget("activity_log_online_users");

    Log::info('User login tracked', [
        'user_id' => $user->id,
        'log_id' => $log->id,
    ]);

    return $log;
}

public function recordLogout(User $user): void
{
    $log = UserActivityLog::where('user_id', $user->id)
        ->where('status', '!=', 'Offline')
        ->latest('login_time')
        ->first();

    if ($log) {
        $log->update([
            'logout_time' => now(),
            'status' => 'Offline',
            'duration_minutes' => now()->diffInMinutes($log->login_time),
        ]);

        Cache::forget("activity_log_online_users");

        Log::info('User logout tracked', [
            'user_id' => $user->id,
            'duration_minutes' => $log->duration_minutes,
        ]);
    }
}

public function updateHeartbeat(User $user): void
{
    UserActivityLog::where('user_id', $user->id)
        ->where('status', 'Online')
        ->latest('login_time')
        ->first()
        ?->update(['last_seen_at' => now()]);
}

private function detectDevice(Request $request): string
{
    $userAgent = $request->userAgent();
    
    if (preg_match('/mobile|android|iphone|ipad/i', $userAgent)) {
        return preg_match('/ipad/i', $userAgent) ? 'tablet' : 'mobile';
    }
    
    return 'desktop';
}

private function detectBrowser(Request $request): string
{
    $userAgent = $request->userAgent();
    
    if (preg_match('/Chrome/i', $userAgent)) return 'Chrome';
    if (preg_match('/Firefox/i', $userAgent)) return 'Firefox';
    if (preg_match('/Safari/i', $userAgent)) return 'Safari';
    if (preg_match('/Edge/i', $userAgent)) return 'Edge';
    
    return 'Unknown';
}

private function detectPlatform(Request $request): string
{
    $userAgent = $request->userAgent();
    
    if (preg_match('/Windows/i', $userAgent)) return 'Windows';
    if (preg_match('/Mac/i', $userAgent)) return 'Mac';
    if (preg_match('/Linux/i', $userAgent)) return 'Linux';
    if (preg_match('/Android/i', $userAgent)) return 'Android';
    if (preg_match('/iPhone|iPad/i', $userAgent)) return 'iOS';
    
    return 'Unknown';
}
```

---

## Controller Patterns

### Pattern 9: Activity Logs Controller
```php
// app/Http/Controllers/ActivityLogController.php

namespace App\Http\Controllers;

use App\Models\UserActivityLog;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    protected ActivityLogService $service;

    public function __construct(ActivityLogService $service)
    {
        $this->service = $service;
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            return $this->getLogsForDataTable($request);
        }

        return view('user_activity_logs.index', [
            'onlineUsers' => $this->service->getOnlineUsers(),
        ]);
    }

    public function show($id)
    {
        $log = UserActivityLog::with('user')
            ->findOrFail($id);

        $this->authorize('view', $log);

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $this->formatLogDetails($log),
            ]);
        }

        return view('user_activity_logs.show', ['log' => $log]);
    }

    public function destroy($id, Request $request)
    {
        $log = UserActivityLog::findOrFail($id);
        $this->authorize('delete', $log);

        $log->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Activity log deleted successfully',
            ]);
        }

        return redirect()->back()->with('success', 'Log deleted');
    }

    public function bulkDelete(Request $request)
    {
        $this->authorize('deleteAll', UserActivityLog::class);

        $ids = $request->input('ids', []);
        $deleted = UserActivityLog::whereIn('id', $ids)->delete();

        return response()->json([
            'success' => true,
            'message' => "Deleted {$deleted} activity logs",
            'deleted' => $deleted,
        ]);
    }

    // AJAX Endpoint for DataTable
    private function getLogsForDataTable(Request $request)
    {
        $filters = [
            'user_id' => $request->input('user_id'),
            'status' => $request->input('status'),
            'device_type' => $request->input('device_type'),
            'browser' => $request->input('browser'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $logs = $this->service->getFilteredLogs($filters, 50);

        return response()->json([
            'draw' => $request->input('draw'),
            'recordsTotal' => $logs->total(),
            'recordsFiltered' => $logs->total(),
            'data' => $logs->items(),
        ]);
    }

    private function formatLogDetails(UserActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'user_name' => $log->user->name,
            'user_email' => $log->user->email,
            'ip_address' => $log->ip_address,
            'device' => $log->device,
            'browser' => $log->browser,
            'platform' => $log->platform,
            'login_time' => $log->login_time->format('Y-m-d H:i:s'),
            'logout_time' => $log->logout_time?->format('Y-m-d H:i:s'),
            'duration_minutes' => $log->duration_minutes,
            'formatted_duration' => $log->formatted_duration,
            'status' => $log->status,
            'test_control' => $log->test_control,
        ];
    }
}
```

---

## API Endpoint Patterns

### Pattern 10: AJAX Heartbeat Endpoint
```php
// routes/api.php

Route::middleware(['auth', 'api'])->group(function () {
    Route::post('/activity/heartbeat', [ActivityLogController::class, 'heartbeat']);
    Route::get('/activity/online', [ActivityLogController::class, 'onlineUsers']);
    Route::post('/activity/cleanup', [ActivityLogController::class, 'cleanup']);
});

// In ActivityLogController
public function heartbeat(Request $request)
{
    $this->service->updateHeartbeat($request->user());

    return response()->json([
        'success' => true,
        'timestamp' => now()->timestamp,
        'last_seen_at' => now()->format('Y-m-d H:i:s'),
    ]);
}

public function onlineUsers(Request $request)
{
    $users = Cache::remember('activity_log_online_users', 120, function () {
        return $this->service->getOnlineUsers();
    });

    return response()->json([
        'success' => true,
        'data' => $users,
        'count' => count($users),
    ]);
}
```

---

## JavaScript Patterns

### Pattern 11: Heartbeat Manager
```javascript
// public/js/activity-logs/heartbeat.js

class HeartbeatManager {
    constructor(options = {}) {
        this.interval = options.interval || 30000; // 30 seconds
        this.url = options.url || '/api/activity/heartbeat';
        this.timerId = null;
        this.active = false;
    }

    start() {
        if (this.active) return;
        
        this.active = true;
        this.timerId = setInterval(() => this.sendHeartbeat(), this.interval);
        
        // Send immediately
        this.sendHeartbeat();
    }

    stop() {
        if (this.timerId) {
            clearInterval(this.timerId);
            this.timerId = null;
        }
        this.active = false;
    }

    async sendHeartbeat() {
        try {
            const response = await fetch(this.url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json',
                }
            });

            if (!response.ok) {
                console.warn('Heartbeat failed:', response.status);
            }
        } catch (error) {
            console.error('Heartbeat error:', error);
        }
    }

    setInterval(newInterval) {
        this.interval = newInterval;
        if (this.active) {
            this.stop();
            this.start();
        }
    }
}

// Usage
const heartbeat = new HeartbeatManager({ interval: 30000 });
heartbeat.start();
```

### Pattern 12: Online Users Updater
```javascript
// public/js/activity-logs/online-users.js

class OnlineUsersUpdater {
    constructor(options = {}) {
        this.interval = options.interval || 20000; // 20 seconds
        this.url = options.url || '/api/activity/online';
        this.containerId = options.containerId || '#online-users-grid';
        this.timerId = null;
        this.active = false;
    }

    start() {
        if (this.active) return;
        
        this.active = true;
        this.timerId = setInterval(() => this.refresh(), this.interval);
        
        // Refresh immediately
        this.refresh();
    }

    stop() {
        if (this.timerId) {
            clearInterval(this.timerId);
            this.timerId = null;
        }
        this.active = false;
    }

    async refresh() {
        try {
            const response = await fetch(this.url);
            const result = await response.json();

            if (result.success) {
                this.render(result.data);
            }
        } catch (error) {
            console.error('Online users refresh error:', error);
        }
    }

    render(users) {
        const container = document.querySelector(this.containerId);
        if (!container) return;

        if (users.length === 0) {
            container.innerHTML = `
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-500">No users currently online</p>
                </div>
            `;
            return;
        }

        container.innerHTML = users.map(user => this.formatUserCard(user)).join('');
    }

    formatUserCard(user) {
        return `
            <div class="bg-white shadow rounded-lg p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center text-white font-medium">
                        ${user.user_name.split(' ').map(n => n[0]).join('').substring(0, 2)}
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm font-medium text-gray-900">${user.user_name}</p>
                        <p class="text-xs text-gray-500">${user.user_email}</p>
                        <p class="text-xs text-gray-500 mt-1">${user.device_type} • ${user.ip_address}</p>
                    </div>
                </div>
            </div>
        `;
    }

    setInterval(newInterval) {
        this.interval = newInterval;
        if (this.active) {
            this.stop();
            this.start();
        }
    }
}

// Usage
const onlineUpdater = new OnlineUsersUpdater({
    interval: 20000,
    containerId: '#online-users-grid'
});
onlineUpdater.start();
```

---

## Testing Patterns

### Pattern 13: Unit Test Example
```php
// tests/Unit/Services/ActivityLogServiceTest.php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\ActivityLogService;

class ActivityLogServiceTest extends TestCase
{
    protected ActivityLogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ActivityLogService::class);
    }

    public function test_record_login_creates_log_entry()
    {
        $user = User::factory()->create();
        $request = $this->createRequest('127.0.0.1');

        $log = $this->service->recordLogin($user, $request);

        $this->assertNotNull($log->id);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertEquals('Online', $log->status);
        $this->assertEquals('127.0.0.1', $log->ip_address);
    }

    public function test_detect_idle_updates_status()
    {
        $log = UserActivityLog::factory()->create([
            'status' => 'Online',
            'last_seen_at' => now()->subMinutes(45),
        ]);

        $this->service->detectIdleUsers(30);

        $log->refresh();
        $this->assertEquals('Idle', $log->status);
    }

    public function test_cleanup_removes_old_logs()
    {
        UserActivityLog::factory(10)->create([
            'logout_time' => now()->subDays(100),
            'test_control' => 'TEST',
        ]);

        $result = $this->service->performCleanup(90, 'TEST');

        $this->assertTrue($result['success']);
        $this->assertEquals(10, $result['deleted']);
    }
}
```

---

## Migration Pattern

### Pattern 14: Database Migration
```php
// database/migrations/2025_11_10_000001_enhance_activity_logs_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->string('related_file_number')->nullable()->after('browser');
            $table->integer('duration_minutes')->nullable()->after('related_file_number');
            $table->enum('status', ['Online', 'Offline', 'Idle'])->nullable()->after('duration_minutes');
            $table->timestamp('last_seen_at')->nullable()->after('status');
            $table->enum('test_control', ['TEST', 'PRO'])->default('PRO')->after('last_seen_at');
            $table->timestamp('indexed_at')->nullable()->after('test_control');

            // Add indexes
            $table->index(['user_id', 'status', 'login_time']);
            $table->index('status');
            $table->index('test_control');
            $table->index('last_seen_at');
        });

        Schema::table('user_activity_log_settings', function (Blueprint $table) {
            $table->string('timezone')->default(config('app.timezone'))->after('refresh_interval');
            $table->text('notes')->nullable()->after('timezone');
        });
    }

    public function down()
    {
        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status', 'login_time']);
            $table->dropIndex(['status']);
            $table->dropIndex(['test_control']);
            $table->dropIndex(['last_seen_at']);
            $table->dropColumn(['related_file_number', 'duration_minutes', 'status', 'last_seen_at', 'test_control', 'indexed_at']);
        });

        Schema::table('user_activity_log_settings', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'notes']);
        });
    }
};
```

---

## Blade Template Pattern

### Pattern 15: Status Indicator Component
```blade
{{-- resources/views/components/activity-status-badge.blade.php --}}

@props(['status' => 'Offline'])

@php
    $statusConfig = [
        'Online' => [
            'color' => 'bg-green-100 text-green-800',
            'icon' => 'fa-check-circle',
            'label' => 'Online',
        ],
        'Idle' => [
            'color' => 'bg-yellow-100 text-yellow-800',
            'icon' => 'fa-exclamation-circle',
            'label' => 'Idle',
        ],
        'Offline' => [
            'color' => 'bg-red-100 text-red-800',
            'icon' => 'fa-times-circle',
            'label' => 'Offline',
        ],
    ];
    
    $config = $statusConfig[$status] ?? $statusConfig['Offline'];
@endphp

<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $config['color'] }}">
    <i class="fas {{ $config['icon'] }} mr-2"></i>
    {{ $config['label'] }}
</span>
```

**Usage in views**:
```blade
@component('components.activity-status-badge', ['status' => $log->status])
@endcomponent
```

---

## Environment Configuration Pattern

### Pattern 16: Configuration File
```php
// config/activity-logs.php

return [
    // Cleanup settings
    'cleanup' => [
        'enabled' => env('ACTIVITY_LOG_CLEANUP_ENABLED', true),
        'retention_days' => env('ACTIVITY_LOG_RETENTION_DAYS', 90),
        'schedule' => env('ACTIVITY_LOG_CLEANUP_SCHEDULE', '02:00'),
        'chunk_size' => 1000,
    ],

    // Heartbeat settings
    'heartbeat' => [
        'interval' => env('ACTIVITY_LOG_HEARTBEAT_INTERVAL', 30000), // milliseconds
        'idle_threshold' => env('ACTIVITY_LOG_IDLE_THRESHOLD', 30), // minutes
        'stale_threshold' => env('ACTIVITY_LOG_STALE_THRESHOLD', 120), // minutes
    ],

    // UI settings
    'ui' => [
        'refresh_interval' => env('ACTIVITY_LOG_UI_REFRESH_INTERVAL', 20), // seconds
        'pagination_per_page' => env('ACTIVITY_LOG_PAGINATION_PER_PAGE', 50),
    ],

    // Cache settings
    'cache' => [
        'online_users_ttl' => env('ACTIVITY_LOG_CACHE_ONLINE_USERS_TTL', 120), // seconds
        'settings_ttl' => env('ACTIVITY_LOG_CACHE_SETTINGS_TTL', 3600), // seconds
    ],
];
```

---

## Summary

These patterns ensure:
- ✅ Consistent code structure
- ✅ Reusable components
- ✅ Proper error handling
- ✅ Performance optimization
- ✅ Easy testing
- ✅ Maintainability

Reference these patterns throughout implementation for consistency and quality.
