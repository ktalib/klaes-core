# File Indexing Form Wiring Gaps (Nov 21, 2025)

## Snapshot
- Reviewed `resources/views/fileindexing/addons/create_indexing.blade.php` and its partials alongside `public/js/fileindexing/create-indexing-dialog.js`.
- Cross-checked expectations in `app/Http/Controllers/FileIndexingController.php` and legacy `FileIndexController.php`.
- Focused on pathways touching CofO staging, file number linkage, entity/customer capture, and the legacy `fileNumber` table.

## Implementation TODO
- [ ] Patch Blade form partials to add the missing `name`/hidden fields (file/grouping metadata, registry/shelf inputs, `test_control`).
- [ ] Rewire `public/js/fileindexing/create-indexing-dialog.js` to submit a single payload that aligns with `FileIndexingController::store`, including grouping resolution and modal post-submit hooks.
- [x] Extend `FileIndexingController::store` to pull tracking/meta from grouping, update grouping (`indexing_mapping`, `indexing_mls_fileno`, `mdc_batch_no`, `test_control`), and cascade updates to `file_indexings` and `fileNumber`.
- [ ] Implement CofO upsert helper (keyed on `cofo_no`/`mlsFNo`) triggered post-save, excluding `np_fileno`.
- [ ] Add entity & customer upsert services that respect staging tables, link via `file_number`, and copy `test_control` when present.
- [ ] Persist property transaction metadata into `file_history` after indexing succeeds, preventing duplicates via composite lookup.
- [ ] Document manual QA steps (API smoke tests, cleanup of `test_control = 'TEST'`) once code paths are in place.

## Front-End Submission Pipeline
- `create-file-btn` has no active handler in `public/js/fileindexing/create-indexing-dialog.js`; the file never binds a click or submit event that posts the form. The only working fetch logic still lives in the deprecated Blade-embedded script (`resources/views/fileindexing/js/FileIndexDialog_js.blade.php`), which the standalone page does not load.
- Most inputs rendered by the new form lack `name` attributes (e.g., `#file-title`, `#plot-number`, `#land-use-type`, `#tp-number`, `#awaiting-file-no`, `#cofo-*`). A vanilla form submit would therefore post almost nothing.
- Hidden metadata introduced by the grouping workflow (`awaiting_file_no`, `grouping_match_id`, `sys_batch_no`, `shelf_rack_no`) is absent. `FileIndexingController@store()` marks `awaiting_file_no` as `required`; any request coming from the new UI will 422 before insert.

## File Number & Grouping Alignment
- The form persists the selected file number in `input#fileno` (name `fileno`), but the controller requires `file_number`. Nothing remaps the value, so validation fails immediately.
- Grouping linkage is saved into `input#grouping-id` with `name="grouping_id"`, yet the controller expects `grouping_match_id`. Even if populated, the payload is dropped during validation.
- `FileIndexingController@store()` enforces registry, SYS batch, and shelf/rack consistency against the grouping record. The form surfaces read-only outputs (`#registry`, `#shelf-rack-no`), but they lack names and never post, guaranteeing a 422 for any grouping that has those values defined.
- Route duplication compounds the confusion: `routes/web.php` registers `fileindexing.store` for `FileIndexingController`, while other route files register the same name for `FileIndexController`. Depending on load order, the UI may hit the stricter controller and fail, or the legacy one and silently ignore grouping requirements.

## CofO Staging (`CofO_staging`)
- The CofO panel collects rich data but every control omits a `name`. No payload reaches the backend.
- Neither `FileIndexingController` nor `FileIndexController` attempts to create or update records in `CofO_staging`; the only CofO interaction is a lookup helper (`getCofORecord`). Result: even if fields were posted, nothing persists to the staging table.

## Entity & Customer Data
- The standalone form exposes full entity/customer inputs with proper names, yet `FileIndexingController@store()` ignores them completely. There is no hand-off to `EntityService`, `EntityCustomerController`, or the relevant models, so nothing reaches `entities`, `customers`, or bridge tables.
- Legacy dialog scripts previously called dedicated endpoints to prefill entity/customer data but never saved updates; the new implementation repeats that read-only behaviour.

## `fileNumber` Table Integration
- `GlobalFileNoModal` returns `fileData.record.id`, but the new script never forwards it. The controller expects `source_file_id`/`file_number_id`, however the request body emitted by the new form omits both fields.
- Neither controller updates the `fileNumber` table to mark a file as indexed, attach a tracking ID, or link back to the new `file_indexings` row. Downstream modules relying on `fileNumber.tracking_id` or status flags will not see the new record.

## High-Risk Failure Points
1. **Immediate 422 on submit** – missing `file_number`/`awaiting_file_no` prevents any insert through `FileIndexingController@store()`.
2. **Route collision** – depending on which controller the route resolves to, either validation fails or data silently ignores grouping, reinforcing inconsistent behaviour.
3. **Ghost data entry** – CofO, entity, and customer sections let operators type but nothing is persisted anywhere.
4. **Shelf/batch enforcement** – the controller compares incoming SYS batch & shelf/rack against grouping, but those values never post, so even correctly populated groupings are rejected.

