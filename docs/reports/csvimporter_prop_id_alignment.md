# CSV Importer `prop_id` Review

## Reference Expectations
- `docs/doc_1_2/PROP_ID_SYSTEM_COMPLETE_FIX.md:14-275` documents the new rule set: `prop_id` is a property-data identifier that must live in `file_history_staging`, `pra`, `pic`, and `CofO` tables, stay out of workflow helpers such as `file_indexings`, and be synchronized to `file_history_staging` for every ingest path.
- `app/Services/PropertyIdAllocationService.php:13-123` centralizes allocation by (a) reusing identifiers from `file_history_staging`, `pra`, and `pic`, (b) generating the next numeric ID from the same tables, and (c) calling `syncPropIdToFileHistory()` to keep the staging hub authoritative.

## Findings and Suggested Fixes

### 1. File History import writes to the wrong table
- **Finding:** The FastAPI staging map purposely routes the File History workflow into the legacy `file_history` table (`csvimporter/main.py:85`), and the final commit loop inserts each record there (`csvimporter/main.py:1551-1668`, `_import_property_record(..., staging_table='file_history')` at `csvimporter/main.py:1611`). The Laravel guidance expects every transaction to land in `file_history_staging`, which now drives property cards, APIs, and cross-table `prop_id` lookups (`docs/doc_1_2/PROP_ID_SYSTEM_COMPLETE_FIX.md:259-275`).
- **Suggested fix:** Point the Python importer at `file_history_staging` everywhere: update `STAGING_TABLES['file_history']`, the clear-data queries, and the `_import_property_record` calls to use the canonical table name and schema (including any extra columns such as `test_control`). After the schema swap, add a smoke test that uploads a CSV and verifies the rows appear in `file_history_staging` with the assigned `prop_id`.

### 2. Workflow metadata still stores `prop_id`
- **Finding:** The SQLAlchemy model for `file_indexings` keeps a `prop_id` column (`csvimporter/app/models/database.py:12-44`), and helper scripts such as `csvimporter/add_prop_id_columns.py` actively recreate that column. This directly contradicts the fix that removed `prop_id` from `file_indexings` so it only lives in property data tables (`docs/doc_1_2/PROP_ID_SYSTEM_COMPLETE_FIX.md:33-105`, `docs/doc_1_2/PROP_ID_ALLOCATION_MASTER_PLAN.md:13-25`).
- **Suggested fix:** Drop every trace of `prop_id` from the Python model and database helpers: remove the column definition, delete the migration helper that adds it back, and rely on `file_history_staging` + property tables for linking. Any UI display that currently reads `FileIndexing.prop_id` should instead join through the shared file number to `file_history_staging` if it needs the identifier.

### 3. Allocation logic queries the wrong sources
- **Finding:** `_bulk_lookup_existing_property_ids()` and `_get_next_property_id_counter()` interrogate `file_indexings`, `CofO`, `property_records`, and `registered_instruments` (`csvimporter/app/services/file_indexing_service.py:709-787` and `csvimporter/app/services/file_indexing_service.py:1335-1361`). The new allocation service purposely uses `file_history_staging`, `pra`, and `pic` as the authoritative sources (`app/Services/PropertyIdAllocationService.php:80-123`), because workflow tables must not store `prop_id` and CofO lookups happen through the dedicated staging write.
- **Suggested fix:** Reuse the same data sources as the Laravel service. The simplest option is to expose a small HTTP endpoint (or stored procedure) that proxies `PropertyIdAllocationService::allocateOrRetrievePropId()` and call it from the FastAPI app. If that is not feasible, mirror the service logic locally: query `file_history_staging`, `pra`, and `pic` for reuse, and compute `MAX(prop_id)+1` from the same three tables so numbering stays consistent with the rest of the platform. Remove `file_indexings` and `registered_instruments` from the lookup list to avoid reintroducing stale IDs.

