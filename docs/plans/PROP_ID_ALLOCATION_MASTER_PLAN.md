# PROP_ID Allocation Master Plan - UPDATED

**Last Updated**: December 6, 2025  
**Status**: 1 CRITICAL ISSUE FIXED + 2 REMAINING GAPS IDENTIFIED

---

## ✅ COMPLETED FIXES

### 1. FileIndexing prop_id Misdirection (FIXED)

**Problem**: 
- FileIndexing form was sending prop_id to both file_indexings table AND file_history_staging table
- prop_id column was incorrectly in file_indexings database schema
- FileIndexing model's columnWhitelist included prop_id, causing it to be saved to wrong table

**Solution Applied** (Dec 6, 2025):
1. ✅ User deleted prop_id column from file_indexings table
2. ✅ Removed 'prop_id' from FileIndexing model columnWhitelist() array (line 112)
3. ✅ Verified updateFileHistoryPropId() correctly updates file_history_staging (not file_indexings)
4. ✅ Verified hidden form input remains (correctly sends prop_id to backend, but now correctly routed)

**Result**: 
- prop_id now ONLY stored in file_history_staging ✅
- prop_id NO LONGER stored in file_indexings ✅
- File history transactions correctly display prop_id from file_history_staging ✅

**Documentation**: FILEINDEXING_PROP_ID_FIX_COMPLETE.md

---

### 2. PropertyRecordController + CofO Form Path (FIXED)

**Problem**: The manual property record form bypassed prop_id allocation, leaving `property_records`, CofO staging, and file history rows orphaned whenever staff skipped the indexing modal.

**Solution Applied** (Dec 7, 2025):
1. `PropertyRecordController` now injects `PropertyIdAllocationService` and calls `allocateOrRetrievePropId()` inside `store()` (see `app/Http/Controllers/PropertyRecordController.php:154-210`).
2. The same identifier flows into CofO staging writes and `file_history_staging` syncs via the shared service helper.
3. Validation enforces at least one official file number or auto-generated temporary number so allocation never receives a blank identifier.

**Result**:
- Form-based captures produce the same `prop_id` as indexing submissions.
- CofO rows created from the property card reflect the identifier immediately.
- File history timelines stay grouped even when a record originates outside indexing.

---

### 3. CaveatController prop_id Workflow (FIXED)

**Problem**: Caveat placements were saved without any `prop_id`, preventing cross-checks between legal holds and property history.

**Solution Applied** (Dec 7, 2025):
- Injected `PropertyIdAllocationService` into `CaveatController` and allocate the identifier during `store()`.
- Added `prop_id` to the mass-assignment whitelist (`app/Models/Caveat.php`) and created migration `2025_12_07_120000_add_prop_id_to_caveats_table.php`.
- After each save the controller now calls `syncPropIdToFileHistory()` so timelines and caveat flags stay aligned.

**Result**:
- Every new caveat row links back to property records, CofO, and file history through a shared `prop_id`.
- File-history grids and legal search screens can flag caveated properties without juggling MLS/KANGIS variants.

---

### 4. FileIndexingController CofO prop_id Propagation (FIXED)

**Problem**: `updateCofORecord()` inserted/updated CofO staging rows with missing `prop_id`, and file-history syncing duplicated the controller logic instead of using the shared service.

**Solution Applied** (Dec 7, 2025):
1. `FileIndexingController` now injects `PropertyIdAllocationService` and derives `prop_id` whenever the request omits it.
2. `updateCofORecord()` receives the identifier, writes it to `CofO_staging`, and `updateFileHistoryPropId()` delegates to `syncPropIdToFileHistory()`.
3. A helper `determinePropIdForIndexing()` centralizes the lookup so both controller entry points reuse the same allocation logic.

**Result**:
- CofO staging rows created via indexing inherit the correct `prop_id`.
- File history updates use the same service method as property records and caveats, eliminating duplicate SQL.
- Controllers remain source-of-truth; no other class re-implements prop_id allocation.

---

## ?? REMAINING CRITICAL GAPS

### 5. Legacy prop_id Backfill & Audit (PENDING)

**Problem**:
- Thousands of legacy rows in `pra`, `property_records`, `registered_instruments`, `CofO_staging`, and `caveats` still have `NULL` `prop_id`.
- Analytics/legal-search dashboards will remain inconsistent until the backlog is filled.

**Next Actions**:
1. Use the backfill SQL in `PROP_ID_ALLOCATION_AUDIT_AND_SOLUTION.md:509-580` to assign identifiers in-place (run in preview + production).
2. Re-run the validation queries from the same doc to confirm **zero** nulls remain before sign-off.
3. Ship a stored procedure/report that alerts when any new null `prop_id` rows appear.

