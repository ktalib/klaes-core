# Manual Attendance System Implementation Plan

## 1. Objectives
- Provide a reliable, auditable attendance capture process for field users without PC or mobile access.
- Ensure attendance records can be keyed in manually while maintaining data integrity and traceability.
- Align collected data with existing payroll and HR workflows without requiring real-time connectivity.

## 2. Scope
- Covered: Daily presence logging, shift association, reconciliation with central payroll database, reporting.
- Excluded: Geo-location tracking, biometric devices, automated scheduling.
- Target Users: Field staff supervisors, HR clerks responsible for manual capture and verification.

## 3. Stakeholders
- HR Operations: Own attendance policies, validation, approvals.
- Field Supervisors: Collect raw attendance on paper/templates.
- Payroll Team: Consume processed attendance for salary computation.
- IT Support: Maintain standalone system, handle imports/exports and integration scripts.

## 4. Functional Requirements
1. **Manual Capture Interface**
   - Simple UI for clerks to input attendance sheets per day/shift.
   - Support bulk entry (e.g., spreadsheet upload) and per-employee quick entry forms.
   - Display employee passport photo preview during entry for visual verification.
2. **Template Generation**
   - Printable attendance sheets with employee rosters per station/department.
   - Provide passport photo placeholders on printable rosters to aid supervisors.
3. **Data Validation**
   - Prevent duplicate entries for the same employee/date.
   - Enforce permissible shift types, workstations, and roster assignments from master data.
4. **Approval Workflow**
   - Draft → Review → Approved states with digital signatures (user + timestamp).
5. **Offline Capability**
   - Optional desktop app or local web server enabling data entry without continuous connectivity.
6. **Audit Trail**
   - Log who captured, edited, or approved each record with reason codes.
7. **Integration Export**
   - Produce standardized CSV/JSON batches consumable by the main payroll API (`/payroll/api/attendance`).
8. **Exception Handling**
   - Flag missing entries, late submissions, and overtime claims needing justification.

## 5. Non-Functional Requirements
- **Usability**: Training target < 2 hours for clerks.
- **Reliability**: Zero data loss when syncing; local backups stored daily.
- **Security**: Role-based access (Clerk, Reviewer, Admin). Local encryption for stored data.
- **Performance**: Data entry for 200 employees/day should complete in < 30 minutes.
- **Compliance**: Align with internal HR policies and Nigerian labor regulations on attendance record keeping.

## 6. Proposed Architecture
- **Frontend**: Lightweight desktop web app (Electron or Tauri) or offline-capable Laravel Breeze install with Alpine.js for UI simplicity.
- **Backend**: Local SQLite database for standalone deployment; sync service pushes approved data to central SQL Server via secured API.
- **Sync Layer**: Scheduled job (e.g., Windows Task Scheduler) that reads approved records and posts to `/payroll/api/attendance/import` endpoint.
- **Security**: Local encryption at rest using OS-provided DPAPI; HTTPS tunnel via reverse proxy (Caddy/nginx) when syncing.
- **Media Handling**: Local cache for passport photos with optional blur hashing; references sync alongside attendance payloads.

## 7. Data Model (Local SQLite)
- `employees_local`
   - `id`, `name`, `department_id`, `work_station_code`, `active`
   - `passport_photo_path`
- `attendance_entries`
  - `id`, `employee_id`, `date`, `shift_code`, `login_hours`, `overtime_hours`, `status`, `notes`
- `attendance_batches`
  - `id`, `period_code`, `status`, `submitted_by`, `submitted_at`
- `audit_logs`
  - `id`, `entity_type`, `entity_id`, `action`, `performed_by`, `performed_at`, `payload`

## 8. Process Flow
1. **Roster Preparation**
   - HR exports active employee list with workstations from main system; import into standalone tool.
2. **Field Collection**
   - Supervisors record daily attendance on printed sheets.
3. **Data Entry**
   - Clerk inputs data via manual UI or scans/imports typed spreadsheet template.
   - Clerk verifies each employee against the displayed passport photo preview before submission.
4. **Validation**
   - System checks for missing days, duplicates, shift mismatches; displays summary warnings.
5. **Approval**
   - Reviewer signs off; status moves to `approved`.
6. **Sync**
   - Scheduled job posts approved batch to central payroll API; marks batch as `synced`.
7. **Reconciliation**
   - Payroll team confirms receipt; discrepancies are corrected and resubmitted.

## 9. Implementation Phases
1. **Discovery (1 week)**
   - Confirm business rules, finalize templates, identify API contracts.
2. **Prototype (2 weeks)**
   - Build minimal UI with SQLite storage, implement manual entry + validation.
3. **Integration (1 week)**
   - Develop export payload, dry-run sync to staging payroll API.
4. **User Acceptance (1 week)**
   - Pilot with selected department, collect feedback, refine workflow.
5. **Deployment (3 days)**
   - Package installer, create documentation, train clerks and reviewers.
6. **Post-Go-Live Support (2 weeks)**
   - Monitor sync jobs, resolve issues, backlog enhancements.

## 10. Deliverables
- Application installer + deployment guide.
- Attendance templates (printable PDF, CSV import format).
- Passport photo asset import and quality guidelines.
- Configuration manual (workstation mapping, shift codes).
- Training materials (slides, quick reference sheets).
- Support runbook outlining backup, restore, and sync troubleshooting.

## 11. Risks & Mitigations
- **Incorrect Manual Entry**: Provide double-entry verification and supervisor sign-off.
- **Sync Failures**: Implement retry logic, alert dashboard, and manual export fallback.
- **Data Drift**: Schedule weekly employee roster imports to keep local master data aligned.
- **Security Breach**: Enforce Windows user authentication, encrypt local DB, disable removable media exports without authorization.

## 12. Open Questions
- Confirm whether biometric devices will be introduced later (affects schema).
- Determine acceptable delay between attendance capture and payroll cutoff.
- Clarify retention policy for physical attendance sheets.

## 13. Next Steps
1. Review and sign off plan with HR & Payroll leads.
2. Draft detailed technical specs (UI wireframes, API payload definitions).
3. Set up proof-of-concept repository and dev environment.
4. Schedule discovery workshops with field supervisors.