### 4. PRA import fabricates sequential IDs without de-duplication
- **Finding:** When uploading PRA CSVs, the importer simply generates sequential IDs in-memory: it grabs the next counter, assigns new IDs to each `property_record`, then keeps incrementing for every `file_number` row (`csvimporter/main.py:2978-2993`). This path never checks `file_history_staging`, `pra`, or `pic` for an existing ID, so it can easily fork the same property across multiple identifiers—the exact failure called out in the audit (`docs/doc_1_2/PROP_ID_SYSTEM_COMPLETE_FIX.md:329-437`).
- **Suggested fix:** Use the same `_assign_property_ids()` pipeline (after it is updated per Finding #3) for PRA uploads instead of the manual loop. Build an assignment payload with every file identifier present in the CSV, reuse existing IDs when found, and only allocate new numbers for genuinely new properties. Ensure the resulting `prop_id` is written to `pra`, `CofO_staging`, and pushed through the new `file_history_staging` sync so timelines remain stitched together.

## Next Steps
1. Refactor the importer to depend on the centralized allocation service (direct call or mirrored logic).
2. Adjust the staging table targets and schema bindings, then run a dry-run import to confirm rows appear in `file_history_staging` with the expected IDs.
3. Remove `prop_id` from workflow models/scripts and add regression tests that fail if the column reappears.
4. Backfill any PRA or File History rows that were inserted via the Python tool while it was generating incompatible IDs, ensuring they inherit the canonical values from `file_history_staging`.

## Implementation Progress
- `_import_property_record()` now understands the richer File History schema and persists `record_type`, `title_type`, `transaction_date_raw`, `reg_time`, `reg_date`, `related_file_number`, `land_use`, `tp_no`, and `lpkn_no` whenever the FastAPI importer writes into the `file_history` table (`csvimporter/main.py:2787-2904`). This closes the first gap from the mapping audit by ensuring those CSV columns survive normalization instead of being dropped before reaching SQL Server.

## CSV Field Mapping Review

### File Indexing CSV ➜ `file_indexings`
- The entire mapping is hard-coded to the 18 headers in `field_mappings` (`csvimporter/main.py:163-214`). Nothing else from the spreadsheet survives the normalization step, so identifiers such as `mlsFNo`, `kangisFileNo`, shelving metadata, or QA flags are silently dropped even though the target table exposes those columns.
- Numeric coercion only runs for the small `numeric_like_fields` set (`csvimporter/main.py:172-212`), which means batch numbers or LPKN/TP numbers remain strings when the database columns are integers. Downstream validation therefore treats `'12'` and `12` as different values.
- Improvement: move the mapping list into a config structure (similar to `MULTI_TABLE_IMPORT_PLAN.md`) and extend it to include all writable `file_indexings` columns. That lets us add new CSV headers without redeploying code, and ensures the importer can hydrate `registry_batch_no`, `sys_batch_no`, `mlsFNo`, and any future workflow fields.

### Property transactions ➜ `property_records` / `file_history`
- File History (`csvimporter/main.py:771-987`), PRA (`csvimporter/main.py:2223-2312`), and PIC (`csvimporter/main.py:3576-3698`) all build very rich `property_record` dictionaries: they include `title_type`, `record_type`, `land_use`, party classifications, `related_file_number`, `reg_time`, several date variants, KN numbers, page-typing metadata, etc.
- `_import_property_record()` only writes a narrow subset of columns: see `insert_columns` and the matching update clause in `csvimporter/main.py:2872-2904`. Everything outside that whitelist—e.g. `title_type`, `record_type`, `land_use`, `Mortgagor`, `Mortgagee`, `related_file_number`, approval dates, lease periods, layout data, `tp_no`, `lpkn_no`, and assignment/surrender timestamps—never reaches SQL Server even though the CSV parsers worked hard to normalize them.
- Because the insert/update SQL omits `reg_time`, `transaction_date_raw`, `reg_date_raw`, and the KN number fields, analysts lose the original human-entered strings that are often needed when reconciling damaged registers.
- Improvement: audit the actual `property_records`/`file_history_staging` schema and extend `_import_property_record()` so it persists every normalized CSV field (or deliberately discards it with a comment). If the table is missing a column, create the migration first, then widen the insert field list so the Python importer stays aligned with Laravel.

### File-number side tables
- PRA’s file-number payload (`csvimporter/main.py:2294-2306`) is limited to `mlsfNo`, `FileName`, a derived `location`, and a few bookkeeping fields. District, LGA, and plot metadata never propagate into `fileNumber`, even though those columns exist in SQL Server.
- PIC’s helper `_build_pic_file_number_record()` (`csvimporter/main.py:3775-3804`) and the actual insert `_import_pic_file_number_record()` (`csvimporter/main.py:3491-3525`) do not capture land-use, district, or grantee classifications. They also default `tracking_id` every time, so bulk uploads can’t correlate to the source CSV row once the record is committed.
- Improvement: mirror the Laravel `fileNumber` model fillable list. Add district/LGA/land_use columns to the insert, store both `tracking_id` and the grid row index for debugging, and carry over any typed-by or upload user IDs if present in the CSV so Activity Monitoring can attribute work correctly.

### Suggested enhancements
1. **Centralize mappings:** Extract the header-to-column dictionaries into a shared YAML/JSON config and reuse it across File History, PRA, and PIC so we never hard-code column names in three places.
2. **Schema-aware inserts:** Generate insert/update column lists from declarative metadata (e.g., `PROPERTY_RECORD_COLUMNS = [...]`). This keeps the Python importer in sync with Laravel migrations and prevents silent data loss when new fields land in the CSV.
3. **Diagnostics:** Add a “dropped fields” warning to the preview payload—if a CSV column is present but not mapped to any table column, surface it so operators know why a value disappeared.
4. **Type fidelity:** When a destination column is numeric/datetime (serial numbers, registration dates, LPKN/TP numbers), coerce it before persistence and log rows that fail conversion instead of writing raw strings that later violate constraints.
