# OP serial validation, persistence and backfill plan

Date: 2026-10-04 (Africa/Lagos)
Status: Implemented and verified. Conservative backfill applied on 2026-10-04. Historical records without reliable evidence remain in the review report.

## Objective

Every saved Occupancy Permit card must have a valid OP serial number. Creating, editing, linking and commissioning an OP must preserve that number on the originating OP and its verified related records. Historical blanks must be repaired only from reliable evidence.

OP serial number is distinct from registration serial/page/volume and an OSS application or recommendation serial. Transfer of Title registration particulars may remain 0/0/0; that does not justify a zero OP serial number.

## Confirmed gaps

| Path | Evidence | Required change |
| --- | --- | --- |
| PRA API | `PraStoreRequest` accepts nullable OP serial; `PraUpdateRequest` accepts sometimes/nullable. `PraRecordService::prepareRecordPayload` normalizes submitted blanks to null without an OP serial guard. | Validate the effective record type and effective serial centrally, including partial edits and type conversions. |
| OSS OP cards | `OpResettlementApplicationController::opCaptureAndLink`, `opCaptureForCommissionedFile`, `opBatchCapture`, and `updateDetails` accept nullable serials. Batch writes use direct SQL. | Require serials in actual OP writes and cover direct SQL paths; duplicate-check/search endpoints may accept incomplete criteria. |
| Land FC | `generate_fileno/mlsfno.blade.php` includes both `instruments.partials.register_modal` and the shared `capture-op-card`. The shared card validates batch serials in JavaScript, but backend validation remains nullable. | Cover single capture, batch capture, existing OP selection, resume/edit, handoff and commissioning. Resolve the selected source row on the server. |
| Land FC fallback | `MlsFileNoController` validates `source_op_serial_number` as nullable; its fallback PRA OP creation payload near line 3566 omits `op_serial_number`. PRA creation failures are logged as non-critical. | Pass the resolved serial into OP creation; an OP-related persistence failure must not produce successful incomplete commissioning. |
| OSS commissioning mirror | `MlsCommissioningOssApplicationService::payload` does not include `op_serial_number`. | Resolve the exact source OP and persist its serial for OP-backed mirrors. |
| OSS application details | Some create paths require the top-level serial for Change of Name, but nested `occupancy_permit.op_serial_number` is nullable. `syncOccupancyPermitToPra` addresses a posted PRA id and catches sync errors without failing the application. | Validate nested/top-level consistency, source ownership/type and the resulting serial; commit required OP writes together. |
| File indexing addon | `FileIndexingController` accepts nullable `occupancy_permit_op_serial_number`; `syncOccupancyPermitRecord` writes a nullable serial into PRA and can update an existing row. | Require it when the OP addon is active; prevent blank overwrites and select the exact OP row. |
| PRA/property transactions | `PropertyRecordController` accepts nullable top-level, extra registration and transaction-array OP serials. | Validate every OP entry independently, including secondary rows. |
| Instrument capture | `InstrumentController` already requires positive digit strings for OP capture. `InstrumentCaptureService::capture` checks emptiness, while its update mapping uses `??`, which does not protect against empty strings. | Share strict validation on create/update and guard direct service callers. |
| Existing repair command | `oss:restore-op-serials` only fills latest blank Change of Name OSS rows from earliest PRA serial by file. It skips 0 but does not resolve conflicting candidates or use exact lineage. | Replace/extend with explicit candidate resolution, conflict reporting and a reversible execution report. |

These are confirmed gaps, not proof of which path created each historical blank. Review actual route bindings and all remaining direct writers before implementation; exclude backup/copy controllers from the live endpoint inventory.

## Database baseline

Read-only audit of active rows (`is_deleted IS NULL OR is_deleted = 0`), grouped by stored instrument type. Counts represent rows, not unique files; the same OP can appear in multiple tables.

| Record group | Total | Null/blank serial | Serial equal to 0 |
| --- | ---: | ---: | ---: |
| PRA Occupancy Permit | 3,084 | 99 | 4 |
| PRA Occupancy Permit (OP) | 39,531 | 1,200 | 13 |
| PRA Transfer of Title (OP) | 7,363 | 5,807 | 3 |
| Instrument capture Occupancy Permit (OP) | 16,675 | 11 | 0 |
| OSS Change of Name | 648 | 441 | 6 |

Classify other spelling/type variants and invalid nonblank strings in the full audit. These totals do not establish how many rows can be safely backfilled.