## Recommended Next Actions
- Rewire the form submission: bind `create-file-btn` to a payload builder that satisfies `FileIndexingController@store()` (or intentionally point back to `FileIndexController` until the new workflow is ready).
- Add missing hidden inputs / name attributes (`file_number`, `awaiting_file_no`, `grouping_match_id`, registry/batch/shelf fields, CofO inputs) and ensure they’re filled when the grouping record loads.
- Decide on persistence responsibilities: either extend `FileIndexingController` to create/update `CofO_staging`, `entities`, `customers`, and `fileNumber`, or explicitly defer to dedicated services and call them from `store()`.
- Resolve the duplicate route definitions so the UI always hits the intended controller.

## Insert Integration Plan
- **file_indexings** – Accept tracking IDs from the grouping record instead of generating new values; once the main record is saved, hydrate `tracking_id`, `indexing_mls_fileno`, `mdc_batch_no`, and `indexing_mapping = 1` from that grouping payload and persist them on the same transaction (with `test_control` carried through for cleanup).
- **grouping** – When a file number is chosen, resolve the matching grouping row (by pseudo/awaiting fileno + year/land use); if it has not been matched, set `indexing_mapping = 1`, stamp `indexing_mls_fileno` with the selected number, capture `mdc_batch_no`, copy over `test_control`, and surface its `tracking_id` back to indexing for propagation.
- **CofO_staging** – After lookup success, upsert CofO metadata keyed by `cofo_no`/`mlsFNo` or similar; skip `np_fileno` inputs (reserved for primary applications) and rely on the current file number when you need a linkage column.
- **fileNumber** – Once the grouping-derived `tracking_id` is available, update the legacy registry record (`file_number_id` or matching fileno) with that tracking value and ensure any decommissioning flags remain untouched (`is_decommissioned = 0`).
- **entities_staging → entities** – Build an entity payload from the applicant section and upsert by `file_number`; on commit, promote out of staging when required and surface the `entity_id` back to indexing.
- **customers_staging → customers** – Mirror the entity flow for customer records, linking to `entity_id` when present; wrap inserts in transactions so customer/state updates stay consistent with the main file indexing create.

- **file_history** – Trigger the modal write after the primary record succeeds, inserting a transaction snapshot keyed on file number plus `serialNo/pageNo/volumeNo` to avoid duplicates.

### Test Data Handling
- For every insert or upsert above, set `test_control = 'TEST'` on synthetic runs so cleanup queries (`DELETE ... WHERE test_control = 'TEST'`) remain effective across all touched tables.

BELWO ARR TABLES  FOR THE FILE INDEXING

  FILE INDEXING TABLE, USE FILE NUMBER TO  CHECK FOR DUPLICATES, 
  SELECT TOP (1000) [id]
      ,[main_application_id]
      ,[subapplication_id]
      ,[recertification_application_id]
      ,[st_fillno]
      ,[file_number_id]
      ,[file_number]
      ,[file_title]
      ,[land_use_type]
      ,[plot_number]
      ,[district]
      ,[lga]
      ,[has_cofo]
      ,[is_merged]
      ,[has_transaction]
      ,[is_problematic]
      ,[is_co_owned_plot]
      ,[created_at]
      ,[updated_at]
      ,[created_by]
      ,[updated_by]
      ,[serial_no]
      ,[batch_no]
      ,[shelf_location]
      ,[is_updated]
      ,[batch_id]
      ,[has_qc_issues]
      ,[workflow_status]
      ,[archived_at]
      ,[shelf_label_id]
      ,[assigned_by]
      ,[assigned_at]
      ,[reserved_by]
      ,[reserved_at]
      ,[status]
      ,[registry]
      ,[location]
      ,[tp_no]
      ,[lpkn_no]
      ,[tracking_id]
      ,[batch_generated]
      ,[last_batch_id]
      ,[batch_generated_at]
      ,[batch_generated_by]
      ,[is_deleted]
      ,[deleted_at]
      ,[related_fileno]
      ,[sys_batch_no]
      ,[group]
      ,[date_migrated]
      ,[source]
      ,[migrated_by]
      ,[date_created]
      ,[prop_id]
      ,[test_control]
      ,[registry_batch_no]
  FROM [klas].[dbo].[file_indexings]
  

