# User Activity Log System - Technical Decisions

**Date**: November 10, 2025  
**Version**: 1.0  
**Status**: Active

---

## Database Architecture Decisions

### Decision 1: Timestamp Storage Format
**Choice**: UTC-based timestamps with timezone conversion on display  
**Rationale**: 
- Ensures consistency across global users
- Eliminates DST-related issues
- Simplifies queries and comparisons
- Database-agnostic approach

**Implementation**:
```php
// Storage: Always UTC
'created_at' => now()->utcFormat(),  // Returns UTC time

// Display: Convert to user timezone
$userTimezone = $user->activityLogSettings?->timezone ?? config('app.timezone');
$displayTime = $log->login_time->setTimezone($userTimezone)->format('Y-m-d H:i:s');
```

**Impact**: Medium - Requires timezone setting in user preferences

---

### Decision 2: Status Enum Values
**Choice**: Enum: 'Online', 'Offline', 'Idle'  
**Rationale**:
- Limited to three states, easier validation
- Better for filtering and reporting
- Enum type in MySQL 5.7+ native support
- Clear semantics for business logic

**State Transitions**:
```
[Login] → Online
  ↓ (heartbeat every 30-60s)
Online (continuously updated last_seen_at)
  ↓ (no heartbeat for 30 mins)
Idle (user inactive but not logged out)
  ↓ (manual logout or session timeout)
Offline
```

**Impact**: Low - No breaking changes

---

### Decision 3: Duration Pre-calculation
**Choice**: Store `duration_minutes` as nullable integer at logout  
**Rationale**:
- Faster queries (no need to calculate on every read)
- Accurate historical data (frozen at logout)
- Handles edge cases (logout_time = null for active sessions)
- Better for reporting and analytics

**Calculation Logic**:
```php
// At logout
$log->logout_time = now();
$log->duration_minutes = $log->logout_time->diffInMinutes($log->login_time);
$log->save();

// For active sessions (no logout)
$currentDuration = now()->diffInMinutes($log->login_time);
```

**Impact**: Low - Backward compatible

---

### Decision 4: Indexing Strategy
**Choice**: Composite and targeted single-column indexes  
**Rationale**:
- Composite `(user_id, status, login_time)` for primary queries
- Reduces query time for common filters
- Supports sorting by login_time after filtering

**Indexes Applied**:
```sql
CREATE INDEX idx_user_status_login 
ON user_activity_logs(user_id, status, login_time DESC);

CREATE INDEX idx_status 
ON user_activity_logs(status);

CREATE INDEX idx_test_control 
ON user_activity_logs(test_control);

CREATE INDEX idx_last_seen 
ON user_activity_logs(last_seen_at);
```

**Query Optimization Impact**: High - 10-100x faster for common queries

---

### Decision 5: Test Data Separation
**Choice**: `test_control` column (TEST/PRO) with separate cleanup policies  
**Rationale**:
- Supports development/staging environments
- Can safely purge test data independently
- Prevents test data from affecting analytics
- Audit trail for environment-specific operations

**Usage**:
```php
// Production: exclude test data
UserActivityLog::where('test_control', '!=', 'TEST')->get();

// Cleanup: handle TEST and PRO separately
$testLogs = UserActivityLog::where('test_control', 'TEST')
    ->where('logout_time', '<', now()->subDays(7))
    ->delete();
```

**Impact**: Medium - Requires environment configuration

---

## Backend Architecture Decisions

### Decision 6: Service Layer Pattern
**Choice**: Dedicated `ActivityLogService` for business logic  
**Rationale**:
- Separates concerns from controller
- Reusable logic across multiple controllers
- Easier testing and mocking
- Reduced controller complexity

**Service Responsibilities**:
```php
class ActivityLogService {
    // Session management
    public function recordLogin(User $user, Request $request)
    public function recordLogout(User $user)
    public function updateHeartbeat(User $user)
    
    // Status management
    public function detectIdleUsers($threshold)
    public function markStaleSessionsAsOffline($threshold)
    
    // Data operations
    public function calculateDuration(UserActivityLog $log)
    public function performCleanup($days, $environment)
    
    // Queries
    public function getOnlineUsers($excludeStale = true)
    public function getUserActivityHistory($userId, $dateRange)
}
```

**Impact**: Medium - Requires architectural refactoring

---

### Decision 7: Caching Strategy
**Choice**: Cache settings and online user lists  
**Rationale**:
- Settings rarely change but frequently accessed
- Online user list changes frequently but reads often
- Reduces database load significantly
- Laravel's built-in caching system handles invalidation

**Cache Keys**:
```php
// Settings cache (1 hour TTL)
$cacheKey = "activity_log_settings_{$userId}";
Cache::remember($cacheKey, 3600, fn() => 
    UserActivityLogSetting::where('user_id', $userId)->first()
);

// Online users cache (2 minute TTL)
$cacheKey = "activity_log_online_users";
Cache::remember($cacheKey, 120, fn() =>
    UserActivityLog::where('status', 'Online')
        ->where('last_seen_at', '>', now()->subMinutes(5))
        ->get()
);

// Invalidation
Cache::forget("activity_log_settings_{$userId}");
Cache::forget("activity_log_online_users");
```