`op_serial_number` exists in PRA, instrument_capture, oss_applications, op_verifications and op_propid_matches. The last two are verification/matching records, not places to manufacture an OP automatically. `file_indexings` has no OP serial column: its occupancy permit addon persists in PRA. No redundant file-indexing column is proposed.

## Implementation sequence

### 1. Define one serial rule and one source resolver

- Use a shared server rule/value normalizer for positive decimal digit strings, with the current strict OP rule (`^[1-9][0-9]*$`) as the starting policy. Trim surrounding whitespace; do not convert through integer types. Reject blank, 0, padded numbers, letters and placeholders for new writes. Retain raw legacy values in the audit; report format-only repair candidates separately.
- Require a serial whenever an OP card is saved, whether standalone, nested, addon, extra registration or batch item. For OP-backed transfer/application/commissioning, resolve and require the originating OP serial even when the field is readonly or hidden.
- Apply the requirement based on the resolved record and operation, not only a client-supplied instrument type or system source. Unrelated instruments/applications must remain saveable without an OP serial.
- Resolve source OP by verified table/id and existing lineage (`source_op_table`, `source_op_id`, `source_pra_id`, `source_instrument_capture_id`, existing capture linkage). Check active status, OP type and association with the intended file/operation.
- Treat reused prop_id, multiple OPs per file, mergers and subdivisions as ambiguous unless an explicit source link identifies the OP. An OP serial alone is not globally unique and must not receive a global unique constraint.

### 2. Enforce create and edit semantics across all writers

- Inventory live routes, imports, commands, services and direct SQL writers as well as forms. Cover all eight previously identified editable form components and both Land FC OP entry points.
- Reject creates without a valid serial using a field-specific 422 validation error; batch errors must identify the offending entry.
- For partial edits, retain the existing valid serial when the field is omitted. Reject explicit blank/null serial submissions. For a legacy OP with a missing serial, resolve an unambiguous linked value or require correction before an OP-related save succeeds.
- Detect OP type conversions using the merged post-update record. Require the serial if the result becomes an OP.
- On a full form submission, carry the selected serial through request serialization; do not let hidden inputs, stale JavaScript or nested/top-level disagreement drop or change it.
- Resolve changes to an existing nonblank serial as an explicit correction to the selected OP; record before/after, actor and affected lineage. Do not silently replace an established conflicting value.
- Put a common guard in PRA and instrument services, then route remaining direct writes through it or apply the same policy at their write boundary. Request validation alone cannot cover direct SQL.

### 3. Persist and synchronize within the same SQL Server transaction

- Write the originating OP serial into its verified PRA/instrument representation and associated OP-backed OSS application/Transfer of Title records as required by that operation.
- Repair the Land FC handoff and fallback payload, and add serial resolution to the OSS commissioning mirror. Validate before allocating/committing commissioning records; required OP persistence errors must roll back related database changes and return a useful error.
- Update by exact row ids and lineage. Do not update every row sharing a file or prop_id, and do not propagate serials to unrelated Right of Occupancy or other transactions.
- Verify that all required target writes succeeded. Keep optional media and notifications outside the database transaction; send notifications only after successful commit.
- Invalidate affected lookup caches so successful saves display the persisted value. Existing display fallbacks must not mask inconsistent storage.

### 4. Align the forms, including Land FC

- Show OP Serial No. as required on every active OP card. Use the same digit/zero/padding policy on browser and server.
- Block incomplete single and batch saves, including edit/resume and duplicate-backfill paths. Keep disabled/non-OP sections from introducing irrelevant validation errors.
- Existing OP selection must display the serial and exact source identity. A selected legacy OP with no serial must be corrected before commissioning.
- Readonly serials must be obtained from the selected server-side source; do not trust hidden serial values as authoritative.
- Present nested and batch backend validation errors next to the corresponding OP serial input.

### 5. Build a safe historical backfill

- Default to dry-run. Export target table/id, file, current serial, proposed serial, source table/id, matching basis, status and reason.
- Start with explicit source links. Where links are missing, consider exact file aliases plus corroborating registration particulars/type/parties. Require one identifiable OP and one consistent valid serial; skip reused/ambiguous identities.
- Classify candidates as safe to fill, invalid placeholder, conflicting values, ambiguous OP, or no source. Treat zeros separately and repair them only when the source is verified.
- Fill only missing/invalid-placeholder targets. Preserve every valid nonblank value; report conflicts for review. Do not choose the oldest/newest PRA row as a substitute for source identity.
- Use the same resolver as live saving so forward and historical behavior agree. Inspect verification/match entries as supporting evidence without treating them as independent authority for automatic cross-file writes.
- Save a before-image and run id before applying approved candidates. Revalidate the source and target at write time, use conditional updates and transactions, record actor/source provenance, verify results and provide guarded rollback.
- A rerun must produce zero additional changes for already repaired records. Export unresolved records for manual document confirmation.
- RES-2023-6336 remains unresolved until its actual source record/document is located; do not create a PRA OP or attach serial 109 to another year's file solely from a near match.

