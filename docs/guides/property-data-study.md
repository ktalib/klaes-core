# Property Data Study

## `prop_id` as the cross-module key
- `prop_id` travels with every major record that describes a property, so it can be used to stitch file indexing rows, property records, CofO entries, and file history timelines together. The model whitelist keeps it available on every `FileIndexing` update (`app/Models/FileIndexing.php:120`), and updates accept it from the UI (`app/Http/Controllers/FileIndexingController.php:584`).
- When property transactions are posted from the indexing modal, the controller calculates or reuses a `prop_id` by searching both the live `property_records` table and the legacy `pra` table, then increments the max value if nothing is found (`app/Http/Controllers/PropertyRecordController.php:1403-1438`). That `prop_id` is injected into every row written to `property_records` (`app/Http/Controllers/PropertyRecordController.php:1257-1330`) **and** into every file-history snapshot that gets synced at the end of the request (`app/Http/Controllers/PropertyRecordController.php:1512`).
- File history APIs and timeline views rely on `prop_id` to group related transactions. The API exposes it as a filterable column (`app/Http/Controllers/Api/FileHistoryApiController.php:58-144`), and the File Index View collapses each group to a single “latest transaction” record by partitioning on `prop_id` (`app/Http/Controllers/FileIndexViewController.php:21-121,362`). File indexing updates push new IDs back into `file_history_staging` via `updateFileHistoryPropId()` whenever metadata changes (`app/Http/Controllers/FileIndexingController.php:2137-2156`).

## Property Index Card (PIC)
- The property index card assistant is a read-only façade over the `pic` table on SQL Server. The controller pulls a small seed set for the initial view and exposes a server-side paginated `/data` endpoint that handles full-text search, ordering, and date-safe sorting (`app/Http/Controllers/PropertyIndexCardController.php:12-116`).
- The Blade entry point reuses the generic property card partials so that users share one UI for browsing PIC and conventional property records. The partial is wired with the PIC-specific data route plus fallbacks to the property record routes for CofO lookups (`resources/views/property_index_card/index.blade.php:3-33`).
- Because PIC data runs through the same component tree as property records, enhancements to filters, modals, or CofO shortcuts automatically show up in both locations, but PIC writes are intentionally disabled from this route.

## Property Records Capture
- `PropertyRecordController` now enforces the non-null `title_type` requirement documented in `Readmes/PROPERTY_RECORD_FIX_SUMMARY.md:7-74`. When users omit it, the controller infers an appropriate value from the transaction type before persisting.
- The `storeFromIndexing` pathway assembles batched transactions coming from the file indexing modal. For each transaction it generates a composite registration number, sets serial/page/volume metadata, copies describing fields from the indexing request, and attaches the shared `prop_id` before inserting or updating the target table (`app/Http/Controllers/PropertyRecordController.php:1257-1346`).
- Transactions also update the legacy `fileNumber` table and mirror the same payload into `file_history_staging`, keeping the searchable timeline in sync with the structured rows (`app/Http/Controllers/PropertyRecordController.php:1486-1515`).

## CofO data flow
- Indexing edits call `updateCofORecord()` after every save so CofO staging always reflects the latest tracking ID, parties, and land data. The helper either matches on `cofo_no` or falls back to the MLS file number before upserting, and it carries over registration particulars plus the optional `test_control` flag (`app/Http/Controllers/FileIndexingController.php:881-931`).
- The standalone CofO number rollout added UI controls, backend validation, and the required SQL migration to add `cofo_no` and `application_id` columns (`COFO_NUMBER_IMPLEMENTATION_COMPLETE.md:1-81`). The same migration is also available as an artisan command for environments that prefer scripted schema updates (`app/Console/Commands/UpdateCofoTable.php:13-86`).
- Because CofO rows also expose `prop_id` (see schema excerpt in `FILE_INDEXING_FORM_GAPS.md:121-179`), any CofO imported through the indexing workflow can be linked back to property cards and file history without additional joins.

## File history surface (`file_history_staging`)
- The API accepts MLS file number, `prop_id`, location, registration ranges, and transaction-type filters, then normalizes parties depending on the type (assignor/assignee vs. grantor/grantee, etc.) before returning paginated JSON (`app/Http/Controllers/Api/FileHistoryApiController.php:20-173`). COFO transactions optionally expose a dedicated `cofo_date`, with `getDisplayDate()` preferring it whenever the transaction type implies a certificate event (`app/Http/Controllers/Api/FileHistoryApiController.php:188-232`).
- File Index View builds on the same staging table but adds row-number partitioning so that each `prop_id` shows a single “most recent transaction” row in the grid, with drill-downs that reconstruct the full timeline (`app/Http/Controllers/FileIndexViewController.php:21-239,362-386`). The UI therefore gives registry staff an instant summary plus a chronological audit trail without hitting multiple tables.
- Property record submissions and indexing updates both write into `file_history_staging`, ensuring that ad-hoc captures (e.g., from scanning) and curated transactions (from the property card modal) land in the same dataset. Duplicates are suppressed via serial/page/volume/date comparisons before inserts (`app/Http/Controllers/PropertyRecordController.php:1486-1515`).

