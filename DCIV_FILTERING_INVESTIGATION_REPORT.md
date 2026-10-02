# DCIV Table Filtering Investigation Report

Date: 2026-03-10
Workspace: `\\dc-02\htdocs\klas` (network path)

## Objective
Study the DCIV generated records table (combined commissioned + indexed sources), find why filtering is not working and why files appear as commissioned, and review the related file view modal path.

## Files Reviewed
- `app/Http/Controllers/DcivGenerationController.php`
- `resources/views/dciv_generation/index.blade.php`
- `public/js/dciv_generation.js`
- `routes/app3.php`
- `app/Models/DcivFileNo.php`
- `app/Models/FileIndexing.php`
- `/memories/repo/dciv-generation.md`

## Architecture Summary
The DCIV table data endpoint (`dciv-generation.data`) supports 3 backend source modes:
- `commissioned` -> rows from `dciv_file_no`
- `indexed` -> rows from `file_indexings` (DCIV registry, excluding already commissioned by file number)
- `all` -> union of commissioned + indexed

Backend source selection is read from request param `source`:
- `app/Http/Controllers/DcivGenerationController.php:84`

Route binding is correct:
- `routes/app3.php:140`
- `routes/app3.php:142`

## Root Cause (Why Filtering Appears Broken)
Primary issue is in the frontend DataTable AJAX payload:
- `resources/views/dciv_generation/index.blade.php:1027`
- Code sends `d.source = 'commissioned';` on every request.

Effect:
- Backend always receives `source=commissioned`.
- Backend therefore always executes commissioned-only branch.
- Indexed rows are never returned in this table request.
- Any expected filter behavior for indexed/all cannot work from the UI in current state.

This matches previous repo memory note indicating source filtering exists but depends on request param.

## Evidence That Backend Logic Is Correct (Conditional)
Backend source filter branches are present:
- Source param extraction: `app/Http/Controllers/DcivGenerationController.php:84`
- Indexed branch begins: `app/Http/Controllers/DcivGenerationController.php:152`
- All branch follows at nearby lines

Source tagging in SQL is explicit:
- Commissioned rows tag: `app/Http/Controllers/DcivGenerationController.php:116` -> `'dciv_file_no' as source_table`
- Indexed rows tag: `app/Http/Controllers/DcivGenerationController.php:136` -> `'file_indexings' as source_table`

UI badge rendering by `source_table` also exists:
- `resources/views/dciv_generation/index.blade.php:1051`
- `resources/views/dciv_generation/index.blade.php:1053` (`INDEXED FILE`)
- `resources/views/dciv_generation/index.blade.php:1056` (`COMMISSIONED`)

Conclusion:
- Badge logic is not the primary defect.
- Data reaching frontend is already restricted to commissioned due to hard-coded request source.

## Related File View Modal Check
Reviewed the related-file modal trigger + modal rendering path:
- Trigger button and event dispatch are wired in the table script section.
- Modal structure is present (`relatedFilesModalOpen`, `relatedFilesData`, source-aware columns).
- Source context (`dciv` vs `fi`) is passed via `data-source` and carried in event detail.

Assessment:
- Related file modal pipeline appears structurally correct.
- No direct evidence that this modal causes the commissioned/indexed filter failure.

## Additional Observation
The table title says "Generated DCIV Records", and current payload hard-locks to commissioned. If this is intentional product behavior, then a visible source filter should be removed from expectations/docs. If mixed listing is required, frontend must send selected source dynamically instead of hard-coded commissioned.

## Recommended Fix
1. Replace hard-coded request source with a UI-driven variable (e.g., dropdown/tabs for `commissioned`, `indexed`, `all`).
2. Default to `commissioned` if product wants current behavior initially.
3. On source selection change, reload DataTable (`window.dcivDataTable.ajax.reload()`).
4. Keep server fallback default (`commissioned`) for safety.

## Quick Verification Plan
1. Open browser devtools network tab.
2. Load DCIV page and inspect `dciv-generation.data` request query string.
3. Confirm current request includes `source=commissioned` only.
4. Test endpoint manually with:
   - `?source=commissioned`
   - `?source=indexed`
   - `?source=all`
5. Confirm rows and badges differ as expected when source changes.

## Final Diagnosis
Filtering is not working because frontend currently forces `source='commissioned'` for every DataTable request. Backend union/filter logic and source-tag rendering exist, but they are bypassed by the hard-coded frontend request parameter.

## Runtime Error Found After Enabling Source Filter
After enabling dynamic source selection, selecting `Indexed` triggered a backend 500 (DataTables Ajax error).

Observed error (from `storage/logs/laravel-2026-03-10.log`):
- `SQLSTATE[42S22] Invalid column name 'is_deleted'`
- Failing query targeted `file_indexing_links` with `where is_deleted = 0 or is_deleted is null`
- Failure point: `DcivGenerationController::data()` during indexed-row related-link existence check.

Reason:
- In this environment, table `file_indexing_links` does not have `is_deleted` column.
- Previous code assumed that column exists.

## Fix Implemented
Implemented in `resources/views/dciv_generation/index.blade.php`:
- Added a source selector above the table with options: `Commissioned`, `Indexed`, `All`.
- Replaced hard-coded DataTable payload with dynamic source value from selector.
- Added a safe fallback to `commissioned` if selector value is invalid.
- Added change listener to reload DataTable when source changes.

Implemented in `app/Http/Controllers/DcivGenerationController.php`:
- Added schema-aware handling for `file_indexing_links.is_deleted`.
- Uses `Schema::connection('sqlsrv')->hasColumn('file_indexing_links', 'is_deleted')`.
- Applies `is_deleted` filter only when the column exists.
- Applied this compatibility logic in both:
   - `data()` indexed related-links existence check.
   - `getRelatedFiles()` (`source=fi`) main and fallback queries.
- Updated source classification SQL rules:
   - `Indexed only`: file exists in `file_indexings` and does **not** exist in `dciv_file_no`.
   - `Commissioned only`: file exists in **both** `file_indexings` and `dciv_file_no`.
   - `All`: union of the two sets above.

Expected behavior now:
- `Commissioned` -> only records from `dciv_file_no`
- `Indexed` -> only records from `file_indexings` not yet commissioned
- `All` -> combined union result