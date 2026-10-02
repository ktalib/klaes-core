# User Activity Log System - Implementation Plan

**Project Date**: November 10, 2025  
**Status**: Planning Phase  
**Repository**: klaes1.2_app (Laravel 9 Monolith)

---

## Executive Summary

This plan outlines the complete implementation of an enhanced User Activity Log System that tracks user sessions, device/browser data, online status, and provides real-time monitoring capabilities. The system will integrate with existing Laravel infrastructure and maintain backward compatibility with the current codebase.

---

## Phase 1: Database Schema Enhancement

### 1.1 Migrate Existing Tables
**Objective**: Extend current `user_activity_logs` and `user_activity_log_settings` tables with new fields

**New Fields for `user_activity_logs`**:
- `related_file_number` (string, nullable) - Links to property/application records
- `duration_minutes` (integer, nullable) - Pre-calculated session duration
- `status` (enum: 'Online', 'Offline', 'Idle') - Current session status
- `last_seen_at` (timestamp, nullable) - Last activity timestamp
- `test_control` (enum: 'TEST', 'PRO') - Environment indicator
- `indexed_at` (timestamp, nullable) - Index tracking for bulk operations

**New Fields for `user_activity_log_settings`**:
- `timezone` (string) - User timezone (default: config timezone)
- `notes` (text, nullable) - Admin notes for settings

**Migration File**: `database/migrations/2025_11_10_000001_enhance_activity_logs_tables.php`

**Indexes to Add**:
- Composite: `(user_id, status, login_time)`
- Single: `user_id`, `status`, `test_control`, `login_time`
- For performance: `last_seen_at` for stale session detection

**Estimated Effort**: 2 hours (migration creation, testing, validation)

---

## Phase 2: Eloquent Models & Relationships

### 2.1 UserActivityLog Model
**Location**: `app/Models/UserActivityLog.php`

**Key Components**:
```php
- relationships: belongsTo(User::class)
- accessors: getDurationMinutesAttribute()
- scopes: scopeOnline(), scopeOffline(), scopeByDateRange($from, $to)
- scopes: scopeByUser($userId), scopeTestData(), scopeProduction()
- scopes: scopeStale($minutes = 30)
- methods: markAsOnline(), markAsOffline(), markAsIdle()
- methods: isOnline(), isOffline(), isIdle()
```

**Estimated Effort**: 3 hours

### 2.2 UserActivityLogSetting Model
**Location**: `app/Models/UserActivityLogSetting.php`

**Key Components**:
```php
- relationships: belongsTo(User::class)
- accessors: getTimezoneAttribute() [with validation]
- scopes: scopeForUser($userId)
- methods: getDefaultSettings(), getCleanupThreshold()
- caching: Implemented with Cache::remember()
```

**Estimated Effort**: 2 hours

---

## Phase 3: Backend Controllers & Services

### 3.1 ActivityLogController
**Location**: `app/Http/Controllers/ActivityLogController.php`

**Actions**:
- `index()` - Display activity logs with filters
- `show($id)` - Show single activity log details
- `destroy($id)` - Delete single log (with permission check)
- `bulkDelete()` - Delete multiple logs (AJAX endpoint)
- `export()` - Export logs as CSV/Excel
- `cleanup()` - Trigger cleanup operation
- `settings()` - Manage user settings
- `online()` - List currently online users (AJAX)

**Estimated Effort**: 4 hours

### 3.2 ActivityLogService
**Location**: `app/Services/ActivityLogService.php`

**Responsibilities**:
- Session lifecycle management (login, logout, heartbeat)
- Status calculation and updates
- Duration calculation logic
- Stale session detection
- Cleanup operations with audit logging
- Export data formatting
- Caching of frequently accessed data

**Key Methods**:
```php
- recordLogin(User $user, Request $request)
- recordLogout(User $user)
- updateHeartbeat(User $user)
- detectIdleUsers($threshold = 30)
- calculateDuration(Log $log)
- performCleanup($days, $environment = 'both')
- getOnlineUsers($excludeStale = true)
- exportToCSV(Collection $logs)
```

**Estimated Effort**: 5 hours

### 3.3 ActivityLogMiddleware
**Location**: `app/Http/Middleware/TrackActivityLog.php`

**Purpose**: Automatically track activity on authenticated routes

**Features**:
- Detect login/logout events
- Capture IP, device, browser information
- Maintain heartbeat for session duration
- Handle concurrent sessions

**Estimated Effort**: 3 hours

---

## Phase 4: API Endpoints

### 4.1 AJAX Endpoints (routes/api.php)
```
POST   /api/activity/heartbeat              - Update last_seen_at
GET    /api/activity/online                 - Get online users
GET    /api/activity/logs                   - Paginated activity logs
GET    /api/activity/logs/{id}              - Single log details
POST   /api/activity/logs/{id}/delete       - Delete log
POST   /api/activity/logs/bulk-delete       - Bulk delete
GET    /api/activity/export                 - Export logs
POST   /api/activity/cleanup                - Trigger cleanup
GET    /api/activity/settings               - Get user settings
POST   /api/activity/settings               - Save user settings
POST   /api/activity/logout-user/{userId}   - Force logout user
```

