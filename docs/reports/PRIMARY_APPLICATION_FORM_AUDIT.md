# Primary Application Form → `mother_applications` Audit

_Last updated: 2025-10-13_

## Scope
- Frontend: `resources/views/primaryform` multi-step form, JS orchestration in `public/js/primaryform/form-submission.js`
- Backend: `PrimaryApplicationController@store`, SQL Server table `mother_applications`
- Goal: catalogue captured columns, spot unmapped inputs, and document fixes for data gaps.

## Schema snapshot (`mother_applications`)
Command: `php check_mother_applications.php`

| Column | Type | Nullable | Captured? | Notes |
| --- | --- | --- | --- | --- |
| `np_fileno` | nvarchar | YES | ✅ | ST file number from API/hidden fields.
| `fileno` | varchar | YES | ✅ | Primary file number.
| `land_use` | varchar | YES | ✅ | Normalised in controller.
| `applicant_type` | varchar | YES | ✅ | Lowercased before insert.
| `applicant_title` | varchar | YES | ✅ | From API / manual override.
| `first_name` / `middle_name` / `surname` | varchar | YES | ✅ | Populated for individual/multiple applicants.
| `corporate_name` | varchar | YES | ✅ | Corporate flow.
| `rc_number` | varchar | YES | ✅ | Corporate flow.
| `multiple_owners_names` | varchar | YES | ✅ | JSON string when multiple owners added.
| `multiple_owners_passport` | varchar | YES | ⚠️ | Form collects files but controller never saves a path.
| `address` | varchar | YES | ✅ | Consolidated contact address string.
| `address_house_no` / `address_street_name` / `address_district` / `address_lga` / `address_state` | varchar | YES | ✅ | Force-captured via JS before submit.
| `address_plot_no` | varchar | YES | ❌ | No matching input; always null.
| `phone_number` | varchar | YES | ✅ | Primary + alternate concatenated.
| `email` | varchar | YES | ✅ | Optional.
| `identification_type` | varchar | YES | ✅ | Radio group stored.
| `identification_others` | varchar | YES | ❌ | No textbox wired for “Others”.
| `scheme_no` | varchar | YES | ✅ | Property section field.
| `scheme_number` | nvarchar | YES | ⚠️ | Legacy duplicate column; never set.
| `plot_size` | varchar | YES | ✅ | Property section.
| `property_*` (house/plot/street/district/lga/state) | varchar | YES | ✅ | Force-captured via JS.
| `NoOfUnits` / `NoOfBlocks` / `NoOfSections` | varchar | YES | ✅ | Numeric counts stored.
| `residential_type` / `commercial_type` / `industrial_type` | varchar | YES | ✅ | Radio groups with “Others” override.
| `ownership_type` | varchar | YES | ⚠️ | Form radios named `ownershipType`; JS looks for `ownership_type`, so nothing persists.
| `ownership_type_others_text` | varchar | YES | ❌ | Textbox `otherOwnership` never captured.
| `ownershipType` | nvarchar | YES | ⚠️ | Legacy PascalCase column remains null.
| `documents` | varchar | YES | ✅ | JSON payload for uploaded support files.
| `shared_areas` | nvarchar | YES | ✅ | Checkbox selections encoded as JSON array.
| `application_fee` / `processing_fee` / `site_plan_fee` | varchar | YES | ✅ | Captured from billing widget.
| `application_fee_payment_date` / `application_fee_receipt_number` / `processing_*` / `site_plan_*` | date & nvarchar | YES | ✅ | Individual tracking columns populated.
| `payment_date` / `receipt_number` | varchar | YES | ✅ | Filled from individual fee section for backward compatibility.
| `Payment_Status` | nvarchar | YES | ✅ | Hard-coded `'Pending'`.
| `application_status` | varchar | YES | ⚠️ | Saved as `'Pendin'` (missing "g").
| `owner_fullname` | varchar | YES | ✅ | Derived from applicant data.
| `passport` / `id_document` / `rc_document` | nvarchar | YES | ✅ | Stored via `Storage::disk('public')`.
| `multiple_owners_identification_type` / `_image` / `_address` / `_email` / `_phone` | nvarchar | YES | ✅ | Arrays JSON-encoded; image paths handled.
| `selected_file_data` / `_id` / `_type` / `applied_file_number` | nvarchar | YES | ✅ | ST API metadata persisted.
| `tracking_id` | nvarchar | YES | ✅ | TRA from ST API.
| `primary_file_id` | nvarchar | YES | ✅ | ST API.
| `application_date` | date | YES | ✅ | Defaults to today if blank.
| `created_at` / `updated_at` / `sys_date` | datetime | YES/NO | ✅ | Filled via `now()`; `sys_date` non-null.
| `action_sheet_generated*`, `deeds_*`, `final_conveyance*`, `ksip_ref_no` | various | YES | 🔄 | System-managed columns untouched by form.