## File indexing workflow
- The controller validates a very rich payload (file metadata, shelf assignment, grouping IDs, prop IDs, CofO flags, etc.) before saving. After persistence it cascades updates to the legacy `fileNumber` table, CofO staging, entity/customer records, and the file history staging table (`app/Http/Controllers/FileIndexingController.php:540-934,2137-2156`).
- `FILE_INDEXING_FORM_GAPS.md:1-188` documents the current form-vs-controller mismatches discovered during the November audit (missing `name` attributes, route collisions, required fields such as `file_number`/`awaiting_file_no` not posting, CofO/entity/customer inputs never submitted). Those gaps explain why operators occasionally hit validation 422s or see empty related tables even after indexing; the backend logic is ready, but the UI still needs to submit the matching field names.
- The workflow intentionally syncs with downstream services: once a file is indexed, shelf/batch data is reserved (`app/Http/Controllers/FileIndexingController.php:2175-2266`), tracking IDs migrate into `fileNumber`, and optional `test_control` flags allow QA teams to purge synthetic data en masse. The `FileIndexing` model exposes helper relationships so scanning, page typing, tracking, and label-print pipelines can quickly inspect record state (`app/Models/FileIndexing.php:1-187`).

## Relationship map at a glance
- **PIC ➜ Property Records**: Property index card UI uses the same propertycard partial, so improvements to the creation/edit modals automatically benefit both the legacy `pic` entries and the modern `property_records`.
- **File Indexing ➜ Property Records / CofO / File History**: Indexing saves call `updateRelatedTables()` which, in turn, updates the `fileNumber` ledger, pushes CofO metadata, refreshes entity & customer records, and rewrites `prop_id` on the file-history staging rows (`app/Http/Controllers/FileIndexingController.php:679-934,2137-2156`).
- **prop_id glue**: Every ingest path now tries to stamp `prop_id`, so downstream analytics (legal searches, file history timelines, caveat checks) can pivot on a single identifier instead of juggling MLS/KANGIS/temporary numbers. Verifying that new UI work (e.g., the standalone indexing form) actually posts `prop_id` should remain a top test case.

---

## ⚠️ CRITICAL ISSUE: prop_id Allocation Gaps (December 2025)

### The Problem
While the **design intent** is correct—`prop_id` as a unique, cross-module identifier—the **implementation has critical gaps**:

#### **Working correctly:**
✅ `PropertyRecordController::storeFromIndexing()` — allocates via `determinePropIdForFile()`  
✅ `FileIndexingController::update()` — respects and propagates incoming `prop_id`  
✅ File history APIs partition by `prop_id` correctly  

#### **Broken / Missing:**
❌ `PropertyRecordController::store()` — **does not allocate prop_id** (form-based entry)  
❌ `PropertyCardController::store()` — cascades NULL prop_id from PropertyRecordController  
❌ `CaveatController::store()` — places caveats **without prop_id context**  
❌ `CofoController::updateCofORecord()` — CofO inserts lack prop_id  
❌ Direct `fileNumber` table inserts — bypass prop_id entirely  
❌ Batch operations — orphaned from property ecosystem  

### Impact
- Users can create property records via the main form, but those records get **NULL prop_id**
- Caveats placed on these orphaned records have no way to link back to property history
- File indexing workflows cannot correlate records from different modules
- Legal searches and timeline views break when prop_id is missing
- **Data integrity**: same file number spread across multiple tables with different prop_ids (or NULL)

### Immediate Actions Required
1. **Read**: `PROP_ID_ALLOCATION_AUDIT_AND_SOLUTION.md` (comprehensive analysis + code examples)
2. **Implement**: Three-layer solution:
   - Layer 1: Centralized `PropertyIdAllocationService` (generate/allocate prop_id once)
   - Layer 2: Update all controllers to use the service before inserting
   - Layer 3: Database migrations to add `prop_id` column to `caveats`, `fileNumber`, scanning tables
3. **Migrate**: Run backfill script to assign prop_id to historical NULL records
4. **Test**: Validate no NULL prop_id and no duplicates post-deployment
5. **Monitor**: Alert on any new NULL prop_id inserts after cutover

### Files Needing Updates
| File | Issue | Priority |
|---|---|---|
| `app/Http/Controllers/PropertyRecordController.php` | `store()` missing prop_id allocation | CRITICAL |
| `app/Http/Controllers/PropertyCardController.php` | Cascades NULL prop_id | CRITICAL |
| `app/Http/Controllers/CaveatController.php` | No prop_id lookup/allocation | CRITICAL |
| `app/Http/Controllers/FileIndexingController.php` | `updateCofORecord()` missing prop_id | HIGH |
| **NEW**: `app/Services/PropertyIdAllocationService.php` | Create centralized service | CRITICAL |
| Database migrations | Add prop_id to caveats, fileNumber, scanning tables | CRITICAL |

### Solution Reference Document
**Location**: `/PROP_ID_ALLOCATION_AUDIT_AND_SOLUTION.md`

Contains:
- Executive summary of all gaps
- Detailed root cause analysis
- Complete code examples for each fix
- Database migrations (ready to run)
- Implementation checklist (9 phases)
- Validation queries & backfill script
- Risk assessment & rollout plan

---