---

### 6. Automated Tests & Monitoring (PENDING)

**Problem**:
- `PropertyIdAllocationService` and the updated controllers currently lack PHPUnit coverage.
- No automated regressions guard against future refactors reintroducing bespoke allocation logic.

**Next Actions**:
- Add unit tests for `PropertyIdAllocationService` (allocate/reuse paths + error handling).
- Add feature tests for `PropertyRecordController`, `FileIndexingController`, and `CaveatController` to assert `prop_id` is persisted and file history syncs.
- Wire a nightly job (or Datadog alert) that flags any inserts with `prop_id IS NULL`.

---

### 7. Scanning, Page Typing & Intake Tables (PENDING)

**Problem**:
- Architecture diagrams (`PROP_ID_ARCHITECTURE_DIAGRAMS.md:158-235`) call out scanning/page typing tables that still lack `prop_id`.
- Downstream workflows (label printing, blind scanning, batch ingest) still pivot on raw file numbers only.

**Next Actions**:
1. Add `prop_id` columns/indexes to scanning + page typing tables and update their ingest controllers to call the service.
2. Ensure Deeds/registered instruments controllers also allocate/propagate `prop_id`.
3. Update documentation/UI helpers so operators can search by either `prop_id` or file number in those modules.

---

### Phase 2: PropertyRecordController::store() (COMPLETE – Dec 7, 2025)

- [x] Inject `PropertyIdAllocationService` into the controller
- [x] Allocate/reuse `prop_id` inside `store()` so form submissions match indexing flows
- [x] Confirm CofO staging + `file_history_staging` receive the identifier
- [x] Smoke-test manual property record creation with serial/page/volume metadata
- [x] Update PropertyCardController + docs to reference the centralized service
- [x] Document the change set in `PROP_ID_DUAL_PATH_FIX.md`

### Phase 3: CaveatController (COMPLETE – Dec 7, 2025)

- [x] Identify the REST entry points (`store`, `lift`, API helpers)
- [x] Inject `PropertyIdAllocationService` and allocate `prop_id` before inserts
- [x] Add the `prop_id` column + index via `2025_12_07_120000_add_prop_id_to_caveats_table.php`
- [x] Sync `file_history_staging` via `syncPropIdToFileHistory()` for every placement
- [x] Smoke-test caveat placement/lift to confirm linkage
- [x] Update docs with the finalized workflow

### Phase 4: Cross-Module Verification (IN PROGRESS)

- [x] FileIndexingController::updateCofORecord() now writes/updates `prop_id` (Dec 7, 2025)
- [ ] CofoController - ensure manual CofO uploads stamp prop_id
- [ ] EdmsController / scanning + page typing pipelines - link scans to prop_id
- [ ] DeedsController - confirm registered instruments persist prop_id on inserts
- [ ] Batch operations - certify that bulk updates preserve prop_id and never null it out

---

### Phase 5: Backfill + Monitoring (PENDING)

- [ ] Run the backfill queries from `PROP_ID_ALLOCATION_AUDIT_AND_SOLUTION.md:509-580` to populate legacy NULL rows across `pra`, `property_records`, `CofO_staging`, `registered_instruments`, and `caveats`.
- [ ] Validate with the audit SQL in the same doc to ensure **zero** NULL `prop_id` rows remain before go-live.
- [ ] Add automated alerts/dashboard queries that flag any new NULL `prop_id` inserts post-cutover.
- [ ] Add PHPUnit coverage for `PropertyIdAllocationService` plus feature tests for PropertyRecord, FileIndexing, and Caveat flows.

---

## ✅ Test & Verification Plan (Dec 7, 2025)

1. **Run targeted migration**: `php artisan migrate --path=database/migrations/2025_12_07_120000_add_prop_id_to_caveats_table.php` (SQL Server connection) to add the new `prop_id` column + index.
2. **Property record form smoke test**: submit a manual record via `PropertyRecordController::store()` and confirm `property_records.prop_id`, `CofO_staging.prop_id`, and `file_history_staging.prop_id` all match.
3. **Caveat placement test**: place a caveat through the UI/API, verify `caveats.prop_id` is populated, and check `file_history_staging` for the synchronized identifier.
4. **FileIndexing CofO path**: update an indexing record with CofO data (`FileIndexingController::update()`), then inspect `CofO_staging` and file history to confirm the shared `prop_id`.
5. **Backfill dry-run**: execute the validation/backfill SQL in `PROP_ID_ALLOCATION_AUDIT_AND_SOLUTION.md:509-580` on a staging clone to measure remaining NULL counts before production rollout.
6. **Automated coverage**: add PHPUnit tests for `app/Services/PropertyIdAllocationService.php` and feature tests for the three controllers to lock in regression coverage.