### 6. Verify and roll out

- Test direct API/service calls without JavaScript: blank/null/0/padded/non-digit serials fail for OP creates and active OP cards; non-OP transactions remain valid.
- Test omitted-field edits, explicit clearing, legacy blanks, type conversion, duplicate serials belonging to separate OPs, wrong source ids and multiple OPs sharing a file/prop_id.
- Cover Land FC single/batch capture, selected existing OP, resume/update, source handoff, fallback PRA creation and OSS mirror; confirm saved values agree and required write failures roll back commissioning.
- Cover OSS nested OP edits, indexing addon, property transactions/extra rows, instrument edits and linked Transfer of Title serial inheritance. Preserve registration 0/0/0 where applicable.
- Test backfill ambiguity/conflict/no-source cases, dry-run immutability, conditional writes, before-images, rollback and idempotence.
- Deploy prevention before applying a reviewed backfill report. Run a read-only consistency report afterward and retain an operational report of new missing or divergent serials.
- Consider conditional database CHECK constraints only after classifying instrument types and repairing eligible legacy data. Global NOT NULL is unsuitable because non-OP rows share these tables; cross-table correctness still belongs in the save service.

## Completion criteria

No new OP/card save can commit a missing or invalid serial. Editing cannot erase a valid serial. Land FC commissioning copies the verified source serial to the required linked records and fails atomically when that cannot be done. Backfill writes only justified values, reports all unresolved records and is repeatable and reversible.


## Implementation and verification results

- Shared policy: `App\Support\OpSerial`; verified source resolution and partial-edit handling: `OpSerialSourceResolver`; transactional propagation: `OpSerialSynchronizer`.
- Covered PRA API/service/repository writes, instrument capture/edit, OSS create/edit and nested OP cards, indexing OP addons and transactions, property entry/import, Land FC single and batch commissioning, OP-to-ToT matching, verification/matching records and staged ToT generation.
- Land FC now carries each batch source OP id server-side and performs required pairing before commissioning commits. Existing readonly/hidden serials are checked against the chosen source.
- Merger sources require individual valid serials. An explicitly linked merger transfer retains a verified aggregate; automatic historical repairs do not collapse a merger to one source serial.
- Added `op:serial-integrity` (dry-run default), `--apply`, `--table` and guarded `--rollback=<report.json>`. The older `oss:restore-op-serials` delegates to this planner for OSS targets.
- Added a daily read-only integrity audit at 03:15 in the existing Laravel scheduler. It runs when the application's scheduler is active.
- Applied 382 blank OSS serial repairs: 253 from one PRA OP for the exact file, 129 from explicit commissioning source links. All were null before the repair; no nonblank values were replaced.
- Apply report and before-images: `storage/app/op-serial-integrity/20261004-022450-dcdff5-apply.json`.
- Post-apply dry-run: `storage/app/op-serial-integrity/20261004-023133-dc29bf-dry-run.json`. It found zero safe candidates, verifying idempotence.
- Unresolved review export: `storage/app/op-serial-integrity/20261004-023133-unresolved.csv` (8,905 record-level audit cases, including missing/broken links and conflicts; not 8,905 unique files or missing serials).
- RES-2023-6336 remains unlocated. No record was created or guessed for serial 109.
- Validation: 44 targeted tests / 106 assertions passed, including SQLite-only transaction/rollback, source identity, batch linking, merger aggregation, API rejection, application editing and dry-run/apply/repeat/rollback coverage. PHP and JavaScript syntax checks passed. Production data was not used for test writes.
- Global database NOT NULL/CHECK enforcement was deliberately not installed while unrepairable legacy records and shared non-OP tables remain. Required validation applies to the covered application write paths; the daily audit detects drift from external/raw SQL changes.

### Operating the repair command

```powershell
php artisan op:serial-integrity
php artisan op:serial-integrity --apply
php artisan op:serial-integrity --table=oss_applications
php artisan op:serial-integrity --rollback=storage/app/op-serial-integrity/<run>-apply.json
```

Rollback restores only the original serial values from that run and refuses records that have changed since the repair. Review conflict, ambiguous, broken-source and no-source cases against the actual OP documents or corrected source links before attempting further repairs.