**Impact**: High - Significant performance improvement

---

### Decision 8: Middleware Placement
**Choice**: Apply `TrackActivityLog` middleware to web routes  
**Rationale**:
- Automatic tracking without manual controller code
- Captures all authenticated activity
- Handles middleware chain properly
- Excludes API/AJAX endpoints that have dedicated tracking

**Middleware Configuration**:
```php
// app/Http/Kernel.php
protected $routeMiddleware = [
    'track.activity' => \App\Http\Middleware\TrackActivityLog::class,
];

// routes/web.php
Route::middleware(['auth', 'track.activity'])->group(function () {
    // All authenticated web routes automatically tracked
});
```

**Impact**: High - Centralized activity tracking

---

### Decision 9: Event-Based Logout Handling
**Choice**: Use Laravel events for logout + browser detection  
**Rationale**:
- Decouples logout logic from authentication
- Handles multiple logout scenarios
- Provides hooks for additional processing

**Events**:
```php
// Events to dispatch
UserLoggedOut::class           // Manual logout
SessionTimedOut::class         // Auto-timeout
BrowserClosedDetected::class   // Browser close (JS + server validation)
ConcurrentLoginDetected::class // Force logout other sessions
```

**Listeners**:
```php
// Listeners
- LogUserLogout (updates logout_time, calculates duration)
- SendLogoutNotification (optional)
- InvalidateCache (clears online user list)
```

**Impact**: High - Enables comprehensive logout tracking

---

## Frontend Architecture Decisions

### Decision 10: AJAX Heartbeat Implementation
**Choice**: Client-side interval with server-side validation  
**Rationale**:
- Lightweight endpoint (minimal data transfer)
- Client controls frequency (respects user settings)
- Server validates request legitimacy
- Handles network failures gracefully

**Implementation**:
```javascript
// Client: heartbeat every 30-60 seconds
setInterval(() => {
    fetch('/api/activity/heartbeat', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': token,
            'Content-Type': 'application/json'
        }
    })
    .catch(error => console.log('Heartbeat failed:', error));
}, heartbeatInterval);

// Server: validate and update
POST /api/activity/heartbeat
- Verify user session is valid
- Update last_seen_at to now()
- Return remaining session time
```

**Impact**: Medium - Adds minimal server load

---

### Decision 11: Real-time Updates with Polling
**Choice**: Polling via AJAX over WebSockets (for compatibility)  
**Rationale**:
- Works with shared hosting (no WebSocket support)
- Simpler implementation and debugging
- Configurable refresh interval
- No additional dependencies
- Can upgrade to WebSockets later

**Polling Intervals**:
- Activity logs: 30 seconds (default)
- Online users: 20 seconds (default)
- Configurable per user

**Implementation**:
```javascript
// Auto-refresh online users
autoRefreshInterval = setInterval(() => {
    if (currentTab === 'online-users') {
        refreshOnlineUsers();
    }
}, refreshInterval * 1000);
```

**Upgrade Path**: Use Laravel Echo + Redis when scaling

**Impact**: Medium - Enables near-real-time UI updates

---

### Decision 12: Filter State Management
**Choice**: Client-side state with server-side processing  
**Rationale**:
- Responsive UI (instant filter updates)
- Server processes actual data filtering
- URL parameters optional (can add for sharing filters)
- Reduces complexity vs Redux/Vuex

**Implementation**:
```javascript
// Client state
let filterState = {
    user_id: '',
    status: '',
    device_type: '',
    browser: '',
    date_from: today(),
    date_to: today()
};

// Apply filters (sends to server)
function applyFilters() {
    activityTable.ajax.reload(function() {
        // Send filterState as query parameters
    });
}

// Clear filters
function clearFilters() {
    filterState = {...initialFilters};
    applyFilters();
}
```

**Impact**: Low - Maintains existing pattern

---

### Decision 13: Modal Management Pattern
**Choice**: jQuery + event-driven approach (no framework)  
**Rationale**:
- Consistent with existing codebase
- No additional dependencies
- Easy to understand and maintain
- Works with inline HTML modals

**Pattern**:
```javascript
// Open modal
function openActivityModal() {
    $('#activity-details-modal').removeClass('hidden');
}

// Close modal
function closeActivityModal() {
    $('#activity-details-modal').addClass('hidden');
}

// Load content via AJAX
function viewActivityDetails(id) {
    $.get(`/user-activity-logs/${id}`, function(response) {
        $('#activity-details-content').html(response.html);
        openActivityModal();
    });
}
```

**Impact**: Low - Existing pattern

---

## Integration Decisions

