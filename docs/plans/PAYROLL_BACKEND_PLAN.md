# Payroll Backend Implementation Plan

## Objectives
- Persist attendance, salary, and payroll calculations for MDC staff leveraging the new user metadata (work days, man hours, staff type).
- Provide APIs and services to populate the payroll UI tabs (Attendance, Salary, Payroll Summary, Rates).
- Integrate with existing EDMS authentication, permissions, and logging standards.

## Scope Overview
1. Attendance ingestion and normalization.
2. Salary computation (base pay, bonuses, overtime, deductions placeholder).
3. Payroll aggregation per unit/month with export hooks.
4. Rate management (CRUD for daily rates, shift hours, effective dating).
5. Audit logging and activity tracking.
6. Scheduled jobs for monthly roll-ups.

## Proposed Architecture
- **Domain Modules**
  - `App\Services\Payroll\AttendanceService`
  - `App\Services\Payroll\SalaryService`
  - `App\Services\Payroll\PayrollSummaryService`
  - `App\Services\Payroll\RateService`
  - Shared helper `App\Support\Payroll\PayrollPeriod` for month handling.
- **Controllers (routes/app3.php)**
  - `PayrollController` (JSON endpoints for tabs, exports, dashboards).
  - `PayrollRateController` (manage rates CRUD, audit).
- **Jobs / Commands**
  - `ProcessMonthlyPayroll` (dispatch per period, idempotent).
  - `ProcessAutoLogout` (optional) to align logout events with configured work hours.

## Data Model Changes
- **Tables**
  - `payroll_periods` (id, month, status, locked_at, created_by).
  - `payroll_attendance` (user_id, period_id, unit_id, login_days, hours_worked, overtime_hours, source_reference).
  - `payroll_salaries` (user_id, period_id, base_salary, bonuses, extra_hours_value, deductions, net_salary, computed_at).
  - `payroll_rates` (user_id, daily_rate, shift_hours, effective_date, expires_at, created_by, is_active).
  - `payroll_audit_logs` (user_id, period_id, action, payload, actor_id, created_at).
- **Indexes**
  - Compound indexes on (user_id, period_id) for attendance and salary tables.
  - Period/month unique constraint inside `payroll_periods`.
- **Relationships**
  - Foreign keys to `users`, `departments`/`units` (confirm source table for units).

## API Contract (Draft)
- `GET /payroll/periods` → list periods with status.
- `POST /payroll/periods` → create new processing period.
- `GET /payroll/attendance?period=YYYY-MM&unit=` → JSON feed for Attendance tab.
- `GET /payroll/salaries?period=YYYY-MM&unit=` → salary breakdown.
- `POST /payroll/adjustments` → add bonuses/extra hours (audit + validation).
- `GET /payroll/summary?period=YYYY-MM` → totals for cards + export data.
- `GET /payroll/rates` / `POST /payroll/rates` / `PUT /payroll/rates/{id}`.
- `POST /payroll/export` → trigger CSV/print payload (server-side generation).

## Business Rules
- Include only users where `staff_type_category = 'MDC'` and `is_active = 1`.
- Default work days/hours from user profile; allow overrides per period.
- Bonuses/extra hours adjustable until period is locked; changes recorded in audit table.
- Rate changes effective-dated; salary computation uses rate valid on workday.
- Fallback logic when attendance missing: flag in response and exclude from net totals.
- Attendance hours derive from login/logout sessions, including system-triggered auto logout once configured shift hours elapse.
- Permissions: new abilities `view payroll`, `manage payroll`, `manage payroll rates` using Spatie roles.

## Integration Points
- Attendance source: derive from EDMS user login/logout activity (UserActivityLog + auto-logout events based on configured working hours). Build adapter inside `AttendanceService` to aggregate sessions per user/day.
- Exports: reuse CSV helper pattern (e.g., `downloadCsv`) but move server-side to align with security.
- Activity logs: call `ActivityLogService::log` after adjustments and rate changes.

## Migration Strategy
1. Create dedicated migrations (SQL Server) for new tables.
2. Seed default payroll period for current month (optional).
3. Backfill `payroll_rates` from current mock data (set effective_date = today).
4. Schedule downtime window for migration if attendance source requires schema changes.

## Operational Considerations
- **Performance**: add database indexes for heavy report queries; paginate API responses.
- **Security**: gate endpoints with permissions; audit all mutations.
- **Idempotency**: ensure monthly job can rerun safely (use unique constraints + upserts).
- **Error Handling**: standardized JSON responses consistent with frontend expectations (`success`, `message`, `data`).

## Testing Plan
- Unit tests for services (attendance normalization, salary math, rate selection).
- Feature tests for API endpoints (authentication + validation + expected payloads).
- Database tests verifying migrations and relationships on sqlsrv connection.
- End-to-end UAT checklist covering period creation → data ingestion → adjustments → export.

## Rollout Checklist
1. Approve plan + schema by stakeholders.
2. Finalize attendance data source mapping.
3. Implement migrations and services.
4. Build API controllers + routes.
5. Wire backend to existing frontend (replace mock data). 
6. QA with sample MDC dataset.
7. Prepare production deployment guide (artisan commands, cron entries).

---
Prepared for review prior to development. Please share feedback or adjustments before implementation begins.
