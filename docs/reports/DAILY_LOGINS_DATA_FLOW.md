# Daily Login Sessions - Data Flow & Query Explanation

This document explains how the **Login Time** and **Logout Time** are retrieved and displayed in the Daily Login Sessions table on the Payroll dashboard.

---

## Overview

The Daily Login Sessions feature tracks when MDC staff members log in and out of the system. The data flows through several layers:

1. **Source Table**: `user_activity_logs` (SQL Server)
2. **Processing Service**: `AttendanceService.php`
3. **Storage Table**: `payroll_attendance` (stores processed `session_breakdown` JSON)
4. **API Controller**: `PayrollDataController.php`
5. **Frontend**: `payroll.js` renders the data

---

## 1. Source Data: `user_activity_logs` Table

The raw login/logout data is captured in the `user_activity_logs` table:

```sql
-- Table: user_activity_logs (SQL Server)
-- Key columns for login tracking:

SELECT 
    id,
    user_id,
    login_time,          -- When the user logged in
    logout_time,         -- When the user logged out (NULL if still online)
    duration_minutes,    -- Session duration in minutes
    status,              -- 'Online', 'Offline', 'Idle'
    last_seen_at,        -- Last heartbeat timestamp
    ip_address,
    device,
    browser,
    platform
FROM user_activity_logs
WHERE user_id = @userId
ORDER BY login_time DESC;
```

---

## 2. Attendance Calculation Query

The `AttendanceService::calculateAttendance()` method queries this data for a payroll period:

```php
// File: app/Services/Payroll/AttendanceService.php
// Method: calculateAttendance()

$logs = UserActivityLog::query()
    // Include sessions that started before the window but closed (or remained open) inside it.
    ->where(function ($query) use ($start, $periodEnd) {
        $query->whereBetween('login_time', [$start, $periodEnd])
            ->orWhere(function ($crossing) use ($start, $periodEnd) {
                $crossing->where('login_time', '<', $start)
                    ->where(function ($logoutWindow) use ($start, $periodEnd) {
                        $logoutWindow->whereBetween('logout_time', [$start, $periodEnd])
                            ->orWhereNull('logout_time');
                    });
            });
    })
    ->whereHas('user', function ($query) {
        $query->where('staff_type_category', 'MDC')
            ->where('is_active', 1);
    })
    ->with(['user' => function ($query) {
        $query->select('id', 'department_id', 'staff_type_category', 'man_hours_per_day', 'work_days_per_week', 'first_name', 'last_name');
    }])
    ->orderBy('user_id')
    ->orderBy('login_time')
    ->get();
```

### Query Logic Explained:

| Condition | Purpose |
|-----------|---------|
| `whereBetween('login_time', [$start, $periodEnd])` | Sessions that started within the payroll period |
| `where('login_time', '<', $start)` | Sessions that started BEFORE the period... |
| `whereBetween('logout_time', [$start, $periodEnd])` | ...but logged out during the period |
| `orWhereNull('logout_time')` | ...or are still active (no logout yet) |
| `staff_type_category = 'MDC'` | Only MDC staff (not admins, contractors, etc.) |
| `is_active = 1` | Only active users |

---

## 3. Session Breakdown Processing

After fetching the raw logs, each session is processed:

```php
// File: app/Services/Payroll/AttendanceService.php (lines 100-145)

foreach ($sessions as $session) {
    $login = $this->parseCarbon($session->login_time);
    $rawLogout = $this->parseCarbon($session->logout_time);

    // Resolve the effective logout time (handles auto-logout logic)
    [$logout, $autoLoggedOut] = $this->resolveLogout(
        $login,
        $rawLogout,
        $shiftHours,
        $periodEnd,
        $allowOvertime,
        $overtimeCap
    );

    // Calculate session duration
    $diffMinutes = $logout->diffInMinutes($login);
    $minutes = $session->duration_minutes !== null
        ? min((int) $session->duration_minutes, $diffMinutes)
        : $diffMinutes;

    // Build the breakdown entry
    $breakdown[] = [
        'login_at' => $login->toDateTimeString(),      // "2026-01-05 08:23:00"
        'logout_at' => $logout->toDateTimeString(),    // "2026-01-05 15:56:00"
        'raw_logout_at' => $rawLogout?->toDateTimeString(),
        'minutes' => $minutes,                          // 453 (7h 33m)
        'status' => $session->status,
        'auto_logout' => $autoLoggedOut,
    ];
}
```

### Key Fields in `session_breakdown`:

| Field | Type | Description |
|-------|------|-------------|
| `login_at` | datetime | When the user logged in |
| `logout_at` | datetime | Effective logout time (may be adjusted) |
| `raw_logout_at` | datetime | Original logout time from logs |
| `minutes` | int | Session duration in minutes |
| `status` | string | Session status (Online/Offline) |
| `auto_logout` | bool | Whether system auto-logged out at shift end |

---

## 4. Storage in `payroll_attendance` Table

The processed data is stored as JSON in the `session_breakdown` column:

```sql
-- Table: payroll_attendance (SQL Server)

SELECT 
    user_id,
    period_id,
    login_days,
    hours_worked,
    overtime_hours,
    session_breakdown,  -- JSON array of sessions
    source_reference
FROM payroll_attendance
WHERE period_id = @periodId;
```

### Example `session_breakdown` JSON:

```json
[
    {
        "login_at": "2026-01-05 08:23:00",
        "logout_at": "2026-01-05 15:56:00",
        "raw_logout_at": "2026-01-05 15:56:00",
        "minutes": 453,
        "status": "Offline",
        "auto_logout": false
    },
    {
        "login_at": "2026-01-06 08:15:00",
        "logout_at": "2026-01-06 16:30:00",
        "raw_logout_at": null,
        "minutes": 495,
        "status": "Online",
        "auto_logout": true
    }
]
```

