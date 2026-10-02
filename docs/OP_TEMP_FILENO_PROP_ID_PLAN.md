# OP Temp FileNo PropID Propagation Plan

## Goal
Ensure `prop_id` is consistently persisted and propagated when OP records start with `temp_fileno`, and when those OP records are later commissioned into a permanent file number.

## Scope
- Save `prop_id` for temp file number captures in `instrument_capture`.
- Pass the same `prop_id` into `deed_registrations` during registration write.
- Ensure OP commissioning/generation path sends `prop_id` to PRA when generating the file number.
- Keep all operations on `sqlsrv` and reuse existing services (`PropertyIdAllocationService`, `InstrumentCaptureService`, `InstrumentRegistrationService`, `PraRecordService`).

## Current State (Verified)
1. `InstrumentCaptureService::capture()` already allocates `prop_id` using fallback primary (`mls/kangis/newkangis/temp_fileno`) and stores it in `instrument_capture`.
2. `InstrumentCaptureService::capture()` currently calls `InstrumentRegistrationService::registerInstrument()` but does not explicitly pass `prop_id` into deed registration payload.
3. `InstrumentRegistrationService::registerInstrument()` does not currently insert `prop_id` into `deed_registrations`.
4. OP commissioning flow in `MlsFileNoController::createResettlementLinkedRecords()` already derives/allocates `prop_id` and sends it to `PraRecordService::createRecord()`.

## Implementation Plan

### 1. Harden Temp FileNo PropID Persistence in Instrument Capture
Files:
- `app/Services/InstrumentCaptureService.php`

Steps:
1. Keep existing PropID allocation logic, but make `temp_fileno` explicit when `mlsFNo/kangis/newkangis` are empty.
2. Add guard logging for cases where `temp_fileno` is present but `prop_id` resolution fails.
3. Ensure `prop_id` is preserved on update path when temp file records are edited.

Expected outcome:
- Every OP capture created with `temp_fileno` stores a stable `prop_id` in `instrument_capture`.

### 2. Propagate PropID to Deed Registration at Insert Time
Files:
- `app/Services/InstrumentCaptureService.php`
- `app/Services/InstrumentRegistrationService.php`

Steps:
1. In `InstrumentCaptureService::capture()`, include `prop_id` in `$regData` before calling `registerInstrument()`.
2. In `InstrumentRegistrationService::registerInstrument()`, add `prop_id` to `$insertData` when the column exists.
3. Use schema-safe write (column presence check) to avoid failures on environments with drifted schema.

Expected outcome:
- `deed_registrations` row created for OP capture receives the same `prop_id` from `instrument_capture`.

### 3. Keep PropID in OP Commissioning -> PRA Path
Files:
- `app/Http/Controllers/MlsFileNoController.php`

Steps:
1. Retain `source_prop_id` acceptance in validation for commissioning payload.
2. In resettlement mirroring path, continue resolving `prop_id` from source capture first, then allocate fallback with `PropertyIdAllocationService`.
3. Ensure PRA payload always includes `prop_id` for OP-generated file number records.
4. Ensure party mapping remains:
   - party 1/grantor: original OP owner (source `party_2_name`)
   - party 2/grantee: commissioning name (`file_name`)

Expected outcome:
- PRA receives canonical `prop_id` for OP-generated commissioning records.

### 4. Backfill Existing Temp OP Records (One-time Data Fix)
Files:
- New script under `database_scripts/` or maintenance command in `routes/console.php`

Steps:
1. Identify `instrument_capture` rows where:
   - `temp_fileno` is not null
   - `prop_id` is null
2. Allocate/retrieve `prop_id` via `PropertyIdAllocationService` using `temp_fileno` context.
3. Update linked `deed_registrations` by `instrument_capture_id` with same `prop_id`.
4. Log skipped/conflicted records for manual review.

Expected outcome:
- Legacy temp OP captures are aligned with the new PropID policy.

### 5. Validation and Regression Testing
Manual test matrix:
1. Capture new OP using `temp_fileno`.
2. Confirm `instrument_capture.prop_id` is saved.
3. Confirm corresponding `deed_registrations.prop_id` is saved.
4. Commission same OP to permanent file number (Direct Allocation + Resettlement).
5. Confirm PRA record has same `prop_id` and correct party mapping.
6. Confirm no regression for non-OP instruments.

Suggested verification queries:
```sql
-- Instrument capture by temp fileno
SELECT TOP 20 id, temp_fileno, mlsFNo, prop_id, registration_number
FROM instrument_capture
WHERE temp_fileno IS NOT NULL
ORDER BY id DESC;

-- Deed registration linkage
SELECT TOP 20 dr.id, dr.instrument_capture_id, dr.fileno, dr.prop_id, dr.registration_number
FROM deed_registrations dr
ORDER BY dr.id DESC;

-- PRA verification
SELECT TOP 20 prop_id, mlsFNo, fileno, transaction_type, Grantor, Grantee
FROM pra
ORDER BY id DESC;
```

## Rollout Order
1. Service-level code changes (`InstrumentCaptureService`, `InstrumentRegistrationService`).
2. Commissioning/PRA validation in `MlsFileNoController` flow.
3. Execute one-time backfill script in staging.
4. Validate with end-to-end OP scenarios.
5. Deploy to production with monitoring logs enabled for PropID misses.

## Risks and Mitigations
1. Schema drift (`prop_id` missing in some environments).
Mitigation: use schema-aware conditional column writes and migration check before deploy.

2. Duplicate PropID assignment when backfilling legacy records.
Mitigation: always use `allocateOrRetrievePropId()` and update only null `prop_id` records.

3. Partial writes across tables.
Mitigation: keep transactional writes in existing service/controller transactions and fail fast on hard errors.