COFO TABLE  USE FILE NUMBER TO  CHECK FOR DUPLICATES, 

  SELECT TOP (1000) [id]
      ,[np_fileno]
      ,[mlsFNo]
      ,[kangisFileNo]
      ,[NewKANGISFileno]
      ,[prop_id]
      ,[title_type]
      ,[transaction_type]
      ,[transaction_date]
      ,[transaction_time]
      ,[serialNo]
      ,[pageNo]
      ,[volumeNo]
      ,[regNo]
      ,[instrument_type]
      ,[period]
      ,[period_unit]
      ,[Assignor]
      ,[Assignee]
      ,[Mortgagor]
      ,[Mortgagee]
      ,[Surrenderor]
      ,[Surrenderee]
      ,[Lessor]
      ,[Lessee]
      ,[Grantor]
      ,[Grantee]
      ,[property_description]
      ,[location]
      ,[plot_no]
      ,[lgsaOrCity]
      ,[layout]
      ,[schedule]
      ,[created_at]
      ,[updated_at]
      ,[deleted_at]
      ,[created_by]
      ,[updated_by]
      ,[land_use]
      ,[cofo_type]
      ,[fileno]
      ,[is_caveated]
      ,[caveated_comment]
      ,[cofo_no]
      ,[application_id]
      ,[cofo_date]
      ,[migration_source]
      ,[date_migrated]
      ,[migrated_by]
      ,[date_created]
      ,[comments]
      ,[assignment_date]
      ,[surrender_date]
      ,[revoked_date]
      ,[regranted_from]
      ,[date_expired]
      ,[remarks]
      ,[oldKNNo]
      ,[test_control]
  FROM [klas].[dbo].[CofO_staging]


USE FILE NUMBER TO  CHECK FOR DUPLICATES, 

FILE NUMBER TABLE 
SELECT TOP (1000) [id]
      ,[application_id]
      ,[kangisFileNo]
      ,[mlsfNo]
      ,[NewKANGISFileNo]
      ,[FileName]
      ,[created_at]
      ,[updated_at]
      ,[location]
      ,[created_by]
      ,[updated_by]
      ,[type]
      ,[is_deleted]
      ,[SOURCE]
      ,[commissioning_date]
      ,[decommissioning_date]
      ,[decommissioning_reason]
      ,[is_decommissioned]
      ,[temp_fileno]
      ,[plot_no]
      ,[tp_no]
      ,[sub_application_id]
      ,[st_file_no]
      ,[tracking_id]
      ,[date_migrated]
      ,[migrated_by]
      ,[migration_source]
      ,[test_control]
  FROM [klas].[dbo].[fileNumber]

USE FILE NUMBER TO  CHECK FOR DUPLICATES, 
   ENTTITIES TABLE
   SELECT TOP (1000) [id]
      ,[entity_type]
      ,[entity_name]
      ,[passport_photo]
      ,[company_logo]
      ,[created_at]
      ,[updated_at]
      ,[test_control]
      ,[file_number]
  FROM [klas].[dbo].[entities_staging]




CUSTOMERS TABLE YES DUPLICATE, CUS IT A TRANSACTIONS
SELECT TOP (1000) [id]
      ,[customer_type]
      ,[status]
      ,[file_number]
      ,[customer_name]
      ,[property_address]
      ,[residential_address]
      ,[notes]
      ,[customer_code]
      ,[created_by]
      ,[updated_by]
      ,[created_at]
      ,[updated_at]
      ,[deleted_at]
      ,[entity_id]
      ,[test_control]
      ,[account_no]
      ,[reason_retired]
      ,[physical_address]
      ,[retired_by]
  FROM [klas].[dbo].[customers_staging]


 FILE HISTORY TABLE CUSTOMERS TABLE YES DUPLICATE, CUS IT A TRANSACTIONS
SELECT TOP (1000) [id]
      ,[mlsFNo]
      ,[kangisFileNo]
      ,[NewKANGISFileno]
      ,[title_type]
      ,[transaction_type]
      ,[transaction_date]
      ,[serialNo]
      ,[pageNo]
      ,[volumeNo]
      ,[regNo]
      ,[instrument_type]
      ,[period]
      ,[period_unit]
      ,[Assignor]
      ,[Assignee]
      ,[Mortgagor]
      ,[Mortgagee]
      ,[Surrenderor]
      ,[Surrenderee]
      ,[Lessor]
      ,[Lessee]
      ,[Grantor]
      ,[Grantee]
      ,[property_description]
      ,[location]
      ,[streetName]
      ,[house_no]
      ,[districtName]
      ,[plot_no]
      ,[lgsaOrCity]
      ,[layout]
      ,[schedule]
      ,[created_at]
      ,[updated_at]
      ,[deleted_at]
      ,[created_by]
      ,[updated_by]
      ,[source]
      ,[tp_no]
      ,[lpkn_no]
      ,[approved_plan_no]
      ,[plot_size]
      ,[date_recommended]
      ,[date_approved]
      ,[lease_begins]
      ,[lease_expires]
      ,[metric_sheet]
      ,[is_caveated]
      ,[caveated_comment]
      ,[caveat_id]
      ,[fileno]
      ,[date_migrated]
      ,[migrated_by]
      ,[prop_id]
      ,[date_created]
      ,[migration_source]
      ,[land_use]
      ,[comments]
      ,[assignment_date]
      ,[surrender_date]
      ,[revoked_date]
      ,[regranted_from]
      ,[date_expired]
      ,[remarks]
      ,[oldKNNo]
      ,[test_control]
      ,[related_file_number]
      ,[deeds_date]
      ,[deeds_time]
      ,[reg_date]
      ,[reg_time]
  FROM [klas].[dbo].[file_history]  this modal resources\views\fileindexing\partial\property_transaction_modal.blade.php
  miwill display once the file indexiong has been sumitted successfully