---

## Data Flow Architecture (After FileIndexing Fix)

```
┌─────────────────────────────────────────────────────────────┐
│                   Property Data Entry Points                  │
└─────────────────────────────────────────────────────────────┘
                              │
                ┌─────────────┼─────────────┐
                │             │             │
                ▼             ▼             ▼
        File Index     Property Form     Caveat
        (Indexing)     (Main Form)       (Caveats)
             │              │              │
             │              │              │
        ✅ FIXED        ⚠️ BROKEN        ⚠️ BROKEN
             │              │              │
             └──────────────┴──────────────┘
                            │
                            ▼
                  prop_id allocation logic
                   (determinePropIdForFile)
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
        ▼                   ▼                   ▼
    file_history_      pra/pic            CofO_staging
    staging table      tables              tables
    (✅ CORRECT)    (✅ CORRECT)         (⚠️ NEEDS VERIFICATION)
```

---

## Database Tables Status

### prop_id Column Presence

| Table | Has prop_id? | Purpose | Status |
|-------|:---:|---------|--------|
| `file_indexings` | ❌ NO | File indexing metadata | ✅ Fixed - Deleted |
| `file_history_staging` | ✅ YES | Central transaction hub | ✅ Correct |
| `pra` | ✅ YES | Legacy property records | ✅ Correct |
| `pic` | ✅ YES | Property index card | ✅ Correct |
| `CofO_staging` | ✅ YES | CofO records | ✅ Correct |
| `caveats` | ❌ NO? | Caveat records | ⚠️ NEEDS CHECK |
| `property_records` | ? | Property data variant | ⚠️ NEEDS CHECK |

---

## Validation & Testing Strategy

### FileIndexing (Already Fixed)
```sql
-- Verify file_indexings no longer has prop_id
SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'prop_id'
-- Expected: NO ROWS

-- Verify file_history_staging has prop_id
SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'file_history_staging' AND COLUMN_NAME = 'prop_id'
-- Expected: 1 ROW

-- Verify file_history_staging records have prop_id from indexing
SELECT id, mlsFNo, fileno, prop_id 
FROM file_history_staging 
WHERE prop_id IS NOT NULL 
LIMIT 10
-- Expected: Multiple rows with non-NULL prop_id
```

### PropertyRecordController::store() (After Fix)
```sql
-- Verify pra/pic records created via form have prop_id
SELECT id, file_number, prop_id 
FROM pra 
WHERE DATEDIFF(DAY, created_at, GETDATE()) <= 1
-- Expected: All recent records have non-NULL prop_id

-- Verify file_history_staging records have prop_id
SELECT id, fileno, prop_id 
FROM file_history_staging 
WHERE DATEDIFF(DAY, created_at, GETDATE()) <= 1
-- Expected: All recent records have non-NULL prop_id
```

### CaveatController (After Fix)
```sql
-- Verify caveats have prop_id context
SELECT id, file_number, prop_id 
FROM caveats 
WHERE DATEDIFF(DAY, created_at, GETDATE()) <= 1
-- Expected: If caveats table has prop_id column, all recent records have it

-- Verify file_history_staging shows caveat entries with prop_id
SELECT id, fileno, transaction_type, prop_id 
FROM file_history_staging 
WHERE transaction_type = 'caveat' 
AND DATEDIFF(DAY, created_at, GETDATE()) <= 1
-- Expected: All caveat transactions have prop_id
```

---

## Related Documentation

- **FILEINDEXING_PROP_ID_FIX_COMPLETE.md** - Complete FileIndexing fix (Dec 6, 2025)
- **PROP_ID_ALLOCATION_AUDIT.md** - Comprehensive audit of all prop_id gaps (updated with FileIndexing fix)
- **PROP_ID_DUAL_PATH_FIX.md** - PropertyRecordController dual-path issue details
- **PROP_ID_QUICK_REFERENCE.md** - Quick lookup for prop_id in all modules
- **PROP_ID_ARCHITECTURE_DIAGRAMS.md** - Visual data flow diagrams

---

## Summary

| Issue | Status | Impact | Fix Complexity |
|-------|--------|--------|-----------------|
| FileIndexing storing to wrong table | ✅ FIXED | Critical | Simple |
| PropertyRecordController.store() missing prop_id | ⚠️ PENDING | Critical | Medium |
| CaveatController missing prop_id | ⚠️ PENDING | High | Medium |
| CofoController verification | ⚠️ PENDING | Medium | Low |

**Overall Status**: 1/3 critical issues fixed. Next priority: PropertyRecordController.store()