**Response Format**: Consistent JSON with `success`, `message`, `data` structure

**Estimated Effort**: 3 hours

---

## Phase 5: Frontend Views (Using Partials)

### 5.1 Main Views
**Location**: `resources/views/user_activity_logs/`

**Already Created Partials**:
- ✅ `partials/header.blade.php`
- ✅ `partials/tabs.blade.php`
- ✅ `partials/filters.blade.php`
- ✅ `partials/activity-table.blade.php`
- ✅ `partials/online-users.blade.php`
- ✅ `partials/modals.blade.php`

**Enhancements Needed**:
- Add status color indicators (Green/Red/Yellow)
- Add duration display formatting
- Add timezone-aware timestamp display
- Add environment indicator (TEST/PRO)
- Add export buttons with filter respect
- Add activity details modal with full session info

**Estimated Effort**: 4 hours

### 5.2 JavaScript Modules
**Location**: `public/js/activity-logs/`

**Modules to Create**:
- `table-manager.js` - DataTable initialization and management
- `filters.js` - Filter application and state management
- `heartbeat.js` - AJAX heartbeat service
- `modals.js` - Modal management (details, cleanup, settings)
- `export.js` - Export functionality
- `offline-detection.js` - Already exists (verify and integrate)

**Estimated Effort**: 4 hours

### 5.3 CSS Styling
**Location**: `resources/css/activity-logs.css`

**Components**:
- Status indicator colors and animations
- Responsive tables and grids
- Modal styling
- Filter panel styling
- Loading spinners and transitions
- Accessibility improvements

**Estimated Effort**: 2 hours

---

## Phase 6: Scheduled Tasks & Cleanup

### 6.1 Laravel Scheduler Command
**Location**: `app/Console/Commands/CleanupActivityLogs.php`

**Purpose**: Automated cleanup based on retention settings

**Schedule**: Daily at 2 AM (configurable)

**Logic**:
1. Check `auto_cleanup_enabled` for each user
2. Get retention days from settings
3. Delete logs older than retention threshold
4. Preserve active sessions
5. Log cleanup operations for audit

**Estimated Effort**: 2 hours

### 6.2 Console Kernel Registration
**Location**: `app/Console/Kernel.php`

**Schedule Definition**:
```php
$schedule->command('activity-logs:cleanup')
    ->dailyAt('02:00')
    ->onOneServer();
```

**Estimated Effort**: 0.5 hours

---

## Phase 7: Testing & Validation

### 7.1 Unit Tests
**Location**: `tests/Unit/`

**Test Suites**:
- `ActivityLogModelTest` - Model relationships and accessors
- `ActivityLogServiceTest` - Business logic
- `ActivityLogSettingsTest` - Settings retrieval and defaults

**Coverage Target**: 80%+

**Estimated Effort**: 4 hours

### 7.2 Feature Tests
**Location**: `tests/Feature/`

**Test Cases**:
- Login/logout tracking
- Heartbeat updates
- Online user detection
- Cleanup operations (TEST vs PRO data)
- Edge cases: concurrent logins, stale sessions
- Permission validation for delete/cleanup
- AJAX endpoint responses

**Coverage Target**: 75%+

**Estimated Effort**: 5 hours

### 7.3 Performance Testing
**Scenarios**:
- Query performance with 10K+ records
- AJAX refresh under load (100 concurrent users)
- Pagination response times
- Cleanup impact on database

**Tools**: Laravel Debugbar, Artillery load testing

**Estimated Effort**: 3 hours

---

## Phase 8: Documentation & Deployment

### 8.1 Technical Documentation
**Files to Create**:
- `ACTIVITY_LOG_IMPLEMENTATION.md` - Setup and integration guide
- `ACTIVITY_LOG_API.md` - API endpoint documentation
- `ACTIVITY_LOG_TROUBLESHOOTING.md` - Common issues and solutions

**Estimated Effort**: 2 hours

### 8.2 User Documentation
**Files to Create**:
- User guide for activity log settings
- Export functionality guide
- Cleanup policy explanation

**Estimated Effort**: 1 hour

### 8.3 Deployment Checklist
**Pre-Deployment**:
- ✓ Run all tests
- ✓ Performance benchmarks
- ✓ Database backup
- ✓ Migration on staging environment

**Deployment Steps**:
1. Run migrations: `php artisan migrate --database=sqlsrv`
2. Clear caches: `php artisan config:clear && php artisan cache:clear`
3. Seed default settings (if needed)
4. Register scheduler in crontab
5. Verify AJAX endpoints
6. Monitor logs for errors

**Estimated Effort**: 2 hours

---

## Phase 9: Integration & Polish

