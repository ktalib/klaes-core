-- =====================================================================
-- DAILY LOGINS QUERY CONDITIONS
-- =====================================================================
-- Location: app/Http/Controllers/Payroll/PayrollDataController.php
-- Method: dailyLogins()
-- =====================================================================

The Daily Logins display shows user login sessions based on the following conditions:

1. USER CONDITIONS:
   ✓ staff_type_category = 'MDC'
   ✓ is_active = 1
   ✓ work_station IS NOT NULL (and not empty string)
   ✓ work_days_per_week IS NOT NULL
   ✓ man_hours_per_day IS NOT NULL

2. LOGIN TIME CONDITIONS:
   ✓ login_time must be between start-of-day and end-of-day of the selected date
   ✓ login_time >= dayStart (00:00:00 of selected date)
   ✓ login_time <= dayEnd (23:59:59 of selected date)

3. PAYROLL PERIOD CONDITIONS:
   ✓ The selected date must fall within the active payroll period
   ✓ If date is outside period, returns empty with status "No login sessions"

4. LOGOUT TIME CONDITIONS:
   ✓ logout_time can be:
     - NULL (session still open/in progress)
     - Any time on the same day
     - Clamped to dayEnd if logout extends beyond the day

5. SESSION CALCULATION:
   ✓ Each login_time = one session entry
   ✓ If logout_time is NULL:
     - Auto-logout is applied at end of shift or dayEnd
     - Based on man_hours_per_day (default 8 hours)
   ✓ If logout_time exists:
     - Uses actual logout time
   ✓ Intervals are merged for overlapping sessions

6. DATA RETRIEVED:
   ✓ user.id
   ✓ user.first_name
   ✓ user.last_name
   ✓ user.department_id
   ✓ user.work_station
   ✓ user.work_days_per_week
   ✓ user.man_hours_per_day
   ✓ user.shift_code
   ✓ user.department.name
   ✓ user_activity_logs.login_time
   ✓ user_activity_logs.logout_time
   ✓ user_activity_logs.duration_minutes (if set)

7. FILTERING & ORDERING:
   ✓ Ordered by: user_id, then login_time
   ✓ Grouped by: user_id
   ✓ For each user, merged into single entry with:
     - First login time
     - Last logout time
     - Total session intervals
     - Activity count
     - Open session indicator

8. RELATED DATA (AttendanceDailySnapshot):
   ✓ Also fetches daily snapshot data for comparison
   ✓ Snapshot date must match selected date
   ✓ Used for status derivation (Present/Absent/Late/etc)

9. STATUS DETERMINATION:
   ✓ Based on:
     - First login time vs shift start
     - Last logout time vs shift end
     - Total worked minutes vs required shift hours
     - Late tolerance (configurable, default 0 min)
     - Auto-logout applied flag

10. FINAL OUTPUT INCLUDES:
    ✓ User details
    ✓ All login/logout intervals for the day
    ✓ Total time worked
    ✓ Shift hours expected
    ✓ Attendance status (Present/Absent/Late/On Time)
    ✓ Auto-logout indicator
    ✓ Late login indicator (if applicable)

-- =====================================================================
-- KEY INSIGHTS FOR YOUR ISSUE
-- =====================================================================

The problem with backwards dates occurs because:
- User marked is_online=1 with login_time from PAST days (e.g., Jan 7)
- Daily Logins only shows sessions with login_time matching the selected DATE
- So old stale sessions won't appear in Daily Logins unless you select that OLD date
- When next day comes, the stale old session with wrong date creates incomplete records

FIX APPLIED:
- Setting logout_time = NULL for stale sessions
- This allows the auto-logout logic to properly calculate session duration
- Fresh login can be created for the current day
- Daily Logins will show correct data for current date

-- =====================================================================
-- SQL QUERY EQUIVALENT FOR DAILY LOGINS
-- =====================================================================

SELECT 
    u.id,
    u.first_name,
    u.last_name,
    u.department_id,
    d.name AS department_name,
    u.work_station,
    l.login_time,
    l.logout_time,
    l.duration_minutes
FROM user_activity_logs l
INNER JOIN users u ON u.id = l.user_id
LEFT JOIN departments d ON d.id = u.department_id
WHERE u.staff_type_category = 'MDC'
  AND u.is_active = 1
  AND u.work_station IS NOT NULL
  AND u.work_station != ''
  AND u.work_days_per_week IS NOT NULL
  AND u.man_hours_per_day IS NOT NULL
  AND CAST(l.login_time AS DATE) = '2026-01-09'  -- Selected date
ORDER BY u.id, l.login_time;