### Decision 14: Permission & Access Control
**Choice**: Use Spatie Permission package (already integrated)  
**Rationale**:
- Already implemented in codebase
- Consistent with existing patterns
- Granular permission control
- Department-level filtering support

**Permissions to Add**:
```
- view-activity-logs
- delete-activity-log
- delete-all-activity-logs
- export-activity-logs
- manage-activity-settings
- perform-cleanup
- force-logout-user
```

**Impact**: Medium - Requires permission setup

---

### Decision 15: Audit Logging
**Choice**: Log cleanup operations and sensitive actions  
**Rationale**:
- Compliance requirement
- Helps troubleshoot issues
- Tracks who performed actions and when

**Audit Table**:
```
activity_log_audits
- id
- user_id (who performed action)
- action (cleanup, export, delete, force-logout)
- affected_records_count
- details (JSON)
- created_at
```

**Impact**: Low - Minimal performance impact

---

## Testing Decisions

### Decision 16: Test Environment Separation
**Choice**: Use `test_control` field to segregate test data  
**Rationale**:
- Allows testing on production-like database
- Doesn't require separate test database
- Realistic data volumes and performance
- Easy to verify via query

**Test Data Strategy**:
```php
// In tests, always use TEST environment
UserActivityLog::factory()
    ->for($user)
    ->create([
        'test_control' => 'TEST',
        'login_time' => now()->subHours(rand(1, 24)),
    ]);

// Cleanup doesn't affect test data in production
UserActivityLog::where('test_control', '!=', 'TEST')
    ->where('logout_time', '<', $threshold)
    ->delete();
```

**Impact**: Low - Enables safer testing

---

### Decision 17: Performance Testing Focus
**Choice**: DataTables pagination, query optimization, caching  
**Rationale**:
- 10K+ record datasets are common
- AJAX refresh performance critical
- Caching provides biggest gain
- Pagination prevents large data transfers

**Benchmarks**:
- Query response: <100ms for 50 records
- AJAX refresh: <300ms with caching
- Pagination: <500ms response time
- Cleanup operation: <1 minute for 1 year of data

**Impact**: High - Ensures production readiness

---

## Migration Decisions

### Decision 18: Zero-Downtime Migration
**Choice**: Add columns nullable, backfill, then add constraints  
**Rationale**:
- Prevents application downtime
- Allows rollback if issues
- Two-step process: migration + backfill

**Migration Strategy**:
```php
// Step 1: Add new nullable columns
Schema::table('user_activity_logs', function (Blueprint $table) {
    $table->string('related_file_number')->nullable();
    $table->integer('duration_minutes')->nullable();
    $table->enum('status', ['Online', 'Offline', 'Idle'])->nullable();
    $table->timestamp('last_seen_at')->nullable();
    // ... other columns
});

// Step 2: Backfill data
// (handled by separate command or part of migration)
UserActivityLog::where('logout_time', '!=', null)
    ->whereNull('duration_minutes')
    ->each(function ($log) {
        $log->duration_minutes = $log->logout_time->diffInMinutes($log->login_time);
        $log->status = 'Offline';
        $log->save();
    });

// Step 3: Add not-null constraints (if needed)
Schema::table('user_activity_logs', function (Blueprint $table) {
    $table->change();  // Make some columns not nullable
});
```

**Impact**: Medium - Requires careful execution

---

## Monitoring & Observability

### Decision 19: Logging & Monitoring
**Choice**: Use Laravel Log channels + structured logging  
**Rationale**:
- Built-in to Laravel
- Consistent with codebase
- Supports multiple channels (single, stack)
- Easy to debug issues

**Logging Points**:
```php
// In ActivityLogService
Log::info('User login tracked', [
    'user_id' => $userId,
    'ip' => $ipAddress,
    'device' => $device
]);

Log::warning('Idle session detected', [
    'user_id' => $userId,
    'inactive_minutes' => $inactiveMinutes
]);

Log::info('Cleanup operation completed', [
    'deleted_records' => $count,
    'retention_days' => $days,
    'execution_time' => $time
]);
```

**Impact**: Low - Minimal performance impact

---

## Key Assumptions & Constraints

| Assumption | Impact | Mitigation |
|-----------|--------|-----------|
| SQL Server 2016+ available | High | Verify before migration |
| Redis cache optional but recommended | Medium | Document fallback to file cache |
| AJAX requests protected by CSRF | High | Verify middleware configuration |
| User sessions stable (not constantly switching) | Medium | Add session conflict handling |
| Timezone data accurate in settings | Medium | Provide timezone picker UI |
| Cleanup runs off-peak (2 AM) | Medium | Monitor and adjust schedule |

---

## Review Checklist

- [ ] All decisions approved by tech lead
- [ ] Compatibility verified with existing codebase
- [ ] Performance implications understood
- [ ] Security considerations addressed
- [ ] Rollback procedures documented
- [ ] Testing strategy defined
- [ ] Monitoring plan established
- [ ] Team trained on new system

---

## Document Version History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-11-10 | AI Agent | Initial planning document |