> _Full column list referenced from `check_mother_applications.php`; only user-facing columns shown above._

## Mapping gaps & risks
1. **Ownership radios never persist** – Form uses `name="ownershipType"` but controller/JS expect `ownership_type`. Result: `ownership_type` stays `NULL`; downstream workflows depending on ownership classification will fail. The legacy `ownershipType` column also stays empty.
2. **“Other” ownership text ignored** – Textbox `name="otherOwnership"` is not mapped to `ownership_type_others_text`.
3. **Identification “Others” has no capture** – Schema provides `identification_others` yet the form exposes no free-text field when `identification_type === others`.
4. **Multiple-owner passport files dropped** – Inputs `multiple_owners_passport[]` arrive, but there is no validation/upload handling, leaving `multiple_owners_passport` column null. Stakeholders lose supporting documents.
5. **Typo in default application status** – Controller writes `'Pendin'`; should be `'Pending'` to match downstream status checks.
6. **Contact plot number column orphaned** – Column `address_plot_no` has no UI field; confirm requirement or deprecate to avoid misleading schema consumers.
7. **Duplicate scheme column** – `scheme_number` never set while `scheme_no` is; consider consolidating to one column or wiring both if legacy integrations rely on the duplicate name.
8. **Residual legacy metadata** – Columns like `applicationID`, `ownership`, `ksip_ref_no`, `action_sheet_generated*`, and `deeds_*` remain untouched. Documented as system-managed but should be reviewed before new features assume they contain data.

## Recommendations
- Rename Step 1 ownership radio inputs to `name="ownership_type"` and wire `otherOwnership` → `ownership_type_others_text`. Alternatively, adjust `collectFormData()` to map camelCase to snake_case before submit.
- Add “Specify other identification” textbox controlled by `identification_type === 'others'` and persist to `identification_others`.
- Extend controller to store `multiple_owners_passport` (mirror ID image handling) or remove the upload UI if not needed.
- Fix `application_status` default string (`'Pending'`).
- Decide on `address_plot_no`: add a form input or plan a migration to drop/rename the column.
- Resolve duplicate scheme columns—either migrate data to a single column or populate both for backward compatibility.
- Create automated smoke test (or scripted assertion) to ensure required columns (`np_fileno`, contact info, counts, payment tracking) populate before releasing future form changes.

## Validation artifacts
- **Schema dump**: `php check_mother_applications.php` (captured above).
- **Controller reference**: `app/Http/Controllers/PrimaryApplicationController.php` (mapping block around `$applicationData`).
- **Frontend capture**: `public/js/primaryform/form-submission.js` (force capture + FormData normalisation).

## Next steps
1. Implement quick fixes (ownership radios, status typo, optional textbox) and re-run manual submission test.
2. Decide strategy for unused columns with product/DBA team; schedule cleanup migrations if feasible.
3. Document expectation in future features that JSON columns (`multiple_owners_*`, `shared_areas`) contain encoded arrays.