---

## 5. API Response

The `PayrollDataController::attendance()` method returns this data:

```php
// File: app/Http/Controllers/Payroll/PayrollDataController.php

return [
    'user_id' => $attendance->user_id,
    'user_name' => $attendance->user?->name,
    'department_name' => $attendance->department?->name,
    'session_breakdown' => $sessionBreakdown,  // Array of sessions
    'cumulative_hours' => $cumulativeHours,
    'shift_hours' => $shiftHours,
    // ... other fields
];
```

### API Endpoint:

```
GET /payroll/api/attendance?period_id={id}
```

---

## 6. Frontend Rendering

The JavaScript processes the `session_breakdown` to display login/logout times:

```javascript
// File: public/js/payroll.js

function resolveLongestSessionForDate(record, filterDate) {
    const breakdown = Array.isArray(record.sessionBreakdown) ? record.sessionBreakdown : [];
    let best = null;

    breakdown.forEach((session) => {
        const loginAt = session.login_at || session.loginAt || session.login_time;
        const logoutAt = session.logout_at || session.logoutAt || session.logout_time;

        // Filter by selected date
        if (filterDate && loginAt) {
            const sessionDate = new Date(loginAt).toISOString().split('T')[0];
            if (sessionDate !== filterDate) {
                return; // Skip sessions not on the filter date
            }
        }

        // Find the longest session
        const minutes = Number(session.minutes ?? session.duration_minutes ?? 0);
        const hours = minutes / 60;

        if (!best || hours > best.hours) {
            best = { loginAt, logoutAt, hours };
        }
    });

    return best || { loginAt: null, logoutAt: null, hours: 0 };
}
```

### Date Filter Logic:

The date filter (defaults to today) compares the `login_at` date with the selected date:

```javascript
const filterDate = state.filters.dailyLoginsDate; // "2026-01-06"
const sessionDate = new Date(loginAt).toISOString().split('T')[0]; // "2026-01-05"

if (sessionDate !== filterDate) {
    return; // Skip this session
}
```

---

## 7. Complete Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                     user_activity_logs                          │
│  (Raw login/logout events captured by activity monitoring)      │
│                                                                 │
│  login_time: 2026-01-05 08:23:00                               │
│  logout_time: 2026-01-05 15:56:00                              │
│  duration_minutes: 453                                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                   AttendanceService.php                         │
│  (Queries logs, processes sessions, handles auto-logout)        │
│                                                                 │
│  - Filters by payroll period dates                             │
│  - Filters by MDC staff only                                   │
│  - Calculates effective logout (shift hour limits)             │
│  - Builds session_breakdown array                              │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                   payroll_attendance                            │
│  (Stores processed attendance with session_breakdown JSON)      │
│                                                                 │
│  session_breakdown: [                                          │
│    {"login_at": "2026-01-05 08:23:00",                        │
│     "logout_at": "2026-01-05 15:56:00", "minutes": 453}       │
│  ]                                                              │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                 PayrollDataController.php                       │
│  (API endpoint returns attendance with session_breakdown)       │
│                                                                 │
│  GET /payroll/api/attendance?period_id=1                       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                       payroll.js                                │
│  (Frontend filters by date, finds longest session)             │
│                                                                 │
│  Date Filter: 2026-01-06 (defaults to today)                   │
│  Display: Login Time | Logout Time | Total Hours               │
│           08:23      | 15:56       | 7.55h                     │
└─────────────────────────────────────────────────────────────────┘
```

---

## 8. Key Files Reference

| File | Purpose |
|------|---------|
| `app/Models/UserActivityLog.php` | Model for raw activity logs |
| `app/Services/Payroll/AttendanceService.php` | Processes logs into attendance records |
| `app/Models/Payroll/PayrollAttendance.php` | Model for stored attendance data |
| `app/Http/Controllers/Payroll/PayrollDataController.php` | API endpoint for attendance data |
| `public/js/payroll.js` | Frontend rendering logic |
| `resources/views/payroll/partials/tabs/daily-logins.blade.php` | UI template |

---

## 9. SQL Query to Debug Login Data

To manually check a user's login sessions:

```sql
-- Check raw activity logs for a specific user and date
SELECT 
    id,
    user_id,
    login_time,
    logout_time,
    duration_minutes,
    status,
    last_seen_at
FROM user_activity_logs
WHERE user_id = 50553
  AND CAST(login_time AS DATE) = '2026-01-05'
ORDER BY login_time;

-- Check processed attendance data
SELECT 
    user_id,
    period_id,
    login_days,
    hours_worked,
    session_breakdown
FROM payroll_attendance
WHERE user_id = 50553
  AND period_id = (SELECT id FROM payroll_periods WHERE is_current = 1);
```

---

## 10. Total Hours Calculation

The **Total Hours** column is calculated by the frontend directly from the login/logout times:

```javascript
function calculateHoursFromTimes(loginAt, logoutAt) {
    if (!loginAt || !logoutAt) return 0;

    const loginTime = new Date(loginAt).getTime();
    const logoutTime = new Date(logoutAt).getTime();

    const diffMs = logoutTime - loginTime;
    return diffMs / (1000 * 60 * 60); // Milliseconds to hours
}

// Example:
// Login: 2026-01-05 08:23:00
// Logout: 2026-01-05 15:56:00
// Result: 7.55 hours (7 hours 33 minutes)
```

This ensures the displayed hours exactly match the login/logout times shown in the same row.