### 9.1 Integration Points
- ✅ Existing user authentication system
- ✅ Spatie permission system for access control
- ✅ Department-based role filtering (if applicable)
- ✅ Existing header/footer layouts
- ✅ Admin menu navigation

**Estimated Effort**: 2 hours

### 9.2 UI/UX Polish
- Add loading states and spinners
- Add confirmation modals for destructive actions
- Add success/error notifications (SweetAlert)
- Improve accessibility (ARIA labels, keyboard nav)
- Add data staleness warnings
- Add timezone conversion indicators

**Estimated Effort**: 3 hours

---

## Implementation Timeline

| Phase | Component | Effort (hours) | Start | End | Priority |
|-------|-----------|---|---|---|---|
| 1 | Database Schema | 2 | Week 1, Day 1 | Week 1, Day 1 | 🔴 Critical |
| 2 | Eloquent Models | 5 | Week 1, Day 2 | Week 1, Day 3 | 🔴 Critical |
| 3 | Controllers & Services | 12 | Week 1, Day 4 | Week 2, Day 2 | 🔴 Critical |
| 4 | API Endpoints | 3 | Week 2, Day 3 | Week 2, Day 3 | 🔴 Critical |
| 5 | Frontend Views | 10 | Week 2, Day 4 | Week 3, Day 2 | 🟡 High |
| 6 | Scheduled Tasks | 2.5 | Week 3, Day 3 | Week 3, Day 3 | 🟡 High |
| 7 | Testing | 12 | Week 3, Day 4 | Week 4, Day 2 | 🟡 High |
| 8 | Documentation | 5 | Week 4, Day 3 | Week 4, Day 4 | 🟢 Medium |
| 9 | Integration & Polish | 5 | Week 4, Day 5 | Week 5, Day 1 | 🟢 Medium |
| | **TOTAL** | **56.5 hours** | | | |

**Estimated Project Duration**: 5-6 weeks (with 40-hour work weeks)

---

## Risk Assessment & Mitigation

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|-----------|
| Performance issues with large datasets | High | Medium | Implement proper indexing, pagination, caching |
| Timezone conversion errors | Medium | Medium | Thorough testing, use UTC storage, PHP Carbon |
| Stale session detection accuracy | Medium | Medium | Configurable thresholds, monitoring |
| Race conditions in concurrent logins | High | Low | Database transaction locks, careful migration |
| Cleanup operation data loss | Critical | Low | Audit logging, staging environment validation |
| AJAX endpoint security vulnerabilities | High | Low | Permission checks, CSRF token, input validation |

---

## Success Criteria

✅ All database migrations execute successfully  
✅ Unit tests pass with 80%+ coverage  
✅ Feature tests pass with 75%+ coverage  
✅ Activity logs accurately track 100% of user sessions  
✅ Online user count matches actual active sessions within 1-minute tolerance  
✅ Cleanup operations respect retention policies without data loss  
✅ AJAX endpoints respond in <500ms under normal load  
✅ UI is fully responsive and accessible (WCAG AA)  
✅ Documentation is complete and developer-friendly  
✅ Deployment to production completes without errors  
✅ Zero data loss or corruption during migration  
✅ System handles 100+ concurrent users without degradation  

---

## Dependencies & Prerequisites

### External Packages to Consider
- `laravel/excel` - For advanced export functionality
- `spatie/laravel-permission` - Already integrated, verify compatibility
- `predis/predis` - For Redis caching (if using Redis)
- `nesbot/carbon` - Already included, for timezone handling

### System Requirements
- SQL Server 2016 or higher
- PHP 8.0+
- Redis (optional but recommended for caching)
- Node.js for asset compilation

### Team Skills Required
- Laravel backend development
- SQL Server query optimization
- Frontend (jQuery, AJAX, Tailwind CSS)
- Testing (PHPUnit)
- DevOps/deployment knowledge

---

## Approval & Sign-off

**Prepared By**: AI Development Agent  
**Date**: November 10, 2025  
**Status**: Ready for Implementation  

**Approvers**:
- [ ] Technical Lead
- [ ] Product Owner
- [ ] Database Administrator
- [ ] QA Lead

---

## Next Steps

1. **Week 1, Day 1**: Review and approve implementation plan
2. **Week 1, Day 1**: Create database migration file
3. **Week 1, Day 2**: Begin Eloquent model development
4. **Week 1, Day 4**: Start backend controller/service development
5. **Week 2, Day 1**: Create API endpoints
6. **Week 2, Day 4**: Begin frontend implementation
7. **Week 3, Day 1**: Begin comprehensive testing
8. **Week 4, Day 1**: Deploy to staging environment
9. **Week 4, Day 4**: Final QA and validation
10. **Week 5, Day 1**: Production deployment

---

## Contact & Support

For questions or clarifications on this implementation plan:
- Review the guidelines document: `USER_ACTIVITY_LOGS_GUIDELINES.md`
- Check technical decisions in: `ACTIVITY_LOG_DECISIONS.md`
- Reference code examples in: `ACTIVITY_LOG_CODE_PATTERNS.md`
